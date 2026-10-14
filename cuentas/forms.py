"""
Formularios de cuentas. Validan lo que llega del navegador antes de tocar el modelo.
"""

from django import forms

from .models import Rol, Usuario


class EstiloCampos:
    """
    Pone la clase CSS "campo__control" a cada control y añade "es-invalido"
    a los que traen error, para que el template solo tenga que pintar {{ campo }}.
    """

    clase_control = 'campo__control'

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        for campo in self.fields.values():
            clases = campo.widget.attrs.get('class', '')
            campo.widget.attrs['class'] = f'{self.clase_control} {clases}'.strip()

    def full_clean(self):
        super().full_clean()
        for nombre in self.errors:
            if nombre in self.fields:
                self.fields[nombre].widget.attrs['class'] += ' es-invalido'


class LoginForm(forms.Form):
    correo = forms.CharField(error_messages={'required': 'Escribe tu correo.'})
    contrasena = forms.CharField(strip=False, error_messages={'required': 'Escribe tu contraseña.'})

    def clean(self):
        datos = super().clean()

        if self.errors:
            return datos

        usuario = Usuario.objects.filter(correo__iexact=datos['correo']).select_related('rol').first()

        # No se distingue "correo no existe" de "contraseña mal" para no revelar qué
        # correos están registrados. Sin usuario igual se calcula un hash, para que
        # la respuesta tarde lo mismo en los dos casos.
        if usuario is None:
            Usuario().set_password(datos['contrasena'])
            raise forms.ValidationError('Correo o contraseña incorrectos.')

        if not usuario.check_password(datos['contrasena']):
            raise forms.ValidationError('Correo o contraseña incorrectos.')

        # Credenciales correctas, pero el administrador le quitó el acceso
        if not usuario.activo:
            raise forms.ValidationError('Tu cuenta está desactivada. Habla con el administrador.')

        self.usuario = usuario
        return datos


class UsuarioForm(EstiloCampos, forms.ModelForm):
    """
    Alta o edición de un empleado. En el alta la contraseña es obligatoria; al
    editar es opcional (vacía = se conserva la actual).
    """

    contrasena = forms.CharField(
        required=False,
        strip=False,
        widget=forms.PasswordInput(attrs={'placeholder': '••••••••', 'autocomplete': 'new-password'}),
    )

    class Meta:
        model = Usuario
        fields = ['nombre', 'correo', 'rol']
        widgets = {
            'nombre': forms.TextInput(attrs={'placeholder': 'Fernando Vargas', 'autocomplete': 'name'}),
            'correo': forms.EmailInput(attrs={'placeholder': 'correo@ejemplo.com', 'autocomplete': 'email'}),
        }
        error_messages = {
            'nombre': {'required': 'El nombre es obligatorio.'},
            'correo': {
                'required': 'El correo es obligatorio.',
                'invalid': 'El correo no tiene un formato válido.',
                'unique': 'Ese correo ya está registrado.',
            },
            'rol': {
                'required': 'Selecciona un rol válido.',
                'invalid_choice': 'Selecciona un rol válido.',
            },
        }

    def __init__(self, *args, es_propio=False, **kwargs):
        super().__init__(*args, **kwargs)
        self.es_alta = self.instance._state.adding

        self.fields['rol'].empty_label = None
        self.fields['contrasena'].widget.attrs['minlength'] = Usuario.PASSWORD_MIN

        if self.es_alta:
            self.fields['contrasena'].widget.attrs['required'] = True
            self.initial.setdefault('rol', Rol.POR_DEFECTO)

        # El administrador no puede quitarse su propio rol: el campo no se envía
        if es_propio:
            self.fields['rol'].disabled = True

    def clean_nombre(self):
        nombre = self.cleaned_data['nombre']
        if len(nombre) < Usuario.NOMBRE_MIN:
            raise forms.ValidationError(f'El nombre debe tener al menos {Usuario.NOMBRE_MIN} caracteres.')
        return nombre

    def clean_correo(self):
        correo = self.cleaned_data['correo'].strip()
        repetido = Usuario.objects.filter(correo__iexact=correo).exclude(pk=self.instance.pk).exists()
        if repetido:
            raise forms.ValidationError('Ese correo ya está registrado.')
        return correo

    def clean_contrasena(self):
        contrasena = self.cleaned_data['contrasena']

        if contrasena == '' and self.es_alta:
            raise forms.ValidationError('La contraseña es obligatoria.')

        if contrasena != '' and len(contrasena) < Usuario.PASSWORD_MIN:
            raise forms.ValidationError(f'La contraseña debe tener al menos {Usuario.PASSWORD_MIN} caracteres.')

        return contrasena

    def validate_unique(self):
        # La unicidad del correo ya la revisó clean_correo, sin distinguir mayúsculas
        pass

    @property
    def cambio_contrasena(self):
        return self.cleaned_data.get('contrasena', '') != ''

    def save(self, commit=True):
        usuario = super().save(commit=False)

        if self.cambio_contrasena:
            usuario.set_password(self.cleaned_data['contrasena'])

        if commit:
            usuario.save()

        return usuario
