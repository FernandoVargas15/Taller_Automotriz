"""
Adapta las tablas que creó la versión anterior del sistema para que Django las use.
En una base nueva solo crea la secuencia de folios.
"""

from django.db import migrations

SQL = [
    # Vistas que solo usaba la versión anterior; el ORM de Django hace esos JOIN solo
    'DROP VIEW IF EXISTS v_bitacora',
    'DROP VIEW IF EXISTS v_vehiculos',

    # Folios TA-00001, TA-00002... Si la base ya la tenía, sigue donde se quedó
    'CREATE SEQUENCE IF NOT EXISTS vehiculos_folio_seq START 1',

    # Fotos: antes la ruta empezaba en public/ ("subidas/bitacora/x.jpg"),
    # ahora empieza en media/ ("bitacora/x.jpg"). Sin foto, Django guarda '' en vez de NULL.
    "UPDATE bitacora SET foto = substr(foto, 9) WHERE foto LIKE 'subidas/%'",
    "UPDATE bitacora SET foto = '' WHERE foto IS NULL",
    'ALTER TABLE bitacora ALTER COLUMN foto SET NOT NULL',
]


class Migration(migrations.Migration):

    dependencies = [
        ('taller', '0002_areas'),
    ]

    operations = [migrations.RunSQL(sentencia, migrations.RunSQL.noop) for sentencia in SQL]
