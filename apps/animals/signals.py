"""
Mantiene `animal_lot_history` y `lot_assigned_at` cuando cambia el lote de un animal.
Equivale a Animal::booted() (saving + saved) de Laravel.
"""

from django.db.models.signals import post_save, pre_save
from django.dispatch import receiver
from django.utils import timezone

from .models import Animal, AnimalLotHistory


@receiver(pre_save, sender=Animal)
def _animal_track_lot_change(sender, instance, **kwargs):
    old_lot_id = None
    if instance.pk:
        old = Animal.objects.filter(pk=instance.pk).values_list("lot_id", flat=True).first()
        old_lot_id = old
    instance._old_lot_id = old_lot_id
    # saving(): setea lot_assigned_at cuando cambia el lote
    if old_lot_id != instance.lot_id:
        instance.lot_assigned_at = timezone.now() if instance.lot_id else None


@receiver(post_save, sender=Animal)
def _animal_maintain_lot_history(sender, instance, created, **kwargs):
    old_lot_id = getattr(instance, "_old_lot_id", None)
    new_lot_id = instance.lot_id
    if old_lot_id == new_lot_id:
        return
    now = timezone.now()
    # cierra la fila abierta anterior
    AnimalLotHistory.objects.filter(animal_id=instance.pk, exited_at__isnull=True).update(exited_at=now)
    # abre fila nueva para el lote destino
    if new_lot_id:
        AnimalLotHistory.objects.create(
            farm_id=instance.farm_id,
            animal_id=instance.pk,
            lot_id=new_lot_id,
            entered_at=instance.lot_assigned_at or now,
        )
