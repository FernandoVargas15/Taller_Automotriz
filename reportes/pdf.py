"""
Reportes en PDF del taller, con ReportLab.

PATRÓN PLANTILLA (Template Method): ReportePdf arma el esqueleto común
(encabezado, pie con "Página X de Y", estilos de tabla) y cada reporte
concreto solo define su título, su subtítulo, su nombre de archivo y su contenido.
"""

from abc import ABC, abstractmethod
from io import BytesIO
from pathlib import Path

from django.conf import settings
from django.utils import timezone
from reportlab.graphics.shapes import Drawing, Rect
from reportlab.lib.colors import Color
from reportlab.lib.pagesizes import landscape, letter
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen.canvas import Canvas
from reportlab.platypus import CondPageBreak, Paragraph, SimpleDocTemplate, Table, TableStyle

from taller.models import Area


def _rgb(r, g, b):
    return Color(r / 255, g / 255, b / 255)


# Paleta, la misma de la interfaz web
TINTA = _rgb(17, 24, 39)      # texto principal
TENUE = _rgb(107, 114, 128)   # texto secundario
LINEA = _rgb(214, 219, 226)   # bordes
FONDO = _rgb(241, 245, 249)   # relleno de filas alternas
CORP = _rgb(225, 29, 43)      # el rojo de la marca, igual que --color-acento
BLANCO = _rgb(255, 255, 255)

# Fuente Unicode: sin ella, "Mecánica" o "Recepción" podrían salir con basura
FUENTE = 'DejaVu'
NEGRITA = 'DejaVu-Bold'
_CARPETA_FUENTES = Path(__file__).resolve().parent / 'fuentes'

# Márgenes, espacio del encabezado y del pie, alto de fila (milímetros)
MARGEN = 12 * mm
ENCABEZADO = 25 * mm
PIE = 14 * mm
FILA = 6.2 * mm


def _registrar_fuentes():
    if FUENTE not in pdfmetrics.getRegisteredFontNames():
        pdfmetrics.registerFont(TTFont(FUENTE, _CARPETA_FUENTES / 'DejaVuSansCondensed.ttf'))
        pdfmetrics.registerFont(TTFont(NEGRITA, _CARPETA_FUENTES / 'DejaVuSansCondensed-Bold.ttf'))


class _LienzoNumerado(Canvas):
    """
    El total de páginas no se conoce hasta el final: este lienzo guarda cada
    página y, al cerrar el documento, les escribe "Página X de Y".
    """

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._paginas = []

    def showPage(self):
        self._paginas.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        total = len(self._paginas)
        for estado in self._paginas:
            self.__dict__.update(estado)
            ancho = self._pagesize[0]
            self.setFont(FUENTE, 8)
            self.setFillColor(TENUE)
            self.drawRightString(ancho - MARGEN, PIE - 5 * mm, f'Página {self._pageNumber} de {total}')
            super().showPage()
        super().save()


