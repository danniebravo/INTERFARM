"""Servicio de facturación de suscripción — portado de SubscriptionInvoiceService (Laravel).

Genera facturas de suscripción cuando corresponde y marca vencidas. La moneda se
lee de platform_settings (billing_currency). Al crear una factura nueva, genera
una notificación in-app para el cliente (source_type='subscription_invoice').
"""

from datetime import date, timedelta

from django.db.models import Max
from django.utils import timezone

from apps.accounts.models import User
from apps.saas.models import PlatformSetting
from .models import SubscriptionInvoice, SubscriptionPlan


def _add_months(d, n):
    y, m = d.year + (d.month - 1 + n) // 12, (d.month - 1 + n) % 12 + 1
    try:
        return d.replace(year=y, month=m)
    except ValueError:
        return d.replace(year=y, month=m, day=28)


def _plan_for(user):
    if not user.subscription_plan_id:
        return None
    return SubscriptionPlan.objects.filter(pk=user.subscription_plan_id).first()


def _currency():
    return (PlatformSetting.value_for("billing_currency", "COP") or "COP").upper()


def _next_invoice_number():
    last = SubscriptionInvoice.objects.aggregate(m=Max("id"))["m"] or 0
    return f"INV-{date.today():%Y%m%d}-{last + 1:06d}"


def next_due_date(user):
    plan = _plan_for(user)
    if not plan:
        return None
    if user.next_billing_date:
        return user.next_billing_date
    today = date.today()
    if user.trial_ends_at and user.trial_ends_at.date() > today:
        return user.trial_ends_at.date()
    base = (user.trial_ends_at or user.created_at or timezone.now()).date()
    months = 12 if plan.billing_period == "yearly" else 1
    d = base
    while d < today:
        d = _add_months(d, months)
    return d


def create_invoice(user, due_date):
    plan = _plan_for(user)
    if not plan:
        return None
    period_start = _add_months(due_date, -12 if plan.billing_period == "yearly" else -1)
    invoice, created = SubscriptionInvoice.objects.get_or_create(
        user_id=user.id, subscription_plan_id=plan.id, due_date=due_date,
        defaults={
            "invoice_number": _next_invoice_number(), "amount": plan.price,
            "currency": _currency(), "billing_period": plan.billing_period,
            "period_start": period_start, "period_end": due_date - timedelta(days=1),
            "issue_date": date.today(), "status": "pending",
            "notes": "Factura generada automáticamente.",
        })
    if created:
        _notify_invoice(invoice)
    return invoice


def generate_for_user_if_due(user, days_before=10):
    plan = _plan_for(user)
    if not plan or not plan.is_active or plan.billing_period == "one_time":
        return None
    due = next_due_date(user)
    if not due:
        return None
    if date.today() < (due - timedelta(days=max(0, days_before))):
        return None
    return create_invoice(user, due)


def mark_overdue_invoices():
    return SubscriptionInvoice.objects.filter(status="pending", due_date__lt=date.today()) \
        .update(status="overdue")


def _notify_invoice(invoice):
    try:
        from apps.notifications.models import FarmNotification
        FarmNotification.objects.update_or_create(
            user_id=invoice.user_id, source_type="subscription_invoice", source_key=str(invoice.id),
            defaults={
                "farm_id": None,
                "level": "high" if invoice.status == "overdue" else "medium",
                "title": "Nueva factura disponible",
                "message": f"Se generó la factura {invoice.invoice_number} por {invoice.currency} ${float(invoice.amount):,.0f}.".replace(",", "."),
                "event_date": invoice.due_date, "scheduled_for": timezone.now(),
                "meta": {"automatic": True, "type_label": "Facturación", "invoice_id": invoice.id},
            })
    except Exception:
        pass
