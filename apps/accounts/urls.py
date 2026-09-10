from django.urls import path

from . import views

app_name = "accounts"

urlpatterns = [
    path("login", views.login_view, name="login"),
    path("logout", views.logout_view, name="logout"),
    path("perfil", views.profile_edit, name="profile"),
    path("perfil/actualizar", views.profile_update, name="profile.update"),
]
