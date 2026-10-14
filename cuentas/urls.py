from django.urls import path

from . import views

app_name = 'cuentas'

urlpatterns = [
    path('', views.inicio, name='inicio'),
    path('login/', views.entrar, name='login'),
    path('salir/', views.salir, name='salir'),

    # Solo administrador
    path('admin/usuarios/', views.usuarios, name='usuarios'),
    path('admin/usuarios/nuevo/', views.usuario_nuevo, name='usuario_nuevo'),
    path('admin/usuarios/<uuid:id>/editar/', views.usuario_editar, name='usuario_editar'),
    path('admin/usuarios/<uuid:id>/estado/', views.usuario_estado, name='usuario_estado'),      # POST
    path('admin/usuarios/<uuid:id>/sesion/', views.usuario_sesion, name='usuario_sesion'),      # POST
    path('admin/usuarios/<uuid:id>/eliminar/', views.usuario_eliminar, name='usuario_eliminar'),  # POST
]
