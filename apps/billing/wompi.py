"""Servicio de pagos Wompi — portado de WompiPaymentService (Laravel).

Checkout con firma de integridad SHA-256, verificación del webhook por checksum
HMAC-SHA256 (comparación segura), y aplicación de la transacción (el webhook es la
fuente de verdad). Al aprobarse: marca la factura pagada, activa la facturación del
cliente y avanza next_billing_date según el período del plan.
"""

import hashlib
import hmac
from datetime import date
from urllib.parse import urlencode

import requests
from django.db import transaction as db_tx
from django.utils import timezone

from apps.accounts.models import User
from apps.saas.models import PlatformSetting
from .models import SubscriptionInvoice, SubscriptionPayment, SubscriptionPlan


def _setting(key):
    v = PlatformSetting.value_for(key)
    return v if (v not in (None, "")) else None


def _public_key():
    return _setting("wompi_public_key") or _setting("payment_public_key")


def _private_key():
    return _setting("wompi_private_key") or _setting("payment_secret_key")


def _integrity_secret():
    return _setting("wompi_integrity_secret")


def _events_secret():
    return _setting("wompi_events_key") or _setting("payment_webhook_secret")


def _api_base():
    return "https://production.wompi.co" if _setting("wompi_environment") == "production" else "https://sandbox.wompi.co"


def is_configured():
    return bool(_public_key() and _integrity_secret())


def _amount_in_cents(amount):
    return int(round(float(amount) * 100))


def _only_digits(v):
    d = "".join(ch for ch in (v or "") if ch.isdigit())
    return d or None


def _doc_type(t):
    t = (t or "").lower()
    if t in ("cc", "ce", "nit", "pp", "ti", "dni"):
        return t.upper()
    return "OTHER" if t else None


def _local_status(provider_status):
    s = (provider_status or "").upper()
    if s == "APPROVED":
        return "paid"
    if s in ("DECLINED", "ERROR", "VOIDED"):
        return "failed"
    return "pending"


def _checkout_signature(reference, amount_in_cents, currency):
    base = f"{reference}{amount_in_cents}{currency}{_integrity_secret()}"
    return hashlib.sha256(base.encode()).hexdigest()


def _next_reference(invoice):
    import random
    ts = timezone.now().strftime("%Y%m%d%H%M%S")
    while True:
        rnd = "".join(random.choice("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789") for _ in range(6))
        ref = f"IF-{invoice.id}-{ts}-{rnd}"
        if not SubscriptionPayment.objects.filter(reference=ref).exists():
            return ref


def create_checkout(invoice, user, redirect_base):
    pub, integ = _public_key(), _integrity_secret()
    if not pub or not integ:
        raise RuntimeError("La pasarela Wompi no tiene llave pública o secreto de integridad configurado.")
    currency = (invoice.currency or "COP").upper()
    cents = _amount_in_cents(invoice.amount)
    reference = _next_reference(invoice)

    with db_tx.atomic():
        payment = SubscriptionPayment.objects.create(
            user_id=user.id, subscription_plan_id=invoice.subscription_plan_id,
            subscription_invoice_id=invoice.id, amount=cents / 100, currency=currency,
            billing_period=invoice.billing_period, status="pending", payment_method="wompi",
            reference=reference, provider="wompi", provider_status="PENDING")

    sep = "&" if "?" in redirect_base else "?"
    redirect_url = f"{redirect_base}{sep}reference={reference}"
    params = {
        "public-key": pub, "currency": currency, "amount-in-cents": cents,
        "reference": reference, "signature:integrity": _checkout_signature(reference, cents, currency),
        "redirect-url": redirect_url,
        "customer-data:email": user.email,
        "customer-data:full-name": user.full_name or user.name or "",
    }
    phone = _only_digits(user.phone)
    if phone:
        params["customer-data:phone-number"] = phone
        params["customer-data:phone-number-prefix"] = "+57"
    legal = _only_digits(user.document)
    if legal:
        params["customer-data:legal-id"] = legal
    dt = _doc_type(user.document_type)
    if dt:
        params["customer-data:legal-id-type"] = dt
    params = {k: v for k, v in params.items() if v not in (None, "")}
    return {"payment": payment, "url": "https://checkout.wompi.co/p?" + urlencode(params)}


def transaction(transaction_id):
    pk = _private_key()
    if not pk:
        return None
    try:
        r = requests.get(f"{_api_base()}/v1/transactions/{transaction_id}",
                         headers={"Authorization": f"Bearer {pk}"}, timeout=15)
        if r.ok:
            return r.json().get("data")
    except Exception:
        pass
    return None


def valid_event(payload, header_checksum=None):
    secret = _events_secret()
    sig = (payload.get("signature") or {})
    properties = sig.get("properties")
    checksum = header_checksum or sig.get("checksum")
    ts = payload.get("timestamp")
    if not secret or not isinstance(properties, list) or not checksum or ts is None:
        return False
    data = payload.get("data") or {}
    base = ""
    for prop in properties:
        # soporta rutas tipo "transaction.status"
        cur = data
        for part in str(prop).split("."):
            cur = cur.get(part) if isinstance(cur, dict) else None
        base += "" if cur is None else str(cur)
    calculated = hashlib.sha256((base + str(ts) + secret).encode()).hexdigest()
    return hmac.compare_digest(str(checksum).lower(), calculated.lower())


def _add_period(d, period):
    n = 12 if period == "yearly" else 1
    y, m = d.year + (d.month - 1 + n) // 12, (d.month - 1 + n) % 12 + 1
    try:
        return d.replace(year=y, month=m)
    except ValueError:
        return d.replace(year=y, month=m, day=28)


def _mark_invoice_paid(payment):
    invoice = SubscriptionInvoice.objects.filter(pk=payment.subscription_invoice_id).first()
    client = User.objects.filter(pk=payment.user_id).first()
    if not invoice or not client:
        return
    invoice.status = "paid"
    invoice.save(update_fields=["status"])
    client.billing_status = "active"
    plan = SubscriptionPlan.objects.filter(pk=(client.subscription_plan_id or invoice.subscription_plan_id)).first()
    if plan and plan.billing_period != "one_time" and invoice.due_date:
        client.next_billing_date = _add_period(invoice.due_date, plan.billing_period)
    client.save()


def apply_transaction(tx, source="webhook"):
    reference = tx.get("reference")
    if not reference:
        return None
    payment = SubscriptionPayment.objects.filter(reference=reference).first()
    if not payment:
        return None

    provider_status = str(tx.get("status") or "PENDING")
    status = _local_status(provider_status)

    if "amount_in_cents" in tx and _amount_in_cents(payment.amount) != int(tx["amount_in_cents"]):
        status, provider_status = "failed", "AMOUNT_OR_CURRENCY_MISMATCH"
    if "currency" in tx and (payment.currency or "").upper() != str(tx["currency"]).upper():
        status, provider_status = "failed", "AMOUNT_OR_CURRENCY_MISMATCH"

    with db_tx.atomic():
        payment.status = status
        if status == "paid" and not payment.paid_at:
            payment.paid_at = timezone.now()
        payment.payment_method = str(tx.get("payment_method_type") or payment.payment_method or "wompi").lower()
        payment.provider = "wompi"
        payment.provider_transaction_id = tx.get("id")
        payment.provider_status = provider_status
        payment.provider_payload = {"source": source, "transaction": tx,
                                    "received_at": timezone.now().isoformat()}
        payment.save()
        if status == "paid":
            _mark_invoice_paid(payment)
    return payment