class ReportePdf(ABC):
    """Base de todos los reportes en PDF del taller."""

    def __init__(self, horizontal=False, generado_por=None):
        _registrar_fuentes()
        self.tamano = landscape(letter) if horizontal else letter
        self.generado_por = generado_por   # el usuario que lo pidió; queda impreso en cada página

    # Lo que cada reporte concreto define

    @abstractmethod
    def titulo(self) -> str:
        """Título del documento: sale en el encabezado y en las propiedades del PDF."""

    @abstractmethod
    def subtitulo(self) -> str:
        """Frase bajo el título que dice de qué va exactamente este reporte."""

    @abstractmethod
    def nombre_archivo(self) -> str:
        """Nombre del archivo, con extensión."""

    @abstractmethod
    def contenido(self) -> list:
        """El cuerpo del reporte: una lista de bloques (flowables) de ReportLab."""

    # Esqueleto común

    def generar(self) -> bytes:
        """Construye el documento y lo devuelve como bytes."""
        salida = BytesIO()
        documento = SimpleDocTemplate(
            salida,
            pagesize=self.tamano,
            leftMargin=MARGEN, rightMargin=MARGEN,
            topMargin=MARGEN + ENCABEZADO, bottomMargin=PIE + 4 * mm,
            title=self.titulo(), author=settings.APP_NOMBRE, creator='ReportLab',
        )
        documento.build(self.contenido(), onFirstPage=self._decorar, onLaterPages=self._decorar,
                        canvasmaker=_LienzoNumerado)
        return salida.getvalue()

    @property
    def ancho_util(self) -> float:
        return self.tamano[0] - 2 * MARGEN

    def _decorar(self, lienzo, documento):
        """Encabezado y pie, repetidos en cada página."""
        ancho, alto = self.tamano
        arriba = alto - MARGEN

        lienzo.saveState()

        # Marca y fecha de emisión
        lienzo.setFont(NEGRITA, 15)
        lienzo.setFillColor(CORP)
        lienzo.drawString(MARGEN, arriba - 6 * mm, settings.APP_NOMBRE)

        lienzo.setFont(FUENTE, 8.5)
        lienzo.setFillColor(TENUE)
        lienzo.drawRightString(ancho - MARGEN, arriba - 6 * mm,
                               'Emitido el ' + timezone.localtime().strftime('%d/%m/%Y %H:%M'))

        # Título y subtítulo
        lienzo.setFont(NEGRITA, 11)
        lienzo.setFillColor(TINTA)
        lienzo.drawString(MARGEN, arriba - 13 * mm, self.titulo())

        lienzo.setFont(FUENTE, 9)
        lienzo.setFillColor(TENUE)
        lienzo.drawString(MARGEN, arriba - 18 * mm, self.subtitulo())

        lienzo.setStrokeColor(CORP)
        lienzo.setLineWidth(0.6 * mm)
        lienzo.line(MARGEN, arriba - 21 * mm, ancho - MARGEN, arriba - 21 * mm)

        # Pie: quién lo generó (el número de página lo pone _LienzoNumerado)
        lienzo.setStrokeColor(LINEA)
        lienzo.setLineWidth(0.2 * mm)
        lienzo.line(MARGEN, PIE, ancho - MARGEN, PIE)

        lienzo.setFont(FUENTE, 8)
        lienzo.setFillColor(TENUE)
        lienzo.drawString(MARGEN, PIE - 5 * mm, self._texto_generado_por())

        lienzo.restoreState()

    def _texto_generado_por(self) -> str:
        if self.generado_por is None:
            return settings.APP_NOMBRE
        return f'Generado por {self.generado_por.nombre} · {self.generado_por.rol.nombre}'

    # Herramientas de maquetación para las subclases

    def seccion(self, texto: str) -> list:
        """
        Título de sección: una banda a todo lo ancho. Si queda poco espacio en la
        página, salta a la siguiente para no quedarse sola al final.
        """
        banda = Table([[texto]], colWidths=[self.ancho_util], rowHeights=[7.5 * mm])
        banda.setStyle(TableStyle([
            ('FONT', (0, 0), (-1, -1), NEGRITA, 10.5),
            ('TEXTCOLOR', (0, 0), (-1, -1), TINTA),
            ('BACKGROUND', (0, 0), (-1, -1), FONDO),
            ('LINEBELOW', (0, 0), (-1, -1), 0.5, LINEA),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('LEFTPADDING', (0, 0), (-1, -1), 3 * mm),
        ]))
        banda.spaceBefore = 2 * mm
        banda.spaceAfter = 2.5 * mm
        return [CondPageBreak(30 * mm), banda]

    def parrafo(self, texto: str, tamano=9.5, color=TINTA) -> Paragraph:
        estilo = ParagraphStyle('parrafo', fontName=FUENTE, fontSize=tamano, leading=tamano * 1.45,
                                textColor=color, spaceAfter=2 * mm)
        return Paragraph(texto, estilo)

    def cifras(self, datos: dict) -> Table:
        """Fila de "tarjetas" con las cifras: lo primero que mira quien recibe el documento."""
        columnas = len(datos)
        tabla = Table([[str(v) for v in datos.values()], list(datos.keys())],
                      colWidths=[self.ancho_util / columnas] * columnas,
                      rowHeights=[10 * mm, 6 * mm])
        tabla.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), FONDO),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('FONT', (0, 0), (-1, 0), NEGRITA, 17),
            ('TEXTCOLOR', (0, 0), (-1, 0), CORP),
            ('FONT', (0, 1), (-1, 1), FUENTE, 8.5),
            ('TEXTCOLOR', (0, 1), (-1, 1), TENUE),
        ]))
        tabla.spaceAfter = 4 * mm
        return tabla

    def tabla(self, columnas: list, filas: list) -> Table:
        """
        Tabla de datos. columnas: [(etiqueta, ancho_mm), ...]. La cabecera se
        repite sola en cada página y el texto que no cabe se recorta con "…",
        así todas las filas miden lo mismo y la tabla no se descuadra.
        """
        anchos = [ancho * mm for _, ancho in columnas]
        cuerpo = [[self.recortar(str(valor), ancho) for valor, ancho in zip(fila, anchos)] for fila in filas]

        tabla = Table([[etiqueta for etiqueta, _ in columnas]] + cuerpo,
                      colWidths=anchos, rowHeights=[FILA + 1 * mm] + [FILA] * len(cuerpo), repeatRows=1)
        tabla.setStyle(TableStyle([
            ('FONT', (0, 0), (-1, 0), NEGRITA, 8.5),
            ('TEXTCOLOR', (0, 0), (-1, 0), BLANCO),
            ('BACKGROUND', (0, 0), (-1, 0), CORP),
            ('FONT', (0, 1), (-1, -1), FUENTE, 8.5),
            ('TEXTCOLOR', (0, 1), (-1, -1), TINTA),
            # Gris muy claro en las filas pares para poder seguirlas con la vista
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [BLANCO, FONDO]),
            ('LINEBELOW', (0, 1), (-1, -1), 0.5, LINEA),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ]))
        tabla.spaceAfter = 3 * mm
        return tabla

    @staticmethod
    def recortar(texto: str, ancho: float, fuente=FUENTE, tamano=8.5) -> str:
        """Recorta el texto a lo que cabe en el ancho (puntos) y le pone "…"."""
        disponible = ancho - 12   # el relleno de la celda
        if pdfmetrics.stringWidth(texto, fuente, tamano) <= disponible:
            return texto
        while texto and pdfmetrics.stringWidth(texto + '…', fuente, tamano) > disponible:
            texto = texto[:-1]
        return texto + '…'

    @staticmethod
    def texto(valor) -> str:
        """Lo que falta se imprime como raya, nunca como hueco confuso."""
        valor = '' if valor is None else str(valor).strip()
        return valor or '—'


