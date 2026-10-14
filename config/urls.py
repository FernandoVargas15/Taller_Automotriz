"""
Rutas del proyecto. Cada aplicación declara las suyas en su propio urls.py.
"""

from django.conf import settings
from django.urls import include, path, re_path
from django.views.static import serve

urlpatterns = [
    path('', include('cuentas.urls')),        # /, /login, /salir, /admin/usuarios...
    path('', include('taller.urls')),         # /admin (panel), /vehiculos, /taller, /consulta
    path('reportes/', include('reportes.urls')),

    # Fotos de la bitácora. En producción las sirve el servidor web (nginx, Apache);
    # aquí las sirve Django para que el proyecto funcione con solo "runserver".
    re_path(r'^media/(?P<path>.*)$', serve, {'document_root': settings.MEDIA_ROOT}),
]
