import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods, require_POST

from .middleware import SESSION_FARM_KEY
from .models import Farm, FarmUser

DEFAULT_ROLES = {
    "owner": {"name": "Propietario", "custom": False},
    "manager": {"name": "Administrador de finca", "custom": False},
    "employee": {"name": "Empleado", "custom": False},
}


@login_required(login_url="accounts:login")
@require_http_methods(["GET", "POST"])
def farm_create(request):
    if request.method == "POST":
        farm = Farm.objects.create(
            name=(request.POST.get("name") or "").strip(),
            location=(request.POST.get("location") or "").strip() or None,
            hectares=request.POST.get("hectares") or None,
            production_type=(request.POST.get("production_type") or "leche"),
            description=(request.POST.get("description") or "").strip() or None,
        )
        FarmUser.objects.create(user_id=request.user.pk, farm_id=farm.pk, role="owner")
        request.user.last_farm_id = farm.pk
        request.user.save(update_fields=["last_farm_id"])
        request.session[SESSION_FARM_KEY] = farm.pk
        return redirect("core:home")

    return render(request, "tenancy/farm_create.html", {
        "production_types": Farm.PRODUCTION_TYPES,
    })


@login_required(login_url="accounts:login")
@require_http_methods(["POST"])
def farm_switch(request):
    farm_id = request.POST.get("farm_id")
    try:
        farm_id = int(farm_id)
    except (TypeError, ValueError):
        return redirect("core:home")

    # Solo permite cambiar a una finca del usuario efectivo.
    effective = getattr(request, "effective_user", request.user)
    if effective.farms().filter(pk=farm_id).exists():
        request.session[SESSION_FARM_KEY] = farm_id
        if effective.pk == request.user.pk:
            request.user.last_farm_id = farm_id
            request.user.save(update_fields=["last_farm_id"])
    return redirect(request.META.get("HTTP_REFERER") or "core:home")


# --- Configuración de finca + miembros (portado de SettingsController) -----
def _roles_for_farm(farm_id):
    from apps.saas.models import FarmSetting
    roles = dict(DEFAULT_ROLES)
    row = FarmSetting.objects.filter(farm_id=farm_id, key="roles").first()
    if row and row.value:
        try:
            custom = json.loads(row.value)
            if isinstance(custom, dict):
                roles.update(custom)
        except (ValueError, TypeError):
            pass
    return roles


def _require_owner(request, farm):
    link = FarmUser.objects.filter(farm_id=farm.id, user_id=request.user.pk).first()
    if not link or link.role != "owner":
        raise PermissionDenied("Solo el propietario puede administrar la finca.")


def _current_farm(request):
    return getattr(request, "current_farm", None)


@login_required(login_url="accounts:login")
def settings_index(request):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)

    links = FarmUser.objects.filter(farm_id=farm.id).select_related("user")
    members = []
    for l in links.order_by("user__first_name"):
        members.append({"user": l.user, "role": l.role,
                        "role_name": _roles_for_farm(farm.id).get(l.role, {}).get("name", l.role)})
    return render(request, "tenancy/settings.html", {
        "farm": farm, "members": members, "roles": _roles_for_farm(farm.id),
        "production_types": Farm.PRODUCTION_TYPES,
        "owner_farms": request.user.farms().order_by("name"),
    })


@login_required(login_url="accounts:login")
@require_POST
def settings_update_farm(request):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    name = (request.POST.get("name") or "").strip()
    ptype = request.POST.get("production_type")
    if not name:
        messages.error(request, "El nombre de la finca es obligatorio.")
        return redirect("tenancy:settings")
    if ptype not in ("leche", "carne", "doble_proposito"):
        messages.error(request, "Tipo de producción inválido.")
        return redirect("tenancy:settings")
    farm.name = name
    farm.location = (request.POST.get("location") or "").strip() or None
    farm.hectares = request.POST.get("hectares") or None
    farm.production_type = ptype
    farm.description = (request.POST.get("description") or "").strip() or None
    farm.save()
    messages.success(request, "Información de la finca actualizada.")
    return redirect("tenancy:settings")


@login_required(login_url="accounts:login")
@require_POST
def settings_attach_member(request):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    from apps.accounts.models import User
    email = (request.POST.get("email") or "").strip()
    role = request.POST.get("role") or ""
    user = User.objects.filter(email=email).first()
    if not user:
        messages.error(request, "El usuario debe estar registrado para poder agregarlo a la finca.")
        return redirect("tenancy:settings")
    if role not in _roles_for_farm(farm.id):
        messages.error(request, "El rol seleccionado no existe.")
        return redirect("tenancy:settings")
    FarmUser.objects.update_or_create(farm_id=farm.id, user_id=user.id, defaults={"role": role})
    messages.success(request, "Empleado agregado correctamente.")
    return redirect("tenancy:settings")


@login_required(login_url="accounts:login")
@require_POST
def settings_member_role(request, user_id):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    role = request.POST.get("role") or ""
    if role not in _roles_for_farm(farm.id):
        messages.error(request, "El rol seleccionado no existe.")
        return redirect("tenancy:settings")
    FarmUser.objects.filter(farm_id=farm.id, user_id=user_id).update(role=role)
    messages.success(request, "Rol del empleado actualizado.")
    return redirect("tenancy:settings")


@login_required(login_url="accounts:login")
@require_POST
def settings_detach_member(request, user_id):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    if int(user_id) == int(request.user.pk):
        messages.warning(request, "No puedes quitarte el acceso a tu propia finca.")
        return redirect("tenancy:settings")
    FarmUser.objects.filter(farm_id=farm.id, user_id=user_id).delete()
    messages.success(request, "Empleado removido de la finca.")
    return redirect("tenancy:settings")
