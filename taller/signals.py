from django.db.models.signals import post_delete
from django.dispatch import receiver

from .models import Bitacora


@receiver(post_delete, sender=Bitacora)
def borrar_foto(sender, instance, **kwargs):
    """
    Al borrar una entrada se quita su foto del disco. También se dispara cuando se
    elimina el vehículo completo (su bitácora se borra en cascada).
    """
    if instance.foto:
        instance.foto.delete(save=False)
