from django.contrib.auth import authenticate, login, logout
from django.shortcuts import redirect, render
from django.views.decorators.http import require_http_methods


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
