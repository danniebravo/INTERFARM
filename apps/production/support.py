"""Helpers de producción — portados de ProductionController (Laravel).

Incluye rangos de fecha (quincena en curso, etc.), leche "oficial" por rango
(usa total diario de finca cuando existe, si no la suma por animal) y utilidades.

NOTA: la asignación de leche VENDIDA (milkSaleRows / saleable allocation) depende
del módulo Finanzas (FinancialTransaction.milk_*), que aún no se porta. Aquí el
resumen calcula producido/terneras/consumo/lista-para-venta; "vendida" queda en 0
hasta portar Finanzas.
"""

from datetime import date, datetime, timedelta

from apps.production.models import DailyMilkProduction, MilkProduction, MilkUsage


def _month_end(d):
    if d.month == 12:
        return d.replace(day=31)
    nxt = d.replace(day=1, month=d.month + 1)
    return nxt - timedelta(days=1)


def resolve_date_range(request, rng):
    today = date.today()
    day = today.day

    if rng == "today":
        return today, today, "Hoy"

    if rng == "fortnight_current":
        start = today.replace(day=1) if day <= 15 else today.replace(day=16)
        return start, today, "Quincena en curso"

    if rng == "month":
        prev_month_end = today.replace(day=1) - timedelta(days=1)
        return prev_month_end.replace(day=1), prev_month_end, "Último mes"

    if rng == "fortnight":
        if day >= 16:
            return today.replace(day=1), today.replace(day=15), "Última quincena"
        prev_month_end = today.replace(day=1) - timedelta(days=1)
        return prev_month_end.replace(day=16), prev_month_end, "Última quincena"

    if rng == "custom":
        def parse(s, default):
            try:
                return datetime.strptime(s, "%Y-%m-%d").date()
            except (TypeError, ValueError):
                return default
        start = parse(request.GET.get("start_date"), today - timedelta(days=today.weekday()))
        end = parse(request.GET.get("end_date"), start + timedelta(days=6))
        return start, end, "Rango personalizado"

    # week (default)
    return today - timedelta(days=6), today, "Última semana"


def fortnight_bounds(today=None):
    today = today or date.today()
    if today.day <= 15:
        return today.replace(day=1), today.replace(day=15)
    return today.replace(day=16), _month_end(today)


def official_milk_for_range(farm_id, start, end, animal_id=None):
    """Leche oficial: si un día tiene total de finca (daily), usa ese; si no, suma por animal."""
    animal_qs = MilkProduction.objects.filter(farm_id=farm_id, production_date__range=(start, end))
    if animal_id:
        return round(float(sum(float(m.liters or 0) for m in animal_qs.filter(animal_id=animal_id))), 2)

    daily_by_date = {d.production_date: float(d.liters or 0)
                     for d in DailyMilkProduction.objects.filter(farm_id=farm_id, production_date__range=(start, end))}
    animal_by_date = {}
    for m in animal_qs:
        animal_by_date.setdefault(m.production_date, 0.0)
        animal_by_date[m.production_date] += float(m.liters or 0)

    total = 0.0
    for d in set(list(daily_by_date.keys()) + list(animal_by_date.keys())):
        total += daily_by_date[d] if d in daily_by_date else animal_by_date.get(d, 0.0)
    return round(total, 2)


def milk_usages_in_range(farm_id, start, end):
    return MilkUsage.objects.filter(farm_id=farm_id, usage_date__range=(start, end))


def available_milk_for_use(farm_id, until_date, ignore_usage_date=None):
    """Litros disponibles = producido hasta la fecha − usado hasta la fecha (ventas se ignoran hasta portar Finanzas)."""
    produced = official_milk_for_range(farm_id, date(2000, 1, 1), until_date)
    used_qs = MilkUsage.objects.filter(farm_id=farm_id, usage_date__lte=until_date)
    if ignore_usage_date:
        used_qs = used_qs.exclude(usage_date=ignore_usage_date)
    used = sum(float(u.calf_liters or 0) + float(u.consumed_liters or 0) for u in used_qs)
    return round(max(0.0, produced - used), 2)


def format_liters(value):
    s = f"{float(value):.2f}".rstrip("0").rstrip(".")
    return s or "0"
