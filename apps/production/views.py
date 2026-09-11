"""Vistas de producción — portado de ProductionController (Laravel).

Núcleo de uso diario: listado por rango (quincena en curso por defecto), KPIs de
leche, tabla de producción (leche por animal / total de finca / carne) y registro,
edición y borrado. La analítica de leche VENDIDA queda pendiente hasta portar Finanzas.
"""

from datetime import date, datetime

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.animals.models import Animal
from . import support as S
from .models import DailyMilkProduction, MeatProduction, MilkProduction, MilkUsage


def _farm(request):
    return getattr(request, "current_farm", None)


def _back(request):
    ref = request.META.get("HTTP_REFERER")
    return redirect(ref) if ref else redirect("production:index")


def _milk_entries(post):
    if post.get("period") == "mañana_tarde":
        out = []
        for p, k in (("mañana", "liters_morning"), ("tarde", "liters_afternoon")):
            v = post.get(k)
            if v not in (None, ""):
                out.append({"period": p, "liters": float(v)})
        return out
    v = post.get("liters")
    if v not in (None, ""):
        return [{"period": post.get("period"), "liters": float(v)}]
    return []


def _dupe_periods(farm_id, animal_id, d, entries, ignore_id=None):
    periods = list({e["period"] for e in entries if e.get("period")})
    if not periods:
        return []
    qs = MilkProduction.objects.filter(farm_id=farm_id, animal_id=animal_id,
                                       production_date=d, period__in=periods)
    if ignore_id:
        qs = qs.exclude(id=ignore_id)
    return list(qs.values_list("period", flat=True).distinct())


