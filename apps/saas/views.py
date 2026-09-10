"""Panel SaaS admin — portado (sub-bloque) de Admin\\SaasController (Laravel).

Este sub-bloque: overview/MRR, clientes, impersonation (ver como cliente) y
configuración global (Google Maps key → activa el mapa de Lotes, branding, WhatsApp,
Wompi). Planes, facturación admin, notificaciones broadcast y auditoría: pendientes.
"""

from datetime import date
from functools import wraps

from django.conf import settings as dj_settings
from django.contrib import messages
from django.core.exceptions import PermissionDenied
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from apps.accounts.models import User
from apps.animals.models import Animal
from apps.billing.models import SubscriptionPlan
from apps.tenancy.models import Farm, FarmUser
from .models import PlatformSetting

ADMIN_ROLES = ["admin", "super_admin", "superadmin"]

SETTING_KEYS = [
    "support_whatsapp_url", "support_whatsapp_message",
    "payment_gateway", "billing_currency", "billing_email", "trial_days",
    "wompi_environment", "wompi_public_key", "wompi_private_key",
    "wompi_events_key", "wompi_integrity_secret", "wompi_webhook_url",
    "google_maps_api_key",
    "app_primary_color", "app_primary_dark_color", "app_accent_color",
    "app_background_color", "app_dark_background_color",
]


def admin_required(view):
    @wraps(view)
    def wrapper(request, *args, **kwargs):
        user = getattr(request, "user", None)
        if not user or not user.is_authenticated or not user.can_access_admin_panel():
            raise PermissionDenied("Solo administradores.")
        return view(request, *args, **kwargs)
    return wrapper


def _settings_dict():
    return {s.key: s.value for s in PlatformSetting.objects.all()}


def _client_qs():
    return User.objects.exclude(role__in=ADMIN_ROLES)


def _estimated_mrr(clients):
    plans = {p.id: p for p in SubscriptionPlan.objects.all()}
    total = 0.0
    for c in clients:
        p = plans.get(c.subscription_plan_id)
        if not p:
            continue
        price = float(p.price or 0)
        if (p.billing_period or "").lower() in ("yearly", "annual", "anual"):
            price /= 12.0
        total += price
    return round(total, 2)


# --------------------------------------------------------------------------
@admin_required
def index(request):
    clients = list(_client_qs())
    client_ids = [c.id for c in clients]
    client_farm_ids = list(FarmUser.objects.filter(user_id__in=client_ids).values_list("farm_id", flat=True).distinct())
    billable = [c for c in clients if c.subscription_plan_id]

    stats = {
        "clients": len(clients),
        "active_clients": sum(1 for c in clients if (c.status or "").lower() in ("active", "activo")),
        "farms": Farm.objects.count(),
        "animals": Animal.objects.filter(farm_id__in=client_farm_ids).count() if client_farm_ids else 0,
        "plans": SubscriptionPlan.objects.count(),
        "estimated_mrr": _estimated_mrr(clients),
        "billable_clients": len(billable),
    }
    recent = _client_qs().order_by("-id")[:8]
    return render(request, "saas/index.html", {"stats": stats, "recent_clients": recent})


@admin_required
def clients(request):
    q = (request.GET.get("q") or "").strip()
    qs = _client_qs()
    if q:
        from django.db.models import Q
        qs = qs.filter(Q(first_name__icontains=q) | Q(last_name__icontains=q) | Q(email__icontains=q))
    rows = []
    for c in qs.order_by("first_name", "email")[:200]:
        farms = list(c.farms().values_list("name", flat=True))
        rows.append({"user": c, "farms": farms, "farm_count": len(farms)})
    return render(request, "saas/clients.html", {"clients": rows, "q": q})


@admin_required
@require_POST
def view_as_client(request, client_id):
    client = get_object_or_404(User, pk=client_id)
    farm = client.farms().order_by("-id").first()
    if not farm:
        messages.warning(request, "Este cliente todavía no tiene fincas para visualizar.")
        return redirect("saas:clients")
    request.session["admin_view_client_id"] = client.id
    request.session["current_farm_id"] = farm.id
    messages.success(request, f"Estás viendo la plataforma como {client.full_name or client.email}.")
    return redirect("core:home")


@admin_required
@require_POST
def stop_viewing(request):
    request.session.pop("admin_view_client_id", None)
    request.session.pop("current_farm_id", None)
    messages.success(request, "Volviste al modo administrador.")
    return redirect("saas:index")


@admin_required
def settings_view(request):
    return render(request, "saas/settings.html", {"settings": _settings_dict()})


@admin_required
@require_POST
def update_settings(request):
    for key in SETTING_KEYS:
        if key not in request.POST:
            continue
        value = (request.POST.get(key) or "").strip() or None
        group = ("appearance" if key.startswith("app_")
                 else "integrations" if key == "google_maps_api_key"
                 else "payments" if (key.startswith("wompi_") or key.startswith("payment_") or key in ("billing_currency", "billing_email"))
                 else "support")
        stype = "integer" if key == "trial_days" else ("color" if key.endswith("_color") else "string")
        PlatformSetting.objects.update_or_create(key=key, defaults={"value": value, "type": stype, "group": group})

    logo = request.FILES.get("app_logo")
    if logo:
        (dj_settings.MEDIA_ROOT / "branding").mkdir(parents=True, exist_ok=True)
        ext = (logo.name.rsplit(".", 1)[-1] if "." in logo.name else "png").lower()
        rel = f"branding/logo.{ext}"
        (dj_settings.MEDIA_ROOT / rel).write_bytes(logo.read())
        PlatformSetting.objects.update_or_create(key="app_logo_path", defaults={"value": rel, "type": "image", "group": "appearance"})

    messages.success(request, "Configuración SaaS actualizada correctamente.")
    return redirect("saas:settings")
