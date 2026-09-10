"""Vistas de lotes — portado de LotController (Laravel).

Mapa con Leaflet (vendorizado, sin API key) para dibujar/ver el polígono.
Búsqueda de ubicación vía Nominatim (sin key), que es lo que producción usa.
El editor avanzado estilo Google Maps (marcadores + rotación) queda para pulido.
"""

import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Count, Q
from django.http import JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone
from django.views.decorators.http import require_POST

from apps.animals.models import Animal, AnimalLotHistory
from .models import Lot

EXCLUDED = ["vendido", "fallecido", "sold", "deceased", "dead"]


def _farm(request):
    return getattr(request, "current_farm", None)


def _owner(request):
    return getattr(request, "effective_user", None) or request.user


def _owner_farm_ids(request):
    return list(_owner(request).farms().values_list("id", flat=True))


def _authorize_lot(request, lot):
    farm = _farm(request)
    if not farm or int(lot.farm_id) != int(farm.id):
        raise PermissionDenied()


def _decode_polygon(raw):
    if not raw:
        return None
    try:
        decoded = json.loads(raw)
    except (ValueError, TypeError):
        return None
    if not isinstance(decoded, list):
        return None
    pts = []
    for p in decoded:
        if isinstance(p, dict) and p.get("lat") is not None and p.get("lng") is not None:
            pts.append({"lat": float(p["lat"]), "lng": float(p["lng"])})
    return pts if len(pts) >= 3 else None


# --------------------------------------------------------------------------
@login_required
def index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    search = (request.GET.get("search") or "").strip()
    status = (request.GET.get("status") or "").strip()
    sort = request.GET.get("sort", "latest")
    if sort not in ("latest", "name_asc", "animals_desc", "area_desc"):
        sort = "latest"

    qs = Lot.objects.filter(farm_id=farm.id).annotate(animals_count=Count("animals"))
    if search:
        qs = qs.filter(Q(name__icontains=search) | Q(code__icontains=search)
                       | Q(type__icontains=search) | Q(description__icontains=search))
    if status in ("activo", "inactivo"):
        qs = qs.filter(status=status)

    if sort == "name_asc":
        qs = qs.order_by("name")
    elif sort == "animals_desc":
        qs = qs.order_by("-animals_count")
    elif sort == "area_desc":
        qs = qs.extra(select={"_area": "COALESCE(area_manual, area_calculated, 0)"}).order_by("-_area")
    else:
        qs = qs.order_by("-id")

    return render(request, "lots/index.html", {
        "lots": qs, "farm": farm, "search": search, "status": status, "sort": sort,
    })


