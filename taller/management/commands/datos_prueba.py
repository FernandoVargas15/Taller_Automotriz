"""
python manage.py datos_prueba

Datos de prueba para desarrollo. Borra todo el personal que no sea
administrador, todos los vehículos y su bitácora, y crea una persona por rol
(contraseña de todas: 12345678) con varios autos de ejemplo repartidos por las áreas.
Si no hay ningún administrador, crea admin@gmail.com.
"""

from datetime import timedelta

from django.core.management.base import BaseCommand
from django.db import connection, transaction
from django.utils import timezone

from cuentas.models import Rol, Usuario
from taller.models import Area, Bitacora, Vehiculo

CONTRASENA = '12345678'


class Command(BaseCommand):
    help = 'Carga personal, vehículos y bitácora de ejemplo (borra los datos anteriores).'

    @transaction.atomic
    def handle(self, *args, **opciones):
        ahora = timezone.now()

        def hace(dias):
            return ahora - timedelta(days=dias)

        Bitacora.objects.all().delete()
        Vehiculo.objects.all().delete()
        Usuario.objects.exclude(rol_id=Rol.ADMINISTRADOR).delete()

        # Los folios vuelven a empezar en TA-00001
        with connection.cursor() as cursor:
            cursor.execute('ALTER SEQUENCE vehiculos_folio_seq RESTART WITH 1')

        if not Usuario.objects.filter(rol_id=Rol.ADMINISTRADOR).exists():
            Usuario.objects.create_superuser('admin@gmail.com', CONTRASENA, nombre='Administrador')
            self.stdout.write('Administrador creado: admin@gmail.com')

        # Personal: uno por rol
        asesor = Usuario.objects.create_user('asesor@gmail.com', CONTRASENA, nombre='Fernando Vargas',
                                             rol_id=Rol.ASESOR)
        mecanico = Usuario.objects.create_user('mecanico@gmail.com', CONTRASENA, nombre='Luis Ramírez',
                                               rol_id=Rol.MECANICO)
        hojalatero = Usuario.objects.create_user('hojalatero@gmail.com', CONTRASENA, nombre='Carlos Mendoza',
                                                 rol_id=Rol.HOJALATERO)

        # Vehículos en distintas áreas: los de mecánica al mecánico, los de pintura al hojalatero
        autos = {}
        for (cliente, telefono, marca, modelo, anio, color, placas, area, asignado, dias, entregado) in [
            ('María López', '55 1234 5678', 'Mazda', '3', 2019, 'Rojo', 'VWX-222-G', Area.ENTREGADO, mecanico, 20, 15),
            ('Miguel Castro', '55 1122 3344', 'Honda', 'Civic', 2016, 'Plata', 'STU-111-F', Area.ENTREGADO, mecanico, 12, 5),
            ('María López', '55 1234 5678', 'Ford', 'Mustang', 1965, 'Rojo', 'ABC-123-D', Area.PINTURA, hojalatero, 6, None),
            ('Juan Pérez', '55 9988 7766', 'Nissan', 'Tsuru', 2012, 'Blanco', 'XYZ-987-A', Area.MECANICA, mecanico, 4, None),
            ('Ana Torres', '33 4455 6677', 'Volkswagen', 'Jetta', 2018, 'Gris', 'JKL-456-B', Area.TERMINADO, mecanico, 3, None),
            ('Laura Hernández', '55 6677 8899', 'Toyota', 'Corolla', 2020, 'Negro', 'PQR-654-E', Area.TERMINADO, hojalatero, 2, None),
            ('Roberto Díaz', '81 2233 4455', 'Chevrolet', 'Aveo', 2015, 'Azul', 'MNO-321-C', Area.RECEPCION, None, 1, None),
        ]:
            autos[placas] = Vehiculo.objects.create(
                cliente_nombre=cliente, cliente_telefono=telefono, marca=marca, modelo=modelo, anio=anio,
                color=color, placas=placas, area_id=area, asignado_a=asignado,
                creado_en=hace(dias), entregado_en=hace(entregado) if entregado else None,
            )

        # Bitácora: el asesor registra la recepción; el mecánico y el hojalatero, su trabajo
        for placas, usuario, area, descripcion, dias in [
            ('ABC-123-D', asesor, Area.RECEPCION, 'Recepción del vehículo. Golpe en defensa trasera y rayón en puerta derecha.', 6),
            ('ABC-123-D', hojalatero, Area.PINTURA, 'Se desmontó la defensa y se enderezó el soporte. Se aplicó primer.', 3),
            ('ABC-123-D', hojalatero, Area.PINTURA, 'Base de color aplicada. Pendiente barniz y pulido.', 1),
            ('XYZ-987-A', asesor, Area.RECEPCION, 'Ingresa por ruido en suspensión delantera y frenos.', 4),
            ('XYZ-987-A', mecanico, Area.MECANICA, 'Amortiguador izquierdo con fuga; se reemplaza el par. Balatas al 20%.', 2),
            ('JKL-456-B', asesor, Area.RECEPCION, 'Servicio de 60,000 km.', 3),
            ('JKL-456-B', mecanico, Area.MECANICA, 'Cambio de aceite, filtros y bujías. Rotación de llantas.', 2),
            ('JKL-456-B', mecanico, Area.TERMINADO, 'Prueba de manejo sin observaciones. Listo para entrega.', 1),
            ('STU-111-F', asesor, Area.RECEPCION, 'Ingresa por falla en alternador.', 12),
            ('STU-111-F', mecanico, Area.MECANICA, 'Alternador reemplazado. Batería cargada y probada.', 8),
            ('STU-111-F', asesor, Area.ENTREGADO, 'Vehículo entregado al cliente.', 5),
        ]:
            Bitacora.objects.create(vehiculo=autos[placas], usuario=usuario, area_id=area,
                                    descripcion=descripcion, creado_en=hace(dias))

        self.stdout.write(self.style.SUCCESS(
            f'Listo: {Usuario.objects.count()} usuarios, {Vehiculo.objects.count()} vehículos y '
            f'{Bitacora.objects.count()} entradas de bitácora. Contraseña de prueba: {CONTRASENA}'
        ))
