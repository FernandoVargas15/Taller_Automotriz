"""
MODELOS del taller: áreas, vehículos y la bitácora de cada uno.
"""

import re
import uuid

from django.conf import settings
from django.db import connection, models
from django.db.models import Case, Count, IntegerField, Q, Value, When
from django.db.models.functions import Cast, Coalesce, Concat
from django.utils import timezone


class Area(models.Model):
    """Por dónde va el auto. El id marca el orden natural del proceso."""

    RECEPCION = 1
    MECANICA = 2
    PINTURA = 3
    TERMINADO = 4   # listo en el taller
    ENTREGADO = 5   # el cliente ya se lo llevó: deja de contar como "en taller"

    # Todo auto entra por recepción
    POR_DEFECTO = RECEPCION

    id = models.PositiveSmallIntegerField(primary_key=True)
    nombre = models.CharField(max_length=50, unique=True)

    class Meta:
        db_table = 'areas'
        ordering = ['id']

    def __str__(self):
        return self.nombre

    @classmethod
    def de_trabajo(cls):
        """Áreas a las que el taller puede mover un auto: todas menos Entregado."""
        return cls.objects.exclude(id=cls.ENTREGADO)

    @classmethod
    def con_autos_en_taller(cls):
        """Cuántos autos hay en cada área, sin contar los entregados. Alimenta el panel."""
        return cls.de_trabajo().annotate(total=Count('vehiculos'))


class VehiculoQuerySet(models.QuerySet):

    def con_relaciones(self):
        """Trae el área y el responsable en la misma consulta (lo que antes hacía la vista v_vehiculos)."""
        return self.select_related('area', 'asignado_a__rol')

    def en_orden(self):
        """Los que siguen en el taller primero; dentro de cada grupo, el ingreso más reciente primero."""
        return (self.annotate(entregado=Case(When(area_id=Area.ENTREGADO, then=Value(1)),
                                             default=Value(0), output_field=IntegerField()))
                    .order_by('entregado', '-creado_en'))

    def en_taller(self):
        return self.exclude(area_id=Area.ENTREGADO)

    def buscar(self, texto: str):
        """
        Filtra por folio, cliente, teléfono, placas, marca, modelo o año.
        Todo se junta en una sola cadena, así "Ford Mustang" o "María 2019" también encuentran.
        """
        if not texto:
            return self

        campos = ['folio', 'cliente_nombre', 'cliente_telefono', 'placas', 'marca', 'modelo']
        partes = []
        for campo in campos:
            partes += [Coalesce(campo, Value('')), Value(' ')]
        partes.append(Coalesce(Cast('anio', models.CharField()), Value('')))

        return (self.annotate(texto_busqueda=Concat(*partes, output_field=models.CharField()))
                    .filter(texto_busqueda__icontains=texto))

    def recibidos_entre(self, periodo):
        """
        Autos RECIBIDOS en un periodo, del primero al último que entró.
        El fin es exclusivo (< y no <=): así no se pierde ningún auto recibido a
        última hora del último día ni se cuela el del día siguiente.
        """
        return (self.con_relaciones()
                    .filter(creado_en__gte=periodo.inicio_dt, creado_en__lt=periodo.fin_dt)
                    .order_by('creado_en'))

    def resumen(self):
        """Números del panel en una sola consulta."""
        return self.aggregate(
            en_taller=Count('id', filter=~Q(area_id=Area.ENTREGADO)),
            terminados=Count('id', filter=Q(area_id=Area.TERMINADO)),
            entregados=Count('id', filter=Q(area_id=Area.ENTREGADO)),
        )

    def de_folio_y_telefono(self, folio: str, telefono: str):
        """
        Consulta pública del cliente: los dos datos deben coincidir para que nadie
        vea el auto de otro adivinando folios. El teléfono se compara solo por dígitos.
        """
        folio = folio.strip()
        digitos = re.sub(r'\D+', '', telefono)

        if not folio or not digitos:
            return None

        vehiculo = self.con_relaciones().filter(folio__iexact=folio).first()

        if vehiculo is None or re.sub(r'\D+', '', vehiculo.cliente_telefono) != digitos:
            return None

        return vehiculo


