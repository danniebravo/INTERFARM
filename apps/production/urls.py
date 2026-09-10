from django.urls import path

from . import views

app_name = "production"

urlpatterns = [
    path("produccion", views.index, name="index"),
    path("produccion/leche", views.store_milk, name="milk.store"),
    path("produccion/leche/total-diario", views.store_daily_milk, name="milk-daily.store"),
    path("produccion/leche/uso-interno", views.store_milk_usage, name="milk-usage.store"),
    path("produccion/leche/<int:pk>/eliminar", views.destroy_milk, name="milk.destroy"),
    path("produccion/leche/total-diario/<int:pk>/eliminar", views.destroy_daily_milk, name="milk-daily.destroy"),
    path("produccion/leche/uso-interno/<int:pk>/eliminar", views.destroy_milk_usage, name="milk-usage.destroy"),
    path("produccion/leche/borrar-multiple", views.bulk_destroy_milk, name="milk.bulk-destroy"),
    path("produccion/carne", views.store_meat, name="meat.store"),
    path("produccion/carne/<int:pk>/eliminar", views.destroy_meat, name="meat.destroy"),
]
