"""Vistas de reportes — portado de ReportsController (Laravel).

Panel de reportes con filtros (fechas, animal, lote, sexo, propósito, estado,
búsqueda), resumen del hato, producción, reproductivos (lactancia/preñez/crías)
y export CSV por módulo. El export XLSX del legacy queda pendiente (CSV cubre el uso).
"""

import csv
from datetime import date, datetime, timedelta

from django.contrib.auth.decorators import login_required
from django.db.models import Count, Q
from django.http import Http404, HttpResponse
from django.shortcuts import redirect, render

from apps.animals.models import Animal
from apps.events.models import Event
from apps.finance.models import FinancialTransaction as FT
from apps.lots.models import Lot
from apps.production.models import MeatProduction, MilkProduction

INACTIVE = ["vendido", "fallecido", "sold", "deceased", "dead"]
EXPORT_MODULES = ["inventario", "lotes", "produccion", "finanzas", "eventos", "lactancia", "prenez", "crias", "todo"]
SERVICE_LABELS = {"monta_natural": "Monta natural (toro)", "inseminacion": "Inseminación (pajilla)",
                  "embrion": "Transferencia de embrión"}


def _farm(request):
    return getattr(request, "current_farm", None)


def _month_bounds(today=None):
    today = today or date.today()
    first = today.replace(day=1)
    nxt = (first.replace(day=28) + timedelta(days=4)).replace(day=1)
    return first, nxt - timedelta(days=1)


def _resolve_filters(request):
    def pd(s):
        try:
            return datetime.strptime(s, "%Y-%m-%d").date()
        except (TypeError, ValueError):
            return None
    mstart, mend = _month_bounds()
    start = pd(request.GET.get("start_date")) or mstart
    end = pd(request.GET.get("end_date")) or mend
    if start > end:
        start, end = end, start
    def i(v):
        return int(v) if v and str(v).isdigit() else None
    return {
        "start_date": start, "end_date": end,
        "animal_id": i(request.GET.get("animal_id")),
        "lot_id": i(request.GET.get("lot_id")),
        "sex": request.GET.get("sex") if request.GET.get("sex") in ("hembra", "macho") else None,
        "purpose": request.GET.get("purpose") if request.GET.get("purpose") in ("leche", "carne", "doble_proposito", "crianza") else None,
        "status": request.GET.get("status") if request.GET.get("status") in ("activo", "vendido", "fallecido") else None,
        "search": (request.GET.get("search") or "").strip(),
    }


def _filtered_animals(farm_id, f):
    qs = Animal.objects.filter(farm_id=farm_id).select_related("lot")
    if f["animal_id"]:
        qs = qs.filter(id=f["animal_id"])
    if f["lot_id"]:
        qs = qs.filter(lot_id=f["lot_id"])
    if f["sex"]:
        qs = qs.filter(sex=f["sex"])
    if f["purpose"]:
        qs = qs.filter(purpose=f["purpose"])
    if f["status"] == "activo":
        qs = qs.filter(Q(status__isnull=True) | ~Q(status__in=INACTIVE))
    elif f["status"]:
        qs = qs.filter(status=f["status"])
    if f["search"]:
        s = f["search"]
        qs = qs.filter(Q(name__icontains=s) | Q(internal_code__icontains=s)
                       | Q(ear_tag__icontains=s) | Q(breed__icontains=s))
    return qs.order_by("name")


def _human_span(frm, to):
    if not frm or not to:
        return "—"
    if frm > to:
        return "—"
    months = (to.year - frm.year) * 12 + (to.month - frm.month)
    anchor = frm
    y, m = frm.year + (frm.month - 1 + months) // 12, (frm.month - 1 + months) % 12 + 1
    try:
        anchor = frm.replace(year=y, month=m)
    except ValueError:
        anchor = frm.replace(year=y, month=m, day=28)
    if anchor > to:
        months -= 1
        y, m = frm.year + (frm.month - 1 + months) // 12, (frm.month - 1 + months) % 12 + 1
        anchor = frm.replace(year=y, month=m, day=min(frm.day, 28))
    days = (to - anchor).days
    parts = []
    if months > 0:
        parts.append(f"{months} {'mes' if months == 1 else 'meses'}")
    parts.append(f"{days} {'día' if days == 1 else 'días'}")
    return " y ".join(parts)


