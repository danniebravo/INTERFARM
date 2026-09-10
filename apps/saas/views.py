"""Panel SaaS admin — portado (sub-bloque) de Admin\\SaasController (Laravel).

Este sub-bloque: overview/MRR, clientes, impersonation (ver como cliente) y
configuración global (Google Maps key → activa el mapa de Lotes, branding, WhatsApp,
Wompi). Planes, facturación admin, notificaciones broadcast y auditoría: pendientes.
"""

import re
from datetime import date
from functools import wraps

from django.conf import settings as dj_settings
from django.contrib import messages
from django.core.exceptions import PermissionDenied
from django.db.models import Max
from django.shortcuts import get_object_or_404, redirect, render
from django.utils.text import slugify
from django.views.decorators.http import require_POST

from apps.accounts.models import User
from apps.animals.models import Animal
from apps.billing.models import SubscriptionPlan
from apps.tenancy.models import Farm, FarmUser
from .models import PlatformSetting, UserActivityLog

BILLING_STATUSES = ["trial", "active", "past_due", "cancelled", "manual"]
USER_STATUSES = ["active", "inactive", "suspended"]
PLAN_PERIODS = ["monthly", "yearly", "one_time"]

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


# --------------------------------------------------------------------------
# Planes
# --------------------------------------------------------------------------
def _parse_features(raw):
    items = [ln.strip() for ln in (raw or "").splitlines() if ln.strip()]
    return items or None


def _unique_plan_slug(name):
    base = slugify(name) or "plan"
    slug, i = base, 1
    while SubscriptionPlan.objects.filter(slug=slug).exists():
        i += 1
        slug = f"{base}-{i}"
    return slug


@admin_required
def plans(request):
    return render(request, "saas/plans.html", {
        "plans": SubscriptionPlan.objects.order_by("sort_order", "price"),
        "periods": PLAN_PERIODS,
    })


def _plan_payload(request):
    post = request.POST
    name = (post.get("name") or "").strip()
    if not name:
        return None, "El nombre del paquete es obligatorio."
    try:
        price = float(post.get("price"))
    except (TypeError, ValueError):
        return None, "El precio es obligatorio."
    period = post.get("billing_period")
    if period not in PLAN_PERIODS:
        return None, "Periodo de facturación inválido."

    def n(v):
        return int(v) if v and str(v).isdigit() else None
    return {
        "name": name, "description": post.get("description") or None, "price": price,
        "billing_period": period, "max_farms": n(post.get("max_farms")),
        "max_users": n(post.get("max_users")), "max_animals": n(post.get("max_animals")),
        "features": _parse_features(post.get("features")),
        "is_active": post.get("is_active") in ("1", "on", "true", True),
    }, None


@admin_required
@require_POST
def store_plan(request):
    payload, err = _plan_payload(request)
    if err:
        messages.error(request, err)
        return redirect("saas:plans")
    payload["slug"] = _unique_plan_slug(payload["name"])
    payload["sort_order"] = (SubscriptionPlan.objects.aggregate(m=Max("sort_order"))["m"] or 0) + 1
    SubscriptionPlan.objects.create(**payload)
    messages.success(request, "Paquete creado correctamente.")
    return redirect("saas:plans")


@admin_required
@require_POST
def update_plan(request, pk):
    plan = get_object_or_404(SubscriptionPlan, pk=pk)
    payload, err = _plan_payload(request)
    if err:
        messages.error(request, err)
        return redirect("saas:plans")
    for k, v in payload.items():
        setattr(plan, k, v)
    plan.save()
    messages.success(request, "Paquete actualizado correctamente.")
    return redirect("saas:plans")


@admin_required
@require_POST
def destroy_plan(request, pk):
    get_object_or_404(SubscriptionPlan, pk=pk).delete()
    messages.success(request, "Paquete eliminado correctamente.")
    return redirect("saas:plans")


