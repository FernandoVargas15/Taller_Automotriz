"""
VISTAS de cuentas: entrar, salir, el reparto por rol y la gestión del personal.
"""

from django.contrib import messages
from django.contrib.auth import login, logout, update_session_auth_hash
from django.shortcuts import redirect, render
from django.utils.html import format_html
from django.views.decorators.http import require_POST

from .forms import LoginForm, UsuarioForm
from .models import Rol, SesionActiva, Usuario
from .permisos import admin_requerido, sesion_requerida


# Entrar y salir

def entrar(request):
    """GET: formulario. POST: intenta entrar."""
    if request.user.is_authenticated:
        return redirect('cuentas:inicio')

    form = LoginForm(request.POST or None)

    if request.method == 'POST' and form.is_valid():
        usuario = form.usuario
        abierta = SesionActiva.objects.filter(usuario=usuario).first()

        # REGLA DE ACCESO ÚNICO: si la cuenta ya está abierta en otro navegador, no se
        # deja entrar. Si es ESTE mismo navegador, no es una segunda sesión y pasa.
        if abierta is not None and not abierta.es_de(request.session.session_key):
            messages.error(request, 'Esta cuenta ya tiene la sesión iniciada en otro dispositivo.')
        else:
            login(request, usuario)   # regenera la clave de sesión contra fijación de sesión
            SesionActiva.abrir(usuario, request.session.session_key)
            return redirect('cuentas:inicio')

    return render(request, 'cuentas/login.html', {'form': form})


def salir(request):
    """Salida normal: libera la cuenta para que su dueño pueda entrar desde otro navegador."""
    if request.user.is_authenticated:
        SesionActiva.cerrar(request.user.pk)
    logout(request)
    return redirect('cuentas:login')


@sesion_requerida
def inicio(request):
    """Raíz de la app: manda a cada rol a su panel."""
    destinos = {
        Rol.ADMINISTRADOR: 'taller:panel',      # panel con las cifras
        Rol.ASESOR: 'taller:vehiculos',         # control de vehículos
        Rol.MECANICO: 'taller:mis_autos',       # sus autos asignados
        Rol.HOJALATERO: 'taller:mis_autos',
    }
    destino = destinos.get(request.user.rol_id)

    if destino is None:
        # Un rol fuera del catálogo solo puede venir de datos inconsistentes
        SesionActiva.cerrar(request.user.pk)
        logout(request)
        messages.error(request, 'Tu rol no tiene un panel asignado. Habla con el administrador.')
        return redirect('cuentas:login')

    return redirect(destino)


# Gestión del personal (solo administrador)

@admin_requerido
def usuarios(request):
    """Tabla de todo el personal con sus acciones y quién está en línea."""
    lista = Usuario.objects.select_related('rol', 'sesion_activa').order_by('-creado_en')[:100]

    return render(request, 'cuentas/usuarios/lista.html', {
        'usuarios': lista,
        'total': Usuario.objects.count(),
    })


@admin_requerido
def usuario_nuevo(request):
    """GET: formulario de alta. POST: registra y vuelve a la lista."""
    form = UsuarioForm(request.POST or None)

    if request.method == 'POST' and form.is_valid():
        usuario = form.save()
        messages.success(request, format_html('Usuario <strong>{}</strong> registrado correctamente.', usuario.nombre))
        return redirect('cuentas:usuarios')

    if request.method == 'POST':
        messages.error(request, 'Revisa los campos marcados.')

    return render(request, 'cuentas/usuarios/formulario.html', {'form': form, 'usuario': None})


@admin_requerido
def usuario_editar(request, id):
    """GET: formulario con los datos actuales. POST: guarda los cambios."""
    usuario = Usuario.objects.filter(pk=id).first()

    if usuario is None:
        messages.error(request, 'Ese usuario ya no existe.')
        return redirect('cuentas:usuarios')

    es_propio = usuario.pk == request.user.pk
    form = UsuarioForm(request.POST or None, instance=usuario, es_propio=es_propio)

    if request.method == 'POST' and form.is_valid():
        usuario = form.save()

        if form.cambio_contrasena:
            if es_propio:
                # Django cerraría la sesión al cambiar la contraseña; se conserva y se
                # vuelve a registrar porque la clave de sesión cambia
                update_session_auth_hash(request, usuario)
                SesionActiva.abrir(usuario, request.session.session_key)
            else:
                # Su sesión deja de valer con la contraseña nueva: se libera la cuenta
                SesionActiva.cerrar(usuario.pk)

        messages.success(request, format_html('Cambios de <strong>{}</strong> guardados.', usuario.nombre))
        return redirect('cuentas:usuarios')

    if request.method == 'POST':
        messages.error(request, 'Revisa los campos marcados.')

    return render(request, 'cuentas/usuarios/formulario.html', {
        'form': form,
        'usuario': usuario,
        'es_propio': es_propio,
    })


def _usuario_ajeno(request, id):
    """El usuario del id, siempre que exista y no sea el propio administrador. Si no, None y aviso."""
    usuario = Usuario.objects.filter(pk=id).first()

    if usuario is None:
        messages.error(request, 'Ese usuario ya no existe.')
        return None

    if usuario.pk == request.user.pk:
        messages.error(request, 'No puedes desactivar ni eliminar tu propia cuenta.')
        return None

    return usuario


@require_POST
@admin_requerido
def usuario_estado(request, id):
    """Activa o desactiva la cuenta (el empleado que renuncia pierde el acceso)."""
    usuario = _usuario_ajeno(request, id)

    if usuario is not None:
        usuario.activo = not usuario.activo
        usuario.save(update_fields=['activo'])

        if usuario.activo:
            messages.success(request, format_html('Cuenta de <strong>{}</strong> reactivada.', usuario.nombre))
        else:
            # Desactivar a alguien que está dentro debe sacarlo ya, no en su próxima visita
            SesionActiva.cerrar(usuario.pk)
            messages.success(request, format_html(
                'Cuenta de <strong>{}</strong> desactivada. Ya no podrá iniciar sesión.', usuario.nombre))

    return redirect('cuentas:usuarios')


@require_POST
@admin_requerido
def usuario_sesion(request, id):
    """
    Libera la sesión de un empleado. Es el caso de "cerró el navegador sin salir
    y ahora no puede volver a entrar": como una sesión abierta no caduca por
    tiempo, esta es la ÚNICA forma de destrabar esa cuenta.
    """
    usuario = _usuario_ajeno(request, id)

    if usuario is not None:
        SesionActiva.cerrar(usuario.pk)
        messages.success(request, format_html(
            'Sesión de <strong>{}</strong> liberada. Ya puede entrar desde cualquier dispositivo.', usuario.nombre))

    return redirect('cuentas:usuarios')


@require_POST
@admin_requerido
def usuario_eliminar(request, id):
    """Borrado definitivo. Sus autos quedan sin asignar y sus reportes sin autor."""
    usuario = _usuario_ajeno(request, id)

    if usuario is not None:
        nombre = usuario.nombre
        usuario.delete()
        messages.success(request, format_html('Usuario <strong>{}</strong> eliminado.', nombre))

    return redirect('cuentas:usuarios')
