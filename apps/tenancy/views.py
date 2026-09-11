import json

from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods, require_POST

from .middleware import SESSION_FARM_KEY
from .models import Farm, FarmUser

AVAILABLE_PERMISSIONS = {
    "animals.view": "Ver animales",
    "animals.manage": "Crear y editar animales",
    "production.manage": "Registrar producción",
    "lots.manage": "Gestionar lotes",
    "events.manage": "Gestionar eventos",
    "settings.manage": "Administrar configuración",
}

DEFAULT_ROLES = {
    "owner": {"name": "Propietario", "description": "Acceso completo a la finca.",
              "permissions": list(AVAILABLE_PERMISSIONS.keys()), "custom": False},
    "manager": {"name": "Administrador de finca", "description": "Gestión de la operación diaria.",
                "permissions": ["animals.view", "animals.manage", "production.manage", "lots.manage", "events.manage"],
                "custom": False},
    "employee": {"name": "Empleado", "description": "Consulta y registro básico.",
                 "permissions": ["animals.view", "production.manage", "events.manage"], "custom": False},
}


def _slugify_role(name):
    from django.utils.text import slugify
    return slugify(name).replace("-", "_") or "rol"


def _save_custom_roles(farm_id, roles):
    from apps.saas.models import FarmSetting
    custom = {k: v for k, v in roles.items() if v.get("custom")}
    FarmSetting.objects.update_or_create(farm_id=farm_id, key="roles",
                                         defaults={"value": json.dumps(custom, ensure_ascii=False)})


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
        "available_permissions": AVAILABLE_PERMISSIONS,
    })


@login_required(login_url="accounts:login")
@require_POST
def settings_store_role(request):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    name = (request.POST.get("name") or "").strip()
    if not name:
        messages.error(request, "El nombre del rol es obligatorio.")
        return redirect("tenancy:settings")
    roles = _roles_for_farm(farm.id)
    key = _slugify_role(name)
    if key in roles:
        messages.error(request, "Ya existe un rol con ese nombre.")
        return redirect("tenancy:settings")
    perms = [p for p in request.POST.getlist("permissions") if p in AVAILABLE_PERMISSIONS]
    roles[key] = {"name": name, "description": request.POST.get("description") or None,
                  "permissions": perms, "custom": True}
    _save_custom_roles(farm.id, roles)
    messages.success(request, "Rol creado correctamente.")
    return redirect("tenancy:settings")


@login_required(login_url="accounts:login")
@require_POST
def settings_destroy_role(request, role):
    farm = _current_farm(request)
    if not farm:
        return redirect("tenancy:farm_create")
    _require_owner(request, farm)
    roles = _roles_for_farm(farm.id)
    data = roles.get(role)
    if not data:
        messages.error(request, "El rol seleccionado no existe.")
        return redirect("tenancy:settings")
    if not data.get("custom"):
        messages.warning(request, "Los roles base no se pueden eliminar.")
        return redirect("tenancy:settings")
    if FarmUser.objects.filter(farm_id=farm.id, role=role).exists():
        messages.warning(request, "No puedes eliminar un rol asignado a usuarios.")
        return redirect("tenancy:settings")
    del roles[role]
    _save_custom_roles(farm.id, roles)
    messages.success(request, "Rol eliminado correctamente.")
    return redirect("tenancy:settings")


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