def _add_months(d, n):
    if not d:
        return None
    y, m = d.year + (d.month - 1 + n) // 12, (d.month - 1 + n) % 12 + 1
    try:
        return d.replace(year=y, month=m)
    except ValueError:
        return d.replace(year=y, month=m, day=28)


# --------------------------------------------------------------------------
@login_required
def index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    f = _resolve_filters(request)
    lots = Lot.objects.filter(farm_id=farm.id).order_by("name")
    animals_for_filter = Animal.objects.filter(farm_id=farm.id).order_by("name")

    animals = list(_filtered_animals(farm.id, f))
    animal_ids = [a.id for a in animals] or [0]
    has_animal_filter = any([f["lot_id"], f["sex"], f["purpose"], f["status"], f["search"]])

    milk = MilkProduction.objects.filter(farm_id=farm.id, production_date__range=(f["start_date"], f["end_date"]))
    meat = MeatProduction.objects.filter(farm_id=farm.id, production_date__range=(f["start_date"], f["end_date"]))
    if f["animal_id"]:
        milk = milk.filter(animal_id=f["animal_id"]); meat = meat.filter(animal_id=f["animal_id"])
    elif has_animal_filter:
        milk = milk.filter(animal_id__in=animal_ids); meat = meat.filter(animal_id__in=animal_ids)
    milk = list(milk.select_related("animal"))
    meat = list(meat.select_related("animal"))

    fin = list(FT.objects.filter(farm_id=farm.id, transaction_date__range=(f["start_date"], f["end_date"])))
    events_count = Event.objects.filter(farm_id=farm.id,
                                        event_date__range=(f["start_date"], f["end_date"])).count()

    summary = {
        "animals": len(animals),
        "active_animals": sum(1 for a in animals if a.is_active()),
        "female_animals": sum(1 for a in animals if a.is_female()),
        "male_animals": sum(1 for a in animals if a.is_male()),
        "sold_animals": sum(1 for a in animals if a.is_sold()),
        "deceased_animals": sum(1 for a in animals if a.is_deceased()),
        "milk_liters": round(sum(float(m.liters or 0) for m in milk), 2),
        "meat_weight": round(sum(float(m.weight_gain_kg or m.weight_kg or 0) for m in meat), 2),
        "income": round(sum(float(t.amount or 0) for t in fin if t.type in ("income", "ingreso")), 2),
        "expense": round(sum(float(t.amount or 0) for t in fin if t.type in ("expense", "gasto")), 2),
        "events": events_count,
    }
    summary["balance"] = round(summary["income"] - summary["expense"], 2)

    # animales por lote
    by_lot = {}
    for a in animals:
        key = a.lot.name if a.lot_id and a.lot else "Sin lote"
        d = by_lot.setdefault(key, {"lot": key, "total": 0, "females": 0, "males": 0})
        d["total"] += 1
        if a.is_female():
            d["females"] += 1
        elif a.is_male():
            d["males"] += 1
    animals_by_lot = sorted(by_lot.values(), key=lambda x: x["total"], reverse=True)

    # producción por animal
    prod = {}
    for m in milk:
        p = prod.setdefault(m.animal_id, {"animal": m.animal, "milk": 0.0, "meat": 0.0})
        p["milk"] += float(m.liters or 0)
    for m in meat:
        p = prod.setdefault(m.animal_id, {"animal": m.animal, "milk": 0.0, "meat": 0.0})
        p["meat"] += float(m.weight_gain_kg or m.weight_kg or 0)
    production_by_animal = sorted(prod.values(), key=lambda x: x["milk"] + x["meat"], reverse=True)

    # reproductivos (estado actual del hato)
    roster = list(Animal.objects.filter(farm_id=farm.id).select_related("dam", "pregnancy_sire").order_by("name"))
    lactation = [{
        "animal": a, "calving_date": a.last_calving_date,
        "milk_time": _human_span(a.last_calving_date, a.dry_off_date or date.today()),
        "dried": bool(a.dry_off_date),
    } for a in roster if a.sex == "hembra" and a.last_calving_date and a.is_active()]
    pregnancy = []
    for a in roster:
        if a.sex == "hembra" and a.is_pregnant == "si" and a.is_active():
            sire = (a.pregnancy_sire.name if a.pregnancy_sire_id and a.pregnancy_sire else None) or a.pregnancy_sire_name_manual
            typ = SERVICE_LABELS.get(a.service_type)
            pregnancy.append({
                "animal": a, "service_date": a.pregnancy_date,
                "how": (typ + (f" — {sire}" if sire else "")) if typ else (sire or "—"),
                "dry_off_date": a.dry_off_date or _add_months(a.pregnancy_date, 7),
                "dry_off_confirmed": bool(a.dry_off_date),
            })
    offspring = [{
        "animal": a, "birth_date": a.birth_date,
        "dam": (a.dam.name if a.dam_id and a.dam else None) or a.dam_name_manual or "—",
        "age": a.age_human() or "—",
    } for a in roster if a.dam_id or a.dam_name_manual]

    return render(request, "reports/index.html", {
        "farm": farm, "filters": f, "lots": lots, "animals_for_filter": animals_for_filter,
        "summary": summary, "animals_by_lot": animals_by_lot,
        "production_by_animal": production_by_animal,
        "lactation_report": lactation, "pregnancy_report": pregnancy, "offspring_report": offspring,
        "export_modules": [("todo", "Todo"), ("inventario", "Inventario"), ("lotes", "Lotes"),
                           ("produccion", "Producción"), ("finanzas", "Finanzas"), ("eventos", "Eventos"),
                           ("lactancia", "Lactancia"), ("prenez", "Preñez"), ("crias", "Crías")],
    })


