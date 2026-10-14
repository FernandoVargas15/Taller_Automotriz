from django.urls import path

from . import views

app_name = 'reportes'

urlpatterns = [
    path('', views.index, name='index'),      # ?periodo=dia|semana|mes&fecha=AAAA-MM-DD
    path('pdf/', views.pdf, name='pdf'),      # mismos parámetros, salida en PDF
]
