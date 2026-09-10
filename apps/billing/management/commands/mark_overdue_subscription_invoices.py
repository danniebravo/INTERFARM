"""Marca como vencidas las facturas pendientes con due_date pasado
(equiv. subscriptions:mark-overdue-invoices). Correr por cron a diario."""

from django.core.management.base import BaseCommand

from apps.billing.services import mark_overdue_invoices


class Command(BaseCommand):
    help = "Marca como vencidas las facturas de suscripción pendientes ya vencidas."

    def handle(self, *args, **options):
        n = mark_overdue_invoices()
        self.stdout.write(self.style.SUCCESS(f"{n} factura(s) marcada(s) como vencida(s)."))
