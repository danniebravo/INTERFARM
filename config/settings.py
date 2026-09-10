"""
Django settings for InterFarm (migración desde Laravel).

Conserva la MISMA base de datos MySQL del sistema Laravel: los modelos usan
`managed = False` y apuntan a las tablas existentes. No redefine el esquema.
"""

from pathlib import Path
import os

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent

# Carga variables desde .env (no versionado)
load_dotenv(BASE_DIR / ".env")


def env(key, default=None):
    return os.environ.get(key, default)


def env_bool(key, default=False):
    val = os.environ.get(key)
    if val is None:
        return default
    return val.strip().lower() in ("1", "true", "yes", "on")


def env_list(key, default=""):
    raw = os.environ.get(key, default) or ""
    return [item.strip() for item in raw.split(",") if item.strip()]


# --- Seguridad -------------------------------------------------------------
SECRET_KEY = env("APP_KEY", "dev-insecure-change-me")
DEBUG = env_bool("APP_DEBUG", True)
ALLOWED_HOSTS = env_list("ALLOWED_HOSTS", "localhost,127.0.0.1")
CSRF_TRUSTED_ORIGINS = env_list("CSRF_TRUSTED_ORIGINS", "")

# --- Aplicaciones ----------------------------------------------------------
DJANGO_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
]

LOCAL_APPS = [
    "apps.core",
    "apps.accounts",
    "apps.tenancy",
    "apps.animals",
    "apps.lots",
    "apps.production",
    "apps.events",
    "apps.finance",
    "apps.reports",
    "apps.billing",
    "apps.notifications",
    "apps.saas",
]

INSTALLED_APPS = DJANGO_APPS + LOCAL_APPS

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
    # Resuelve la finca activa (multi-tenant) e impersonation admin.
    "apps.tenancy.middleware.CurrentFarmMiddleware",
    # Bloquea clientes suspendidos (solo facturación/notificaciones/logout).
    "apps.core.middleware.SuspendedClientMiddleware",
]

ROOT_URLCONF = "config.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
                "apps.core.context_processors.branding",
                "apps.tenancy.context_processors.current_farm",
            ],
        },
    },
]

WSGI_APPLICATION = "config.wsgi.application"
ASGI_APPLICATION = "config.asgi.application"

# --- Base de datos (MySQL existente, mismo esquema) ------------------------
DATABASES = {
    "default": {
        "ENGINE": "django.db.backends.mysql",
        "NAME": env("DB_DATABASE", "somosint_ganado_db"),
        "USER": env("DB_USERNAME", "root"),
        "PASSWORD": env("DB_PASSWORD", ""),
        "HOST": env("DB_HOST", "127.0.0.1"),
        "PORT": env("DB_PORT", "3306"),
        "OPTIONS": {
            "charset": "utf8mb4",
            "init_command": "SET sql_mode='STRICT_TRANS_TABLES'",
        },
    }
}

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

# --- Autenticación ---------------------------------------------------------
AUTH_USER_MODEL = "accounts.User"

# Backend que valida los hashes bcrypt heredados de Laravel ($2y$) sin resetear.
AUTHENTICATION_BACKENDS = [
    "apps.accounts.backends.LaravelCompatBackend",
    "django.contrib.auth.backends.ModelBackend",
]

# El LaravelCompatBackend valida el bcrypt heredado ($2y$) y, al primer login
# exitoso, re-hashea con el primer hasher de esta lista (bcrypt estándar).
PASSWORD_HASHERS = [
    "django.contrib.auth.hashers.BCryptSHA256PasswordHasher",
    "django.contrib.auth.hashers.PBKDF2PasswordHasher",
]

AUTH_PASSWORD_VALIDATORS = [
    {"NAME": "django.contrib.auth.password_validation.MinimumLengthValidator"},
]

LOGIN_URL = "accounts:login"
LOGIN_REDIRECT_URL = "core:home"
LOGOUT_REDIRECT_URL = "accounts:login"

# --- Internacionalización (Colombia / español) ----------------------------
LANGUAGE_CODE = "es-co"
TIME_ZONE = "America/Bogota"
USE_I18N = True
USE_TZ = False  # Laravel guardaba fechas naive; se conserva para paridad 1:1.

# --- Archivos estáticos y media -------------------------------------------
STATIC_URL = "/static/"
STATICFILES_DIRS = [BASE_DIR / "static"]
STATIC_ROOT = BASE_DIR / "staticfiles"

MEDIA_URL = "/storage/"
MEDIA_ROOT = BASE_DIR / "media"

# --- Sesiones (equivalente a la cookie de sesión de Laravel) --------------
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SAMESITE = "Lax"
SESSION_ENGINE = "django.contrib.sessions.backends.db"

# --- Integraciones (leídas también desde platform_settings en BD) ---------
GOOGLE_MAPS_API_KEY = env("GOOGLE_MAPS_API_KEY", "")
VAPID_PUBLIC_KEY = env("VAPID_PUBLIC_KEY", "")
VAPID_PRIVATE_KEY = env("VAPID_PRIVATE_KEY", "")
VAPID_SUBJECT = env("VAPID_SUBJECT", "mailto:hola@somosinterfarm.com")

HIDDEN_ADMIN_USER_IDS = [
    int(x) for x in env_list("HIDDEN_ADMIN_USER_IDS", "1") if x.isdigit()
]
