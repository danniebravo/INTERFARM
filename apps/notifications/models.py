from django.db import models


class FarmNotification(models.Model):
    LEVEL_CHOICES = [("low", "Baja"), ("medium", "Media"), ("high", "Alta"), ("critical", "Crítica")]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id", null=True, blank=True,
                             on_delete=models.DO_NOTHING, related_name="notifications")
    user = models.ForeignKey("accounts.User", db_column="user_id",
                             on_delete=models.DO_NOTHING, related_name="notifications")
    event_id = models.BigIntegerField(null=True, blank=True)
    source_type = models.CharField(max_length=80, null=True, blank=True)
    source_key = models.CharField(max_length=120, null=True, blank=True)
    level = models.CharField(max_length=30, default="medium")
    title = models.CharField(max_length=191)
    message = models.TextField()
    event_date = models.DateField(null=True, blank=True)
    lot_name = models.CharField(max_length=191, null=True, blank=True)
    meta = models.JSONField(null=True, blank=True)
    scheduled_for = models.DateTimeField(null=True, blank=True)
    read_at = models.DateTimeField(null=True, blank=True)
    dismissed_at = models.DateTimeField(null=True, blank=True)
    dismissed_until = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "farm_notifications"

    def is_read(self):
        return self.read_at is not None


class WebPushSubscription(models.Model):
    user = models.ForeignKey("accounts.User", db_column="user_id",
                             on_delete=models.DO_NOTHING, related_name="web_push_subscriptions")
    endpoint = models.TextField()
    endpoint_hash = models.CharField(max_length=64, unique=True)
    public_key = models.TextField(null=True, blank=True)
    auth_token = models.TextField(null=True, blank=True)
    content_encoding = models.CharField(max_length=32, null=True, blank=True)
    user_agent = models.TextField(null=True, blank=True)
    last_seen_at = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "web_push_subscriptions"
