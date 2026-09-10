from django.db import models


class Event(models.Model):
    TYPE_CHOICES = [
        ("general", "General"),
        ("parto", "Parto"),
        ("vacuna", "Vacuna"),
        ("tratamiento", "Tratamiento"),
        ("inseminacion", "Inseminación"),
        ("celo", "Celo"),
        ("revision", "Revisión"),
    ]
    STATUS_CHOICES = [
        ("pending", "Pendiente"),
        ("completed", "Completado"),
        ("cancelled", "Cancelado"),
    ]
    PRIORITY_CHOICES = [("low", "Baja"), ("medium", "Media"), ("high", "Alta")]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="events")
    animal = models.ForeignKey("animals.Animal", db_column="animal_id", null=True, blank=True,
                               on_delete=models.DO_NOTHING, related_name="events")
    title = models.CharField(max_length=191)
    description = models.TextField(null=True, blank=True)
    type = models.CharField(max_length=50, default="general")
    event_date = models.DateField(null=True, blank=True)
    start_datetime = models.DateTimeField(null=True, blank=True)
    end_datetime = models.DateTimeField(null=True, blank=True)
    all_day = models.BooleanField(default=True)
    status = models.CharField(max_length=30, default="pending")
    priority = models.CharField(max_length=30, default="medium")
    lot_name = models.CharField(max_length=191, null=True, blank=True)
    color = models.CharField(max_length=20, null=True, blank=True)
    meta = models.JSONField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "events"

    def __str__(self):
        return self.title
