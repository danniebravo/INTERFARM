from django.db import models


class PlatformSetting(models.Model):
    """Configuración global clave/valor (Wompi, Google Maps, branding, mail...)."""

    key = models.CharField(max_length=191, unique=True)
    value = models.TextField(null=True, blank=True)
    type = models.CharField(max_length=30, default="string")
    group = models.CharField(max_length=60, default="general")
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "platform_settings"

    def __str__(self):
        return self.key

    @classmethod
    def value_for(cls, key, default=None):
        row = cls.objects.filter(key=key).first()
        if row is None or row.value in (None, ""):
            return default
        return row.value

    @classmethod
    def google_maps_api_key(cls):
        from django.conf import settings
        return cls.value_for("google_maps_api_key", settings.GOOGLE_MAPS_API_KEY or "")


class FarmSetting(models.Model):
    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="settings")
    key = models.CharField(max_length=191)
    value = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "farm_settings"


class UserActivityLog(models.Model):
    user = models.ForeignKey("accounts.User", db_column="user_id",
                             on_delete=models.DO_NOTHING, related_name="activity_logs")
    action = models.CharField(max_length=120)
    description = models.CharField(max_length=255)
    route_name = models.CharField(max_length=160, null=True, blank=True)
    method = models.CharField(max_length=10, null=True, blank=True)
    ip_address = models.CharField(max_length=64, null=True, blank=True)
    user_agent = models.TextField(null=True, blank=True)
    metadata = models.JSONField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "user_activity_logs"
