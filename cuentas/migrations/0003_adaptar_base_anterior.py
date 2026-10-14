"""
Adapta las tablas que creó la versión anterior del sistema para que Django las use.
En una base nueva no hace nada: cada sentencia comprueba antes de cambiar.
"""

from django.db import migrations

SQL = [
    # Django anota la fecha del último inicio de sesión
    'ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS last_login TIMESTAMPTZ NULL',

    # Las contraseñas venían en bcrypt "$2y$..."; Django las reconoce como "bcrypt$$2b$..."
    # (es el mismo algoritmo). Al entrar, Django las vuelve a guardar con PBKDF2.
    "UPDATE usuarios SET password_hash = 'bcrypt$$2b$' || substr(password_hash, 5) "
    "WHERE password_hash LIKE '$2y$%'",

    # Vistas que solo usaba la versión anterior; el ORM de Django hace esos JOIN solo
    'DROP VIEW IF EXISTS v_sesiones',
    'DROP VIEW IF EXISTS v_usuarios',
]


class Migration(migrations.Migration):

    dependencies = [
        ('cuentas', '0002_roles'),
    ]

    operations = [migrations.RunSQL(sentencia, migrations.RunSQL.noop) for sentencia in SQL]
