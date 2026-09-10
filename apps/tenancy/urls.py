from django.urls import path

from . import views

app_name = "tenancy"

urlpatterns = [
    path("fincas/crear", views.farm_create, name="farm_create"),
    path("fincas/cambiar", views.farm_switch, name="farm_switch"),
    path("configuracion", views.settings_index, name="settings"),
    path("configuracion/finca", views.settings_update_farm, name="settings.farm.update"),
    path("configuracion/empleados", views.settings_attach_member, name="settings.members.attach"),
    path("configuracion/empleados/<int:user_id>/rol", views.settings_member_role, name="settings.members.role"),
    path("configuracion/empleados/<int:user_id>/eliminar", views.settings_detach_member, name="settings.members.detach"),
]
