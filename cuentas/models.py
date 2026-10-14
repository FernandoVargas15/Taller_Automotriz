"""
MODELOS de cuentas: roles, personal del taller y su sesión activa.

El cliente no tiene cuenta aquí: consulta su auto con folio + teléfono.
"""

import hashlib
import hmac
import uuid

from django.contrib.auth.base_user import AbstractBaseUser, BaseUserManager
from django.db import models
from django.db.models import Count, Q
from django.db.models.functions import Lower
from django.utils import timezone


class Rol(models.Model):
    """Catálogo de roles. El id es el nivel de permiso: a menor número, mayor privilegio."""

    ADMINISTRADOR = 1
    ASESOR = 2
    MECANICO = 3
    HOJALATERO = 4

    # El personal nuevo entra como asesor
    POR_DEFECTO = ASESOR

    # Los que reciben autos asignados y trabajan en ellos
    DE_TALLER = (MECANICO, HOJALATERO)

    # Los que atienden al cliente y manejan el alta de vehículos
    DE_MOSTRADOR = (ADMINISTRADOR, ASESOR)

    id = models.PositiveSmallIntegerField(primary_key=True)
    nombre = models.CharField(max_length=50, unique=True)

    class Meta:
        db_table = 'roles'
        ordering = ['id']
        verbose_name_plural = 'roles'

    def __str__(self):
        return self.nombre

    @classmethod
    def con_personal_activo(cls):
        """Cuántas personas activas hay de cada rol. Alimenta la barra del panel."""
        return cls.objects.annotate(total=Count('usuarios', filter=Q(usuarios__activo=True)))


class UsuarioManager(BaseUserManager):
    """Altas de usuario. Django lo usa también en "manage.py createsuperuser"."""

    def create_user(self, correo, password=None, **campos):
        campos['nombre'] = (campos.get('nombre') or '').strip()
        usuario = self.model(correo=self.normalize_email(correo).strip(), **campos)
        usuario.set_password(password)
        usuario.save(using=self._db)
        return usuario

    def create_superuser(self, correo, password=None, **campos):
        """En este sistema el superusuario es simplemente un Administrador."""
        campos['rol_id'] = Rol.ADMINISTRADOR
        return self.create_user(correo, password, **campos)

    def get_by_natural_key(self, correo):
        # Correo sin distinguir mayúsculas: "Ana@Mail.com" == "ana@mail.com"
        return self.get(correo__iexact=correo)

    def asignables(self):
        """Personal activo al que se le puede asignar un auto: mecánicos y hojalateros."""
        return (self.filter(activo=True, rol_id__in=Rol.DE_TALLER)
                    .select_related('rol')
                    .order_by('rol_id', 'nombre'))


class Usuario(AbstractBaseUser):
    """
    Personal del taller. Entra con su correo y su contraseña.

    La contraseña se guarda con el hash de Django (PBKDF2), nunca en claro.
    Un empleado que renuncia no se borra: se desactiva y deja de poder entrar,
    pero su historial en la bitácora se conserva.
    """

    NOMBRE_MIN = 3
    NOMBRE_MAX = 100
    CORREO_MAX = 150
    PASSWORD_MIN = 8

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    nombre = models.CharField(max_length=NOMBRE_MAX)
    correo = models.EmailField(max_length=CORREO_MAX, unique=True)
    password = models.CharField(max_length=255, db_column='password_hash')
    rol = models.ForeignKey(Rol, on_delete=models.PROTECT, default=Rol.POR_DEFECTO, related_name='usuarios')
    activo = models.BooleanField(default=True)   # False = ya no puede entrar
    creado_en = models.DateTimeField(default=timezone.now)

    objects = UsuarioManager()

    USERNAME_FIELD = 'correo'
    EMAIL_FIELD = 'correo'
    REQUIRED_FIELDS = ['nombre']

    class Meta:
        db_table = 'usuarios'
        ordering = ['-creado_en']
        constraints = [
            # Correo único ignorando mayúsculas, garantizado por la base de datos
            models.UniqueConstraint(Lower('correo'), name='usuarios_correo_unico'),
        ]
        indexes = [models.Index(fields=['-creado_en'], name='usuarios_creado_en_idx')]

    def __str__(self):
        return self.nombre

    # Django pregunta por is_active para decidir si la cuenta puede entrar
    @property
    def is_active(self):
        return self.activo

    @property
    def es_admin(self):
        return self.rol_id == Rol.ADMINISTRADOR

    @property
    def es_de_mostrador(self):
        return self.rol_id in Rol.DE_MOSTRADOR

    @property
    def es_de_taller(self):
        return self.rol_id in Rol.DE_TALLER

    @property
    def primer_nombre(self):
        return (self.nombre.split() or [''])[0]


class SesionActiva(models.Model):
    """
    REGLA DE ACCESO ÚNICO: una cuenta solo puede estar abierta en un navegador
    o dispositivo a la vez. usuario es OneToOne (UNIQUE), así que la regla la
    sostiene la base de datos, no la confianza en el código.

    Se guarda el SHA-256 de la clave de sesión de Django, nunca la clave en
    claro: la tabla sirve para comparar, no para suplantar.

    UNA SESIÓN ABIERTA NO CADUCA POR SÍ SOLA. Termina cuando su dueño pulsa
    "Cerrar sesión", cuando el administrador la libera o cuando el propio
    usuario entra de nuevo desde otro dispositivo.
    """

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    usuario = models.OneToOneField(Usuario, on_delete=models.CASCADE, related_name='sesion_activa')
    token = models.CharField(max_length=64)
    iniciada_en = models.DateTimeField(default=timezone.now)

    # Solo informativa: el administrador ve desde cuándo no se usa una sesión
    ultima_actividad = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'sesiones'
        indexes = [models.Index(fields=['usuario', 'token'], name='sesiones_token_idx')]

    @staticmethod
    def token_de(clave_sesion: str | None) -> str:
        return hashlib.sha256((clave_sesion or '').encode()).hexdigest()

    def es_de(self, clave_sesion: str | None) -> bool:
        """¿Esta fila corresponde a la sesión de este navegador?"""
        return clave_sesion is not None and hmac.compare_digest(self.token, self.token_de(clave_sesion))

    @classmethod
    def abrir(cls, usuario, clave_sesion: str) -> None:
        """Registra la sesión recién abierta. Si había otra, se sobrescribe: siempre UNA por cuenta."""
        ahora = timezone.now()
        cls.objects.update_or_create(
            usuario=usuario,
            defaults={'token': cls.token_de(clave_sesion), 'iniciada_en': ahora, 'ultima_actividad': ahora},
        )

    @classmethod
    def refrescar(cls, usuario_id, clave_sesion: str | None) -> bool:
        """
        ¿Sigue siendo esta la sesión registrada del usuario? De paso anota la
        hora de actividad: una sola consulta comprueba y actualiza.
        """
        if clave_sesion is None:
            return False

        actualizadas = (cls.objects
                        .filter(usuario_id=usuario_id, token=cls.token_de(clave_sesion))
                        .update(ultima_actividad=timezone.now()))
        return actualizadas == 1

    @classmethod
    def cerrar(cls, usuario_id) -> None:
        """Libera la cuenta: al salir, al desactivarla o cuando el administrador la fuerza."""
        cls.objects.filter(usuario_id=usuario_id).delete()
