from django.conf import settings
from django.conf.urls.static import static
from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("django-admin/", admin.site.urls),
    path("", include("apps.core.urls")),
    path("", include("apps.accounts.urls")),
    path("", include("apps.tenancy.urls")),
    path("", include("apps.animals.urls")),
    path("", include("apps.production.urls")),
    path("", include("apps.lots.urls")),
    path("", include("apps.finance.urls")),
    path("", include("apps.events.urls")),
    path("", include("apps.reports.urls")),
    path("", include("apps.notifications.urls")),
    path("", include("apps.saas.urls")),
    path("", include("apps.billing.urls")),
]

# Servir archivos subidos (fotos de animales) en desarrollo.
if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
