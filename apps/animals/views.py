"""Vistas del módulo de animales — portado de AnimalController (Laravel), paridad 1:1."""

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.db import transaction
from django.db.models import Count, Q
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.lots.models import Lot
from apps.production.models import MilkProduction

from . import support as S
from .forms import AnimalForm
from .models import Animal, AnimalPhoto

EXCLUDED_STATUSES = ["vendido", "fallecido", "sold", "deceased", "dead"]


# --------------------------------------------------------------------------
# Helpers de payload
# --------------------------------------------------------------------------
def _build_payload(data, farm_id, animal=None):
    plain = (data.get("notes") or "").strip()
    meta = S.get_notes_meta(animal.notes) if animal else {}
    notes = S.build_notes_payload(plain, meta)
    return {
        "farm_id": farm_id,
        "lot_id": data.get("lot_id") or None,
        "internal_code": data.get("internal_code") or None,
        "ear_tag": data.get("ear_tag") or None,
        "name": data.get("name") or None,
        "breed": data.get("breed") or None,
        "sex": data.get("sex") or None,
        "purpose": data.get("purpose") or None,
        "birth_date": data.get("birth_date") or None,
        "dam_id": data.get("dam_id") or None,
        "sire_id": data.get("sire_id") or None,
        "dam_name_manual": data.get("dam_name_manual") or None,
        "sire_name_manual": data.get("sire_name_manual") or None,
        "weight_current": data.get("weight_current") or None,
        "location": data.get("location") or None,
        "notes": notes,
        "has_calved_before": data.get("has_calved_before") or None,
        "is_pregnant": data.get("is_pregnant") or None,
        "pregnancy_date": data.get("pregnancy_date") or None,
        "pregnancy_sire_id": data.get("pregnancy_sire_id") or (animal.pregnancy_sire_id if animal else None),
        "pregnancy_sire_name_manual": data.get("pregnancy_sire_name_manual") or (animal.pregnancy_sire_name_manual if animal else None),
        "service_type": data.get("service_type") or (animal.service_type if animal else None),
        "last_calving_date": data.get("last_calving_date") or None,
        "calving_count": data.get("calving_count"),
        "status": data.get("status") or (animal.status if animal else Animal.STATUS_ACTIVE),
        "status_date": data.get("status_date") or (animal.status_date if animal else None),
        "status_notes": data.get("status_notes") or (animal.status_notes if animal else None),
    }


def _validate_parents(request, data, ignore_id=None):
    """Réplica de validateParents(): madre/padre válidos, edad reproductiva, no iguales."""
    dam_id, sire_id = data.get("dam_id"), data.get("sire_id")
    if dam_id and sire_id and int(dam_id) == int(sire_id):
        return "La madre y el padre no pueden ser el mismo animal."
    ids = S.owner_farm_ids(request)
    if dam_id:
        dam = Animal.objects.filter(farm_id__in=ids, id=dam_id, sex="hembra").first()
        if ignore_id and dam and dam.id == ignore_id:
            dam = None
        if not dam:
            return "La madre seleccionada no pertenece a esta finca o no es válida."
        if (dam.age_in_months() or 0) < 22:
            return "La madre seleccionada aún no tiene edad suficiente para registrarse como madre."
    if sire_id:
        sire = Animal.objects.filter(farm_id__in=ids, id=sire_id, sex="macho").first()
        if ignore_id and sire and sire.id == ignore_id:
            sire = None
        if not sire:
            return "El padre seleccionado no pertenece a esta finca o no es válido."
        if (sire.age_in_months() or 0) < 18:
            return "El padre seleccionado aún no tiene edad reproductiva suficiente."
    return None


def _validate_lot(farm_id, lot_id):
    if not lot_id:
        return None
    if not Lot.objects.filter(farm_id=farm_id, id=lot_id).exists():
        return "El lote seleccionado no pertenece a esta finca."
    return None


