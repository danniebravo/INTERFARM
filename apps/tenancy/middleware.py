"""
Resuelve la finca activa en cada request (multi-tenant) e impersonation admin.

Equivale a `User::currentFarm()` de Laravel:
- usuario efectivo = cliente impersonado (session admin_view_client_id) o el propio;
- finca activa = session['current_farm_id'] validada contra las fincas del usuario,
  si no → last_farm_id, si no → última finca disponible.
Deja en el request: `request.current_farm` y `request.effective_user`.
"""

from django.utils.deprecation import MiddlewareMixin

SESSION_FARM_KEY = "current_farm_id"
SESSION_IMPERSONATE_KEY = "admin_view_client_id"


class CurrentFarmMiddleware(MiddlewareMixin):
    def process_request(self, request):
        request.current_farm = None
        request.effective_user = None
        request.is_impersonating = False

        user = getattr(request, "user", None)
        if user is None or not user.is_authenticated:
            return

        effective = user

        # Impersonation: un admin viendo "como cliente".
        if user.can_access_admin_panel():
            client_id = request.session.get(SESSION_IMPERSONATE_KEY)
            if client_id:
                from apps.accounts.models import User as UserModel
                client = UserModel.objects.filter(pk=client_id).first()
                if client:
                    effective = client
                    request.is_impersonating = True

        request.effective_user = effective

        # Los admin no impersonando no tienen finca activa.
        if user.can_access_admin_panel() and not request.is_impersonating:
            return

        farms = list(effective.farms())
        if not farms:
            return

        farm_ids = {f.pk for f in farms}
        current_id = request.session.get(SESSION_FARM_KEY)
        chosen = None
        if current_id in farm_ids:
            chosen = next((f for f in farms if f.pk == current_id), None)
        if chosen is None:
            chosen = effective.default_farm() or farms[-1]

        request.current_farm = chosen
        if chosen and request.session.get(SESSION_FARM_KEY) != chosen.pk:
            request.session[SESSION_FARM_KEY] = chosen.pk
