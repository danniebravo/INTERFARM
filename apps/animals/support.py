"""Helpers del módulo de animales — portados de AnimalController (Laravel).

Scoping por finca/dueño, fotos con Pillow, metadatos embebidos en `notes`,
elegibilidad de madres/padres, agregación de producción/salud y traslados.
"""

import io
import json
import uuid
from datetime import datetime

from django.conf import settings
from django.core.exceptions import PermissionDenied
from django.db import transaction

from apps.lots.models import Lot
from apps.production.models import MeatProduction, MilkProduction

from .models import META_END, META_START, Animal, AnimalPhoto

MAX_ANIMAL_WEIGHT_KG = 2000
MAX_CALVING_COUNT = 25

PHOTO_EXTENSIONS = ("jpg", "jpeg", "png", "webp", "gif", "bmp", "tif", "tiff", "avif", "heic", "heif")


# --- Dueño / scoping por finca --------------------------------------------
def farms_owner(request):
    """Usuario efectivo dueño de las fincas (cliente impersonado si admin, o el propio)."""
    return getattr(request, "effective_user", None) or request.user


def owner_farm_ids(request):
    return list(farms_owner(request).farms().values_list("id", flat=True))


def require_farm_access(request, farm_id):
    if farm_id not in owner_farm_ids(request):
        raise PermissionDenied("Sin acceso a esta finca.")


def current_farm(request):
    return getattr(request, "current_farm", None)


# --- Metadatos embebidos en notes -----------------------------------------
def get_notes_meta(notes):
    if not notes:
        return {}
    start = notes.find(META_START)
    end = notes.find(META_END)
    if start == -1 or end == -1 or end < start:
        return {}
    try:
        decoded = json.loads(notes[start + len(META_START):end].strip())
        return decoded if isinstance(decoded, dict) else {}
    except (ValueError, TypeError):
        return {}


def get_plain_notes(notes):
    if not notes:
        return ""
    start = notes.find(META_START)
    end = notes.find(META_END)
    if start == -1 or end == -1:
        return (notes or "").strip()
    return (notes[:start] + notes[end + len(META_END):]).strip()


def build_notes_payload(plain_notes, meta):
    payload = (plain_notes or "").strip()
    if meta:
        if payload != "":
            payload += "\n\n"
        payload += META_START + json.dumps(meta, ensure_ascii=False) + META_END
    return payload


def save_meta_section(animal, section, records):
    animal.refresh_from_db()
    meta = get_notes_meta(animal.notes)
    meta[section] = list(records)
    animal.notes = build_notes_payload(get_plain_notes(animal.notes), meta)
    animal.save(update_fields=["notes"])


def now_str():
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


# --- Fotos (Pillow: resize + WebP) -----------------------------------------
def store_animal_photo(uploaded):
    """Guarda una foto optimizada en MEDIA_ROOT/animals y devuelve la ruta relativa."""
    from PIL import Image

    animals_dir = settings.MEDIA_ROOT / "animals"
    animals_dir.mkdir(parents=True, exist_ok=True)
    name = f"animal-{uuid.uuid4()}"

    try:
        img = Image.open(uploaded)
        img.load()
        if img.mode in ("RGBA", "P", "LA"):
            img = img.convert("RGB")
        img.thumbnail((1800, 1800))
        buf = io.BytesIO()
        img.save(buf, format="WEBP", quality=75)
        rel = f"animals/{name}.webp"
        (settings.MEDIA_ROOT / rel).write_bytes(buf.getvalue())
        return rel
    except Exception:
        # Fallback: guardar original con su extensión.
        ext = (uploaded.name.rsplit(".", 1)[-1] if "." in uploaded.name else "jpg").lower()
        ext = "jpg" if ext == "jpeg" else ext
        if ext not in PHOTO_EXTENSIONS:
            ext = "jpg"
        rel = f"animals/{name}.{ext}"
        uploaded.seek(0)
        (settings.MEDIA_ROOT / rel).write_bytes(uploaded.read())
        return rel


def delete_photo_file(path):
    if not path:
        return
    full = settings.MEDIA_ROOT / path
    try:
        if full.exists():
            full.unlink()
    except OSError:
        pass


# --- Elegibilidad de progenitores ------------------------------------------
def _active_status_filter(qs):
    from django.db.models import Q
    excluded = ["vendido", "fallecido", "sold", "deceased", "dead", "vendida", "muerto", "muerta"]
    return qs.filter(Q(status__isnull=True) | ~Q(status__in=excluded))


def eligible_dams(request, exclude_id=None):
    qs = Animal.objects.filter(farm_id__in=owner_farm_ids(request), sex="hembra",
                               birth_date__isnull=False)
    qs = _active_status_filter(qs).order_by("name")
    if exclude_id:
        qs = qs.exclude(id=exclude_id)
    return [a for a in qs.select_related("farm") if (a.age_in_months() or 0) >= 22]