@login_required
def create(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    return render(request, "lots/create.html", {"farm": farm, "types": Lot.TYPE_CHOICES})


@login_required
@require_POST
def store(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    post = request.POST
    name = (post.get("name") or "").strip()
    code = (post.get("code") or "").strip()
    if not name:
        messages.error(request, "El nombre del lote es obligatorio.")
        return render(request, "lots/create.html", {"farm": farm, "old": post, "types": Lot.TYPE_CHOICES})
    if not code:
        messages.error(request, "Debes asignar un código único al lote.")
        return render(request, "lots/create.html", {"farm": farm, "old": post, "types": Lot.TYPE_CHOICES})
    if Lot.objects.filter(farm_id=farm.id, code=code).exists():
        messages.error(request, "Ya existe un lote con este código en esta finca.")
        return render(request, "lots/create.html", {"farm": farm, "old": post, "types": Lot.TYPE_CHOICES})

    Lot.objects.create(
        farm_id=farm.id, name=name, code=code, type=post.get("type") or None,
        status=post.get("status") or "activo",
        area_manual=post.get("area_manual") or None,
        area_calculated=post.get("area_calculated") or None,
        center_lat=post.get("center_lat") or None, center_lng=post.get("center_lng") or None,
        polygon=_decode_polygon(post.get("polygon_json")),
        description=post.get("description") or None, notes=post.get("notes") or None)
    messages.success(request, "Lote creado correctamente.")
    return redirect("lots:index")


@login_required
def show(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    owner_ids = _owner_farm_ids(request)

    # animales candidatos: de cualquier finca del dueño, que no estén ya en este lote, activos
    candidate_animals = (Animal.objects.filter(farm_id__in=owner_ids)
                         .exclude(Q(farm_id=lot.farm_id) & Q(lot_id=lot.id))
                         .filter(Q(status__isnull=True) | ~Q(status__in=EXCLUDED))
                         .select_related("lot", "farm").order_by("farm_id", "name"))

    farm_lots = Lot.objects.filter(farm_id=lot.farm_id).order_by("name").only("id", "name")

    history = (AnimalLotHistory.objects.filter(lot_id=lot.id)
               .select_related("animal").order_by("-entered_at")[:300])

    return render(request, "lots/show.html", {
        "lot": lot, "candidate_animals": candidate_animals, "farm_lots": farm_lots,
        "lot_history": history, "animals": lot.animals.all(),
    })


@login_required
def edit(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    return render(request, "lots/edit.html", {"lot": lot, "farm": _farm(request), "types": Lot.TYPE_CHOICES})


@login_required
@require_POST
def update(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    post = request.POST
    name = (post.get("name") or "").strip()
    if not name:
        messages.error(request, "El nombre del lote es obligatorio.")
        return render(request, "lots/edit.html", {"lot": lot, "farm": _farm(request), "types": Lot.TYPE_CHOICES})

    # el código solo se puede fijar si estaba vacío (paridad Laravel)
    if not lot.code:
        code = (post.get("code") or "").strip() or None
        if code and Lot.objects.filter(farm_id=lot.farm_id, code=code).exclude(id=lot.id).exists():
            messages.error(request, "Ya existe un lote con este código en esta finca.")
            return render(request, "lots/edit.html", {"lot": lot, "farm": _farm(request), "types": Lot.TYPE_CHOICES})
        lot.code = code

    lot.name = name
    lot.type = post.get("type") or None
    lot.status = post.get("status") or "activo"
    lot.area_manual = post.get("area_manual") or None
    lot.area_calculated = post.get("area_calculated") or None
    lot.center_lat = post.get("center_lat") or None
    lot.center_lng = post.get("center_lng") or None
    lot.polygon = _decode_polygon(post.get("polygon_json"))
    lot.description = post.get("description") or None
    lot.notes = post.get("notes") or None
    lot.save()
    messages.success(request, "Lote actualizado correctamente.")
    return redirect("lots:show", pk=lot.id)


@login_required
@require_POST
def destroy(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    # Los animales quedan sin lote (no se eliminan). save() por instancia para disparar el signal de historial.
    for animal in Animal.objects.filter(lot_id=lot.id):
        animal.lot_id = None
        animal.save()
    lot.delete()
    messages.success(request, "Lote eliminado correctamente.")
    return redirect("lots:index")


@login_required
@require_POST
def assign_animals(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    ids = [int(x) for x in request.POST.getlist("animal_ids") if str(x).isdigit()]
    if not ids:
        messages.error(request, "Selecciona al menos un animal.")
        return redirect("lots:show", pk=lot.id)

    target_lot_id = request.POST.get("target_lot_id")
    target_lot = lot
    if target_lot_id and str(target_lot_id).isdigit():
        target_lot = Lot.objects.filter(farm_id=lot.farm_id, id=int(target_lot_id)).first()
        if not target_lot:
            messages.error(request, "El lote destino no es válido.")
            return redirect("lots:show", pk=lot.id)

    animals = Animal.objects.filter(farm_id__in=_owner_farm_ids(request), id__in=ids)
    added = moved = from_other = 0
    for animal in animals:
        if int(animal.lot_id or 0) == int(target_lot.id) and int(animal.farm_id) == int(target_lot.farm_id):
            continue
        was_in_lot = bool(animal.lot_id)
        if int(animal.farm_id) != int(target_lot.farm_id):
            animal.farm_id = target_lot.farm_id
            from_other += 1
        animal.lot_id = target_lot.id
        animal.save()  # dispara signal (lot_assigned_at + historial)
        moved += 1 if was_in_lot else 0
        added += 0 if was_in_lot else 1

    parts = []
    if added: parts.append(f"{added} agregado(s)")
    if moved: parts.append(f"{moved} trasladado(s)")
    if from_other: parts.append(f"{from_other} de otra finca")
    messages.success(request, ("Animales actualizados: " + " · ".join(parts) + ".") if parts else "No hubo cambios.")
    return redirect("lots:show", pk=target_lot.id)


@login_required
@require_POST
def update_history(request, pk):
    lot = get_object_or_404(Lot, pk=pk)
    _authorize_lot(request, lot)
    row = AnimalLotHistory.objects.filter(id=request.POST.get("history_id"), lot_id=lot.id).first()
    if not row:
        messages.error(request, "No se encontró ese registro de historial.")
        return redirect("lots:show", pk=lot.id)
    entered = request.POST.get("entered_at") or None
    exited = request.POST.get("exited_at") or None
    row.entered_at = entered
    row.exited_at = exited
    row.updated_at = timezone.now()
    row.save()
    messages.success(request, "Fechas del historial actualizadas.")
    return redirect("lots:show", pk=lot.id)


@login_required
def search_location(request):
    """Búsqueda de ubicación (Nominatim, sin API key). Network-only en el SW."""
    farm = _farm(request)
    if not farm:
        return JsonResponse({"results": []}, status=403)
    q = (request.GET.get("q") or "").strip()
    if len(q) < 3:
        return JsonResponse({"results": []})
    import requests
    try:
        r = requests.get("https://nominatim.openstreetmap.org/search",
                         params={"format": "jsonv2", "q": q, "countrycodes": "co",
                                 "limit": 5, "addressdetails": 1},
                         headers={"User-Agent": "InterFarm location search (app.somosinterfarm.com)"},
                         timeout=8)
        data = r.json() if r.ok else []
    except Exception:
        data = []
    results = [{
        "lat": float(x["lat"]), "lng": float(x["lon"]),
        "name": x.get("display_name", "Ubicación buscada"),
        "boundingbox": x.get("boundingbox"),
    } for x in data if isinstance(x, dict) and x.get("lat") and x.get("lon")]
    return JsonResponse({"results": results})
