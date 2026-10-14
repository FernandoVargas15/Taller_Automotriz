"""
Decoradores de acceso para las vistas.

    @rol_requerido(Rol.DE_MOSTRADOR)     administrador y asesor
    @rol_requerido(Rol.DE_TALLER)        mecánico y hojalatero
    @admin_requerido                     solo administrador
    @sesion_requerida                    cualquiera del personal
"""

from functools import wraps

from django.contrib import messages
from django.shortcuts import redirect

from .models import Rol


def rol_requerido(roles=None):
    """Sin sesión → al login. Con sesión pero con un rol que no está en la lista → a su propio inicio."""

    def decorador(vista):
        @wraps(vista)
        def envoltura(request, *args, **kwargs):
            if not request.user.is_authenticated:
                messages.error(request, 'Inicia sesión para continuar.')
                return redirect('cuentas:login')

            if roles is not None and request.user.rol_id not in roles:
                messages.error(request, 'No tienes permiso para entrar a esa sección.')
                return redirect('cuentas:inicio')

            return vista(request, *args, **kwargs)

        return envoltura

    return decorador


sesion_requerida = rol_requerido()
admin_requerido = rol_requerido((Rol.ADMINISTRADOR,))
