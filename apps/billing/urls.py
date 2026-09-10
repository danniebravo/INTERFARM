from django.urls import path

from . import views

app_name = "billing"

urlpatterns = [
    path("mi-facturacion", views.index, name="index"),
    path("facturacion/facturas/<int:pk>/pagar", views.checkout_invoice, name="checkout"),
    path("facturacion/pagos/respuesta", views.payment_response, name="payment-response"),
    path("webhooks/wompi", views.wompi_webhook, name="wompi.webhook"),
]
