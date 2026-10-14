from django.contrib import messages
from django.contrib.auth import logout
from django.shortcuts import redirect

from .models import SesionActiva


class SesionUnicaMiddleware:
    """
    REGLA DE ACCESO ÚNICO: en cada petición se comprueba que la sesión de este
    navegador siga siendo LA sesión registrada de la cuenta.

    Solo se sale de aquí por dos motivos, nunca por tiempo: que la cuenta se
    haya abierto en otro dispositivo, o que el administrador haya liberado la sesión.
    """

    def __init__(self, get_response):
        self.get_response = get_response

    def __call__(self, request):
        usuario = request.user

        if usuario.is_authenticated and not SesionActiva.refrescar(usuario.pk, request.session.session_key):
            # Se cierra solo en ESTE navegador: la fila de "sesiones" es del otro
            # dispositivo, y borrarla lo expulsaría a él también.
            logout(request)
            messages.error(request, 'Tu sesión se cerró: la cuenta se abrió en otro dispositivo.')
            return redirect('cuentas:login')

        return self.get_response(request)
