from django.urls import path

from . import views

app_name = "notifications"

urlpatterns = [
    path("notificaciones/sincronizar", views.sync, name="sync"),
    path("notificaciones/lista", views.list_json, name="list"),
    path("notificaciones/marcar-todas", views.mark_all_read, name="mark-all-read"),
    path("notificaciones/<int:pk>/leer", views.read, name="read"),
    path("notificaciones/<int:pk>/descartar", views.dismiss, name="dismiss"),
    path("notificaciones/push/suscribir", views.subscribe_push, name="push.subscribe"),
    path("notificaciones/push/desuscribir", views.unsubscribe_push, name="push.unsubscribe"),
]
