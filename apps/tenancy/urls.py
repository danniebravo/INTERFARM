from django.urls import path

from . import views

app_name = "tenancy"

urlpatterns = [
    path("fincas/crear", views.farm_create, name="farm_create"),
    path("fincas/cambiar", views.farm_switch, name="farm_switch"),
]