class ReporteVehiculos(ReportePdf):
    """Vehículos recibidos en un periodo (día, semana o mes)."""

    # Los anchos suman el ancho útil de una hoja carta horizontal (279.4 mm menos 2 márgenes de 12 mm)
    COLUMNAS = [
        ('Folio', 20.0), ('Ingreso', 30.0), ('Cliente', 46.0), ('Teléfono', 26.0),
        ('Vehículo', 58.0), ('Estado', 26.0), ('Responsable', 49.4),
    ]

    def __init__(self, periodo, vehiculos, generado_por=None):
        # Horizontal: la tabla lleva siete columnas y en vertical no se leerían
        super().__init__(horizontal=True, generado_por=generado_por)
        self.periodo = periodo
        self.vehiculos = list(vehiculos)

    def titulo(self):
        return 'Reporte de vehículos recibidos'

    def subtitulo(self):
        return f'{self.periodo.titulo[0].upper()}{self.periodo.titulo[1:]}  ·  {self.periodo.rango_corto}'

    def nombre_archivo(self):
        return f'vehiculos-{self.periodo.tipo}-{self.periodo.clave_archivo}.pdf'

    def contenido(self):
        bloques = [self.cifras(resumen(self.vehiculos))]

        if not self.vehiculos:
            return bloques + self.seccion('Detalle') + [
                self.parrafo('No se recibió ningún vehículo en este periodo.')]

        return bloques + self._por_area() + self._detalle() + self._cierre()

    # Bloques del documento

    def _por_area(self):
        """En qué área quedó cada auto de los que entraron, con su barra de proporción."""
        total = len(self.vehiculos)
        conteo = {}
        for vehiculo in self.vehiculos:
            conteo.setdefault(vehiculo.area_id, [vehiculo.area.nombre, 0])[1] += 1

        filas = []
        for _, (nombre, cuantos) in sorted(conteo.items()):   # Recepción, Mecánica, ... Entregado
            proporcion = cuantos / total
            barra = Drawing(120 * mm, 5.5 * mm)
            barra.add(Rect(0, 1.45 * mm, 120 * mm, 2.6 * mm, fillColor=FONDO, strokeColor=None))
            barra.add(Rect(0, 1.45 * mm, 120 * mm * proporcion, 2.6 * mm, fillColor=CORP, strokeColor=None))
            filas.append([nombre, f'{cuantos} ({round(proporcion * 100)}%)', barra])

        tabla = Table(filas, colWidths=[45 * mm, 24 * mm, 122 * mm], rowHeights=[5.5 * mm] * len(filas),
                      hAlign='LEFT')
        tabla.setStyle(TableStyle([
            ('FONT', (0, 0), (-1, -1), FUENTE, 9),
            ('TEXTCOLOR', (0, 0), (0, -1), TINTA),
            ('TEXTCOLOR', (1, 0), (1, -1), TENUE),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ]))
        tabla.spaceAfter = 4 * mm

        return self.seccion('Cómo quedaron los vehículos recibidos') + [tabla]

    def _detalle(self):
        """La tabla con un renglón por vehículo."""
        filas = [[
            v.folio,
            timezone.localtime(v.creado_en).strftime('%d/%m/%Y %H:%M'),
            v.cliente_nombre,
            v.cliente_telefono,
            ' · '.join(p for p in (v.descripcion, v.color, v.placas) if p),
            v.area.nombre,
            self.texto(v.asignado_a.nombre if v.asignado_a else None),
        ] for v in self.vehiculos]

        return (self.seccion(f'Detalle de los {len(self.vehiculos)} vehículos recibidos')
                + [self.tabla(self.COLUMNAS, filas)])

    def _cierre(self):
        """De dónde salen los datos y desde cuándo."""
        return [self.parrafo(
            f'Se incluyen los vehículos cuya fecha de ingreso cae dentro del periodo {self.periodo.rango_corto}. '
            'El estado y el responsable son los actuales, no los que tenían al momento de ingresar.',
            tamano=8, color=TENUE,
        )]


def resumen(vehiculos) -> dict:
    """Las cifras del reporte; las usan el PDF y su vista previa en pantalla."""
    total = len(vehiculos)
    terminados = sum(1 for v in vehiculos if v.area_id == Area.TERMINADO)
    entregados = sum(1 for v in vehiculos if v.area_id == Area.ENTREGADO)

    return {
        'Recibidos': total,
        'En proceso': total - terminados - entregados,
        'Terminados': terminados,
        'Ya entregados': entregados,
    }
