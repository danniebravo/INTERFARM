from django.contrib.auth.decorators import login_required
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods

from .middleware import SESSION_FARM_KEY
from .models import Farm, FarmUser


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
