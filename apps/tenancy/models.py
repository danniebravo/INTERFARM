from django.db import models


class Farm(models.Model):
    name = models.CharField(max_length=191)
    location = models.CharField(max_length=191, null=True, blank=True)
    hectares = models.DecimalField(max_digits=10, decimal_places=2, null=True, blank=True)
    production_type = models.CharField(max_length=191)
    description = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    PRODUCTION_TYPES = [
        ("leche", "Leche"),
        ("carne", "Carne"),
        ("doble_proposito", "Doble propósito"),
    ]

    class Meta:
        managed = False
        db_table = "farms"

    def __str__(self):
        return self.name


class FarmUser(models.Model):
    """Pivote multi-tenant usuario⇄finca (rol por finca)."""

    user = models.ForeignKey(
        "accounts.User", db_column="user_id", on_delete=models.DO_NOTHING,
        related_name="farm_links",
    )
    farm = models.ForeignKey(
        Farm, db_column="farm_id", on_delete=models.DO_NOTHING,
        related_name="user_links",
    )
    role = models.CharField(max_length=191, default="owner")
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "farm_user"

    def __str__(self):
        return f"{self.user_id}@{self.farm_id} ({self.role})"
