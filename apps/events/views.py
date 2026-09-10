"""Vistas de eventos — portado de EventController (Laravel).

Eventos manuales (tabla events) + automáticos en memoria (reproductivos/salud).
Calendario servido por `feed` (JSON) y consumido por un calendario JS vanilla.
"""

from datetime import date, datetime

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.db.models import Q
from django.http import JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.animals.models import Animal
from . import support as S
from .models import Event

TYPES = ["general", "parto", "vacuna", "tratamiento", "inseminacion", "celo", "revision"]
STATUSES = ["pending", "completed", "cancelled"]
PRIORITIES = ["low", "medium", "high"]
INACTIVE = ["vendido", "fallecido", "sold", "deceased", "dead"]


def _farm(request):
    return getattr(request, "current_farm", None)


def _manual_qs(farm_id):
    return Event.objects.filter(farm_id=farm_id).filter(
        Q(animal_id__isnull=True) | Q(animal__status__isnull=True) | ~Q(animal__status__in=INACTIVE)
    ).select_related("animal")


def _sort_date(ev):
    return ev.start_datetime.date() if ev.start_datetime else (ev.event_date or date.max)


@login_required
def index(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")

    animals = [a for a in Animal.objects.filter(farm_id=farm.id).order_by("name") if a.is_active()]

    manual = []
    for ev in _manual_qs(farm.id):
        manual.append({
            "id": ev.id, "title": ev.title, "description": ev.description,
            "type": ev.type, "type_label": S.type_label(ev.type), "status": ev.status,
            "priority": ev.priority, "event_date": _sort_date(ev),
            "animal_name": (ev.animal.name or ev.animal.ear_tag) if ev.animal_id and ev.animal else None,
            "lot_name": ev.lot_name, "color": ev.color or S.event_color(ev.type, ev.status),
            "automatic": False,
        })
    combined = manual + S.automatic_events(farm.id)
    today = date.today()
    future = sorted([e for e in combined if (e["event_date"] or date.max) >= today], key=lambda e: e["event_date"] or date.max)
    past = sorted([e for e in combined if (e["event_date"] or date.max) < today], key=lambda e: e["event_date"] or date.min, reverse=True)
    upcoming = future + past

    return render(request, "events/index.html", {
        "farm": farm, "animals": animals, "upcoming_events": upcoming,
        "types": TYPES, "statuses": STATUSES, "priorities": PRIORITIES,
        "today": today.isoformat(),
    })


@login_required
def feed(request):
    farm = _farm(request)
    if not farm:
        return JsonResponse([], safe=False)

    def parse(s):
        try:
            return datetime.strptime(s[:10], "%Y-%m-%d").date()
        except (TypeError, ValueError):
            return None
    rs = parse(request.GET.get("filter_start") or request.GET.get("start") or "")
    re_ = parse(request.GET.get("filter_end") or request.GET.get("end") or "")

    out = []
    for ev in _manual_qs(farm.id):
        d = _sort_date(ev)
        if rs and re_ and d not in (None, date.max) and not (rs <= d <= re_):
            continue
        color = ev.color or S.event_color(ev.type, ev.status)
        out.append({
            "id": str(ev.id), "title": ev.title,
            "start": (ev.start_datetime.isoformat() if ev.start_datetime else (ev.event_date.isoformat() if ev.event_date else None)),
            "allDay": bool(ev.all_day), "color": color,
            "extendedProps": {"type": ev.type, "type_label": S.type_label(ev.type), "status": ev.status,
                              "priority": ev.priority, "animal_name": (ev.animal.name or ev.animal.ear_tag) if ev.animal_id and ev.animal else None,
                              "lot_name": ev.lot_name, "automatic": False, "description": ev.description},
        })
    for e in S.automatic_events(farm.id):
        d = e["event_date"]
        if rs and re_ and d and not (rs <= d <= re_):
            continue
        out.append({
            "id": e["id"], "title": e["title"], "start": d.isoformat() if d else None,
            "allDay": True, "color": e["color"],
            "extendedProps": {"type": e["type"], "type_label": e["type_label"], "status": e["status"],
                              "priority": e["priority"], "animal_name": e["animal_name"],
                              "lot_name": e["lot_name"], "automatic": True, "description": e["description"]},
        })
    return JsonResponse(out, safe=False)


def _validate_event(request, post):
    title = (post.get("title") or "").strip()
    if not title:
        return None, "Debes escribir el título del evento."
    t = post.get("type")
    if t not in TYPES:
        return None, "Debes seleccionar el tipo de evento."
    if post.get("status") not in STATUSES:
        return None, "Debes seleccionar el estado."
    if post.get("priority") not in PRIORITIES:
        return None, "Debes seleccionar la prioridad."
    all_day = (post.get("all_day") or "1") == "1"
    event_date = post.get("event_date") or None
    start_dt = post.get("start_datetime") or None
    end_dt = post.get("end_datetime") or None
    if all_day and not event_date:
        return None, "Debes seleccionar la fecha del evento."
    if not all_day and not start_dt:
        return None, "Debes seleccionar la fecha y hora de inicio."
    animal_id = post.get("animal_id") or None
    if animal_id:
        a = Animal.objects.filter(farm_id=_farm(request).id, id=animal_id).first()
        if not a or not a.is_active():
            return None, "El animal seleccionado no pertenece a esta finca o no está activo."
    payload = {
        "animal_id": animal_id, "title": title, "description": post.get("description") or None,
        "type": t, "event_date": event_date if all_day else None,
        "start_datetime": None if all_day else start_dt, "end_datetime": None if all_day else end_dt,
        "all_day": all_day, "status": post.get("status"), "priority": post.get("priority"),
        "lot_name": post.get("lot_name") or None, "color": post.get("color") or None,
    }
    return payload, None


@login_required
@require_POST
def store(request):
    farm = _farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    payload, err = _validate_event(request, request.POST)
    if err:
        messages.error(request, err)
        return redirect("events:index")
    Event.objects.create(farm_id=farm.id, meta=None, **payload)
    messages.success(request, "Evento creado correctamente.")
    return redirect("events:index")


def _authorize(request, ev):
    farm = _farm(request)
    if not farm or int(ev.farm_id) != int(farm.id):
        raise PermissionDenied()


@login_required
def show(request, pk):
    ev = get_object_or_404(Event, pk=pk)
    _authorize(request, ev)
    return JsonResponse({
        "id": ev.id, "title": ev.title, "description": ev.description, "type": ev.type,
        "type_label": S.type_label(ev.type),
        "event_date": ev.event_date.isoformat() if ev.event_date else None,
        "start_datetime": ev.start_datetime.strftime("%Y-%m-%dT%H:%M") if ev.start_datetime else None,
        "end_datetime": ev.end_datetime.strftime("%Y-%m-%dT%H:%M") if ev.end_datetime else None,
        "all_day": bool(ev.all_day), "status": ev.status, "priority": ev.priority,
        "lot_name": ev.lot_name, "color": ev.color,
        "animal": {"id": ev.animal.id, "name": ev.animal.name, "ear_tag": ev.animal.ear_tag} if ev.animal_id and ev.animal else None,
    })


@login_required
@require_POST
def update(request, pk):
    ev = get_object_or_404(Event, pk=pk)
    _authorize(request, ev)
    payload, err = _validate_event(request, request.POST)
    if err:
        messages.error(request, err)
        return redirect("events:index")
    for k, v in payload.items():
        setattr(ev, k, v)
    ev.save()
    messages.success(request, "Evento actualizado correctamente.")
    return redirect("events:index")


@login_required
@require_POST
def destroy(request, pk):
    ev = get_object_or_404(Event, pk=pk)
    _authorize(request, ev)
    ev.delete()
    messages.success(request, "Evento eliminado correctamente.")
    return redirect("events:index")


@login_required
@require_POST
def update_status(request, pk):
    ev = get_object_or_404(Event, pk=pk)
    _authorize(request, ev)
    if request.POST.get("status") not in STATUSES:
        messages.error(request, "El estado seleccionado no es válido.")
        return redirect("events:index")
    ev.status = request.POST.get("status")
    ev.save(update_fields=["status"])
    messages.success(request, "Estado del evento actualizado correctamente.")
    return redirect("events:index")
