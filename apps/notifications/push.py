"""Envío de Web Push (VAPID) — pywebpush.

Envía a todas las suscripciones activas del usuario. Suscripciones expiradas
(404/410) se eliminan. Si no hay llaves VAPID configuradas, no hace nada.
"""

import json

from django.conf import settings

from .models import WebPushSubscription


def is_configured():
    return bool(settings.VAPID_PUBLIC_KEY and settings.VAPID_PRIVATE_KEY)


def send_to_user(user_id, title, message, url="/"):
    if not is_configured():
        return 0
    try:
        from pywebpush import webpush, WebPushException
    except Exception:
        return 0

    payload = json.dumps({"title": title, "body": message, "url": url})
    claims = {"sub": settings.VAPID_SUBJECT}
    sent = 0
    for sub in WebPushSubscription.objects.filter(user_id=user_id):
        subscription_info = {
            "endpoint": sub.endpoint,
            "keys": {"p256dh": sub.public_key, "auth": sub.auth_token},
        }
        try:
            webpush(subscription_info=subscription_info, data=payload,
                    vapid_private_key=settings.VAPID_PRIVATE_KEY, vapid_claims=dict(claims))
            sent += 1
        except WebPushException as e:
            status = getattr(getattr(e, "response", None), "status_code", None)
            if status in (404, 410):
                sub.delete()  # suscripción expirada
        except Exception:
            pass
    return sent