def eligible_sires(request, exclude_id=None):
    qs = Animal.objects.filter(farm_id__in=owner_farm_ids(request), sex="macho",
                               birth_date__isnull=False)
    qs = _active_status_filter(qs).order_by("name")
    if exclude_id:
        qs = qs.exclude(id=exclude_id)
    return [a for a in qs.select_related("farm") if (a.age_in_months() or 0) >= 18]


# --- Agregación de producción / salud (para show) --------------------------
def get_legacy_production_records(animal):
    meta = get_notes_meta(animal.notes)
    out = []
    for r in meta.get("productions", []):
        r = dict(r)
        r.setdefault("source", "legacy")
        r.setdefault("id", None)
        if not r.get("type"):
            r["type"] = "Leche" if r.get("liters") else ("Carne" if r.get("weight") else "Producción")
        r.setdefault("weight_gain", None)
        out.append(r)
    out.sort(key=lambda x: x.get("date") or "", reverse=True)
    return out


def _group_key(r):
    return "|".join([str(r.get("type") or "Producción"), str(r.get("date") or ""), str(r.get("period") or "")])


def get_production_records(animal):
    records = []
    for rec in MilkProduction.objects.filter(farm_id=animal.farm_id, animal_id=animal.id) \
            .order_by("-production_date", "-id"):
        records.append({
            "source": "milk", "id": rec.id, "type": "Leche",
            "date": rec.production_date.strftime("%Y-%m-%d") if rec.production_date else None,
            "period": rec.period,
            "liters": float(rec.liters) if rec.liters is not None else None,
            "weight": None, "weight_gain": None, "feeding_type": None, "notes": rec.notes,
        })
    for rec in MeatProduction.objects.filter(farm_id=animal.farm_id, animal_id=animal.id) \
            .order_by("-production_date", "-id"):
        records.append({
            "source": "meat", "id": rec.id, "type": "Carne",
            "date": rec.production_date.strftime("%Y-%m-%d") if rec.production_date else None,
            "period": None, "liters": None,
            "weight": float(rec.weight_kg) if rec.weight_kg is not None else None,
            "weight_gain": float(rec.weight_gain_kg) if rec.weight_gain_kg is not None else None,
            "feeding_type": None, "notes": rec.notes,
        })
    table_keys = {_group_key(r) for r in records}
    for r in get_legacy_production_records(animal):
        if _group_key(r) not in table_keys:
            records.append(r)
    # dedupe
    seen, out = set(), []
    for r in records:
        k = "|".join([str(r.get("type") or "Producción"), str(r.get("date") or ""),
                      str(r.get("period") or ""), str(r.get("liters") or ""), str(r.get("weight") or "")])
        if k in seen:
            continue
        seen.add(k)
        out.append(r)
    out.sort(key=lambda x: x.get("date") or "", reverse=True)
    return out


def get_health_records(animal):
    meta = get_notes_meta(animal.notes)
    recs = list(meta.get("health_records", []))
    recs.sort(key=lambda x: x.get("date") or "", reverse=True)
    return recs


# --- Traslado de finca ------------------------------------------------------
def move_animals_to_farm(animals, source_farm, target_farm, target_lot):
    with transaction.atomic():
        moved_ids = [a.id for a in animals]
        ts = now_str()
        for animal in animals:
            meta = get_notes_meta(animal.notes)
            transfers = meta.get("transfers", [])
            transfers.append({
                "date": ts,
                "from_farm_id": source_farm.id, "from_farm_name": source_farm.name,
                "from_lot_id": animal.lot_id, "from_lot_name": animal.lot.name if animal.lot_id and animal.lot else None,
                "to_farm_id": target_farm.id, "to_farm_name": target_farm.name,
                "to_lot_id": target_lot.id if target_lot else None,
                "to_lot_name": target_lot.name if target_lot else None,
            })
            meta["transfers"] = transfers
            animal.notes = build_notes_payload(get_plain_notes(animal.notes), meta)
            animal.farm_id = target_farm.id
            animal.lot_id = target_lot.id if target_lot else None
            animal.location = target_lot.name if target_lot else None
            animal.save()
        MilkProduction.objects.filter(animal_id__in=moved_ids).update(farm_id=target_farm.id)
        MeatProduction.objects.filter(animal_id__in=moved_ids).update(farm_id=target_farm.id)
        try:
            from apps.events.models import Event
            Event.objects.filter(animal_id__in=moved_ids).update(
                farm_id=target_farm.id, lot_name=(target_lot.name if target_lot else None))
        except Exception:
            pass
