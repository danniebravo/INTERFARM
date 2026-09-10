"""Facturación del cliente ("mi-facturación") — portado de ClientBillingController.

Este sub-bloque: listar facturas y pagos propios. El pago online (Wompi checkout),
la descarga PDF y las formas de pago van en el sub-bloque de integraciones (Fase 5).
"""

from django.contrib.auth.decorators import login_required
from django.shortcuts import render

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
        "pending_count": len(pending),
    })
