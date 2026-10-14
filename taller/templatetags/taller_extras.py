"""
Etiquetas y filtros propios para los templates: {% load taller_extras %}
"""

import os

from django import template
from django.contrib.staticfiles import finders
from django.templatetags.static import static
from django.urls import reverse

from cuentas.models import Rol

register = template.Library()


@register.filter
def iniciales(nombre):
    """Iniciales para el avatar: "Fernando Vargas" → "FV", "Ana" → "A". Máximo 2."""
    return ''.join(palabra[0] for palabra in str(nombre).split()[:2]).upper()


@register.simple_tag
def css(nombre):
    """
    URL de una hoja de estilos con la fecha del archivo al final: /static/css/app.css?v=1758...
    Así, al editar el CSS, el navegador descarga la versión nueva en lugar de la que tiene en caché.
    """
    ruta = f'css/{nombre}.css'
    archivo = finders.find(ruta)
    version = f'?v={int(os.path.getmtime(archivo))}' if archivo else ''
    return static(ruta) + version


# Pestañas de cada rol: (nombre de la ruta, texto). El cliente y el login no tienen.
PESTANAS = {
    Rol.ADMINISTRADOR: [('taller:panel', 'Panel'), ('cuentas:usuarios', 'Usuarios'),
                        ('taller:vehiculos', 'Vehículos'), ('reportes:index', 'Reportes')],
    Rol.ASESOR: [('taller:vehiculos', 'Vehículos'), ('reportes:index', 'Reportes')],
    Rol.MECANICO: [('taller:mis_autos', 'Mis autos')],
    Rol.HOJALATERO: [('taller:mis_autos', 'Mis autos')],
}


@register.inclusion_tag('parciales/encabezado.html', takes_context=True)
def encabezado(context):
    """Barra superior: la marca, las pestañas del rol y quién entró (personal o cliente)."""
    request = context['request']
    usuario = request.user
    cliente = context.get('cliente')

    pestanas = []
    if cliente is None and usuario.is_authenticated:
        pestanas = [{'url': reverse(ruta), 'nombre': nombre} for ruta, nombre in PESTANAS.get(usuario.rol_id, [])]

    # La activa es la de prefijo más largo: /admin/usuarios/nuevo/ → "Usuarios", no "Panel"
    coincidencias = [p for p in pestanas if request.path.startswith(p['url'])]
    if coincidencias:
        max(coincidencias, key=lambda p: len(p['url']))['activa'] = True

    if cliente is not None:
        identidad = {'nombre': cliente, 'rol': 'Cliente', 'salir': reverse('taller:consulta_salir'), 'texto': 'Salir'}
    elif usuario.is_authenticated:
        identidad = {'nombre': usuario.nombre, 'rol': usuario.rol.nombre,
                     'salir': reverse('cuentas:salir'), 'texto': 'Cerrar sesión'}
    else:
        identidad = None

    return {'APP_NOMBRE': context.get('APP_NOMBRE'), 'pestanas': pestanas, 'identidad': identidad}