# --------------------------------------------------------------------------
@login_required
def index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    rng = request.GET.get("range", "fortnight_current")
    animal_id = request.GET.get("animal_id") or None
    ptype = (request.GET.get("production_type", "all") or "all").lower()
    if ptype in ("leche",):
        ptype = "milk"
    elif ptype in ("carne",):
        ptype = "meat"
    elif ptype not in ("milk", "meat"):
        ptype = "all"
    sort = request.GET.get("sort", "date_desc")
    if sort not in ("date_desc", "date_asc", "animal_asc", "type_asc"):
        sort = "date_desc"

    start, end, range_label = S.resolve_date_range(request, rng)

    animals = list(Animal.objects.filter(farm_id=farm.id).order_by("name"))
    milk_animals = [a for a in animals if a.can_register_milk_production()]
    production_animals = [a for a in animals if a.can_register_production()]

    milk_qs = MilkProduction.objects.select_related("animal").filter(
        farm_id=farm.id, production_date__range=(start, end))
    meat_qs = MeatProduction.objects.select_related("animal").filter(
        farm_id=farm.id, production_date__range=(start, end))
    if animal_id:
        milk_qs = milk_qs.filter(animal_id=animal_id)
        meat_qs = meat_qs.filter(animal_id=animal_id)
    milk_qs = milk_qs.order_by("-production_date", "-id")
    meat_qs = meat_qs.order_by("-production_date", "-id")

    daily_qs = [] if animal_id else list(
        DailyMilkProduction.objects.filter(farm_id=farm.id, production_date__range=(start, end))
        .order_by("-production_date", "-id"))
    usages = list(S.milk_usages_in_range(farm.id, start, end).order_by("-usage_date"))

    milk_list = list(milk_qs)
    meat_list = list(meat_qs)

    # KPIs de leche oficial
    today = date.today()
    fn_start, fn_end = S.fortnight_bounds(today)
    milk_today = S.official_milk_for_range(farm.id, today, today, animal_id)
    week_start = today.fromordinal(today.toordinal() - today.weekday())
    milk_week = S.official_milk_for_range(farm.id, week_start, today, animal_id)
    milk_month = S.official_milk_for_range(farm.id, today.replace(day=1), S._month_end(today), animal_id)
    milk_fortnight = S.official_milk_for_range(farm.id, fn_start, fn_end, animal_id)

    # Resumen (sin ventas: vendida=0, disponible=lista-para-venta)
    produced = S.official_milk_for_range(farm.id, start, end, animal_id)
    calf = round(sum(float(u.calf_liters or 0) for u in usages), 2)
    consumed = round(sum(float(u.consumed_liters or 0) for u in usages), 2)
    ready = round(max(0.0, produced - calf - consumed), 2)
    total_meat = round(sum(float(m.weight_gain_kg or m.weight_kg or 0) for m in meat_list), 2)

    # Tabla combinada: resúmenes de leche por (animal, día) + totales de finca + carne
    rows = []
    if ptype in ("all", "milk"):
        summ = {}
        for m in milk_list:
            key = (m.production_date, m.animal_id)
            s = summ.setdefault(key, {"date": m.production_date, "animal_id": m.animal_id,
                                      "animal": m.animal, "morning": 0.0, "afternoon": 0.0, "total": 0.0})
            liters = float(m.liters or 0)
            s["total"] += liters
            if (m.period or "").lower() == "mañana":
                s["morning"] += liters
            elif (m.period or "").lower() == "tarde":
                s["afternoon"] += liters
        for s in summ.values():
            rows.append({"type": "milk", "type_label": "Leche", "date": s["date"],
                         "animal_name": (s["animal"].name if s["animal"] else None) or f"Animal {s['animal_id']}",
                         "animal_id": s["animal_id"], "period": "Día completo",
                         "quantity": S.format_liters(s["total"]) + " L"})
        for d in daily_qs:
            rows.append({"type": "milk_total", "type_label": "Finca", "date": d.production_date,
                         "animal_name": "Finca completa", "animal_id": None, "period": "Día completo",
                         "quantity": S.format_liters(float(d.liters or 0)) + " L", "daily_id": d.id})
    if ptype in ("all", "meat"):
        for m in meat_list:
            w = float(m.weight_gain_kg or m.weight_kg or 0)
            rows.append({"type": "meat", "type_label": "Carne", "date": m.production_date,
                         "animal_name": (m.animal.name if m.animal else None) or f"Animal {m.animal_id}",
                         "animal_id": m.animal_id, "period": "No aplica",
                         "quantity": f"{w:.2f}".replace(".", ",") + " kg", "meat_id": m.id})

    key_date = lambda r: r["date"] or date.min
    if sort == "date_asc":
        rows.sort(key=key_date)
    elif sort == "animal_asc":
        rows.sort(key=lambda r: (r["animal_name"] or "").lower())
    elif sort == "type_asc":
        rows.sort(key=lambda r: r["type_label"])
    else:
        rows.sort(key=key_date, reverse=True)

    return render(request, "production/index.html", {
        "farm": farm, "animals": animals, "milk_animals": milk_animals,
        "production_animals": production_animals,
        "selected_animal_id": int(animal_id) if animal_id else None,
        "selected_range": rng, "selected_type": ptype, "selected_sort": sort,
        "range_label": range_label, "start_date": start, "end_date": end,
        "milk_today": milk_today, "milk_week": milk_week, "milk_month": milk_month,
        "milk_fortnight": milk_fortnight, "total_meat_weight": total_meat,
        "produced_liters": produced, "calf_liters": calf, "consumed_liters": consumed,
        "ready_for_sale_liters": ready, "rows": rows, "usages": usages,
        "today": today.isoformat(),
        "ranges": [
            ("fortnight_current", "Quincena en curso"), ("today", "Hoy"),
            ("week", "Última semana"), ("fortnight", "Última quincena"),
            ("month", "Último mes"), ("custom", "Personalizado"),
        ],
    })


