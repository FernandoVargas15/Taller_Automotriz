from django.urls import path

from .views import consulta, panel, taller, vehiculos

app_name = 'taller'

urlpatterns = [
    # Solo administrador
    path('admin/', panel.panel, name='panel'),

    # Mostrador: administrador y asesor de servicio
    path('vehiculos/', vehiculos.lista, name='vehiculos'),                                    # ?q= buscador
    path('vehiculos/nuevo/', vehiculos.nuevo, name='vehiculo_nuevo'),                         # ?desde= reingreso
    path('vehiculos/<uuid:id>/editar/', vehiculos.editar, name='vehiculo_editar'),
    path('vehiculos/<uuid:id>/entregar/', vehiculos.entregar, name='vehiculo_entregar'),      # POST
    path('vehiculos/<uuid:id>/eliminar/', vehiculos.eliminar, name='vehiculo_eliminar'),      # POST, admin
    path('vehiculos/<uuid:id>/bitacora/', vehiculos.bitacora, name='bitacora'),
    path('vehiculos/bitacora/<uuid:id>/eliminar/', vehiculos.bitacora_eliminar, name='bitacora_eliminar'),  # POST, admin

    # Taller: mecánico y hojalatero / pintor, solo sus autos asignados
    path('taller/', taller.mis_autos, name='mis_autos'),
    path('taller/vehiculo/<uuid:id>/', taller.vehiculo, name='vehiculo'),
    path('taller/vehiculo/<uuid:id>/area/', taller.cambiar_area, name='vehiculo_area'),       # POST

    # Cliente: sin cuenta, entra con folio + teléfono
    path('consulta/', consulta.acceso, name='consulta'),
    path('consulta/estado/', consulta.estado, name='consulta_estado'),
    path('consulta/salir/', consulta.salir, name='consulta_salir'),
]
