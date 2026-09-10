"""Facturación del cliente ("mi-facturación") — portado de ClientBillingController.

Este sub-bloque: listar facturas y pagos propios. El pago online (Wompi checkout),
la descarga PDF y las formas de pago van en el sub-bloque de integraciones (Fase 5).
"""

import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.http import HttpResponse, HttpResponseRedirect, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_POST

from . import wompi
from .models import SubscriptionInvoice, SubscriptionPayment, SubscriptionPlan


def _user(request):
    return getattr(request, "effective_user", None) or request.user


@login_required
def index(request):
    user = _user(request)
    invoices = list(SubscriptionInvoice.objects.filter(user_id=user.id).order_by("-due_date", "-id"))
    plan_ids = {i.subscription_plan_id for i in invoices if i.subscription_plan_id}
    plans = {p.id: p for p in SubscriptionPlan.objects.filter(pk__in=plan_ids)} if plan_ids else {}
    for i in invoices:
        i.plan_name = plans[i.subscription_plan_id].name if i.subscription_plan_id in plans else "—"
    payments = SubscriptionPayment.objects.filter(user_id=user.id).order_by("-paid_at", "-id")[:50]
    current_plan = SubscriptionPlan.objects.filter(pk=user.subscription_plan_id).first() if user.subscription_plan_id else None

    pending = [i for i in invoices if i.status in ("pending", "overdue")]
    return render(request, "billing/index.html", {
        "invoices": invoices, "payments": payments, "current_plan": current_plan,
        "billing_status": user.billing_status, "next_billing_date": user.next_billing_date,
        "pending_count": len(pending), "wompi_ready": wompi.is_configured(),
    })


@login_required
@require_POST
def checkout_invoice(request, pk):
    user = _user(request)
    invoice = get_object_or_404(SubscriptionInvoice, pk=pk)
    if int(invoice.user_id or 0) != int(user.id):
        messages.error(request, "Sin acceso a esta factura.")
        return redirect("billing:index")
    if invoice.status not in ("pending", "overdue"):
        messages.success(request, "Esta factura ya no tiene saldo pendiente.")
        return redirect("billing:index")
    if not wompi.is_configured():
        messages.error(request, "La pasarela de pago no está configurada todavía.")
        return redirect("billing:index")
    try:
        redirect_base = request.build_absolute_uri(reverse("billing:payment-response"))
        checkout = wompi.create_checkout(invoice, user, redirect_base)
    except Exception as e:
        messages.error(request, str(e))
        return redirect("billing:index")
    return HttpResponseRedirect(checkout["url"])


@login_required
def payment_response(request):
    """Retorno del checkout Wompi. La confirmación final llega por el webhook."""
    tx_id = request.GET.get("id")
    if tx_id:
        tx = wompi.transaction(tx_id)
        if tx:
            payment = wompi.apply_transaction(tx, "redirect")
            if payment:
                messages.success(request, "Pago confirmado correctamente." if payment.status == "paid"
                                 else "El pago todavía no fue aprobado por la pasarela.")
                return redirect("billing:index")
    messages.success(request, "Recibimos la respuesta de Wompi. Revisa el estado de tu factura en unos segundos.")
    return redirect("billing:index")


@csrf_exempt
@require_POST
def wompi_webhook(request):
    """Webhook Wompi (fuente de verdad). CSRF-exempt; valida checksum HMAC."""
    try:
        payload = json.loads(request.body.decode("utf-8"))
    except (ValueError, UnicodeDecodeError):
        return JsonResponse({"message": "Invalid payload"}, status=400)
    if not wompi.valid_event(payload, request.headers.get("X-Event-Checksum")):
        return JsonResponse({"message": "Invalid checksum"}, status=403)
    if payload.get("event") == "transaction.updated":
        tx = ((payload.get("data") or {}).get("transaction")) or {}
        if isinstance(tx, dict):
            wompi.apply_transaction(tx, "webhook")
    return JsonResponse({"ok": True})