# --------------------------------------------------------------------------
# Index
# --------------------------------------------------------------------------
@login_required
def index(request):
    farm = S.current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    status = request.GET.get("status", "activos")
    search = (request.GET.get("search") or "").strip()
    sex = (request.GET.get("sex") or "").strip()
    purpose = (request.GET.get("purpose") or "").strip()
    lot_id = request.GET.get("lot_id")
    lot_id = int(lot_id) if (lot_id and lot_id.isdigit()) else None
    sort = request.GET.get("sort", "latest")

    if status not in ("activos", "vendidos", "fallecidos", "todos"):
        status = "activos"
    if purpose not in ("leche", "carne", "doble_proposito", "crianza"):
        purpose = ""

    base = Animal.objects.filter(farm_id=farm.id)
    lots = Lot.objects.filter(farm_id=farm.id).order_by("name")
    if lot_id is not None and not lots.filter(id=lot_id).exists():
        lot_id = None

    active_count = base.filter(Q(status__isnull=True) | ~Q(status__in=EXCLUDED_STATUSES)).count()
    sold_count = base.filter(status=Animal.STATUS_SOLD).count()
    deceased_count = base.filter(status__in=[Animal.STATUS_DECEASED, "deceased", "dead"]).count()

    qs = base.select_related("lot").prefetch_related("photos")

    if search:
        if search.isdigit():
            qs = qs.filter(Q(ear_tag=search) | Q(ear_tag=str(int(search))))
        else:
            qs = qs.filter(Q(name__icontains=search) | Q(internal_code__icontains=search)
                           | Q(ear_tag__icontains=search) | Q(breed__icontains=search))
    if sex in ("hembra", "macho"):
        qs = qs.filter(sex=sex)
    if purpose:
        qs = qs.filter(purpose=purpose)
    if lot_id is not None:
        qs = qs.filter(lot_id=lot_id)

    if status == "vendidos":
        qs = qs.filter(status=Animal.STATUS_SOLD)
    elif status == "fallecidos":
        qs = qs.filter(status__in=[Animal.STATUS_DECEASED, "deceased", "dead"])
    elif status == "todos":
        pass
    else:
        qs = qs.filter(Q(status__isnull=True) | ~Q(status__in=EXCLUDED_STATUSES))
        status = "activos"

    sort_map = {
        "latest": ("-created_at", "-id"), "oldest": ("created_at", "id"),
        "name_asc": ("name", "id"), "name_desc": ("-name", "id"),
        "ear_tag_asc": ("ear_tag", "id"), "ear_tag_desc": ("-ear_tag", "id"),
        "code_asc": ("internal_code", "id"), "code_desc": ("-internal_code", "id"),
        "birth_date_asc": ("birth_date", "id"), "birth_date_desc": ("-birth_date", "id"),
        "weight_asc": ("weight_current", "id"), "weight_desc": ("-weight_current", "id"),
        "status_asc": ("status", "id"), "status_desc": ("-status", "id"),
    }
    qs = qs.order_by(*sort_map.get(sort, sort_map["latest"]))

    page = Paginator(qs, 25).get_page(request.GET.get("page"))

    transfer_farms = S.farms_owner(request).farms().exclude(id=farm.id).order_by("name")

    return render(request, "animals/index.html", {
        "animals": page, "status": status, "search": search, "sex": sex,
        "purpose": purpose, "lot_id": lot_id, "sort": sort, "lots": lots,
        "transfer_farms": transfer_farms, "active_count": active_count,
        "sold_count": sold_count, "deceased_count": deceased_count,
        "sort_options": sort_map.keys(),
    })


# --------------------------------------------------------------------------
# Create / Store
# --------------------------------------------------------------------------
@login_required
def create(request):
    farm = S.current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    return render(request, "animals/create.html", {
        "lots": Lot.objects.filter(farm_id=farm.id).order_by("name"),
        "eligible_dams": S.eligible_dams(request),
        "eligible_sires": S.eligible_sires(request),
        "form": AnimalForm(farm_id=farm.id, require_internal_code=True),
    })


