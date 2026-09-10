from django.urls import path

from . import views

app_name = "billing"

urlpatterns = [
    path("mi-facturacion", views.index, name="index"),
]
