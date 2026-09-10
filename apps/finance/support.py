"""Helpers de finanzas — portados de FinanceController (Laravel).

Rangos de fecha e inventario de leche disponible (producida − usada − vendida),
que gobierna cuántos litros se pueden registrar como vendidos. Reutiliza la leche
oficial de producción (milk + daily).
"""

from datetime import date, datetime, timedelta

from apps.production.models import MilkUsage
from apps.production.support import official_milk_for_range, _month_end
from .models import FinancialTransaction

EPOCH = date(2000, 1, 1)


def resolve_date_range(request, rng):
    today = date.today()
    if rng in ("annual", "yearly", "this_year"):
        rng = "year"
    if rng not in ("today", "week", "fortnight", "month", "year", "custom"):
        rng = "month"

    if rng == "custom":
        def parse(s):
            try:
                return datetime.strptime(s, "%Y-%m-%d").date()
            except (TypeError, ValueError):
                return None
        start = parse(request.GET.get("start_date")) or parse(request.GET.get("end_date")) or today.replace(day=1)
        end = parse(request.GET.get("end_date")) or parse(request.GET.get("start_date")) or _month_end(today)
        if start > end:
            start, end = end, start
        return start, end, f"{start:%d/%m/%Y} al {end:%d/%m/%Y}", rng

    if rng == "today":
        return today, today, "Hoy", rng
    if rng == "week":
        ws = today - timedelta(days=today.weekday())
        return ws, ws + timedelta(days=6), "Esta semana", rng
    if rng == "fortnight":
        if today.day >= 16:
            return today.replace(day=1), today.replace(day=15), "Primera quincena", rng
        prev_end = today.replace(day=1) - timedelta(days=1)
        return prev_end.replace(day=16), prev_end, "Segunda quincena", rng
    if rng == "year":
        return today.replace(month=1, day=1), today.replace(month=12, day=31), "Este año", rng
    # month
    return today.replace(day=1), _month_end(today), "Este mes", rng


def _milk_used(farm_id, start, end):
    return round(float(sum(
        float(u.calf_liters or 0) + float(u.consumed_liters or 0)
        for u in MilkUsage.objects.filter(farm_id=farm_id, usage_date__range=(start, end)))), 2)


def _milk_sold(farm_id, start, end, ignore_id=None):
    """Litros vendidos que solapan el rango (por periodo de la leche o fecha de registro)."""
    qs = FinancialTransaction.objects.filter(farm_id=farm_id, type=FinancialTransaction.TYPE_INCOME,
                                             milk_liters_sold__gt=0)
    if ignore_id:
        qs = qs.exclude(id=ignore_id)
    total = 0.0
    for t in qs:
        s = t.milk_sale_start_date or t.transaction_date
        e = t.milk_sale_end_date or t.transaction_date
        if s and e and s <= end and e >= start:
            total += float(t.milk_liters_sold or 0)
    return round(total, 2)


def available_milk_for_range(farm_id, start, end, ignore_id=None):
    if start > end:
        start, end = end, start
    produced = official_milk_for_range(farm_id, start, end)
    used = _milk_used(farm_id, start, end)
    sold = _milk_sold(farm_id, start, end, ignore_id)
    return round(max(0.0, produced - used - sold), 2)


def available_milk_until(farm_id, until, ignore_id=None):
    produced = official_milk_for_range(farm_id, EPOCH, until)
    used = _milk_used(farm_id, EPOCH, until)
    sold = _milk_sold(farm_id, EPOCH, until, ignore_id)
    return round(max(0.0, produced - used - sold), 2)


def first_unavailable_date_in_range(farm_id, start, end, ignore_id=None):
    if start > end:
        start, end = end, start
    d = start
    while d <= end:
        if available_milk_for_range(farm_id, d, d, ignore_id) <= 0:
            return d
        d += timedelta(days=1)
    return None


def format_liters(value):
    s = f"{float(value):.2f}".rstrip("0").rstrip(".")
    return s or "0"
