"""Formulario de alta/edición de animal — reglas portadas de AnimalController::rules()."""

from django import forms

from .models import Animal
from .support import MAX_ANIMAL_WEIGHT_KG, MAX_CALVING_COUNT

SEX_CHOICES = [("macho", "Macho"), ("hembra", "Hembra")]
PURPOSE_CHOICES = [("", "—"), ("carne", "Carne"), ("leche", "Leche"),
                   ("doble_proposito", "Doble propósito"), ("crianza", "Crianza")]
SINO_CHOICES = [("", "—"), ("si", "Sí"), ("no", "No")]
SERVICE_CHOICES = [("", "—"), ("monta_natural", "Monta natural"),
                   ("inseminacion", "Inseminación"), ("embrion", "Embrión")]
STATUS_CHOICES = [("activo", "Activo"), ("vendido", "Vendido"), ("fallecido", "Fallecido")]


class AnimalForm(forms.Form):
    internal_code = forms.CharField(max_length=50, required=False)
    ear_tag = forms.CharField(max_length=50, required=False)
    name = forms.CharField(max_length=255, required=False)
    breed = forms.CharField(max_length=255, required=False)
    sex = forms.ChoiceField(choices=SEX_CHOICES)
    purpose = forms.ChoiceField(choices=PURPOSE_CHOICES, required=False)
    birth_date = forms.DateField(required=False)
    lot_id = forms.IntegerField(required=False)
    dam_id = forms.IntegerField(required=False)
    sire_id = forms.IntegerField(required=False)
    dam_name_manual = forms.CharField(max_length=255, required=False)
    sire_name_manual = forms.CharField(max_length=255, required=False)
    weight_current = forms.DecimalField(required=False, min_value=0, max_value=MAX_ANIMAL_WEIGHT_KG)
    location = forms.CharField(max_length=255, required=False)
    notes = forms.CharField(required=False, widget=forms.Textarea)
    has_calved_before = forms.ChoiceField(choices=SINO_CHOICES, required=False)
    is_pregnant = forms.ChoiceField(choices=SINO_CHOICES, required=False)
    pregnancy_date = forms.DateField(required=False)
    pregnancy_sire_id = forms.IntegerField(required=False)
    pregnancy_sire_name_manual = forms.CharField(max_length=255, required=False)
    service_type = forms.ChoiceField(choices=SERVICE_CHOICES, required=False)
    last_calving_date = forms.DateField(required=False)
    calving_count = forms.IntegerField(required=False, min_value=0, max_value=MAX_CALVING_COUNT)
    status = forms.ChoiceField(choices=STATUS_CHOICES, required=False)
    status_date = forms.DateField(required=False)
    status_notes = forms.CharField(required=False, widget=forms.Textarea)

    def __init__(self, *args, farm_id=None, instance_id=None, require_internal_code=False, **kwargs):
        super().__init__(*args, **kwargs)
        self.farm_id = farm_id
        self.instance_id = instance_id
        if require_internal_code:
            self.fields["internal_code"].required = True
            self.fields["internal_code"].error_messages["required"] = \
                "Debes escribir el código interno del animal."

    def _unique_identifier(self, field, label):
        value = (self.cleaned_data.get(field) or "").strip()
        if not value or not self.farm_id:
            return value
        qs = Animal.objects.filter(farm_id=self.farm_id, **{field: value})
        if self.instance_id:
            qs = qs.exclude(id=self.instance_id)
        if qs.exists():
            raise forms.ValidationError(f"Ya existe un animal con este {label} en esta finca.")
        return value

    def clean_internal_code(self):
        return self._unique_identifier("internal_code", "código interno")

    def clean_ear_tag(self):
        return self._unique_identifier("ear_tag", "arete")