@login_required
@require_POST
def store(request):
    farm = S.current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    form = AnimalForm(request.POST, farm_id=farm.id, require_internal_code=True)
    ctx_extra = {
        "lots": Lot.objects.filter(farm_id=farm.id).order_by("name"),
        "eligible_dams": S.eligible_dams(request),
        "eligible_sires": S.eligible_sires(request),
        "form": form,
    }
    if not form.is_valid():
        return render(request, "animals/create.html", ctx_extra)

    data = form.cleaned_data
    err = _validate_parents(request, data) or _validate_lot(farm.id, data.get("lot_id"))
    if err:
        messages.error(request, err)
        return render(request, "animals/create.html", ctx_extra)

    photos = request.FILES.getlist("photos")
    with transaction.atomic():
        animal = Animal.objects.create(**_build_payload(data, farm.id))
        created = []
        for i, photo in enumerate(photos[:6]):
            created.append(AnimalPhoto.objects.create(
                animal_id=animal.id, path=S.store_animal_photo(photo), is_main=(i == 0)))
        main = next((p for p in created if p.is_main), created[0] if created else None)
        if main:
            animal.photo = main.path
            animal.save(update_fields=["photo"])

    messages.success(request, "Animal registrado correctamente.")
    return redirect("animals:show", pk=animal.id)


# --------------------------------------------------------------------------
# Show
# --------------------------------------------------------------------------
@login_required
def show(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)

    ids = S.owner_farm_ids(request)
    offspring = list(Animal.objects.filter(farm_id__in=ids)
                     .filter(Q(dam_id=animal.id) | Q(sire_id=animal.id)).order_by("name"))
    offspring_ids = [o.id for o in offspring]
    eligible_offspring = Animal.objects.filter(farm_id__in=ids).exclude(id=animal.id) \
        .exclude(id__in=offspring_ids).select_related("farm").order_by("name")

    return render(request, "animals/show.html", {
        "animal": animal,
        "offspring": offspring,
        "eligible_offspring": eligible_offspring,
        "eligible_sires": S.eligible_sires(request, exclude_id=animal.id),
        "production_records": S.get_production_records(animal),
        "health_records": S.get_health_records(animal),
        "can_register_production": animal.can_register_production(),
        "can_register_milk": animal.can_register_milk_production(),
        "production_blocked_reason": animal.production_blocked_reason(),
    })


