<?php
declare(strict_types=1);

/**
 * Un rango de fechas con nombre: el día, la semana o el mes de una fecha dada.
 *
 * Es un OBJETO DE VALOR: se crea ya válido y no cambia nunca (sus propiedades son
 * readonly). Así, quien recibe un Periodo sabe que las fechas son coherentes y no
 * tiene que volver a comprobarlas.
 *
 * Toda la aritmética de fechas del reporte vive aquí: el controlador solo elige
 * "semana", el modelo solo consulta entre dos marcas de tiempo y el PDF solo pide
 * el título. Cada uno hace una cosa.
 */
final class Periodo
{
    public const DIA    = 'dia';
    public const SEMANA = 'semana';
    public const MES    = 'mes';

    /** Lo que se ofrece en el formulario: valor => texto del botón. */
    public const OPCIONES = [
        self::DIA    => 'Día',
        self::SEMANA => 'Semana',
        self::MES    => 'Mes',
    ];

    private const MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    private const DIAS = [
        1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo',
    ];

    /**
     * $fin es EXCLUSIVO (el primer instante fuera del periodo). Comparar con
     * "creado_en < fin" evita el clásico error de perder los registros de las
     * últimas horas del último día.
     *
     * $referencia es el día que eligió el usuario, que no tiene por qué ser el
     * inicio del periodo: si pide el mes del 25 de septiembre, el rango empieza
     * el día 1 pero el formulario debe seguir mostrando el 25.
     */
    private function __construct(
        public readonly string $tipo,
        public readonly DateTimeImmutable $referencia,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin
    ) {}

    /**
     * Construye el periodo a partir de lo que llegó del formulario.
     * Un tipo raro o una fecha inválida no son un error: se cae al valor por
     * defecto (el mes de hoy), porque cualquiera puede escribir lo que sea en la URL.
     */
    public static function desde(?string $tipo, ?string $fecha): self
    {
        $tipo = isset(self::OPCIONES[(string) $tipo]) ? (string) $tipo : self::MES;
        $dia  = self::fechaValida($fecha);

        return match ($tipo) {
            self::DIA    => new self($tipo, $dia, $dia, $dia->modify('+1 day')),
            self::SEMANA => self::semanaDe($tipo, $dia),
            default      => self::mesDe($tipo, $dia),
        };
    }

    /** Semana de lunes a domingo que contiene ese día. */
    private static function semanaDe(string $tipo, DateTimeImmutable $dia): self
    {
        // 'N' es 1 el lunes y 7 el domingo
        $lunes = $dia->modify('-' . ((int) $dia->format('N') - 1) . ' days');

        return new self($tipo, $dia, $lunes, $lunes->modify('+7 days'));
    }

    /** Mes natural completo que contiene ese día. */
    private static function mesDe(string $tipo, DateTimeImmutable $dia): self
    {
        $primero = $dia->modify('first day of this month');

        return new self($tipo, $dia, $primero, $primero->modify('+1 month'));
    }

    /** La fecha del formulario (AAAA-MM-DD) a medianoche, o hoy si no sirve. */
    private static function fechaValida(?string $fecha): DateTimeImmutable
    {
        $dia = $fecha === null ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($dia === false) {
            return new DateTimeImmutable('today');
        }

        // createFromFormat devuelve un objeto aunque la fecha no exista: un
        // "2026-02-31" se convierte solo en el 3 de marzo. Eso cuenta como error.
        $avisos = DateTimeImmutable::getLastErrors();

        if ($avisos !== false && ($avisos['warning_count'] > 0 || $avisos['error_count'] > 0)) {
            return new DateTimeImmutable('today');
        }

        return $dia;
    }

    // Lectura

    /** Nombre del periodo para el botón activo del formulario: "Día", "Semana", "Mes". */
    public function nombreTipo(): string
    {
        return self::OPCIONES[$this->tipo];
    }

    /**
     * Fecha que el formulario deja escrita al recargar (AAAA-MM-DD): la que
     * eligió el usuario, no el inicio del rango. Así, al cambiar de "Mes" a
     * "Día" el calendario no salta al día 1.
     */
    public function fechaFormulario(): string
    {
        return $this->referencia->format('Y-m-d');
    }

    /** Último día que SÍ entra en el periodo (el fin es exclusivo). */
    public function ultimoDia(): DateTimeImmutable
    {
        return $this->fin->modify('-1 day');
    }

    /** Marcas de tiempo tal como las espera PostgreSQL. */
    public function inicioSql(): string
    {
        return $this->inicio->format('Y-m-d H:i:s');
    }

    public function finSql(): string
    {
        return $this->fin->format('Y-m-d H:i:s');
    }

    /**
     * Título en español para la portada del PDF:
     *   día    → "jueves 25 de septiembre de 2026"
     *   semana → "semana del 21 al 27 de septiembre de 2026"
     *   mes    → "septiembre de 2026"
     */
    public function titulo(): string
    {
        return match ($this->tipo) {
            self::DIA    => self::diaLargo($this->inicio),
            self::SEMANA => 'semana del ' . self::rango($this->inicio, $this->ultimoDia()),
            default      => self::MESES[(int) $this->inicio->format('n')] . ' de ' . $this->inicio->format('Y'),
        };
    }

    /** El mismo dato en corto, para la cabecera de la tabla: "01/09/2026 – 30/09/2026". */
    public function rangoCorto(): string
    {
        return $this->inicio->format('d/m/Y') . ' – ' . $this->ultimoDia()->format('d/m/Y');
    }

    /**
     * Trozo del nombre del archivo, distinto según el corte para que dos reportes
     * no se pisen al guardarlos: "2026-09" un mes, "2026-09-21" un día o una semana.
     */
    public function claveArchivo(): string
    {
        return $this->tipo === self::MES
            ? $this->inicio->format('Y-m')
            : $this->inicio->format('Y-m-d');
    }

    // Texto en español (date() de PHP solo habla inglés)

    private static function diaLargo(DateTimeImmutable $dia): string
    {
        return self::DIAS[(int) $dia->format('N')] . ' '
             . $dia->format('j') . ' de ' . self::MESES[(int) $dia->format('n')]
             . ' de ' . $dia->format('Y');
    }

    /** "21 al 27 de septiembre de 2026", sin repetir mes ni año cuando coinciden. */
    private static function rango(DateTimeImmutable $desde, DateTimeImmutable $hasta): string
    {
        $mesDesde = self::MESES[(int) $desde->format('n')];
        $mesHasta = self::MESES[(int) $hasta->format('n')];

        if ($desde->format('Y-n') === $hasta->format('Y-n')) {
            return $desde->format('j') . ' al ' . $hasta->format('j') . ' de ' . $mesHasta . ' de ' . $hasta->format('Y');
        }

        if ($desde->format('Y') === $hasta->format('Y')) {
            return $desde->format('j') . ' de ' . $mesDesde . ' al ' . $hasta->format('j') . ' de ' . $mesHasta . ' de ' . $hasta->format('Y');
        }

        return $desde->format('j') . ' de ' . $mesDesde . ' de ' . $desde->format('Y')
             . ' al ' . $hasta->format('j') . ' de ' . $mesHasta . ' de ' . $hasta->format('Y');
    }
}
