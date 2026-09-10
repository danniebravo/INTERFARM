"""Lactancia y Genealogía — portado de LactationController y GenealogyController."""

from datetime import date, timedelta

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from .models import Animal

GESTATION_DAYS = 283
WEANING_DAYS = 90
MAX_DEPTH = 3


def _farm(request):
    return getattr(request, "current_farm", None)


def _owner_farm_ids(request):
    owner = getattr(request, "effective_user", None) or request.user
    return list(owner.farms().values_list("id", flat=True))


def _add_months(d, n):
    y, m = d.year + (d.month - 1 + n) // 12, (d.month - 1 + n) % 12 + 1
    try:
        return d.replace(year=y, month=m)
    except ValueError:
        return d.replace(year=y, month=m, day=28)


def _human_duration(frm, to):
    if not frm or not to or frm > to:
        return "—"
    months = (to.year - frm.year) * 12 + (to.month - frm.month)
    anchor = _add_months(frm, months)
    if anchor > to:
        months -= 1
        anchor = _add_months(frm, months)
    days = (to - anchor).days
    parts = []
    if months > 0:
        parts.append(f"{months} {'mes' if months == 1 else 'meses'}")
    parts.append(f"{days} {'día' if days == 1 else 'días'}")
    return " y ".join(parts)


# --------------------------------------------------------------------------
# Lactancia
# --------------------------------------------------------------------------
@login_required
def lactation_index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    today = date.today()
    search = (request.GET.get("search") or "").strip().lower()
    sort = request.GET.get("sort", "lactation_desc")
    filt = request.GET.get("filter", "relevant")
    if filt not in ("relevant", "lactating", "pregnant", "alerts", "all"):
        filt = "relevant"

    females = [a for a in Animal.objects.filter(farm_id=farm.id, sex="hembra").order_by("name")
               if not a.is_sold() and not a.is_deceased()]

    rows = []
    for a in females:
        lastc = a.last_calving_date
        preg = a.is_pregnant == "si"
        service = a.pregnancy_date
        dry = a.dry_off_date
        exp_calving = _add_months(service, 0) + timedelta(days=GESTATION_DAYS) if (preg and service) else None
        exp_dryoff = _add_months(service, 7) if (preg and service) else None
        exp_weaning = lastc + timedelta(days=WEANING_DAYS) if lastc else None

        alerts = []
        if exp_dryoff and preg and not dry:
            if today >= exp_dryoff:
                alerts.append({"label": "Confirmar secado", "tone": "amber"})
            elif (exp_dryoff - today).days <= 10:
                alerts.append({"label": f"Secar en {(exp_dryoff - today).days} d", "tone": "amber"})
        if exp_calving:
            if today > exp_calving and (today - exp_calving).days <= 30:
                alerts.append({"label": "Registrar parto", "tone": "red"})
            elif today <= exp_calving and (exp_calving - today).days <= 15:
                alerts.append({"label": f"Parto en {(exp_calving - today).days} d", "tone": "red"})
        if exp_weaning and today <= exp_weaning and (exp_weaning - today).days <= 10:
            alerts.append({"label": f"Destetar en {(exp_weaning - today).days} d", "tone": "amber"})

        in_dryoff = bool(exp_dryoff and preg and today >= exp_dryoff)
        state = None
        if lastc:
            state = "Seca" if dry else ("En secado" if in_dryoff else "En producción")
        milk_end = dry or ((exp_dryoff if (exp_dryoff and today >= exp_dryoff) else today))
        days_in_milk = (milk_end - lastc).days if lastc else None
        milk_duration = _human_duration(lastc, milk_end) if lastc else None

        rows.append({
            "animal": a, "name": a.name or f"Animal {a.id}", "tag": a.ear_tag or a.internal_code,
            "pregnant": preg, "service_date": service, "last_calving": lastc, "dry_off": dry,
            "expected_calving": exp_calving, "expected_dry_off": exp_dryoff,
            "days_in_milk": days_in_milk, "milk_duration": milk_duration,
            "state": state, "alerts": alerts, "in_cycle": bool(lastc or preg),
        })

    if search:
        rows = [r for r in rows if search in (r["name"] or "").lower() or search in (r["tag"] or "").lower()]
    if filt == "lactating":
        rows = [r for r in rows if r["last_calving"]]
    elif filt == "pregnant":
        rows = [r for r in rows if r["pregnant"]]
    elif filt == "alerts":
        rows = [r for r in rows if r["alerts"]]
    elif filt == "relevant":
        rows = [r for r in rows if r["in_cycle"]]

    if sort == "lactation_asc":
        rows.sort(key=lambda r: r["days_in_milk"] if r["days_in_milk"] is not None else 10**9)
    elif sort == "name_asc":
        rows.sort(key=lambda r: (r["name"] or "").lower())
    elif sort == "calving_soon":
        rows.sort(key=lambda r: r["expected_calving"] or date.max)
    else:
        rows.sort(key=lambda r: r["days_in_milk"] if r["days_in_milk"] is not None else -1, reverse=True)

    lactating = [a for a in females if a.last_calving_date and a.is_active()]
    pregnant = [a for a in females if a.is_pregnant == "si" and a.is_active()]
    avg = None
    if lactating:
        avg = round(sum((today - a.last_calving_date).days for a in lactating) / len(lactating))

    # hato joven (no en ciclo)
    young = {}
    for a in Animal.objects.filter(farm_id=farm.id).select_related("lot").order_by("name"):
        if a.is_sold() or a.is_deceased():
            continue
        if a.is_female() and (a.last_calving_date or a.is_pregnant == "si"):
            continue
        stage = a.development_stage() or "Sin edad registrada"
        young.setdefault(stage, []).append({
            "name": a.name or f"Animal {a.id}", "tag": a.ear_tag or a.internal_code,
            "sex": "Hembra" if a.is_female() else "Macho", "age": a.age_human(),
            "lot": a.lot.name if a.lot_id and a.lot else None,
        })

    return render(request, "animals/lactation.html", {
        "rows": rows, "search": search, "sort": sort, "filter": filt,
        "summary": {"lactating": len(lactating), "pregnant": len(pregnant),
                    "avg_label": _human_duration(today - timedelta(days=avg), today) if avg is not None else "—",
                    "alerts": sum(1 for r in rows if r["alerts"])},
        "young_stock": young, "today": today.isoformat(),
    })


