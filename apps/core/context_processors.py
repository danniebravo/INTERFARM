"""Branding global leído desde platform_settings (colores, nombre, logo).

Equivale a leer app_* de PlatformSetting en Laravel. Un fallo de BD no debe romper
el render (p. ej. login antes de tener sesión), así que cae a valores por defecto.
"""

from django.conf import settings

DEFAULTS = {
    "name": "InterFarm",
    "primary": "#166534",
    "dark": "#14532d",
    "accent": "#22c55e",
    "background": "#f6f8fb",
    "logo": None,
}


def branding(request):
    brand = dict(DEFAULTS)
    try:
        from apps.saas.models import PlatformSetting
        rows = {s.key: s.value for s in PlatformSetting.objects.filter(key__in=[
            "app_name", "app_primary_color", "app_primary_dark_color",
            "app_accent_color", "app_background_color", "app_logo_path",
        ])}
        brand["name"] = rows.get("app_name") or brand["name"]
        brand["primary"] = rows.get("app_primary_color") or brand["primary"]
        brand["dark"] = rows.get("app_primary_dark_color") or brand["dark"]
        brand["accent"] = rows.get("app_accent_color") or brand["accent"]
        brand["background"] = rows.get("app_background_color") or brand["background"]
        brand["logo"] = rows.get("app_logo_path") or None
    except Exception:
        pass
    return {"brand": brand, "vapid_public_key": settings.VAPID_PUBLIC_KEY or ""}
