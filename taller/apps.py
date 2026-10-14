from django.apps import AppConfig


class TallerConfig(AppConfig):
    name = 'taller'
    verbose_name = 'Taller'

    def ready(self):
        # Conecta las señales (borrar fotos del disco al borrar reportes)
        from . import signals  # noqa: F401
