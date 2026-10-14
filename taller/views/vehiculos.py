"""
VISTAS del mostrador (administrador y asesor de servicio): control general de vehículos y su bitácora.
"""

import uuid

from django.contrib import messages
from django.shortcuts import redirect, render
from django.urls import reverse
from django.utils.html import format_html
from django.views.decorators.http import require_POST

from cuentas.models import Rol
from cuentas.permisos import admin_requerido, rol_requerido

from ..forms import VehiculoForm
from ..models import Area, Bitacora, Vehiculo
from .comun import buscar_vehiculo, procesar_reporte

mostrador = rol_requerido(Rol.DE_MOSTRADOR)

# Datos del auto que se copian al reingresarlo (el área y el responsable se eligen de nuevo)
CAMPOS_REINGRESO = ['cliente_nombre', 'cliente_telefono', 'marca', 'modelo', 'anio', 'color', 'placas']


@mostrador
def lista(request):
    """Todos los autos registrados, con buscador. Los entregados van al final."""
    busqueda = request.GET.get('q', '').strip()
    vehiculos = Vehiculo.objects.con_relaciones().buscar(busqueda).en_orden()[:500]

    return render(request, 'taller/vehiculos/lista.html', {
        'vehiculos': vehiculos,
        'busqueda': busqueda,
        'total': Vehiculo.objects.count(),
    })


@mostrador
def nuevo(request):
    """
    GET: formulario de ingreso. POST: registra y vuelve a la lista.
    Con ?desde=<id> es un reingreso: el mismo auto vuelve al taller y el formulario
    llega rellenado con el cliente y el vehículo; solo se completa lo nuevo.
    """
    origen = None
    inicial = {}

    if request.method == 'GET' and request.GET.get('desde'):
        # Un id mal escrito en la URL no es un error: simplemente no hay origen
        try:
            origen = Vehiculo.objects.filter(pk=uuid.UUID(request.GET['desde'])).first()
        except ValueError:
            origen = None

        if origen is not None:
            inicial = {campo: getattr(origen, campo) for campo in CAMPOS_REINGRESO}

    form = VehiculoForm(request.POST or None, initial=inicial)

    if request.method == 'POST' and form.is_valid():
        vehiculo = form.save()
        messages.success(request, format_html('Vehículo registrado con folio <strong>{}</strong>.', vehiculo.folio))
        return redirect('taller:vehiculos')

    if request.method == 'POST':
        messages.error(request, 'Revisa los campos marcados.')

    return render(request, 'taller/vehiculos/formulario.html', {
        'form': form,
        'vehiculo': None,
        'origen': origen,
    })


@mostrador
def editar(request, id):
    """Corrige datos capturados mal: color, modelo, área o persona asignada."""
    vehiculo = buscar_vehiculo(request, id)
    if vehiculo is None:
        return redirect('taller:vehiculos')

    form = VehiculoForm(request.POST or None, instance=vehiculo)

    if request.method == 'POST' and form.is_valid():
        form.save()
        messages.success(request, format_html('Cambios del vehículo <strong>{}</strong> guardados.', vehiculo.folio))
        return redirect('taller:vehiculos')

    if request.method == 'POST':
        messages.error(request, 'Revisa los campos marcados.')

    return render(request, 'taller/vehiculos/formulario.html', {
        'form': form,
        'vehiculo': vehiculo,
        'origen': None,
    })


@require_POST
@mostrador
def entregar(request, id):
    """El cliente se lleva el auto. Solo desde Terminado."""
    vehiculo = buscar_vehiculo(request, id)
    if vehiculo is None:
        return redirect('taller:vehiculos')

    if not vehiculo.esta_terminado:
        messages.error(request, 'Solo se puede entregar un auto que esté en Terminado.')
        return redirect('taller:vehiculos')

    vehiculo.entregar()

    # Queda registrado en la línea de tiempo, como cualquier otro paso
    Bitacora.registrar(vehiculo, request.user, Area.ENTREGADO, 'Vehículo entregado al cliente.')

    messages.success(request, format_html(
        'Vehículo <strong>{}</strong> entregado. Ya no cuenta como en taller.', vehiculo.folio))
    return redirect('taller:vehiculos')


@require_POST
@admin_requerido
def eliminar(request, id):
    """Borrado definitivo del auto, su bitácora y sus fotos. Solo el administrador."""
    vehiculo = buscar_vehiculo(request, id)

    if vehiculo is not None:
        folio = vehiculo.folio
        vehiculo.delete()   # la bitácora se va en cascada y la señal borra las fotos
        messages.success(request, format_html('Vehículo <strong>{}</strong> eliminado junto con su bitácora.', folio))

    return redirect('taller:vehiculos')


@mostrador
def bitacora(request, id):
    """GET: línea de tiempo del auto. POST: agrega un reporte."""
    vehiculo = buscar_vehiculo(request, id)
    if vehiculo is None:
        return redirect('taller:vehiculos')

    form, respuesta = procesar_reporte(request, vehiculo, reverse('taller:bitacora', args=[vehiculo.pk]))
    if respuesta is not None:
        return respuesta

    return render(request, 'taller/vehiculos/bitacora.html', {
        'vehiculo': vehiculo,
        'entradas': Bitacora.de_vehiculo(vehiculo),
        'form': form,
        # Solo el administrador ve el botón de borrar en cada entrada
        'puede_eliminar': request.user.es_admin,
    })


@require_POST
@admin_requerido
def bitacora_eliminar(request, id):
    """Borra una entrada de la bitácora y su foto. Solo el administrador."""
    entrada = Bitacora.objects.filter(pk=id).first()

    if entrada is None:
        messages.error(request, 'Esa entrada ya no existe.')
        return redirect('taller:vehiculos')

    entrada.delete()   # la señal post_delete quita la foto del disco
    messages.success(request, 'Entrada eliminada de la bitácora.')
    return redirect('taller:bitacora', id=entrada.vehiculo_id)
