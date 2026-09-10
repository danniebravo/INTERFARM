"""Branding global (colores, nombre) — luego leído desde platform_settings."""

from django.conf import settings


def branding(request):
    return {
        "brand": {
            "name": "InterFarm",
            "primary": "#166534",
            "accent": "#22c55e",
        },
        "vapid_public_key": settings.VAPID_PUBLIC_KEY or "",
    }
