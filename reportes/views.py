"""
VISTAS de reportes. Mostrador: administrador y asesor de servicio.

Mismo permiso que el listado de vehículos: el reporte no enseña ningún dato que
el mostrador no vea ya, solo lo resume por fechas. El taller no entra.
"""

from urllib.parse import urlencode

from django.contrib import messages
from django.http import HttpResponse
from django.shortcuts import redirect, render
from django.urls import reverse
from django.utils import timezone

from cuentas.models import Rol
from cuentas.permisos import rol_requerido
from taller.models import Vehiculo

from .pdf import ReporteVehiculos, resumen
from .periodo import OPCIONES, Periodo

mostrador = rol_requerido(Rol.DE_MOSTRADOR)


def _periodo_pedido(request) -> Periodo:
    """Periodo.desde() ya se encarga de los valores inventados (?periodo=hola)."""
    return Periodo.desde(request.GET.get('periodo'), request.GET.get('fecha'))


def _consulta(periodo: Periodo) -> str:
    """Los mismos parámetros, para el enlace del PDF y para volver sin perder la selección."""
    return urlencode({'periodo': periodo.tipo, 'fecha': periodo.fecha_formulario})


@mostrador
def index(request):
    """Elige el periodo y muestra lo que saldrá en el PDF."""
    periodo = _periodo_pedido(request)
    vehiculos = list(Vehiculo.objects.recibidos_entre(periodo))

    return render(request, 'reportes/index.html', {
        'periodo': periodo,
        'opciones': OPCIONES,
        'vehiculos': vehiculos,
        'cifras': resumen(vehiculos),
        'consulta': _consulta(periodo),
        'hoy': timezone.localdate().isoformat(),
    })


@mostrador
def pdf(request):
    """
    El mismo corte, ya en PDF. Se muestra en el visor del navegador en lugar de
    descargarse a ciegas; desde ahí se decide si se guarda o se imprime.
    """
    periodo = _periodo_pedido(request)
    reporte = ReporteVehiculos(periodo, Vehiculo.objects.recibidos_entre(periodo), generado_por=request.user)

    try:
        contenido = reporte.generar()
    except Exception as error:
        # Mejor un aviso claro que un archivo PDF corrupto
        messages.error(request, f'No se pudo generar el PDF: {error}')
        return redirect(reverse('reportes:index') + '?' + _consulta(periodo))

    respuesta = HttpResponse(contenido, content_type='application/pdf')
    respuesta['Content-Disposition'] = f'inline; filename="{reporte.nombre_archivo()}"'
    return respuesta
