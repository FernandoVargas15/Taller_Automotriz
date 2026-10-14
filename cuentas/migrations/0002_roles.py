from django.db import migrations

# Solo personal del taller. El cliente no tiene cuenta: consulta su auto con folio + teléfono.
ROLES = [
    (1, 'Administrador'),
    (2, 'Asesor de Servicio'),
    (3, 'Mecánico'),
    (4, 'Hojalatero / Pintor'),
]


def crear_roles(apps, schema_editor):
    Rol = apps.get_model('cuentas', 'Rol')
    for id, nombre in ROLES:
        Rol.objects.update_or_create(id=id, defaults={'nombre': nombre})


class Migration(migrations.Migration):

    dependencies = [
        ('cuentas', '0001_initial'),
    ]

    operations = [
        migrations.RunPython(crear_roles, migrations.RunPython.noop),
    ]
