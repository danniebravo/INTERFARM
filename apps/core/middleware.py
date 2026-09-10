"""Bloqueo de clientes suspendidos — portado de EnsureClientIsNotSuspended (Laravel).

Un cliente (no admin) con estado 'suspended' solo puede ver: la página de cuenta
suspendida, su facturación (para regularizar el pago), notificaciones y logout.
Cualquier otra ruta lo redirige a /cuenta-suspendida.
"""

from django.shortcuts import redirect
from django.utils.deprecation import MiddlewareMixin

ALLOWED_PREFIXES = ("billing:", "notifications:")
ALLOWED_NAMES = {"core:suspended", "core:service-worker", "core:manifest", "accounts:logout", "accounts:login"}


class SuspendedClientMiddleware(MiddlewareMixin):
    def process_view(self, request, view_func, view_args, view_kwargs):
        user = getattr(request, "user", None)
        if not user or not user.is_authenticated:
            return None
        if user.can_access_admin_panel() or not user.is_suspended():
            return None
        match = request.resolver_match
        if match is None:
            return None
        name = match.view_name or ""
        if name in ALLOWED_NAMES or any(name.startswith(p) for p in ALLOWED_PREFIXES):
            return None
        return redirect("core:suspended")
