from django.db import migrations

# Por dónde va el auto, en el orden natural del proceso
AREAS = [
    (1, 'Recepción'),
    (2, 'Mecánica'),
    (3, 'Pintura'),
    (4, 'Terminado'),
    (5, 'Entregado'),
]


def crear_areas(apps, schema_editor):
    Area = apps.get_model('taller', 'Area')
    for id, nombre in AREAS:
        Area.objects.update_or_create(id=id, defaults={'nombre': nombre})


class Migration(migrations.Migration):

    dependencies = [
        ('taller', '0001_initial'),
    ]

    operations = [
        migrations.RunPython(crear_areas, migrations.RunPython.noop),
    ]
