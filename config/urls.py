from django.contrib import admin
from django.urls import include, path

urlpatterns = [
    path("django-admin/", admin.site.urls),
    path("", include("apps.core.urls")),
    path("", include("apps.accounts.urls")),
    path("", include("apps.tenancy.urls")),
    # Los demás módulos se irán conectando por fases:
    # path("", include("apps.animals.urls")),
    # path("", include("apps.lots.urls")),
    # ...
]
