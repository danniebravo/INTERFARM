"""Vistas de notificaciones — portado de NotificationController (Laravel).

Campana in-app: `sync` genera y devuelve conteo + lista; read/dismiss/mark-all.
El ENVÍO de web push (VAPID) queda para la fase de integraciones; aquí solo se
guarda/borra la suscripción.
"""

import hashlib

from django.contrib.auth.decorators import login_required
from django.http import JsonResponse
from django.shortcuts import get_object_or_404
from django.utils import timezone
from django.views.decorators.http import require_POST, require_http_methods

from . import support as S
from .models import FarmNotification, WebPushSubscription


def _user(request):
    return getattr(request, "effective_user", None) or request.user


def _payload(request):
    user = _user(request)
    qs = S.unread_qs(user).order_by("-scheduled_for", "-id")
    items = [{
        "id": n.id, "title": n.title, "message": n.message, "level": n.level,
        "event_date": n.event_date.isoformat() if n.event_date else None,
        "automatic": bool((n.meta or {}).get("automatic")),
        "type_label": (n.meta or {}).get("type_label"),
    } for n in qs[:30]]
    return {"success": True, "unread_count": qs.count(), "notifications": items}


@login_required
@require_POST
def sync(request):
    user = _user(request)
    farm = getattr(request, "current_farm", None)
    if farm and user:
        try:
            S.sync_for_farm_and_user(farm, user)
        except Exception:
            pass  # la sincronización no debe romper la campana
    return JsonResponse(_payload(request))


@login_required
def list_json(request):
    return JsonResponse(_payload(request))


@login_required
@require_POST
def mark_all_read(request):
    FarmNotification.objects.filter(user_id=_user(request).id, read_at__isnull=True).update(read_at=timezone.now())
    return JsonResponse({"success": True, "unread_count": 0, "notifications": []})


@login_required
@require_POST
def read(request, pk):
    n = get_object_or_404(FarmNotification, pk=pk)
    if int(n.user_id) != int(_user(request).id):
        return JsonResponse({"success": False}, status=403)
    if not n.read_at:
        n.read_at = timezone.now()
        n.save(update_fields=["read_at"])
    return JsonResponse(_payload(request))


@login_required
@require_POST
def dismiss(request, pk):
    n = get_object_or_404(FarmNotification, pk=pk)
    if int(n.user_id) != int(_user(request).id):
        return JsonResponse({"success": False}, status=403)
    n.read_at = n.read_at or timezone.now()
    n.dismissed_at = timezone.now()
    n.dismissed_until = None
    n.save(update_fields=["read_at", "dismissed_at", "dismissed_until"])
    return JsonResponse(_payload(request))


@login_required
@require_POST
def subscribe_push(request):
    endpoint = request.POST.get("endpoint")
    if not endpoint:
        return JsonResponse({"success": False, "message": "endpoint requerido"}, status=422)
    WebPushSubscription.objects.update_or_create(
        endpoint_hash=hashlib.sha256(endpoint.encode()).hexdigest(),
        defaults={
            "user_id": _user(request).id, "endpoint": endpoint,
            "public_key": request.POST.get("p256dh") or request.POST.get("keys[p256dh]"),
            "auth_token": request.POST.get("auth") or request.POST.get("keys[auth]"),
            "content_encoding": "aes128gcm",
            "user_agent": (request.META.get("HTTP_USER_AGENT") or "")[:1000],
            "last_seen_at": timezone.now(),
        })
    return JsonResponse({"success": True})


@login_required
@require_http_methods(["POST", "DELETE"])
def unsubscribe_push(request):
    endpoint = request.POST.get("endpoint") or request.GET.get("endpoint")
    if endpoint:
        WebPushSubscription.objects.filter(endpoint_hash=hashlib.sha256(endpoint.encode()).hexdigest()).delete()
    return JsonResponse({"success": True})