# --------------------------------------------------------------------------
# Gestión de clientes (detalle + estado/plan/contraseña/info)
# --------------------------------------------------------------------------
def _client_or_404(pk):
    u = get_object_or_404(User, pk=pk)
    if u.role in ADMIN_ROLES:
        raise PermissionDenied()
    return u


@admin_required
def client_show(request, pk):
    client = _client_or_404(pk)
    farms = list(client.farms())
    farm_ids = [f.id for f in farms]
    animals = list(Animal.objects.filter(farm_id__in=farm_ids)) if farm_ids else []
    metrics = {
        "farms": len(farms), "animals": len(animals),
        "active_animals": sum(1 for a in animals if a.is_active()),
        "female_animals": sum(1 for a in animals if a.is_female()),
        "male_animals": sum(1 for a in animals if a.is_male()),
    }
    plan = SubscriptionPlan.objects.filter(pk=client.subscription_plan_id).first() if client.subscription_plan_id else None
    activity = UserActivityLog.objects.filter(user_id=client.id).order_by("-created_at")[:60]
    return render(request, "saas/client_show.html", {
        "client": client, "farms": farms, "metrics": metrics, "plan": plan,
        "plans": SubscriptionPlan.objects.filter(is_active=True).order_by("sort_order", "price"),
        "activity": activity, "billing_statuses": BILLING_STATUSES, "user_statuses": USER_STATUSES,
    })


@admin_required
@require_POST
def client_update(request, pk):
    client = _client_or_404(pk)
    email = (request.POST.get("email") or "").strip()
    if not email:
        messages.error(request, "El correo es obligatorio.")
        return redirect("saas:client", pk=pk)
    if User.objects.filter(email=email).exclude(pk=client.pk).exists():
        messages.error(request, "Ese correo ya está en uso.")
        return redirect("saas:client", pk=pk)
    client.first_name = (request.POST.get("first_name") or "").strip() or client.first_name
    client.last_name = (request.POST.get("last_name") or "").strip() or client.last_name
    client.phone = (request.POST.get("phone") or "").strip() or None
    client.email = email
    client.save()
    messages.success(request, "Información del usuario actualizada correctamente.")
    return redirect("saas:client", pk=pk)


@admin_required
@require_POST
def client_status(request, pk):
    client = _client_or_404(pk)
    st = request.POST.get("status")
    if st not in USER_STATUSES:
        messages.error(request, "El estado seleccionado no es válido.")
        return redirect("saas:client", pk=pk)
    client.status = st
    client.save(update_fields=["status"])
    messages.success(request, "Estado del usuario actualizado correctamente.")
    return redirect("saas:client", pk=pk)


@admin_required
@require_POST
def client_plan(request, pk):
    client = _client_or_404(pk)
    bs = request.POST.get("billing_status")
    if bs not in BILLING_STATUSES:
        messages.error(request, "Estado de facturación inválido.")
        return redirect("saas:client", pk=pk)
    plan_id = request.POST.get("subscription_plan_id")
    client.subscription_plan_id = int(plan_id) if plan_id and plan_id.isdigit() else None
    client.billing_status = bs
    client.next_billing_date = request.POST.get("next_billing_date") or None
    client.save(update_fields=["subscription_plan_id", "billing_status", "next_billing_date"])
    messages.success(request, "Plan y fecha de facturación actualizados correctamente.")
    return redirect("saas:client", pk=pk)


@admin_required
@require_POST
def client_password(request, pk):
    client = _client_or_404(pk)
    pw = request.POST.get("password") or ""
    if len(pw) < 8 or pw != request.POST.get("password_confirmation"):
        messages.error(request, "La contraseña debe tener al menos 8 caracteres y coincidir.")
        return redirect("saas:client", pk=pk)
    client.set_password(pw)
    client.save()
    messages.success(request, "Contraseña del cliente actualizada correctamente.")
    return redirect("saas:client", pk=pk)
