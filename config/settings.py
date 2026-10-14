"""
Configuración del proyecto Django (patrón MVT: Modelo - Vista - Template).

Los datos sensibles (conexión a la base, clave secreta) no viven en el código:
se leen del archivo .env de la raíz, igual que antes.
"""

import os
from pathlib import Path

from django.contrib.messages import constants as mensajes

BASE_DIR = Path(__file__).resolve().parent.parent


def _cargar_env(ruta: Path) -> None:
    """Lee el .env una sola vez. Una variable que ya exista en el entorno manda sobre el archivo."""
    if not ruta.is_file():
        return

    for linea in ruta.read_text(encoding='utf-8').splitlines():
        linea = linea.strip()

        if not linea or linea.startswith('#') or '=' not in linea:
            continue

        clave, valor = (parte.strip() for parte in linea.split('=', 1))

        # Quita las comillas que envuelven al valor: APP_NOMBRE="Mi App"
        if len(valor) >= 2 and valor[0] == valor[-1] and valor[0] in '"\'':
            valor = valor[1:-1]

        os.environ.setdefault(clave, valor)


def env(clave: str, por_defecto: str | None = None) -> str | None:
    """Valor del .env, o por_defecto si falta o está vacío."""
    valor = os.environ.get(clave, '')
    return valor if valor != '' else por_defecto


_cargar_env(BASE_DIR / '.env')


# Entorno

DEBUG = env('APP_ENTORNO', 'desarrollo') == 'desarrollo'

SECRET_KEY = env('DJANGO_SECRET_KEY')
if SECRET_KEY is None:
    if not DEBUG:
        raise RuntimeError("Falta la variable 'DJANGO_SECRET_KEY' en el .env.")
    SECRET_KEY = 'solo-para-desarrollo-no-usar-en-produccion'

ALLOWED_HOSTS = [h.strip() for h in env('APP_HOSTS', 'localhost,127.0.0.1').split(',') if h.strip()]


# Aplicaciones: cada una agrupa sus modelos, vistas y templates

INSTALLED_APPS = [
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',

    'cuentas',    # personal, roles, login y sesión única
    'taller',     # vehículos, bitácora, panel, consulta del cliente
    'reportes',   # reporte por periodo en PDF
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
    # REGLA DE ACCESO ÚNICO: va después de la autenticación y de los mensajes
    'cuentas.middleware.SesionUnicaMiddleware',
]

ROOT_URLCONF = 'config.urls'

TEMPLATES = [
    {
        'BACKEND': 'django.template.backends.django.DjangoTemplates',
        'DIRS': [BASE_DIR / 'templates'],
        'APP_DIRS': True,
        'OPTIONS': {
            'context_processors': [
                'django.template.context_processors.request',
                'django.contrib.auth.context_processors.auth',
                'django.contrib.messages.context_processors.messages',
                'taller.context_processors.aplicacion',
            ],
        },
    },
]

WSGI_APPLICATION = 'config.wsgi.application'


# Base de datos: PostgreSQL, la misma base que usaba la versión anterior

DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.postgresql',
        'HOST': env('DB_HOST', '127.0.0.1'),
        'PORT': env('DB_PORT', '5432'),
        'NAME': env('DB_NAME', 'desarrollo_web'),
        'USER': env('DB_USER', 'postgres'),
        'PASSWORD': env('DB_PASSWORD', ''),
        'OPTIONS': {'connect_timeout': 5},
    }
}

DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'


# Autenticación: el usuario es el personal del taller y entra con su correo

AUTH_USER_MODEL = 'cuentas.Usuario'
LOGIN_URL = 'cuentas:login'

# Las reglas de la contraseña las aplica el formulario de usuarios (mínimo 8)
AUTH_PASSWORD_VALIDATORS = []

# Las contraseñas nuevas se guardan con PBKDF2. Bcrypt queda para leer las que
# venían de la versión anterior; al entrar con una de ellas, Django la vuelve a
# guardar con PBKDF2 sin que el usuario note nada.
PASSWORD_HASHERS = [
    'django.contrib.auth.hashers.PBKDF2PasswordHasher',
    'django.contrib.auth.hashers.BCryptPasswordHasher',
]


# Sesión
#
# Sistema de mostrador: el personal lo deja abierto toda la jornada. La cookie
# muere al cerrar el navegador y, del lado del servidor, la sesión aguanta
# 12 horas sin uso: lo que dura un turno de trabajo.

SESSION_EXPIRE_AT_BROWSER_CLOSE = True
SESSION_COOKIE_AGE = 12 * 60 * 60
SESSION_SAVE_EVERY_REQUEST = True
SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SAMESITE = 'Lax'


# Avisos de un solo uso: las clases CSS son aviso--exito y aviso--error

MESSAGE_TAGS = {
    mensajes.SUCCESS: 'exito',
    mensajes.ERROR: 'error',
    mensajes.INFO: 'info',
    mensajes.WARNING: 'error',
}


# Idioma y zona horaria

LANGUAGE_CODE = 'es-mx'
TIME_ZONE = 'America/Mexico_City'
USE_I18N = True
USE_TZ = True


# Archivos estáticos (CSS, imágenes) y subidos (fotos de la bitácora)

STATIC_URL = 'static/'
STATICFILES_DIRS = [BASE_DIR / 'static']
STATIC_ROOT = BASE_DIR / 'staticfiles'

MEDIA_URL = 'media/'
MEDIA_ROOT = BASE_DIR / 'media'


# Datos de la aplicación

APP_NOMBRE = env('APP_NOMBRE', 'Taller Automotriz')
APP_CONTACTO = env('APP_CONTACTO', 'admin@gmail.com')
