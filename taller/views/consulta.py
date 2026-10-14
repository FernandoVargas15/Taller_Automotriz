"""
VISTAS de la consulta pública del cliente: no tiene cuenta ni contraseña, entra
con el folio de su comprobante y su teléfono.
"""

from django.contrib import messages
from django.shortcuts import redirect, render

from ..forms import ConsultaForm
from ..models import Bitacora, Vehiculo

# Auto que esta sesión ya comprobó; así el estado no lleva datos en la URL
AUTO = 'consulta_auto'


def acceso(request):
    """GET: formulario de acceso. POST: comprueba folio + teléfono."""
    if request.method == 'POST':
        form = ConsultaForm(request.POST)

        if form.is_valid():
            request.session[AUTO] = str(form.vehiculo.pk)
            return redirect('taller:consulta_estado')
    else:
        # Al volver al formulario se cierra la consulta anterior
        request.session.pop(AUTO, None)
        form = ConsultaForm()

    return render(request, 'taller/consulta/acceso.html', {'form': form})


def estado(request):
    """Avance del auto comprobado."""
    id = request.session.get(AUTO)
    vehiculo = Vehiculo.objects.con_relaciones().filter(pk=id).first() if id else None

    if vehiculo is None:
        messages.error(request, 'Escribe de nuevo tu folio y tu teléfono.')
        return redirect('taller:consulta')

    return render(request, 'taller/consulta/estado.html', {
        'vehiculo': vehiculo,
        'entradas': Bitacora.de_vehiculo(vehiculo),
        # La cabecera lo usa para saludar al cliente en lugar de mostrar al personal
        'cliente': vehiculo.cliente_nombre,
    })


def salir(request):
    """Cierra la consulta y vuelve al formulario."""
    request.session.pop(AUTO, None)
    return redirect('taller:consulta')
