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
    path("admin/saas/planes", views.plans, name="plans"),
    path("admin/saas/planes/nuevo", views.store_plan, name="plans.store"),
    path("admin/saas/planes/<int:pk>/actualizar", views.update_plan, name="plans.update"),
    path("admin/saas/planes/<int:pk>/eliminar", views.destroy_plan, name="plans.destroy"),
    path("admin/saas/clientes/<int:pk>", views.client_show, name="client"),
    path("admin/saas/clientes/<int:pk>/info", views.client_update, name="client.update"),
    path("admin/saas/clientes/<int:pk>/estado", views.client_status, name="client.status"),
    path("admin/saas/clientes/<int:pk>/plan", views.client_plan, name="client.plan"),
    path("admin/saas/clientes/<int:pk>/password", views.client_password, name="client.password"),
]