@login_required
@require_POST
def lactation_dry_off(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    farm = _farm(request)
    if not farm or animal.farm_id != farm.id:
        raise PermissionDenied()
    from datetime import datetime
    try:
        d = datetime.strptime(request.POST.get("dry_off_date"), "%Y-%m-%d").date()
    except (TypeError, ValueError):
        d = date.today()
    if d > date.today():
        d = date.today()
    animal.dry_off_date = d
    animal.save()
    messages.success(request, "Secado confirmado.")
    return redirect("animals:lactation")


@login_required
@require_POST
def lactation_calving(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    farm = _farm(request)
    if not farm or animal.farm_id != farm.id:
        raise PermissionDenied()
    from datetime import datetime
    try:
        d = datetime.strptime(request.POST.get("calving_date"), "%Y-%m-%d").date()
    except (TypeError, ValueError):
        d = date.today()
    if d > date.today():
        d = date.today()

    sire_id = animal.pregnancy_sire_id
    sire_manual = animal.pregnancy_sire_name_manual
    animal.last_calving_date = d
    animal.has_calved_before = "si"
    animal.calving_count = int(animal.calving_count or 0) + 1
    animal.is_pregnant = "no"
    animal.pregnancy_date = None
    animal.pregnancy_sire_id = None
    animal.pregnancy_sire_name_manual = None
    animal.service_type = None
    animal.dry_off_date = None
    animal.save()

    calf_note = ""
    if request.POST.get("register_calf") in ("1", "on", "true"):
        sex = request.POST.get("calf_sex")
        if sex not in ("macho", "hembra"):
            messages.error(request, "Sexo de la cría inválido.")
            return redirect("animals:lactation")
        tag = (request.POST.get("calf_ear_tag") or "").strip() or None
        if tag and Animal.objects.filter(farm_id=animal.farm_id, ear_tag=tag).exists():
            tag = None
        calf = Animal(farm_id=animal.farm_id, lot_id=animal.lot_id,
                      name=(request.POST.get("calf_name") or "").strip() or None, ear_tag=tag,
                      sex=sex, birth_date=d, dam_id=animal.id, breed=animal.breed)
        if sire_id:
            calf.sire_id = sire_id
        elif sire_manual:
            calf.sire_name_manual = sire_manual
        calf.save()
        calf_note = f' Se registró la cría "{calf.name}".' if calf.name else " Se registró la cría."

    messages.success(request, "Parto registrado. Comienza una nueva lactancia." + calf_note)
    return redirect("animals:lactation")


# --------------------------------------------------------------------------
# Genealogía
# --------------------------------------------------------------------------
def _node_name(a):
    tag = a.ear_tag or a.internal_code
    name = a.name or f"Animal {a.id}"
    label = f"{name} ({tag})" if tag else name
    if a.is_sold() or a.is_deceased():
        label += " - " + a.status_label()
    return label


def _ancestors(a, depth, by_id):
    node = {"name": _node_name(a), "id": a.id, "role": None, "sex": a.sex, "children": []}
    if depth <= 0:
        return node
    for pid, manual, role in ((a.dam_id, a.dam_name_manual, "Madre"), (a.sire_id, a.sire_name_manual, "Padre")):
        if pid and pid in by_id:
            sub = _ancestors(by_id[pid], depth - 1, by_id)
            sub["role"] = role
            node["children"].append(sub)
        elif manual:
            node["children"].append({"name": manual, "id": None, "role": role, "sex": None, "children": []})
    return node


def _descendants(a, depth, children_of):
    node = {"name": _node_name(a), "id": a.id, "role": None, "sex": a.sex, "children": []}
    if depth <= 0:
        return node
    for kid in children_of.get(a.id, []):
        sub = _descendants(kid, depth - 1, children_of)
        sub["role"] = "Cría"
        node["children"].append(sub)
    return node


@login_required
def genealogy_index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    farm_ids = _owner_farm_ids(request)
    animals = Animal.objects.filter(farm_id=farm.id).order_by("name")
    all_animals = list(Animal.objects.filter(farm_id__in=farm_ids).order_by("name"))
    by_id = {a.id: a for a in all_animals}
    children_of = {}
    for a in all_animals:
        if a.dam_id:
            children_of.setdefault(a.dam_id, []).append(a)
        if a.sire_id:
            children_of.setdefault(a.sire_id, []).append(a)

    sel_id = request.GET.get("animal")
    selected = by_id.get(int(sel_id)) if sel_id and sel_id.isdigit() else None
    ancestors = _ancestors(selected, MAX_DEPTH, by_id) if selected else None
    descendants = _descendants(selected, MAX_DEPTH, children_of) if selected else None
    return render(request, "animals/genealogy.html", {
        "animals": animals, "selected": selected, "ancestors": ancestors, "descendants": descendants,
    })
