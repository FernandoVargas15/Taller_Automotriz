"""
Un rango de fechas con nombre: el día, la semana o el mes de una fecha dada.

Es un OBJETO DE VALOR: se crea ya válido y no cambia nunca (frozen=True). Así,
quien recibe un Periodo sabe que las fechas son coherentes.

Toda la aritmética de fechas del reporte vive aquí: la vista solo elige
"semana", el modelo solo filtra entre dos instantes y el PDF solo pide el título.
"""

from dataclasses import dataclass
from datetime import date, datetime, time, timedelta

from django.utils import timezone

DIA = 'dia'
SEMANA = 'semana'
MES = 'mes'

# Lo que se ofrece en el formulario: valor → texto del botón
OPCIONES = {DIA: 'Día', SEMANA: 'Semana', MES: 'Mes'}

MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
         'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
DIAS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo']


@dataclass(frozen=True)
class Periodo:
    """
    fin es EXCLUSIVO (el primer día fuera del periodo): comparar con "< fin" evita
    perder los registros de las últimas horas del último día.

    referencia es el día que eligió el usuario, que no tiene por qué ser el inicio:
    si pide el mes del 25 de septiembre, el rango empieza el 1 pero el formulario
    debe seguir mostrando el 25.
    """

    tipo: str
    referencia: date
    inicio: date
    fin: date

    @classmethod
    def desde(cls, tipo: str | None, fecha: str | None) -> 'Periodo':
        """
        Construye el periodo con lo que llegó en la URL. Un tipo raro o una fecha
        inválida no son un error: se cae al valor por defecto (el mes de hoy).
        """
        tipo = tipo if tipo in OPCIONES else MES
        dia = cls._fecha_valida(fecha)

        if tipo == DIA:
            return cls(tipo, dia, dia, dia + timedelta(days=1))

        if tipo == SEMANA:
            lunes = dia - timedelta(days=dia.weekday())   # semana de lunes a domingo
            return cls(tipo, dia, lunes, lunes + timedelta(days=7))

        primero = dia.replace(day=1)
        siguiente = date(primero.year + primero.month // 12, primero.month % 12 + 1, 1)
        return cls(tipo, dia, primero, siguiente)

    @staticmethod
    def _fecha_valida(fecha: str | None) -> date:
        """La fecha del formulario (AAAA-MM-DD), o hoy si no sirve. "2026-02-31" no sirve."""
        try:
            return datetime.strptime(fecha or '', '%Y-%m-%d').date()
        except ValueError:
            return timezone.localdate()

    # Lectura

    @property
    def fecha_formulario(self) -> str:
        """La fecha que el formulario deja escrita al recargar: la que eligió el usuario."""
        return self.referencia.isoformat()

    @property
    def ultimo_dia(self) -> date:
        """Último día que SÍ entra en el periodo (el fin es exclusivo)."""
        return self.fin - timedelta(days=1)

    @property
    def inicio_dt(self) -> datetime:
        """Medianoche del primer día, en la hora de México."""
        return timezone.make_aware(datetime.combine(self.inicio, time.min))

    @property
    def fin_dt(self) -> datetime:
        return timezone.make_aware(datetime.combine(self.fin, time.min))

    @property
    def titulo(self) -> str:
        """
        Título en español:
            día    → "jueves 25 de septiembre de 2026"
            semana → "semana del 21 al 27 de septiembre de 2026"
            mes    → "septiembre de 2026"
        """
        if self.tipo == DIA:
            d = self.inicio
            return f'{DIAS[d.weekday()]} {d.day} de {MESES[d.month - 1]} de {d.year}'

        if self.tipo == SEMANA:
            return 'semana del ' + self._rango(self.inicio, self.ultimo_dia)

        return f'{MESES[self.inicio.month - 1]} de {self.inicio.year}'

    @property
    def rango_corto(self) -> str:
        """El mismo dato en corto: "01/09/2026 – 30/09/2026"."""
        return f"{self.inicio:%d/%m/%Y} – {self.ultimo_dia:%d/%m/%Y}"

    @property
    def clave_archivo(self) -> str:
        """Trozo del nombre del archivo: "2026-09" un mes, "2026-09-21" un día o una semana."""
        return f'{self.inicio:%Y-%m}' if self.tipo == MES else self.inicio.isoformat()

    @staticmethod
    def _rango(desde: date, hasta: date) -> str:
        """"21 al 27 de septiembre de 2026", sin repetir mes ni año cuando coinciden."""
        mes_desde, mes_hasta = MESES[desde.month - 1], MESES[hasta.month - 1]

        if (desde.year, desde.month) == (hasta.year, hasta.month):
            return f'{desde.day} al {hasta.day} de {mes_hasta} de {hasta.year}'

        if desde.year == hasta.year:
            return f'{desde.day} de {mes_desde} al {hasta.day} de {mes_hasta} de {hasta.year}'

        return f'{desde.day} de {mes_desde} de {desde.year} al {hasta.day} de {mes_hasta} de {hasta.year}'
