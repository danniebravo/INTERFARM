from django.urls import path

from . import views

app_name = "finance"

urlpatterns = [
    path("finanzas", views.index, name="index"),
    path("finanzas/nuevo", views.store, name="store"),
    path("finanzas/<int:pk>/actualizar", views.update, name="update"),
    path("finanzas/<int:pk>/eliminar", views.destroy, name="destroy"),
]
