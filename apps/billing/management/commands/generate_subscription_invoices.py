"""Genera facturas de suscripción próximas a vencer (equiv. subscriptions:generate-invoices).

Correr por cron: python manage.py generate_subscription_invoices --days=10
"""

from django.core.management.base import BaseCommand

from apps.accounts.models import User
from apps.billing.services import generate_for_user_if_due

ADMIN_ROLES = ["admin", "super_admin", "superadmin"]


class Command(BaseCommand):
    help = "Genera facturas de suscripción para clientes con plan próximo a vencer."

    def add_arguments(self, parser):
        parser.add_argument("--days", type=int, default=10, help="Días antes del vencimiento")

    def handle(self, *args, **options):
        days = options["days"]
        generated = 0
        for user in User.objects.exclude(role__in=ADMIN_ROLES).filter(subscription_plan_id__isnull=False):
            invoice = generate_for_user_if_due(user, days)
            if invoice:
                generated += 1
                self.stdout.write(f"  factura {invoice.invoice_number} → {user.email}")
        self.stdout.write(self.style.SUCCESS(f"{generated} factura(s) generada(s)."))
