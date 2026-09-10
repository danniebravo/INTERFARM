"""Facturación del cliente ("mi-facturación") — portado de ClientBillingController.

Este sub-bloque: listar facturas y pagos propios. El pago online (Wompi checkout),
la descarga PDF y las formas de pago van en el sub-bloque de integraciones (Fase 5).
"""

import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.http import HttpResponseRedirect, JsonResponse
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
