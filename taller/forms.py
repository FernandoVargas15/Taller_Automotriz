"""
Formularios del taller: alta/edición de vehículos, reportes de bitácora y la consulta del cliente.
"""

import uuid

from django import forms
from django.utils import timezone

from cuentas.forms import EstiloCampos
from cuentas.models import Usuario

from .models import Area, Bitacora, Vehiculo


class AsignableField(forms.ModelChoiceField):
    """En el <select> se ve "Luis Ramírez · Mecánico"."""

    def label_from_instance(self, usuario):
        return f'{usuario.nombre} · {usuario.rol.nombre}'


class VehiculoForm(EstiloCampos, forms.ModelForm):
    """Ingreso o corrección de un vehículo."""

    area = forms.ModelChoiceField(
        queryset=Area.objects.all(),
        empty_label=None,
        error_messages={'required': 'Selecciona un área válida.', 'invalid_choice': 'Selecciona un área válida.'},
    )
    asignado_a = AsignableField(
        queryset=Usuario.objects.none(),
        required=False,
        empty_label='Sin asignar',
        error_messages={'invalid_choice': 'Selecciona un empleado válido.'},
    )
    anio = forms.CharField(required=False)

    class Meta:
        model = Vehiculo
        fields = ['cliente_nombre', 'cliente_telefono', 'marca', 'modelo', 'anio', 'color', 'placas',
                  'area', 'asignado_a']
        widgets = {
            'cliente_nombre': forms.TextInput(attrs={'placeholder': 'María López', 'autocomplete': 'off'}),
            'cliente_telefono': forms.TextInput(attrs={'type': 'tel', 'placeholder': '55 1234 5678',
                                                       'autocomplete': 'off'}),
            'marca': forms.TextInput(attrs={'placeholder': 'Ford'}),
            'modelo': forms.TextInput(attrs={'placeholder': 'Mustang'}),
            'color': forms.TextInput(attrs={'placeholder': 'Rojo'}),
            'placas': forms.TextInput(attrs={'placeholder': 'ABC-123-D', 'style': 'text-transform: uppercase'}),
        }
        error_messages = {
            'cliente_nombre': {'required': 'El nombre del cliente es obligatorio.'},
            'cliente_telefono': {'required': 'El teléfono es obligatorio.'},
            'marca': {'required': 'La marca es obligatoria.'},
            'modelo': {'required': 'El modelo es obligatorio.'},
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.es_alta = self.instance._state.adding

        # El área como está guardada, antes de que el formulario la cambie
        self.area_original = None if self.es_alta else self.instance.area_id

        self.fields['asignado_a'].queryset = Usuario.objects.asignables()
        self.fields['anio'].widget = forms.NumberInput(attrs={
            'class': 'campo__control', 'min': Vehiculo.ANIO_MIN, 'max': self.anio_max(),
            'placeholder': '1965', 'inputmode': 'numeric',
        })

        if self.es_alta:
            self.initial.setdefault('area', Area.POR_DEFECTO)

    @staticmethod
    def anio_max():
        return timezone.localdate().year + 1

    def clean_cliente_nombre(self):
        nombre = self.cleaned_data['cliente_nombre']
        if len(nombre) < Vehiculo.CLIENTE_MIN:
            raise forms.ValidationError(f'El nombre debe tener al menos {Vehiculo.CLIENTE_MIN} caracteres.')
        return nombre

    def clean_cliente_telefono(self):
        # Con este teléfono (y el folio) el cliente consultará su auto
        telefono = self.cleaned_data['cliente_telefono']
        permitido = set('0123456789 +()-')
        if not (8 <= len(telefono) <= Vehiculo.TELEFONO_MAX) or not set(telefono) <= permitido:
            raise forms.ValidationError('Escribe un teléfono válido (solo dígitos, espacios, + o guiones).')
        return telefono

    def clean_anio(self):
        anio = self.cleaned_data['anio'].strip()
        if anio == '':
            return None
        if not anio.isdigit() or not (Vehiculo.ANIO_MIN <= int(anio) <= self.anio_max()):
            raise forms.ValidationError(f'El año debe estar entre {Vehiculo.ANIO_MIN} y {self.anio_max()}.')
        return int(anio)

    def clean_placas(self):
        placas = self.cleaned_data['placas']
        return placas.upper() if placas else None

    def clean_area(self):
        area = self.cleaned_data['area']

        if area.id == Area.TERMINADO and self.es_alta:
            raise forms.ValidationError('Un auto recién ingresado no puede estar Terminado: '
                                        'primero necesita fotos en su bitácora.')

        # REGLA DE EVIDENCIA: solo se revisa cuando se intenta PASAR a Terminado
        if (area.id == Area.TERMINADO and self.area_original != Area.TERMINADO
                and not self.instance.puede_terminar()):
            raise forms.ValidationError(Vehiculo.MENSAJE_SIN_EVIDENCIA)

        return area


class ReporteForm(EstiloCampos, forms.ModelForm):
    """Un reporte de la bitácora: qué se hizo o qué se encontró, con foto opcional."""

    # Formato real que detecta Pillow (no la extensión que mande el navegador) → extensión con la que se guarda
    FORMATOS = {'JPEG': 'jpg', 'PNG': 'png', 'WEBP': 'webp'}

    descripcion = forms.CharField(
        min_length=Bitacora.DESCRIPCION_MIN,
        max_length=Bitacora.DESCRIPCION_MAX,
        widget=forms.Textarea(attrs={
            'rows': 4,
            'placeholder': 'Se desmontó la defensa trasera y se detectó óxido en el soporte.',
        }),
        error_messages={
            'required': 'Escribe qué se hizo o qué se encontró.',
            'min_length': f'El reporte debe tener al menos {Bitacora.DESCRIPCION_MIN} caracteres.',
            'max_length': f'El reporte no puede pasar de {Bitacora.DESCRIPCION_MAX} caracteres.',
        },
    )
    foto = forms.ImageField(
        required=False,
        widget=forms.FileInput(attrs={
            'class': 'campo__control--archivo',
            'accept': 'image/jpeg,image/png,image/webp',
        }),
        error_messages={
            'invalid_image': 'Solo se aceptan fotos JPG, PNG o WebP.',
            'invalid': 'No se pudo recibir la foto. Inténtalo de nuevo.',
        },
    )

    class Meta:
        model = Bitacora
        fields = ['descripcion', 'foto']

    def clean_foto(self):
        foto = self.cleaned_data.get('foto')

        if not foto:
            return None

        if foto.size > Bitacora.FOTO_MAX_BYTES:
            raise forms.ValidationError('La foto es demasiado grande (máximo 5 MB).')

        formato = getattr(foto, 'image', None) and foto.image.format
        if formato not in self.FORMATOS:
            raise forms.ValidationError('Solo se aceptan fotos JPG, PNG o WebP.')

        # Nombre aleatorio: nunca se usa el que trae el archivo del usuario
        foto.name = f'{uuid.uuid4().hex}.{self.FORMATOS[formato]}'
        return foto


class ConsultaForm(forms.Form):
    """Acceso del cliente: el folio de su comprobante y el teléfono que dejó."""

    folio = forms.CharField(max_length=10)
    telefono = forms.CharField(max_length=30)

    def clean(self):
        datos = super().clean()

        if self.errors:
            raise forms.ValidationError('Escribe el folio y el teléfono de tu comprobante.')

        self.vehiculo = Vehiculo.objects.de_folio_y_telefono(datos['folio'], datos['telefono'])

        if self.vehiculo is None:
            raise forms.ValidationError('No encontramos un auto con ese folio y teléfono. Revisa tu comprobante.')

        return datos