# --------------------------------------------------------------------------
def _status_label(a):
    return a.status_label()


def _w(writer, module, farm_id, f, animals, animal_ids):
    has_af = any([f["lot_id"], f["sex"], f["purpose"], f["status"], f["search"]])

    if module == "inventario":
        writer.writerow(["ID", "Animal", "Código interno", "Arete", "Lote", "Sexo", "Propósito", "Estado",
                         "Fecha nacimiento", "Peso actual", "Ubicación"])
        for a in animals:
            writer.writerow([a.id, a.name, a.internal_code, a.ear_tag, a.lot.name if a.lot_id and a.lot else "",
                             a.sex, a.purpose, a.status_label(),
                             a.birth_date or "", a.weight_current or "", a.location or ""])
    elif module == "lotes":
        writer.writerow(["ID", "Código", "Nombre", "Tipo", "Estado", "Área manual", "Área calculada", "Animales", "Descripción"])
        for l in Lot.objects.filter(farm_id=farm_id).annotate(ac=Count("animals")).order_by("name"):
            writer.writerow([l.id, l.code, l.name, l.type, l.status, l.area_manual or "", l.area_calculated or "", l.ac, l.description or ""])
    elif module == "produccion":
        writer.writerow(["Tipo", "Fecha", "Animal", "Lote", "Periodo", "Litros", "Peso kg", "Ganancia kg", "Notas"])
        mq = MilkProduction.objects.filter(farm_id=farm_id, production_date__range=(f["start_date"], f["end_date"])).select_related("animal", "animal__lot")
        xq = MeatProduction.objects.filter(farm_id=farm_id, production_date__range=(f["start_date"], f["end_date"])).select_related("animal", "animal__lot")
        if f["animal_id"]:
            mq = mq.filter(animal_id=f["animal_id"]); xq = xq.filter(animal_id=f["animal_id"])
        elif has_af:
            mq = mq.filter(animal_id__in=animal_ids); xq = xq.filter(animal_id__in=animal_ids)
        for r in mq.order_by("-production_date"):
            an = r.animal
            writer.writerow(["Leche", r.production_date or "", an.name if an else "", (an.lot.name if an and an.lot_id and an.lot else ""), r.period or "", r.liters, "", "", r.notes or ""])
        for r in xq.order_by("-production_date"):
            an = r.animal
            writer.writerow(["Carne", r.production_date or "", an.name if an else "", (an.lot.name if an and an.lot_id and an.lot else ""), "", "", r.weight_kg or "", r.weight_gain_kg or "", r.notes or ""])
    elif module == "finanzas":
        writer.writerow(["Fecha", "Tipo", "Nombre", "Categoría", "Método de pago", "Referencia", "Valor", "Descripción"])
        for t in FT.objects.filter(farm_id=farm_id, transaction_date__range=(f["start_date"], f["end_date"])).order_by("-transaction_date"):
            writer.writerow([t.transaction_date or "", "Ingreso" if t.type in ("income", "ingreso") else "Gasto",
                             t.title, t.category or "", t.payment_method or "", t.reference or "", t.amount, t.description or ""])
    elif module == "eventos":
        writer.writerow(["Fecha", "Inicio", "Fin", "Evento", "Animal", "Tipo", "Estado", "Prioridad", "Lote", "Descripción"])
        eq = Event.objects.filter(farm_id=farm_id).filter(
            Q(event_date__range=(f["start_date"], f["end_date"])) | Q(start_datetime__date__range=(f["start_date"], f["end_date"]))
        ).select_related("animal")
        if f["animal_id"]:
            eq = eq.filter(animal_id=f["animal_id"])
        elif has_af:
            eq = eq.filter(animal_id__in=animal_ids)
        for e in eq.order_by("-event_date"):
            writer.writerow([e.event_date or "", e.start_datetime or "", e.end_datetime or "", e.title,
                             e.animal.name if e.animal_id and e.animal else "", e.type, e.status, e.priority, e.lot_name or "", e.description or ""])
    elif module == "lactancia":
        writer.writerow(["Animal", "Arete/Código", "Fecha de parto", "Tiempo dando leche", "Estado"])
        for a in Animal.objects.filter(farm_id=farm_id, sex="hembra").exclude(last_calving_date__isnull=True).order_by("name"):
            if not a.is_active():
                continue
            writer.writerow([a.name or f"Animal #{a.id}", a.ear_tag or a.internal_code or "", a.last_calving_date or "",
                             _human_span(a.last_calving_date, a.dry_off_date or date.today()),
                             "Seca" if a.dry_off_date else "En producción"])
    elif module == "prenez":
        writer.writerow(["Animal", "Arete/Código", "Fecha de preñez", "Servicio / padre", "Fecha de secado"])
        for a in Animal.objects.filter(farm_id=farm_id, sex="hembra", is_pregnant="si").select_related("pregnancy_sire").order_by("name"):
            if not a.is_active():
                continue
            sire = (a.pregnancy_sire.name if a.pregnancy_sire_id and a.pregnancy_sire else None) or a.pregnancy_sire_name_manual
            typ = SERVICE_LABELS.get(a.service_type)
            dry = a.dry_off_date or _add_months(a.pregnancy_date, 7)
            writer.writerow([a.name or f"Animal #{a.id}", a.ear_tag or a.internal_code or "", a.pregnancy_date or "",
                             (typ + (f" — {sire}" if sire else "")) if typ else (sire or "—"),
                             (str(dry) if dry else "") + (" (confirmado)" if a.dry_off_date else " (estimado)")])
    elif module == "crias":
        writer.writerow(["Cría", "Arete/Código", "Fecha de nacimiento", "Madre", "Edad"])
        for a in Animal.objects.filter(farm_id=farm_id).filter(Q(dam_id__isnull=False) | ~Q(dam_name_manual__isnull=True) & ~Q(dam_name_manual="")).select_related("dam").order_by("name"):
            writer.writerow([a.name or f"Animal #{a.id}", a.ear_tag or a.internal_code or "", a.birth_date or "",
                             (a.dam.name if a.dam_id and a.dam else None) or a.dam_name_manual or "—", a.age_human() or "—"])


MODULE_LABELS = {"inventario": "Inventario", "lotes": "Lotes", "produccion": "Producción",
                 "finanzas": "Finanzas", "eventos": "Eventos", "lactancia": "Lactancia",
                 "prenez": "Preñez", "crias": "Crías"}


@login_required
def export(request, module):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    module = module.lower()
    if module not in EXPORT_MODULES:
        raise Http404()

    f = _resolve_filters(request)
    animals = list(_filtered_animals(farm.id, f))
    animal_ids = [a.id for a in animals] or [0]

    resp = HttpResponse(content_type="text/csv; charset=utf-8")
    resp["Content-Disposition"] = f'attachment; filename="reporte-{module}-{date.today():%Y-%m-%d}.csv"'
    resp.write("﻿")  # BOM para Excel
    writer = csv.writer(resp)

    if module == "todo":
        for section in ["inventario", "lotes", "produccion", "finanzas", "eventos", "lactancia", "prenez", "crias"]:
            writer.writerow(["Módulo", MODULE_LABELS[section]])
            _w(writer, section, farm.id, f, animals, animal_ids)
            writer.writerow([])
    else:
        _w(writer, module, farm.id, f, animals, animal_ids)
    return resp
