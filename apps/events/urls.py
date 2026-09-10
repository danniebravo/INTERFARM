from django.urls import path

from . import views

app_name = "events"

urlpatterns = [
    path("eventos", views.index, name="index"),
    path("eventos/feed", views.feed, name="feed"),
    path("eventos/nuevo", views.store, name="store"),
    path("eventos/<int:pk>", views.show, name="show"),
    path("eventos/<int:pk>/actualizar", views.update, name="update"),
    path("eventos/<int:pk>/eliminar", views.destroy, name="destroy"),
    path("eventos/<int:pk>/estado", views.update_status, name="update-status"),
]
