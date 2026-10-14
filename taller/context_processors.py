from django.conf import settings


def aplicacion(request):
    """Datos que todos los templates necesitan: el nombre del taller y el correo de contacto."""
    return {
        'APP_NOMBRE': settings.APP_NOMBRE,
        'APP_CONTACTO': settings.APP_CONTACTO,
    }
