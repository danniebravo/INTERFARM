from django.urls import path

from . import views

app_name = "saas"

urlpatterns = [
    path("admin/saas", views.index, name="index"),
    path("admin/saas/clientes", views.clients, name="clients"),
    path("admin/saas/clientes/<int:client_id>/ver-como", views.view_as_client, name="view-as"),
    path("admin/saas/ver-como-cliente/salir", views.stop_viewing, name="stop-viewing"),
    path("admin/saas/configuracion", views.settings_view, name="settings"),
    path("admin/saas/configuracion/actualizar", views.update_settings, name="settings.update"),
]
