"""Branding global (colores, nombre) — luego leído desde platform_settings."""


def branding(request):
    return {
        "brand": {
            "name": "InterFarm",
            "primary": "#166534",
            "accent": "#22c55e",
        }
    }
