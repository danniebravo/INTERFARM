from django.urls import path

from . import views

app_name = "animals"

urlpatterns = [
    path("animales", views.index, name="index"),
    path("animales/crear", views.create, name="create"),
    path("animales/nuevo", views.store, name="store"),
    path("animales/trasladar", views.bulk_transfer, name="bulk-transfer"),
    path("animales/<int:pk>", views.show, name="show"),
    path("animales/<int:pk>/editar", views.edit, name="edit"),
    path("animales/<int:pk>/actualizar", views.update, name="update"),
    path("animales/<int:pk>/eliminar", views.destroy, name="destroy"),
    path("animales/<int:pk>/trasladar", views.transfer, name="transfer"),
    path("animales/<int:pk>/produccion", views.production, name="production"),
    path("animales/<int:pk>/produccion/registrar", views.store_production, name="production.store"),
    path("animales/<int:pk>/salud", views.health, name="health"),
    path("animales/<int:pk>/salud/registrar", views.store_health, name="health.store"),
    path("animales/<int:pk>/reproductivo", views.update_reproductive, name="reproductive.update"),
    path("animales/<int:pk>/cria", views.attach_offspring, name="offspring.attach"),
]