# --------------------------------------------------------------------------
# Detalle de factura + PDF
# --------------------------------------------------------------------------
def _own_invoice(request, pk):
    inv = get_object_or_404(SubscriptionInvoice, pk=pk)
    if int(inv.user_id or 0) != int(_user(request).id):
        raise PermissionDenied()
    return inv


@login_required
def invoice_show(request, pk):
    inv = _own_invoice(request, pk)
    plan = SubscriptionPlan.objects.filter(pk=inv.subscription_plan_id).first() if inv.subscription_plan_id else None
    payments = SubscriptionPayment.objects.filter(subscription_invoice_id=inv.id).order_by("-paid_at", "-id")
    return render(request, "billing/invoice_show.html", {
        "invoice": inv, "plan": plan, "payments": payments, "wompi_ready": wompi.is_configured(),
    })


@login_required
def download_invoice(request, pk):
    from . import pdf
    inv = _own_invoice(request, pk)
    plan = SubscriptionPlan.objects.filter(pk=inv.subscription_plan_id).first() if inv.subscription_plan_id else None
    data = pdf.render_invoice_pdf(inv, _user(request), plan)
    resp = HttpResponse(data, content_type="application/pdf")
    resp["Content-Disposition"] = f'attachment; filename="{inv.invoice_number or "factura"}.pdf"'
    return resp


# --------------------------------------------------------------------------
# Formas de pago
# --------------------------------------------------------------------------
@login_required
def payment_methods(request):
    from .models import SubscriptionPaymentMethod
    methods = SubscriptionPaymentMethod.objects.filter(user_id=_user(request).id, status="active") \
        .order_by("-is_default", "-id")
    return render(request, "billing/payment_methods.html", {"methods": methods})


@login_required
@require_POST
def store_payment_method(request):
    from datetime import date
    from .models import SubscriptionPaymentMethod
    user = _user(request)
    post = request.POST
    last4 = (post.get("last_four") or "").strip()
    if not (post.get("holder_name") and post.get("brand") and last4.isdigit() and len(last4) == 4):
        messages.error(request, "Completa titular, marca y últimos 4 dígitos.")
        return redirect("billing:payment-methods")
    try:
        em, ey = int(post.get("expiry_month")), int(post.get("expiry_year"))
    except (TypeError, ValueError):
        messages.error(request, "Vencimiento inválido.")
        return redirect("billing:payment-methods")
    if not (1 <= em <= 12) or ey < date.today().year:
        messages.error(request, "Vencimiento inválido.")
        return redirect("billing:payment-methods")

    make_default = post.get("is_default") in ("1", "on", "true") or \
        not SubscriptionPaymentMethod.objects.filter(user_id=user.id, status="active").exists()
    if make_default:
        SubscriptionPaymentMethod.objects.filter(user_id=user.id).update(is_default=False)
    SubscriptionPaymentMethod.objects.create(
        user_id=user.id, provider="wompi", holder_name=post.get("holder_name"),
        brand=post.get("brand"), last_four=last4, expiry_month=em, expiry_year=ey,
        is_default=make_default, status="active")
    messages.success(request, "Forma de pago vinculada correctamente.")
    return redirect("billing:payment-methods")


@login_required
@require_POST
def default_payment_method(request, pk):
    from .models import SubscriptionPaymentMethod
    m = get_object_or_404(SubscriptionPaymentMethod, pk=pk)
    if int(m.user_id) != int(_user(request).id):
        raise PermissionDenied()
    SubscriptionPaymentMethod.objects.filter(user_id=m.user_id).update(is_default=False)
    m.is_default = True
    m.status = "active"
    m.save(update_fields=["is_default", "status"])
    messages.success(request, "Forma de pago principal actualizada.")
    return redirect("billing:payment-methods")


@login_required
@require_POST
def destroy_payment_method(request, pk):
    from .models import SubscriptionPaymentMethod
    m = get_object_or_404(SubscriptionPaymentMethod, pk=pk)
    if int(m.user_id) != int(_user(request).id):
        raise PermissionDenied()
    m.status = "inactive"
    m.is_default = False
    m.save(update_fields=["status", "is_default"])
    messages.success(request, "Forma de pago desactivada.")
    return redirect("billing:payment-methods")
