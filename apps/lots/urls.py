from django.urls import path

from . import views

app_name = "lots"

urlpatterns = [
    path("lotes", views.index, name="index"),
    path("lotes/crear", views.create, name="create"),
    path("lotes/buscar-ubicacion", views.search_location, name="location-search"),
    path("lotes/nuevo", views.store, name="store"),
    path("lotes/<int:pk>", views.show, name="show"),
    path("lotes/<int:pk>/editar", views.edit, name="edit"),
    path("lotes/<int:pk>/actualizar", views.update, name="update"),
    path("lotes/<int:pk>/eliminar", views.destroy, name="destroy"),
    path("lotes/<int:pk>/animales", views.assign_animals, name="animals.assign"),
    path("lotes/<int:pk>/historial", views.update_history, name="history.update"),
]
