from django.shortcuts import render

from cuentas.models import Rol, Usuario
from cuentas.permisos import admin_requerido

from ..models import Area, Vehiculo


def _distribucion(nombre, total, partes):
    """Una cifra del panel con su barra: cada parte lleva el ancho ya calculado en %."""
    divisor = max(total, 1)
    for parte in partes:
        parte['ancho'] = f"{parte['valor'] / divisor * 100:.2f}"
    return {'nombre': nombre, 'total': total, 'partes': partes}


@admin_requerido
def panel(request):
    """Estado del taller de un vistazo (solo administrador)."""
    resumen = Vehiculo.objects.resumen()

    distribuciones = [
        _distribucion('Autos en el taller', resumen['en_taller'], [
            {'nombre': area.nombre, 'valor': area.total, 'color': f'area{area.id}'}
            for area in Area.con_autos_en_taller()
        ]),
        _distribucion('Autos registrados', resumen['en_taller'] + resumen['entregados'], [
            {'nombre': 'En el taller', 'valor': resumen['en_taller'], 'color': 'taller'},
            {'nombre': 'Entregados', 'valor': resumen['entregados'], 'color': 'entregado'},
        ]),
        _distribucion('Personal activo', Usuario.objects.filter(activo=True).count(), [
            {'nombre': rol.nombre, 'valor': rol.total, 'color': f'rol{rol.id}'}
            for rol in Rol.con_personal_activo()
        ]),
    ]

    return render(request, 'taller/panel.html', {
        'distribuciones': distribuciones,
        'recientes': Vehiculo.objects.en_taller().con_relaciones().order_by('-creado_en')[:5],
    })
