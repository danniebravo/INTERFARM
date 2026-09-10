"""
Modelo de usuario mapeado a la tabla `users` heredada de Laravel (managed=False).

Nota: Django (AbstractBaseUser) requiere una columna `last_login`. La tabla de
Laravel trae `last_login_at` en su lugar; el comando `setup_legacy_db` agrega
`last_login` (nullable) sin tocar el resto del esquema.
"""

from django.contrib.auth.models import AbstractBaseUser, BaseUserManager
from django.db import models


ADMIN_PERMISSION_KEYS = [
    "overview",
    "users",
    "admins",
    "billing",
    "plans",
    "notifications",
    "settings",
    "audit",
]


class UserManager(BaseUserManager):
    def create_user(self, email, password=None, **extra):
        if not email:
            raise ValueError("El correo es obligatorio")
        email = self.normalize_email(email)
        user = self.model(email=email, **extra)
        user.set_password(password)
        user.save(using=self._db)
        return user

    def create_superuser(self, email, password=None, **extra):
        extra.setdefault("role", "super_admin")
        extra.setdefault("status", "active")
        extra.setdefault("first_name", "Admin")
        extra.setdefault("last_name", "InterFarm")
        extra.setdefault("document_type", "cc")
        extra.setdefault("document", email)
        return self.create_user(email, password, **extra)


class User(AbstractBaseUser):
    # password y last_login los aporta AbstractBaseUser.
    first_name = models.CharField(max_length=191)
    last_name = models.CharField(max_length=191)
    document_type = models.CharField(max_length=191)
    name = models.CharField(max_length=191, null=True, blank=True)
    document = models.CharField(max_length=191, unique=True)
    phone = models.CharField(max_length=191, null=True, blank=True)
    email = models.CharField(max_length=191, unique=True)
    email_verified_at = models.DateTimeField(null=True, blank=True)
    trial_ends_at = models.DateTimeField(null=True, blank=True)
    status = models.CharField(max_length=191, default="active")
    role = models.CharField(max_length=40, default="user")
    admin_permissions = models.JSONField(null=True, blank=True)
    subscription_plan_id = models.BigIntegerField(null=True, blank=True)
    billing_status = models.CharField(max_length=40, default="trial")
    next_billing_date = models.DateField(null=True, blank=True)
    has_completed_onboarding = models.BooleanField(default=True)
    remember_token = models.CharField(max_length=100, null=True, blank=True)
    last_login_at = models.DateTimeField(null=True, blank=True)
    last_farm_id = models.BigIntegerField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    objects = UserManager()

    USERNAME_FIELD = "email"
    REQUIRED_FIELDS = ["first_name", "last_name", "document", "document_type"]

    SUPER_ADMIN_ROLES = {"super_admin", "superadmin"}
    ADMIN_ROLES = {"admin", "super_admin", "superadmin"}

    class Meta:
        managed = False
        db_table = "users"

    def __str__(self):
        return self.full_name or self.email

    # --- Nombre / presentación -------------------------------------------
    @property
    def full_name(self):
        parts = [self.first_name or "", self.last_name or ""]
        name = " ".join(p for p in parts if p).strip()
        return name or (self.name or "")

    @property
    def short_name(self):
        return (self.first_name or self.full_name or self.email).split(" ")[0]

    @property
    def initials(self):
        parts = [p for p in [self.first_name, self.last_name] if p]
        return "".join(p[0].upper() for p in parts)[:2] or (self.email[:1].upper())

    def get_full_name(self):
        return self.full_name

    def get_short_name(self):
        return self.short_name

    # --- Estado SaaS ------------------------------------------------------
    def is_active_status(self):
        return (self.status or "").lower() in ("active", "activo")

    def is_suspended(self):
        return (self.status or "").lower() in ("suspended", "suspendido")

    def is_inactive(self):
        return (self.status or "").lower() in ("inactive", "inactivo")

    @property
    def is_active(self):  # usado por los backends de auth de Django
        # Los suspendidos SÍ pueden autenticarse (para regularizar pagos);
        # la restricción operativa la aplica el middleware. Los inactivos no.
        return not self.is_inactive()

    @property
    def display_status(self):
        return {
            "active": "Activo",
            "inactive": "Inactivo",
            "suspended": "Suspendido",
        }.get((self.status or "").lower(), self.status or "")

    # --- Roles / permisos -------------------------------------------------
    def is_super_admin(self):
        return (self.role or "").lower() in self.SUPER_ADMIN_ROLES

    def is_admin_role(self):
        return (self.role or "").lower() in self.ADMIN_ROLES

    def can_access_admin_panel(self):
        return self.is_admin_role()

    @property
    def admin_permission_list(self):
        perms = self.admin_permissions
        if isinstance(perms, str):
            import json
            try:
                perms = json.loads(perms)
            except (ValueError, TypeError):
                perms = []
        return perms or []

    def has_admin_permission(self, key):
        if self.is_super_admin():
            return True
        if not self.is_admin_role():
            return False
        return key in self.admin_permission_list

    # Shims mínimos para compatibilidad con django-admin (no se usa para clientes)
    @property
    def is_staff(self):
        return self.can_access_admin_panel()

    @property
    def is_superuser(self):
        return self.is_super_admin()

    def has_perm(self, perm, obj=None):
        return self.is_super_admin()

    def has_module_perms(self, app_label):
        return self.is_super_admin()

    # --- Multi-finca ------------------------------------------------------
    def farm_memberships(self):
        from apps.tenancy.models import FarmUser
        return FarmUser.objects.filter(user_id=self.pk).select_related("farm")

    def farms(self):
        from apps.tenancy.models import Farm
        farm_ids = self.farm_memberships().values_list("farm_id", flat=True)
        return Farm.objects.filter(pk__in=list(farm_ids))

    def default_farm(self):
        """Finca por defecto: last_farm_id si es válida, si no la última disponible."""
        from apps.tenancy.models import Farm
        qs = self.farms()
        if self.last_farm_id:
            farm = qs.filter(pk=self.last_farm_id).first()
            if farm:
                return farm
        return qs.order_by("-id").first()