# --------------------------------------------------------------------------
# Edit / Update
# --------------------------------------------------------------------------
@login_required
def edit(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    initial = {f: getattr(animal, f) for f in AnimalForm.base_fields if hasattr(animal, f)}
    initial["lot_id"] = animal.lot_id
    initial["dam_id"] = animal.dam_id
    initial["sire_id"] = animal.sire_id
    initial["notes"] = animal.clean_notes()
    return render(request, "animals/edit.html", {
        "animal": animal,
        "form": AnimalForm(initial=initial, farm_id=animal.farm_id, instance_id=animal.id),
        "lots": Lot.objects.filter(farm_id=animal.farm_id).order_by("name"),
        "eligible_dams": S.eligible_dams(request, exclude_id=animal.id),
        "eligible_sires": S.eligible_sires(request, exclude_id=animal.id),
        "transfer_farms": S.farms_owner(request).farms().exclude(id=animal.farm_id).order_by("name"),
    })


@login_required
@require_POST
def update(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    farm_id = animal.farm_id

    form = AnimalForm(request.POST, farm_id=farm_id, instance_id=animal.id)
    base_ctx = {
        "animal": animal, "form": form,
        "lots": Lot.objects.filter(farm_id=farm_id).order_by("name"),
        "eligible_dams": S.eligible_dams(request, exclude_id=animal.id),
        "eligible_sires": S.eligible_sires(request, exclude_id=animal.id),
        "transfer_farms": S.farms_owner(request).farms().exclude(id=farm_id).order_by("name"),
    }
    if not form.is_valid():
        return render(request, "animals/edit.html", base_ctx)

    data = form.cleaned_data
    err = _validate_parents(request, data, ignore_id=animal.id) or _validate_lot(farm_id, data.get("lot_id"))
    if err:
        messages.error(request, err)
        return render(request, "animals/edit.html", base_ctx)

    new_photos = request.FILES.getlist("photos")
    existing = list(animal.photos.order_by("-is_main", "id"))
    keep_ids = [int(x) for x in request.POST.getlist("keep_existing_photos") if str(x).isdigit()] \
        if request.POST.get("photo_management_present") else [p.id for p in existing]
    main_photo_id = request.POST.get("main_photo_id")

    with transaction.atomic():
        for k, v in _build_payload(data, farm_id, animal).items():
            setattr(animal, k, v)
        animal.save()

        for photo in existing:
            if photo.id not in keep_ids:
                S.delete_photo_file(photo.path)
                photo.delete()

        created = []
        for photo in new_photos[:6]:
            created.append(AnimalPhoto.objects.create(
                animal_id=animal.id, path=S.store_animal_photo(photo), is_main=False))

        final_photos = list(animal.photos.order_by("id"))
        resolved = None
        if main_photo_id:
            if main_photo_id.startswith("new_"):
                idx = int(main_photo_id.replace("new_", "") or 0)
                resolved = created[idx] if idx < len(created) else None
            else:
                resolved = next((p for p in final_photos if str(p.id) == main_photo_id), None)
        if not resolved:
            resolved = final_photos[0] if final_photos else None
        for photo in final_photos:
            is_main = bool(resolved and photo.id == resolved.id)
            if photo.is_main != is_main:
                photo.is_main = is_main
                photo.save(update_fields=["is_main"])
        animal.photo = resolved.path if resolved else None
        animal.save(update_fields=["photo"])

    messages.success(request, "Animal actualizado correctamente")
    return redirect("animals:show", pk=animal.id)


@login_required
@require_POST
def destroy(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    with transaction.atomic():
        for photo in animal.photos.all():
            S.delete_photo_file(photo.path)
            photo.delete()
        animal.delete()
    messages.success(request, "Animal eliminado correctamente.")
    return redirect("animals:index")


# --------------------------------------------------------------------------
# Traslados
# --------------------------------------------------------------------------
def _resolve_target(request, target_farm_id, target_lot_id, source_farm_id):
    target = S.farms_owner(request).farms().filter(id=target_farm_id).first()
    if not target or int(target.id) == int(source_farm_id):
        return None, None, "Selecciona otra finca del cliente como destino."
    lot = None
    if target_lot_id:
        lot = Lot.objects.filter(farm_id=target.id, id=target_lot_id).first()
        if not lot:
            return None, None, "El lote destino no pertenece a la finca seleccionada."
    return target, lot, None


@login_required
@require_POST
def transfer(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    target, lot, err = _resolve_target(request, request.POST.get("target_farm_id"),
                                       request.POST.get("target_lot_id"), animal.farm_id)
    if err:
        messages.error(request, err)
        return redirect("animals:edit", pk=animal.id)
    S.move_animals_to_farm([animal], animal.farm, target, lot)
    messages.success(request, f"Animal trasladado a {target.name}"
                     + (f" / {lot.name}" if lot else " sin lote asignado") + ".")
    return redirect("animals:edit", pk=animal.id)


@login_required
@require_POST
def bulk_transfer(request):
    farm = S.current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    animal_ids = [int(x) for x in request.POST.getlist("animal_ids") if str(x).isdigit()]
    if not animal_ids:
        messages.error(request, "Selecciona al menos un animal para trasladar.")
        return redirect("animals:index")
    target, lot, err = _resolve_target(request, request.POST.get("target_farm_id"),
                                       request.POST.get("target_lot_id"), farm.id)
    if err:
        messages.error(request, err)
        return redirect("animals:index")
    animals = list(Animal.objects.filter(farm_id=farm.id, id__in=animal_ids))
    if not animals or len(animals) != len(set(animal_ids)):
        messages.error(request, "No encontramos animales válidos en la finca actual.")
        return redirect("animals:index")
    S.move_animals_to_farm(animals, farm, target, lot)
    messages.success(request, f"{len(animals)} animal(es) trasladado(s) a {target.name}"
                     + (f" / {lot.name}" if lot else " sin lote asignado") + ".")
    return redirect("animals:index")


# --------------------------------------------------------------------------
# Producción / Salud (páginas + registro)
# --------------------------------------------------------------------------
@login_required
def production(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    return render(request, "animals/production.html", {
        "animal": animal,
        "production_records": S.get_production_records(animal),
        "can_register_production": animal.can_register_production(),
        "can_register_milk": animal.can_register_milk_production(),
        "production_blocked_reason": animal.production_blocked_reason(),
    })


@login_required
def health(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    return render(request, "animals/health.html", {
        "animal": animal,
        "health_records": S.get_health_records(animal),
    })


def _milk_entries(post, animal):
    if not animal.can_register_milk_production():
        return []
    period = post.get("period")
    if period == "mañana_tarde":
        out = []
        for p, key in (("mañana", "liters_morning"), ("tarde", "liters_afternoon")):
            v = post.get(key)
            if v not in (None, ""):
                out.append({"period": p, "liters": float(v)})
        return out
    v = post.get("liters")
    if v not in (None, ""):
        return [{"period": period or None, "liters": float(v)}]
    return []


@login_required
@require_POST
def store_production(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)

    if not animal.can_register_production():
        messages.error(request, animal.production_blocked_reason() or "No se puede registrar producción.")
        return redirect("animals:show", pk=animal.id)

    post = request.POST
    date = post.get("date")
    if not date:
        messages.error(request, "La fecha es obligatoria.")
        return redirect("animals:show", pk=animal.id)
    weight_raw = post.get("weight")
    try:
        weight = float(weight_raw) if weight_raw not in (None, "") else None
    except ValueError:
        weight = None
    if weight is not None and (weight < 0 or weight > S.MAX_ANIMAL_WEIGHT_KG):
        messages.error(request, "El peso no puede ser mayor a 2.000 kg.")
        return redirect("animals:show", pk=animal.id)

    milk = _milk_entries(post, animal)

    if animal.can_register_milk_production() and any(post.get(k) not in (None, "") for k in
                                                     ("liters", "liters_morning", "liters_afternoon")) \
            and not post.get("period"):
        messages.error(request, "Debes seleccionar el período de producción.")
        return redirect("animals:show", pk=animal.id)

    if animal.is_male() and milk:
        messages.error(request, "No puedes registrar litros de leche en un macho.")
        return redirect("animals:show", pk=animal.id)
    if milk and not animal.can_register_milk_production():
        messages.error(request, animal.milk_production_blocked_reason() or "No puede registrar leche.")
        return redirect("animals:show", pk=animal.id)
    if not milk and weight is None:
        messages.error(request, "Debes registrar al menos litros o peso en producción.")
        return redirect("animals:show", pk=animal.id)

    # duplicados de leche por (fecha, periodo)
    periods = [e["period"] for e in milk if e.get("period")]
    if periods:
        dupes = list(MilkProduction.objects.filter(
            farm_id=animal.farm_id, animal_id=animal.id, production_date=date,
            period__in=periods).values_list("period", flat=True).distinct())
        if dupes:
            messages.error(request, "Este animal ya tiene producción registrada para "
                           + " y ".join("la " + d for d in dupes) + " en esa fecha.")
            return redirect("animals:show", pk=animal.id)

    with transaction.atomic():
        for e in milk:
            MilkProduction.objects.create(farm_id=animal.farm_id, animal_id=animal.id,
                                          production_date=date, period=e["period"],
                                          liters=e["liters"], notes=post.get("notes") or None)
        if weight is not None:
            animal.weight_current = weight
            animal.save(update_fields=["weight_current"])

        legacy = S.get_legacy_production_records(animal)
        rows = milk if milk else [{"period": post.get("period"), "liters": None}]
        for e in rows:
            legacy.append({
                "date": date, "period": e.get("period"),
                "liters": e.get("liters"), "weight": weight,
                "feeding_type": post.get("feeding_type") or None,
                "notes": post.get("notes") or None, "created_at": S.now_str(),
            })
        S.save_meta_section(animal, "productions", legacy)

    messages.success(request, "Registro de producción guardado correctamente.")
    return redirect("animals:show", pk=animal.id)


@login_required
@require_POST
def store_health(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    post = request.POST
    if not post.get("date"):
        messages.error(request, "La fecha es obligatoria.")
        return redirect("animals:show", pk=animal.id)
    if not any(post.get(k) for k in ("treatment_type", "disease", "diagnosis", "medication", "notes")):
        messages.error(request, "Debes completar al menos un dato del registro de salud.")
        return redirect("animals:show", pk=animal.id)
    records = S.get_health_records(animal)
    days = post.get("days")
    records.append({
        "date": post.get("date"),
        "treatment_type": post.get("treatment_type") or None,
        "disease": post.get("disease") or None,
        "diagnosis": post.get("diagnosis") or None,
        "medication": post.get("medication") or None,
        "days": int(days) if days and days.isdigit() else None,
        "notes": post.get("notes") or None, "created_at": S.now_str(),
    })
    S.save_meta_section(animal, "health_records", records)
    messages.success(request, "Registro de salud guardado correctamente.")
    return redirect("animals:show", pk=animal.id)


@login_required
@require_POST
def update_reproductive(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    post = request.POST
    is_pregnant = post.get("is_pregnant")
    if is_pregnant not in ("si", "no"):
        messages.error(request, "Indica si está preñada o no.")
        return redirect("animals:show", pk=animal.id)
    if not animal.is_female() and is_pregnant == "si":
        messages.error(request, "Solo las hembras pueden marcarse como preñadas.")
        return redirect("animals:show", pk=animal.id)

    sire_id = post.get("pregnancy_sire_id") or None
    if sire_id:
        sire = Animal.objects.filter(farm_id=animal.farm_id, id=sire_id, sex="macho").first()
        if not sire or (sire.age_in_months() or 0) < 18:
            messages.error(request, "El toro seleccionado no pertenece a esta finca o aún no tiene edad reproductiva.")
            return redirect("animals:show", pk=animal.id)

    female = animal.is_female()
    was_pregnant = female and animal.is_pregnant == "si"
    submitted_calving = post.get("last_calving_date") or None
    current_calving = animal.last_calving_date.strftime("%Y-%m-%d") if animal.last_calving_date else None
    registered_delivery = was_pregnant and is_pregnant == "no" and submitted_calving and submitted_calving != current_calving

    calving_count = post.get("calving_count")
    calving_count = int(calving_count) if calving_count and calving_count.isdigit() else animal.calving_count
    if registered_delivery:
        calving_count = min(int(animal.calving_count or 0) + 1, S.MAX_CALVING_COUNT)

    animal.is_pregnant = is_pregnant if female else "no"
    animal.pregnancy_date = post.get("pregnancy_date") if (female and is_pregnant == "si") else None
    animal.pregnancy_sire_id = sire_id if (female and is_pregnant == "si") else None
    animal.pregnancy_sire_name_manual = post.get("pregnancy_sire_name_manual") if (female and is_pregnant == "si") else None
    animal.service_type = post.get("service_type") if (female and is_pregnant == "si") else None
    animal.has_calved_before = "si" if (registered_delivery or int(calving_count or 0) > 0) else animal.has_calved_before
    animal.last_calving_date = submitted_calving if registered_delivery else (submitted_calving or animal.last_calving_date)
    animal.calving_count = calving_count
    if post.get("status"):
        animal.status = post.get("status")
    animal.status_date = post.get("status_date") or None
    animal.status_notes = post.get("status_notes") or None
    animal.save()

    messages.success(request, "Seguimiento reproductivo actualizado correctamente.")
    return redirect("animals:show", pk=animal.id)


@login_required
@require_POST
def attach_offspring(request, pk):
    animal = get_object_or_404(Animal, pk=pk)
    S.require_farm_access(request, animal.farm_id)
    child_id = request.POST.get("child_id")
    child = Animal.objects.filter(farm_id__in=S.owner_farm_ids(request), id=child_id).first()
    if not child or child.id == animal.id:
        messages.error(request, "La cría seleccionada no es válida.")
        return redirect("animals:show", pk=animal.id)
    if animal.dam_id == child.id or animal.sire_id == child.id:
        messages.error(request, "Ese animal es un progenitor de este animal; no puede ser también su cría.")
        return redirect("animals:show", pk=animal.id)
    if animal.sex == "hembra":
        child.dam_id = animal.id
    else:
        child.sire_id = animal.id
    child.save()
    messages.success(request, "Cría vinculada: " + (child.name or f"Animal {child.id}") + ".")
    return redirect("animals:show", pk=animal.id)
