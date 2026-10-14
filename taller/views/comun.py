"""
Lo que comparten las vistas que giran alrededor de un auto y su bitácora.
"""

from django.contrib import messages
from django.shortcuts import redirect

from ..forms import ReporteForm
from ..models import Bitacora, Vehiculo


def buscar_vehiculo(request, id):
    """El auto del id, o None con aviso si ya no existe."""
    vehiculo = Vehiculo.objects.con_relaciones().filter(pk=id).first()

    if vehiculo is None:
        messages.error(request, 'Ese vehículo ya no existe.')

    return vehiculo


def procesar_reporte(request, vehiculo, volver_a):
    """
    Formulario de reporte de la pantalla. En un POST lo valida y lo guarda en el
    área donde está el auto.

    Devuelve (formulario, respuesta). Si respuesta no es None, la vista la devuelve
    tal cual (redirección tras guardar); si no, vuelve a pintar la pantalla con
    el formulario y sus errores.
    """
    if request.method != 'POST':
        return ReporteForm(), None

    # REGLA DE FLUJO: si ya se entregó, la bitácora está cerrada
    if vehiculo.esta_entregado:
        messages.error(request, Vehiculo.MENSAJE_ENTREGADO)
        return None, redirect(volver_a)

    form = ReporteForm(request.POST, request.FILES)

    if not form.is_valid():
        messages.error(request, 'Revisa los campos marcados.')
        return form, None

    Bitacora.registrar(vehiculo, request.user, vehiculo.area_id,
                       form.cleaned_data['descripcion'], form.cleaned_data['foto'])
    messages.success(request, 'Reporte agregado a la bitácora.')
    return None, redirect(volver_a)