class Vehiculo(models.Model):
    """Cada auto que entra al taller, con los datos de contacto de su cliente."""

    CLIENTE_MIN = 3
    CLIENTE_MAX = 100
    TELEFONO_MAX = 20
    MARCA_MAX = 50
    MODELO_MAX = 50
    COLOR_MAX = 30
    PLACAS_MAX = 15
    ANIO_MIN = 1900

    # REGLA DE EVIDENCIA: no pasa a Terminado sin al menos una foto en la bitácora
    MENSAJE_SIN_EVIDENCIA = ('No se puede marcar como Terminado: la bitácora necesita '
                             'al menos una foto del trabajo.')

    # REGLA DE FLUJO: una vez entregado, la bitácora se cierra
    MENSAJE_ENTREGADO = 'Este auto ya fue entregado: su bitácora está cerrada.'

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)

    # Folio legible para el cliente: TA-00001, TA-00002... Sale de la secuencia
    # vehiculos_folio_seq de PostgreSQL, así nunca se repite aunque se borren autos.
    folio = models.CharField(max_length=10, unique=True, editable=False)

    # El cliente no tiene cuenta: solo se guardan sus datos de contacto
    cliente_nombre = models.CharField(max_length=CLIENTE_MAX)
    cliente_telefono = models.CharField(max_length=TELEFONO_MAX)

    marca = models.CharField(max_length=MARCA_MAX)
    modelo = models.CharField(max_length=MODELO_MAX)
    anio = models.PositiveSmallIntegerField(null=True, blank=True)
    color = models.CharField(max_length=COLOR_MAX, null=True, blank=True)
    placas = models.CharField(max_length=PLACAS_MAX, null=True, blank=True)

    area = models.ForeignKey(Area, on_delete=models.PROTECT, default=Area.POR_DEFECTO, related_name='vehiculos')

    # Si se elimina al empleado, el auto queda sin asignar (no se pierde)
    asignado_a = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, db_column='asignado_a',
                                   null=True, blank=True, related_name='vehiculos_asignados')

    creado_en = models.DateTimeField(default=timezone.now)
    actualizado_en = models.DateTimeField(auto_now=True)
    entregado_en = models.DateTimeField(null=True, blank=True)

    objects = VehiculoQuerySet.as_manager()

    class Meta:
        db_table = 'vehiculos'
        ordering = ['-creado_en']
        indexes = [
            models.Index(fields=['-creado_en'], name='vehiculos_creado_en_idx'),
            models.Index(fields=['area'], name='vehiculos_area_idx'),
        ]

    def __str__(self):
        return f'{self.folio} · {self.descripcion}'

    def save(self, *args, **kwargs):
        """En un alta pide el siguiente número a la secuencia y arma el folio."""
        if not self.folio:
            with connection.cursor() as cursor:
                cursor.execute("SELECT nextval('vehiculos_folio_seq')")
                self.folio = f'TA-{cursor.fetchone()[0]:05d}'

        super().save(*args, **kwargs)

    # Lectura

    @property
    def descripcion(self):
        """'Ford Mustang 2019'"""
        return ' '.join(str(p) for p in (self.marca, self.modelo, self.anio) if p)

    @property
    def detalle(self):
        """'Rojo · ABC-123'"""
        return ' · '.join(p for p in (self.color, self.placas) if p)

    @property
    def esta_entregado(self):
        return self.area_id == Area.ENTREGADO

    @property
    def esta_terminado(self):
        return self.area_id == Area.TERMINADO

    # Reglas de negocio

    def puede_terminar(self) -> bool:
        """REGLA DE EVIDENCIA: hace falta al menos una foto en la bitácora."""
        return self.bitacora.exclude(foto='').exists()

    def esta_asignado_a(self, usuario) -> bool:
        """REGLA DE ASIGNACIÓN: un mecánico solo trabaja los autos que le asignaron."""
        return self.asignado_a_id is not None and self.asignado_a_id == usuario.pk

    def cambiar_area(self, area_id: int) -> None:
        self.area_id = area_id
        self.save(update_fields=['area', 'actualizado_en'])

    def entregar(self) -> None:
        """El cliente se lleva el auto. El mecánico sigue asignado para conservarlo en su historial."""
        self.area_id = Area.ENTREGADO
        self.entregado_en = timezone.now()
        self.save(update_fields=['area', 'entregado_en', 'actualizado_en'])


class Bitacora(models.Model):
    """
    La línea de tiempo de cada auto: reportes con foto opcional.
    Solo el administrador puede borrar entradas; el resto solo agrega.
    """

    DESCRIPCION_MIN = 5
    DESCRIPCION_MAX = 1000
    FOTO_MAX_BYTES = 5 * 1024 * 1024   # 5 MB

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    vehiculo = models.ForeignKey(Vehiculo, on_delete=models.CASCADE, related_name='bitacora')

    # Quién reportó; null si el empleado fue eliminado
    usuario = models.ForeignKey(settings.AUTH_USER_MODEL, on_delete=models.SET_NULL,
                                null=True, blank=True, related_name='reportes')

    # En qué área estaba el auto al reportar
    area = models.ForeignKey(Area, on_delete=models.PROTECT, related_name='+')

    descripcion = models.TextField()
    foto = models.ImageField(upload_to='bitacora/', blank=True)
    creado_en = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'bitacora'
        ordering = ['-creado_en']
        verbose_name_plural = 'bitácora'
        indexes = [models.Index(fields=['vehiculo', '-creado_en'], name='bitacora_vehiculo_idx')]

    def __str__(self):
        return f'{self.vehiculo.folio}: {self.descripcion[:40]}'

    @classmethod
    def registrar(cls, vehiculo, usuario, area_id: int, descripcion: str, foto=None):
        """Agrega una entrada a la línea de tiempo del auto."""
        return cls.objects.create(vehiculo=vehiculo, usuario=usuario, area_id=area_id,
                                  descripcion=descripcion.strip(), foto=foto or '')

    @classmethod
    def de_vehiculo(cls, vehiculo):
        """Entradas de un auto, la más reciente primero, con su área y su autor."""
        return cls.objects.filter(vehiculo=vehiculo).select_related('area', 'usuario__rol')
