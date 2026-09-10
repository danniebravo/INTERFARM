from django.urls import path

from . import views

app_name = "core"

urlpatterns = [
    path("", views.home, name="home"),
    path("service-worker.js", views.service_worker, name="service-worker"),
    path("manifest.webmanifest", views.manifest, name="manifest"),
]
