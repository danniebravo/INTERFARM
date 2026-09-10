"""Generación de notificaciones de finca — portado (pragmático) de FarmNotificationService.

Sincroniza notificaciones desde eventos manuales próximos + eventos automáticos
(reproductivos/salud) en la ventana [hoy, +1 mes], deduplicando por source_key.
Billing/recordatorio diario de producción quedan pendientes (Facturación no portada).
"""

from datetime import date, timedelta

from django.db.models import Q
from django.utils import timezone

from apps.animals.models import Animal
from apps.events.models import Event
from apps.events.support import automatic_events, type_label
from .models import FarmNotification

INACTIVE = ["vendido", "fallecido", "sold", "deceased", "dead"]
PRIORITY_LEVEL = {"high": "high", "medium": "medium", "low": "low"}


def _window():
    today = date.today()
    return today, today + timedelta(days=31)


def sync_for_farm_and_user(farm, user):
    start, end = _window()
    now = timezone.now()
    current_auto_keys = []
    new_notifs = []  # (title, message) recién creadas → web push

    # 1) Eventos manuales pendientes en la ventana
    manual = (Event.objects.filter(farm_id=farm.id)
              .filter(Q(status="pending") | Q(status__isnull=True))
              .filter(Q(animal_id__isnull=True) | Q(animal__status__isnull=True) | ~Q(animal__status__in=INACTIVE))
              .filter(Q(event_date__range=(start, end)) | Q(start_datetime__date__range=(start, end)))
              .select_related("animal")[:50])
    for ev in manual:
        d = ev.event_date or (ev.start_datetime.date() if ev.start_datetime else None)
        if not d:
            continue
        animal_name = (ev.animal.name or ev.animal.ear_tag) if ev.animal_id and ev.animal else None
        msg = f"{type_label(ev.type)} · {d:%d/%m/%Y}" + (f" · {animal_name}" if animal_name else "")
        _, created = FarmNotification.objects.update_or_create(
            user_id=user.id, source_type="calendar_event", source_key=str(ev.id),
            defaults={
                "farm_id": farm.id, "event_id": ev.id,
                "level": PRIORITY_LEVEL.get(ev.priority, "medium"),
                "title": ev.title, "message": msg,
                "event_date": d, "lot_name": ev.lot_name,
                "meta": {"automatic": False, "type_label": type_label(ev.type)},
                "scheduled_for": now,
            })
        if created:
            new_notifs.append((ev.title, msg))

    # 2) Eventos automáticos (reproductivos/salud) en la ventana
    for e in automatic_events(farm.id):
        d = e["event_date"]
        if not d or not (start <= d <= end):
            continue
        current_auto_keys.append(e["id"])
        msg = f"{e['type_label']} · {d:%d/%m/%Y}" + (f" · {e['animal_name']}" if e["animal_name"] else "")
        _, created = FarmNotification.objects.update_or_create(
            user_id=user.id, source_type="automatic_event", source_key=e["id"],
            defaults={
                "farm_id": farm.id, "event_id": None,
                "level": PRIORITY_LEVEL.get(e["priority"], "medium"),
                "title": e["title"], "message": msg,
                "event_date": d, "lot_name": e["lot_name"],
                "meta": {"automatic": True, "type_label": e["type_label"], "source": e["source"]},
                "scheduled_for": now,
            })
        if created:
            new_notifs.append((e["title"], msg))

    # 3) Purga de automáticas obsoletas (ya no vigentes)
    obsolete = FarmNotification.objects.filter(user_id=user.id, farm_id=farm.id, source_type="automatic_event")
    if current_auto_keys:
        obsolete = obsolete.exclude(source_key__in=current_auto_keys)
    obsolete.delete()

    # Web push para las notificaciones recién creadas (no reenvía las existentes).
    if new_notifs:
        try:
            from .push import send_to_user
            for title, message in new_notifs[:10]:
                send_to_user(user.id, title, message, url="/eventos")
        except Exception:
            pass


def unread_qs(user):
    now = timezone.now()
    return FarmNotification.objects.filter(user_id=user.id, read_at__isnull=True).filter(
        Q(dismissed_at__isnull=True) | (Q(dismissed_until__isnull=False) & Q(dismissed_until__lte=now))
    )
