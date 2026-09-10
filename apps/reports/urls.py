from django.urls import path

from . import views

app_name = "reports"

urlpatterns = [
    path("reportes", views.index, name="index"),
    path("reportes/exportar/<str:module>", views.export, name="export"),
]
