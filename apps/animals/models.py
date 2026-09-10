"""Modelos de animales (tabla `animals` heredada, managed=False) + fotos e historial de lote."""

import json
from datetime import date

from django.db import models

META_START = "<!--INTERFARM_META_START-->"
META_END = "<!--INTERFARM_META_END-->"


class Animal(models.Model):
    STATUS_ACTIVE = "activo"
    STATUS_SOLD = "vendido"
    STATUS_DECEASED = "fallecido"

    SEX_CHOICES = [("macho", "Macho"), ("hembra", "Hembra")]
    PURPOSE_CHOICES = [
        ("carne", "Carne"),
        ("leche", "Leche"),
        ("doble_proposito", "Doble propósito"),
        ("crianza", "Crianza"),
    ]

    farm = models.ForeignKey("tenancy.Farm", db_column="farm_id",
                             on_delete=models.DO_NOTHING, related_name="animals")
    lot = models.ForeignKey("lots.Lot", db_column="lot_id", null=True, blank=True,
                            on_delete=models.DO_NOTHING, related_name="animals")
    lot_assigned_at = models.DateTimeField(null=True, blank=True)
    internal_code = models.CharField(max_length=191, null=True, blank=True)
    ear_tag = models.CharField(max_length=191, null=True, blank=True)
    name = models.CharField(max_length=191, null=True, blank=True)
    species = models.CharField(max_length=191, default="bovino")
    breed = models.CharField(max_length=191, null=True, blank=True)
    sex = models.CharField(max_length=10, choices=SEX_CHOICES)
    category = models.CharField(max_length=191, null=True, blank=True)
    purpose = models.CharField(max_length=20, choices=PURPOSE_CHOICES, null=True, blank=True)
    birth_date = models.DateField(null=True, blank=True)
    dam = models.ForeignKey("self", db_column="dam_id", null=True, blank=True,
                            on_delete=models.DO_NOTHING, related_name="offspring_as_dam")
    sire = models.ForeignKey("self", db_column="sire_id", null=True, blank=True,
                             on_delete=models.DO_NOTHING, related_name="offspring_as_sire")
    dam_name_manual = models.CharField(max_length=191, null=True, blank=True)
    sire_name_manual = models.CharField(max_length=191, null=True, blank=True)
    has_calved_before = models.CharField(max_length=191, null=True, blank=True)
    is_pregnant = models.CharField(max_length=191, null=True, blank=True)
    pregnancy_date = models.DateField(null=True, blank=True)
    pregnancy_sire = models.ForeignKey("self", db_column="pregnancy_sire_id", null=True, blank=True,
                                       on_delete=models.DO_NOTHING, related_name="pregnancies_as_sire")
    pregnancy_sire_name_manual = models.CharField(max_length=191, null=True, blank=True)
    service_type = models.CharField(max_length=30, null=True, blank=True)
    last_calving_date = models.DateField(null=True, blank=True)
    dry_off_date = models.DateField(null=True, blank=True)
    calving_count = models.PositiveSmallIntegerField(null=True, blank=True)
    weight_birth = models.DecimalField(max_digits=8, decimal_places=2, null=True, blank=True)
    weight_current = models.DecimalField(max_digits=8, decimal_places=2, null=True, blank=True)
    last_weight_date = models.DateField(null=True, blank=True)
    status = models.CharField(max_length=191, default=STATUS_ACTIVE)
    status_date = models.DateField(null=True, blank=True)
    status_notes = models.TextField(null=True, blank=True)
    location = models.CharField(max_length=191, null=True, blank=True)
    notes = models.TextField(null=True, blank=True)
    photo = models.CharField(max_length=191, null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "animals"

    def __str__(self):
        return self.name or self.ear_tag or f"Animal #{self.pk}"

    # --- Sexo / estado ----------------------------------------------------
    def is_female(self):
        return (self.sex or "").lower() in ("hembra", "female", "f")

    def is_male(self):
        return (self.sex or "").lower() in ("macho", "male", "m")

    def is_sold(self):
        return (self.status or "").lower() in ("vendido", "vendida", "sold")

    def is_deceased(self):
        return (self.status or "").lower() in (
            "fallecido", "fallecida", "muerto", "muerta", "deceased", "dead",
        )

    def is_active(self):
        return not (self.is_sold() or self.is_deceased())

    def status_label(self):
        if self.is_sold():
            return "Vendido"
        if self.is_deceased():
            return "Fallecido"
        return "Activo"

    @staticmethod
    def status_options():
        return [
            (Animal.STATUS_ACTIVE, "Activo"),
            (Animal.STATUS_SOLD, "Vendido"),
            (Animal.STATUS_DECEASED, "Fallecido"),
        ]

    # --- Edad -------------------------------------------------------------
    def age_in_months(self):
        if not self.birth_date:
            return None
        today = date.today()
        return (today.year - self.birth_date.year) * 12 + (today.month - self.birth_date.month)

    def age_in_years(self):
        m = self.age_in_months()
        return None if m is None else m // 12

    def age_human(self):
        if not self.birth_date:
            return "—"
        today = date.today()
        years = today.year - self.birth_date.year
        months = today.month - self.birth_date.month
        if today.day < self.birth_date.day:
            months -= 1
        if months < 0:
            years -= 1
            months += 12
        parts = []
        if years:
            parts.append(f"{years} año{'s' if years != 1 else ''}")
        if months:
            parts.append(f"{months} mes{'es' if months != 1 else ''}")
        return " ".join(parts) or "Recién nacido"

    # --- Elegibilidad reproductiva / producción ---------------------------
    def can_be_dam(self):
        m = self.age_in_months()
        return self.is_female() and self.is_active() and (m is None or m >= 22)

    def can_be_sire(self):
        m = self.age_in_months()
        return self.is_male() and self.is_active() and (m is None or m >= 18)

    def can_register_milk_production(self):
        if not self.is_active():
            return False
        if not self.is_female():
            return False
        # Paridad con Laravel: requiere haber parido.
        return (
            self.has_calved_before == "si"
            or int(self.calving_count or 0) > 0
            or self.last_calving_date is not None
        )

    def can_register_production(self):
        # El peso (producción de carne) se permite en cualquier animal activo,
        # incluidas hembras/terneras sin parto. La leche va aparte
        # (can_register_milk_production). Equivale al fix aplicado en Laravel.
        return self.is_active()

    def milk_production_blocked_reason(self):
        if self.is_sold():
            return "No se puede registrar producción de leche en un animal vendido."
        if self.is_deceased():
            return "No se puede registrar producción en un animal fallecido."
        if not self.is_active():
            return "Solo se puede registrar producción de leche en animales activos dentro de la finca."
        if not self.is_female():
            return "Solo las hembras pueden registrar producción de leche."
        if not self.can_register_milk_production():
            return "Este animal aún no tiene partos registrados, por eso no puede registrar producción de leche."
        return None

    def production_blocked_reason(self):
        if self.can_register_production():
            return None
        if self.is_deceased():
            return "No se puede registrar producción en un animal fallecido."
        if self.is_sold():
            return "No se puede registrar producción en un animal vendido."
        if not self.is_active():
            return "Solo se puede registrar producción en animales activos dentro de la finca."
        return None

    def development_stage(self):
        m = self.age_in_months()
        if m is None:
            return "—"
        if self.is_female():
            if m < 12:
                return "Ternera"
            if m < 24:
                return "Novilla"
            return "Vaca"
        if m < 12:
            return "Ternero"
        if m < 24:
            return "Novillo"
        return "Toro"

    # --- Metadatos embebidos en notes (salud/producción ad-hoc) -----------
    def notes_meta(self):
        raw = self.notes or ""
        start = raw.find(META_START)
        end = raw.find(META_END)
        if start == -1 or end == -1 or end < start:
            return {}
        block = raw[start + len(META_START):end].strip()
        try:
            return json.loads(block) or {}
        except (ValueError, TypeError):
            return {}

    def clean_notes(self):
        raw = self.notes or ""
        start = raw.find(META_START)
        end = raw.find(META_END)
        if start == -1 or end == -1:
            return raw.strip()
        return (raw[:start] + raw[end + len(META_END):]).strip()

    def production_records(self):
        return self.notes_meta().get("production", [])

    def health_records(self):
        return self.notes_meta().get("health", [])


class AnimalPhoto(models.Model):
    animal = models.ForeignKey(Animal, db_column="animal_id",
                               on_delete=models.DO_NOTHING, related_name="photos")
    path = models.CharField(max_length=191)
    is_main = models.BooleanField(default=False)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "animal_photos"

    def __str__(self):
        return self.path


class AnimalLotHistory(models.Model):
    """Ingresos/salidas de lote. Se mantiene por signal al cambiar Animal.lot (ver signals.py)."""

    farm_id = models.BigIntegerField(null=True, blank=True)
    animal = models.ForeignKey(Animal, db_column="animal_id",
                               on_delete=models.DO_NOTHING, related_name="lot_history")
    lot_id = models.BigIntegerField()
    entered_at = models.DateTimeField(null=True, blank=True)
    exited_at = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(null=True, blank=True)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        managed = False
        db_table = "animal_lot_history"
