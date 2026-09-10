from django.db import models


class MilkProduction(models.Model):
    PERIOD_CHOICES = [("mañana", "Mañana"), ("tarde", "Tarde")]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="milk_productions")
    animal = models.ForeignKey("animals.Animal", db_column="animal_id", null=True, blank=True,
                               on_delete=models.DO_NOTHING, related_name="milk_productions")
    production_date = models.DateField()
    period = models.CharField(max_length=10, choices=PERIOD_CHOICES, null=True, blank=True)
    liters = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    price_per_liter = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    total_income = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    weight_kg = models.DecimalField(max_digits=10, decimal_places=2, null=True, blank=True)
    feeding_type = models.CharField(max_length=191, null=True, blank=True)
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "milk_productions"

    def compute_total(self):
        return (self.liters or 0) * (self.price_per_liter or 0)


class DailyMilkProduction(models.Model):
    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="daily_milk_productions")
    production_date = models.DateField()
    liters = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    price_per_liter = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    total_income = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "daily_milk_productions"


class MeatProduction(models.Model):
    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="meat_productions")
    animal = models.ForeignKey("animals.Animal", db_column="animal_id",
                               on_delete=models.DO_NOTHING, related_name="meat_productions")
    production_date = models.DateField()
    weight_kg = models.DecimalField(max_digits=10, decimal_places=2, null=True, blank=True)
    weight_gain_kg = models.DecimalField(max_digits=10, decimal_places=2, null=True, blank=True)
    price_per_kg = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    estimated_total = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "meat_productions"


class MilkUsage(models.Model):
    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="milk_usages")
    usage_date = models.DateField()
    calf_liters = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    consumed_liters = models.DecimalField(max_digits=10, decimal_places=2, default=0)
    notes = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "milk_usages"

    @property
    def total_used_liters(self):
        return (self.calf_liters or 0) + (self.consumed_liters or 0)
