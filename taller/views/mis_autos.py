"""
VISTAS del personal de taller (mecánico y hojalatero / pintor): solo sus autos asignados.
"""

from django.contrib import messages
from django.shortcuts import redirect, render
from django.urls import reverse
from django.utils.html import format_html
from django.views.decorators.http import require_POST

from cuentas.models import Rol
from cuentas.permisos import rol_requerido

from ..models import Area, Bitacora, Vehiculo
from .comun import procesar_reporte

de_taller = rol_requerido(Rol.DE_TALLER)


def _vehiculo_asignado(request, id):
    """
    REGLA DE ASIGNACIÓN: el auto del id, solo si existe y está asignado a quien
    entró. Si no, None con aviso (no se revela si el auto existe).
    """
    vehiculo = Vehiculo.objects.con_relaciones().filter(pk=id).first()

    if vehiculo is None or not vehiculo.esta_asignado_a(request.user):
        messages.error(request, 'Ese auto no está asignado a ti.')
        return None

    return vehiculo


@de_taller
def lista(request):
    """Los autos que le asignaron; los que siguen en el taller primero."""
    vehiculos = list(Vehiculo.objects.con_relaciones().filter(asignado_a=request.user).en_orden())

    return render(request, 'taller/mis_autos/lista.html', {
        'vehiculos': vehiculos,
        'en_taller': sum(1 for v in vehiculos if not v.esta_entregado),
    })


@de_taller
def vehiculo(request, id):
    """GET: detalle con bitácora. POST: agrega un reporte."""
    auto = _vehiculo_asignado(request, id)
    if auto is None:
        return redirect('taller:mis_autos')

    form, respuesta = procesar_reporte(request, auto, reverse('taller:vehiculo', args=[auto.pk]))
    if respuesta is not None:
        return respuesta

    return render(request, 'taller/mis_autos/vehiculo.html', {
        'vehiculo': auto,
        'entradas': Bitacora.de_vehiculo(auto),
        'areas': Area.de_trabajo(),
        'form': form,
    })


@require_POST
@de_taller
def cambiar_area(request, id):
    """Mueve el auto por el proceso: Recepción → Mecánica → Pintura → Terminado."""
    auto = _vehiculo_asignado(request, id)
    if auto is None:
        return redirect('taller:mis_autos')

    volver_a = redirect('taller:vehiculo', id=auto.pk)

    # REGLA DE FLUJO
    if auto.esta_entregado:
        messages.error(request, Vehiculo.MENSAJE_ENTREGADO)
        return volver_a

    # El mecánico no entrega: eso lo hace el mostrador cuando el cliente recoge el auto
    area_id = request.POST.get('area_id', '')
    area = Area.de_trabajo().filter(pk=int(area_id)).first() if area_id.isdigit() else None

    if area is None:
        messages.error(request, 'Selecciona un área válida.')
        return volver_a

    if area.id == auto.area_id:
        return volver_a

    # REGLA DE EVIDENCIA
    if area.id == Area.TERMINADO and not auto.puede_terminar():
        messages.error(request, Vehiculo.MENSAJE_SIN_EVIDENCIA)
        return volver_a

    auto.cambiar_area(area.id)

    # El cambio queda en la línea de tiempo para que el cliente lo vea
    Bitacora.registrar(
        auto, request.user, area.id,
        'Trabajo terminado. El auto está listo para entregarse.' if area.id == Area.TERMINADO
        else f'El auto pasa a {area.nombre}.',
    )

    messages.success(request, format_html('El auto ahora está en <strong>{}</strong>.', area.nombre))
    return volver_a
