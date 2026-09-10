from django.contrib import messages
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods, require_POST


@require_http_methods(["GET", "POST"])
def login_view(request):
    if request.user.is_authenticated:
        return redirect("core:home")

    if request.method == "POST":
        email = (request.POST.get("email") or "").strip()
        password = request.POST.get("password") or ""
        user = authenticate(request, username=email, password=password)
        if user is not None:
            login(request, user)
            # Sella el "último login" al estilo Laravel (last_login_at).
            try:
                from django.utils import timezone
                user.last_login_at = timezone.now()
                user.save(update_fields=["last_login_at"])
            except Exception:
                pass
            return redirect("core:home")
        return render(
            request,
            "accounts/login.html",
            {"error": "Credenciales inválidas.", "email": email},
        )

    return render(request, "accounts/login.html", {})


@require_http_methods(["POST"])
def logout_view(request):
    logout(request)
    return redirect("accounts:login")


# --- Perfil (portado de ProfileController) --------------------------------
@login_required(login_url="accounts:login")
def profile_edit(request):
    return render(request, "accounts/profile.html", {"account": request.user})


@login_required(login_url="accounts:login")
@require_POST
def profile_update(request):
    user = request.user
    first = (request.POST.get("first_name") or "").strip()
    last = (request.POST.get("last_name") or "").strip()
    email = (request.POST.get("email") or "").strip()
    phone = (request.POST.get("phone") or "").strip()
    if not first or not email:
        messages.error(request, "Nombre y correo son obligatorios.")
        return redirect("accounts:profile")
    # unicidad de correo
    from .models import User as UserModel
    if UserModel.objects.filter(email=email).exclude(pk=user.pk).exists():
        messages.error(request, "Ese correo ya está en uso.")
        return redirect("accounts:profile")

    email_changed = email != user.email
    user.first_name = first
    user.last_name = last
    user.phone = phone or None
    user.email = email
    user.name = (first + " " + last).strip() or user.name
    if email_changed:
        user.email_verified_at = None

    new_pw = request.POST.get("password") or ""
    if new_pw:
        if new_pw != request.POST.get("password_confirmation"):
            messages.error(request, "La confirmación de contraseña no coincide.")
            return redirect("accounts:profile")
        if len(new_pw) < 8:
            messages.error(request, "La contraseña debe tener al menos 8 caracteres.")
            return redirect("accounts:profile")
        user.set_password(new_pw)

    user.save()
    if new_pw:
        # mantener la sesión tras cambiar contraseña
        from django.contrib.auth import update_session_auth_hash
        update_session_auth_hash(request, user)
    messages.success(request, "Perfil actualizado correctamente.")
    return redirect("accounts:profile")
