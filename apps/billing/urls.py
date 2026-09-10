from django.urls import path

from . import views

app_name = "billing"

urlpatterns = [
    path("mi-facturacion", views.index, name="index"),
    path("facturacion/facturas/<int:pk>", views.invoice_show, name="invoice"),
    path("facturacion/facturas/<int:pk>/pdf", views.download_invoice, name="invoice.pdf"),
    path("facturacion/facturas/<int:pk>/pagar", views.checkout_invoice, name="checkout"),
    path("facturacion/pagos/respuesta", views.payment_response, name="payment-response"),
    path("facturacion/formas-de-pago", views.payment_methods, name="payment-methods"),
    path("facturacion/formas-de-pago/nueva", views.store_payment_method, name="payment-methods.store"),
    path("facturacion/formas-de-pago/<int:pk>/principal", views.default_payment_method, name="payment-methods.default"),
    path("facturacion/formas-de-pago/<int:pk>/eliminar", views.destroy_payment_method, name="payment-methods.destroy"),
    path("webhooks/wompi", views.wompi_webhook, name="wompi.webhook"),
]