# --------------------------------------------------------------------------
# Registro
# --------------------------------------------------------------------------
@login_required
@require_POST
def store_milk(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    d = post.get("production_date")
    animal = Animal.objects.filter(farm_id=farm.id, id=post.get("animal_id")).first()
    if not animal or not animal.is_female():
        messages.error(request, "Debes seleccionar una hembra válida.")
        return _back(request)
    if not animal.can_register_milk_production():
        messages.error(request, animal.milk_production_blocked_reason() or "No puede registrar leche.")
        return _back(request)
    if not post.get("period"):
        messages.error(request, "Debes seleccionar el período de producción.")
        return _back(request)
    entries = _milk_entries(post)
    if not entries:
        messages.error(request, "Debes registrar litros.")
        return _back(request)
    if _dupe_periods(farm.id, animal.id, d, entries):
        messages.error(request, "Este animal ya tiene producción registrada para esa fecha y período.")
        return _back(request)
    for e in entries:
        MilkProduction.objects.create(farm_id=farm.id, animal_id=animal.id, production_date=d,
                                      period=e["period"], liters=e["liters"], notes=post.get("notes") or None)
    messages.success(request, "Producción de leche registrada.")
    return _back(request)


@login_required
@require_POST
def store_daily_milk(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    d = post.get("production_date")
    liters = post.get("liters")
    if not d or liters in (None, ""):
        messages.error(request, "Fecha y litros son obligatorios.")
        return _back(request)
    if DailyMilkProduction.objects.filter(farm_id=farm.id, production_date=d).exists():
        messages.error(request, "Ya se agregó producción de día completo para esa fecha. Edítala desde el historial.")
        return _back(request)
    DailyMilkProduction.objects.create(farm_id=farm.id, production_date=d, liters=float(liters),
                                       notes=post.get("notes") or None)
    messages.success(request, "Total diario de leche guardado.")
    return _back(request)


@login_required
@require_POST
def store_meat(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    animal = Animal.objects.filter(farm_id=farm.id, id=post.get("animal_id")).first()
    if not animal:
        messages.error(request, "Animal inválido.")
        return _back(request)
    if not animal.can_register_production():
        messages.error(request, animal.production_blocked_reason() or "No puede registrar producción.")
        return _back(request)
    wk, wg = post.get("weight_kg"), post.get("weight_gain_kg")
    if wk in (None, "") and wg in (None, ""):
        messages.error(request, "Debes registrar peso actual o ganancia de peso.")
        return _back(request)
    MeatProduction.objects.create(farm_id=farm.id, animal_id=animal.id,
                                  production_date=post.get("production_date"),
                                  weight_kg=float(wk) if wk not in (None, "") else None,
                                  weight_gain_kg=float(wg) if wg not in (None, "") else None,
                                  notes=post.get("notes") or None)
    messages.success(request, "Producción de carne registrada.")
    return _back(request)


@login_required
@require_POST
def store_milk_usage(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    d = post.get("usage_date")
    utype = post.get("usage_type")
    if utype not in ("calves", "internal", "both"):
        messages.error(request, "Tipo de uso inválido.")
        return _back(request)
    calf = float(post.get("calf_liters") or 0) if utype in ("calves", "both") else 0.0
    consumed = float(post.get("consumed_liters") or 0) if utype in ("internal", "both") else 0.0
    if utype in ("calves", "both") and calf <= 0:
        messages.error(request, "Registra los litros para terneras.")
        return _back(request)
    if utype in ("internal", "both") and consumed <= 0:
        messages.error(request, "Registra los litros de consumo interno.")
        return _back(request)
    available = S.available_milk_for_use(farm.id, datetime.strptime(d, "%Y-%m-%d").date(), ignore_usage_date=d)
    if available <= 0:
        messages.error(request, "No hay litros disponibles para descontar en esa fecha.")
        return _back(request)
    if round(calf + consumed, 2) > available:
        messages.error(request, f"No hay suficientes litros. Disponible: {S.format_liters(available)} L.")
        return _back(request)
    MilkUsage.objects.update_or_create(
        farm_id=farm.id, usage_date=d,
        defaults={"calf_liters": calf, "consumed_liters": consumed, "notes": post.get("notes") or None})
    messages.success(request, "Uso interno de leche actualizado.")
    return _back(request)


# --------------------------------------------------------------------------
# Borrado (edición se hará en pulido posterior)
# --------------------------------------------------------------------------
def _owned_or_403(request, obj):
    farm = _farm(request)
    if not farm or int(obj.farm_id) != int(farm.id):
        from django.core.exceptions import PermissionDenied
        raise PermissionDenied()
    return farm


@login_required
@require_POST
def destroy_milk(request, pk):
    m = get_object_or_404(MilkProduction, pk=pk)
    _owned_or_403(request, m)
    m.delete()
    messages.success(request, "Registro de leche eliminado correctamente.")
    return _back(request)


@login_required
@require_POST
def destroy_daily_milk(request, pk):
    d = get_object_or_404(DailyMilkProduction, pk=pk)
    _owned_or_403(request, d)
    d.delete()
    messages.success(request, "Total diario de leche eliminado correctamente.")
    return _back(request)


@login_required
@require_POST
def destroy_meat(request, pk):
    m = get_object_or_404(MeatProduction, pk=pk)
    _owned_or_403(request, m)
    m.delete()
    messages.success(request, "Registro de carne eliminado correctamente.")
    return _back(request)


@login_required
@require_POST
def destroy_milk_usage(request, pk):
    u = get_object_or_404(MilkUsage, pk=pk)
    _owned_or_403(request, u)
    u.delete()
    messages.success(request, "Uso interno de leche eliminado correctamente.")
    return _back(request)


@login_required
@require_POST
def update_milk_day(request, pk=None):
    """Edita la leche de un animal en un día (mañana/tarde): upsert por período."""
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    d = post.get("production_date")
    animal = Animal.objects.filter(farm_id=farm.id, id=post.get("animal_id")).first()
    if not animal or not animal.can_register_milk_production():
        messages.error(request, "Selecciona una hembra válida para leche.")
        return _back(request)
    morning, afternoon = post.get("liters_morning"), post.get("liters_afternoon")
    mv = float(morning) if morning not in (None, "") else 0.0
    av = float(afternoon) if afternoon not in (None, "") else 0.0
    if mv <= 0 and av <= 0:
        messages.error(request, "Debes dejar al menos un período con litros.")
        return _back(request)
    for period, val in (("mañana", morning), ("tarde", afternoon)):
        existing = MilkProduction.objects.filter(farm_id=farm.id, animal_id=animal.id,
                                                 production_date=d, period=period).first()
        if val in (None, "") or float(val) <= 0:
            if existing:
                existing.delete()
            continue
        MilkProduction.objects.update_or_create(
            farm_id=farm.id, animal_id=animal.id, production_date=d, period=period,
            defaults={"liters": float(val), "notes": post.get("notes") or (existing.notes if existing else None)})
    messages.success(request, "Producción diaria del animal actualizada correctamente.")
    return _back(request)


@login_required
@require_POST
def update_daily_milk(request, pk):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    row = get_object_or_404(DailyMilkProduction, pk=pk)
    if int(row.farm_id) != int(farm.id):
        from django.core.exceptions import PermissionDenied
        raise PermissionDenied()
    post = request.POST
    d, liters = post.get("production_date"), post.get("liters")
    if not d or liters in (None, ""):
        messages.error(request, "Fecha y litros son obligatorios.")
        return _back(request)
    if DailyMilkProduction.objects.filter(farm_id=farm.id, production_date=d).exclude(id=row.id).exists():
        messages.error(request, "Ya existe un registro de finca completa para esa fecha.")
        return _back(request)
    row.production_date = d
    row.liters = float(liters)
    row.notes = post.get("notes") or None
    row.save()
    messages.success(request, "Registro de finca actualizado correctamente.")
    return _back(request)


@login_required
@require_POST
def update_meat(request, pk):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    row = get_object_or_404(MeatProduction, pk=pk)
    if int(row.farm_id) != int(farm.id):
        from django.core.exceptions import PermissionDenied
        raise PermissionDenied()
    post = request.POST
    animal = Animal.objects.filter(farm_id=farm.id, id=post.get("animal_id")).first()
    if not animal or not animal.can_register_production():
        messages.error(request, animal.production_blocked_reason() if animal else "Animal inválido.")
        return _back(request)
    wk, wg = post.get("weight_kg"), post.get("weight_gain_kg")
    if wk in (None, "") and wg in (None, ""):
        messages.error(request, "Debes registrar peso actual o ganancia de peso.")
        return _back(request)
    row.animal_id = animal.id
    row.production_date = post.get("production_date")
    row.weight_kg = float(wk) if wk not in (None, "") else None
    row.weight_gain_kg = float(wg) if wg not in (None, "") else None
    row.notes = post.get("notes") or None
    row.save()
    messages.success(request, "Producción de carne actualizada correctamente.")
    return _back(request)


@login_required
@require_POST
def bulk_destroy_milk(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    deleted = 0
    for token in request.POST.getlist("targets"):
        parts = str(token).split(":")
        t = parts[0] if parts else None
        if t == "milk" and len(parts) > 1 and parts[1].isdigit():
            deleted += MilkProduction.objects.filter(farm_id=farm.id, id=int(parts[1])).delete()[0]
        elif t == "daily" and len(parts) > 1 and parts[1].isdigit():
            deleted += DailyMilkProduction.objects.filter(farm_id=farm.id, id=int(parts[1])).delete()[0]
        elif t == "day" and len(parts) > 2:
            qs = MilkProduction.objects.filter(farm_id=farm.id, production_date=parts[2])
            qs = qs.filter(animal_id__isnull=True) if parts[1] in ("0", "") else qs.filter(animal_id=int(parts[1]))
            deleted += qs.delete()[0]
    messages.success(request, f"{deleted} registro(s) de leche eliminado(s).")
    return _back(request)
