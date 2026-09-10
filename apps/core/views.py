from django.contrib.auth.decorators import login_required
from django.shortcuts import redirect, render


@login_required(login_url="accounts:login")
def home(request):
    user = request.user
    # Los administradores irán al panel SaaS (se conecta en Fase 4).
    if user.can_access_admin_panel():
        return render(request, "core/home.html", {"admin_landing": True})
    # Cliente sin finca → onboarding de finca (Fase 1/2).
    if not user.farms().exists():
        return redirect("tenancy:farm_create")
    return render(request, "core/home.html", {"admin_landing": False})
