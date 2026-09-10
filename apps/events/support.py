"""Helpers de eventos — portados de EventController (Laravel).

Genera eventos AUTOMÁTICOS en memoria (no se guardan): reproductivos derivados de
preñez/último parto por offsets de días, y de salud desde los health_records del
animal. Más helpers de etiqueta/color de tipo.
"""

import hashlib
import json
from datetime import date, timedelta

from apps.animals.models import Animal

TYPE_LABELS = {
    "parto": "Próximo parto", "vacuna": "Vacunación", "tratamiento": "Tratamiento",
    "inseminacion": "Inseminación", "celo": "Celo", "revision": "Revisión",
}
TYPE_COLORS = {
    "parto": "#dc2626", "vacuna": "#2563eb", "tratamiento": "#7c3aed",
    "inseminacion": "#d97706", "celo": "#db2777", "revision": "#0891b2",
}


def type_label(t):
    return TYPE_LABELS.get(t, "Evento")


def event_color(t, status):
    if status == "completed":
        return "#166534"
    if status == "cancelled":
        return "#6b7280"
    return TYPE_COLORS.get(t, "#166534")


def _name(a):
    return a.name or a.ear_tag or "animal"


def _auto(eid, title, t, tlabel, priority, d, animal, color, source):
    return {
        "id": eid, "title": title, "description": "", "type": t, "type_label": tlabel,
        "status": "pending", "priority": priority, "event_date": d,
        "animal_id": animal.id, "animal_name": animal.name or animal.ear_tag,
        "lot_name": animal.location, "color": color, "automatic": True, "source": source,
    }


def _reproductive_events(animal):
    out = []
    if animal.sex != "hembra":
        return out
    preg = animal.pregnancy_date
    calv = animal.last_calving_date
    is_pregnant = animal.is_pregnant == "si"
    nm = _name(animal)

    if is_pregnant and preg:
        calving = preg + timedelta(days=283)
        out.append(_auto(f"auto-inseminacion-{animal.id}-{preg:%Y%m%d}",
                         f"Servicio / inseminación de {nm}", "inseminacion", "Inseminación", "medium",
                         preg, animal, "#d97706", "pregnancy_date"))
        out.append(_auto(f"auto-parto-{animal.id}-{calving:%Y%m%d}",
                         f"Parto probable de {nm}", "parto", "Próximo parto", "high",
                         calving, animal, "#dc2626", "pregnancy_date"))
        out.append(_auto(f"auto-preparto-{animal.id}-{(calving - timedelta(days=30)):%Y%m%d}",
                         f"Preparto de {nm}", "revision", "Preparto", "high",
                         calving - timedelta(days=30), animal, "#f59e0b", "pregnancy_date"))
        if animal.purpose in ("leche", "doble_proposito"):
            out.append(_auto(f"auto-secado-{animal.id}-{(calving - timedelta(days=60)):%Y%m%d}",
                             f"Secado de {nm}", "revision", "Secado", "medium",
                             calving - timedelta(days=60), animal, "#7c3aed", "pregnancy_date"))

    if calv:
        out.append(_auto(f"auto-revision-posparto-{animal.id}-{(calv + timedelta(days=7)):%Y%m%d}",
                         f"Revisión posparto de {nm}", "revision", "Revisión posparto", "medium",
                         calv + timedelta(days=7), animal, "#0891b2", "last_calving_date"))
        if not is_pregnant:
            out.append(_auto(f"auto-fertilidad-{animal.id}-{(calv + timedelta(days=45)):%Y%m%d}",
                             f"Nueva fertilidad estimada de {nm}", "celo", "Nueva fertilidad estimada", "medium",
                             calv + timedelta(days=45), animal, "#db2777", "last_calving_date"))
        out.append(_auto(f"auto-destete-{animal.id}-{(calv + timedelta(days=90)):%Y%m%d}",
                         f"Destete estimado de cría de {nm}", "revision", "Destete", "medium",
                         calv + timedelta(days=90), animal, "#16a34a", "last_calving_date"))
    return out


def _health_events(animal):
    out = []
    nm = _name(animal)
    for rec in animal.health_records():
        raw = rec.get("date")
        try:
            from datetime import datetime
            d = datetime.strptime(str(raw)[:10], "%Y-%m-%d").date()
        except (TypeError, ValueError):
            continue
        treatment = (rec.get("treatment_type") or "").strip()
        is_vaccine = treatment.lower() == "vacuna"
        t = "vacuna" if is_vaccine else "tratamiento"
        tlabel = "Vacunación" if is_vaccine else "Tratamiento"
        key = hashlib.md5(json.dumps(rec, sort_keys=True, ensure_ascii=False).encode()).hexdigest()[:10]
        out.append(_auto(f"auto-health-{animal.id}-{d:%Y%m%d}-{key}",
                         f"{treatment or tlabel} de {nm}", t, tlabel,
                         "medium" if is_vaccine else "high", d, animal,
                         "#2563eb" if is_vaccine else "#7c3aed", "health_records"))
        days = int(rec.get("days") or 0)
        if not is_vaccine and days > 1:
            rd = d + timedelta(days=days)
            out.append(_auto(f"auto-health-review-{animal.id}-{rd:%Y%m%d}-{key}",
                             f"Revisión de tratamiento de {nm}", "revision", "Revisión", "medium",
                             rd, animal, "#0891b2", "health_records"))
    return out


def automatic_events(farm_id):
    out = []
    for a in Animal.objects.filter(farm_id=farm_id):
        if not a.is_active():
            continue
        out.extend(_health_events(a))
        out.extend(_reproductive_events(a))
    out.sort(key=lambda e: e["event_date"] or date.max)
    return out
