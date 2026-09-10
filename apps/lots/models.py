from django.db import models


class Lot(models.Model):
    TYPE_CHOICES = [
        ("pradera", "Pradera"),
        ("potrero", "Potrero"),
        ("corral", "Corral"),
        ("descanso", "Descanso"),
        ("otro", "Otro"),
    ]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="lots")
    name = models.CharField(max_length=191)
    code = models.CharField(max_length=191, null=True, blank=True)
    type = models.CharField(max_length=191, null=True, blank=True)
    status = models.CharField(max_length=191, default="activo")
    area_manual = models.DecimalField(max_digits=12, decimal_places=2, null=True, blank=True)
    area_calculated = models.DecimalField(max_digits=12, decimal_places=2, null=True, blank=True)
    polygon = models.JSONField(null=True, blank=True)  # array de {lat, lng}
    center_lat = models.DecimalField(max_digits=10, decimal_places=7, null=True, blank=True)
    center_lng = models.DecimalField(max_digits=10, decimal_places=7, null=True, blank=True)
    description = models.TextField(null=True, blank=True)
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "lots"

    def __str__(self):
        return self.name

    def is_active(self):
        return (self.status or "").lower() == "activo"

    def has_polygon(self):
        return bool(self.polygon) and len(self.polygon) >= 3

    def display_area(self):
        value = self.area_calculated or self.area_manual
        if not value:
            return "Sin calcular"
        value = float(value)
        if value >= 10000:
            return f"{value:,.2f} m² · {value / 10000:,.3f} ha"
        return f"{value:,.2f} m²"
