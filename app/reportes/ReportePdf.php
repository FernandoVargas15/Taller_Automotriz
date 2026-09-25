<?php
declare(strict_types=1);

// Base de todos los reportes en PDF del taller | PATRÓN PLANTILLA (Template Method)

abstract class ReportePdf extends tFPDF
{
    // Paleta, la misma de la interfaz web
    protected const TINTA  = [17, 24, 39];     // texto principal
    protected const TENUE  = [107, 114, 128];  // texto secundario
    protected const LINEA  = [214, 219, 226];  // bordes
    protected const FONDO  = [241, 245, 249];  // relleno de filas alternas
    protected const CORP   = [225, 29, 43];    // el rojo de la marca, igual que --color-acento
    protected const BLANCO = [255, 255, 255];

    protected const FUENTE        = 'DejaVu';
    private   const FUENTE_NORMAL = 'DejaVuSansCondensed.ttf';
    private   const FUENTE_NEGRITA = 'DejaVuSansCondensed-Bold.ttf';

    /** Márgenes y espacio reservado al pie, en milímetros. */
    protected const MARGEN = 12;
    protected const PIE    = 14;

    /** Alto de una fila de tabla, en milímetros. */
    protected const FILA = 6.2;

    /**
     * Columnas de la tabla que se está dibujando: cada una con
     * ['etiqueta' => ..., 'ancho' => mm, 'alineacion' => 'L'|'C'|'R'].
     * Mientras no esté vacío, la cabecera se repite sola en cada página nueva.
     */
    private array $columnas = [];

    /** Para pintar las filas alternas en gris y que la tabla se lea mejor. */
    private int $filasDibujadas = 0;

    private bool $construido = false;

    /** @param string $orientacion 'P' vertical, 'L' horizontal (para tablas anchas). */
    public function __construct(string $orientacion = 'P')
    {
        parent::__construct($orientacion, 'mm', 'Letter');

        // Fuente Unicode: sin ella, "Mecánica" o "Recepción" salen con basura.
        // El cuarto parámetro (true) es lo que activa el modo UTF-8 de tFPDF.
        $this->AddFont(self::FUENTE, '',  self::FUENTE_NORMAL,  true);
        $this->AddFont(self::FUENTE, 'B', self::FUENTE_NEGRITA, true);

        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $this->SetAutoPageBreak(true, self::PIE + 4);

        // El segundo parámetro avisa a tFPDF de que el texto ya viene en UTF-8
        $this->SetTitle($this->titulo(), true);
        $this->SetAuthor((string) Config::obtener('APP_NOMBRE', 'Taller Automotriz'), true);
        $this->SetCreator('tFPDF ' . self::VERSION, true);
        $this->AliasNbPages();
    }

    // Lo que cada reporte concreto define

    /** Título del documento: sale en el encabezado y en las propiedades del PDF. */
    abstract protected function titulo(): string;

    /** Frase bajo el título que dice de qué va exactamente este reporte. */
    abstract protected function subtitulo(): string;

    /** Nombre del archivo que descarga el usuario, con extensión. */
    abstract public function nombreArchivo(): string;

    /** El cuerpo del reporte. Aquí escribe cada subclase. */
    abstract protected function contenido(): void;

    // Esqueleto común

    /** Encabezado repetido en cada página: marca, título, periodo y cabecera de tabla. */
    public function Header(): void
    {
        $ancho = $this->anchoUtil();

        $this->SetFont(self::FUENTE, 'B', 15);
        $this->SetTextColor(...self::CORP);
        $this->Cell($ancho * 0.6, 8, (string) Config::obtener('APP_NOMBRE', 'Taller Automotriz'), 0, 0, 'L');

        $this->SetFont(self::FUENTE, '', 8.5);
        $this->SetTextColor(...self::TENUE);
        $this->Cell($ancho * 0.4, 8, 'Emitido el ' . fecha(date('c')), 0, 1, 'R');

        $this->SetFont(self::FUENTE, 'B', 11);
        $this->SetTextColor(...self::TINTA);
        $this->Cell($ancho, 6, $this->titulo(), 0, 1, 'L');

        $this->SetFont(self::FUENTE, '', 9);
        $this->SetTextColor(...self::TENUE);
        $this->Cell($ancho, 5, $this->subtitulo(), 0, 1, 'L');

        $this->Ln(1);
        $this->SetDrawColor(...self::CORP);
        $this->SetLineWidth(0.6);
        $this->Line(self::MARGEN, $this->GetY(), $this->w - self::MARGEN, $this->GetY());
        $this->SetLineWidth(0.2);
        $this->Ln(4);

        // Si el salto de página cayó en mitad de una tabla, se repiten sus títulos
        if ($this->columnas !== []) {
            $this->dibujarCabeceraTabla();
        }
    }

    /** Pie repetido: quién lo generó y "Página X de Y". */
    public function Footer(): void
    {
        $this->SetY(-self::PIE);

        $this->SetDrawColor(...self::LINEA);
        $this->Line(self::MARGEN, $this->GetY(), $this->w - self::MARGEN, $this->GetY());

        $ancho = $this->anchoUtil();

        $this->Ln(1.5);
        $this->SetFont(self::FUENTE, '', 8);
        $this->SetTextColor(...self::TENUE);
        $this->Cell($ancho * 0.7, 5, $this->generadoPor(), 0, 0, 'L');
        $this->Cell($ancho * 0.3, 5, 'Página ' . $this->PageNo() . ' de {nb}', 0, 0, 'R');
    }

    /** Quién pidió el documento; queda impreso en cada página como respaldo. */
    protected function generadoPor(): string
    {
        $usuario = Sesion::usuario();

        return $usuario === null
            ? (string) Config::obtener('APP_NOMBRE', 'Taller Automotriz')
            : 'Generado por ' . $usuario['nombre'] . ' · ' . $usuario['rol_nombre'];
    }

    // Herramientas de maquetación para las subclases

    /** Título de sección: una banda a todo lo ancho. */
    protected function seccion(string $texto): void
    {
        $this->reservar(14);

        $this->SetFont(self::FUENTE, 'B', 10.5);
        $this->SetTextColor(...self::TINTA);
        $this->SetFillColor(...self::FONDO);
        $this->SetDrawColor(...self::LINEA);
        $this->Cell($this->anchoUtil(), 7.5, '  ' . $texto, 'B', 1, 'L', true);
        $this->Ln(2.5);
    }

    /** Párrafo normal. */
    protected function parrafo(string $texto, float $alto = 5.2): void
    {
        $this->SetFont(self::FUENTE, '', 9.5);
        $this->SetTextColor(...self::TINTA);
        $this->MultiCell($this->anchoUtil(), $alto, $this->texto($texto), 0, 'L');
    }

    /**
     * Fila de "tarjetas" con las cifras del reporte: [etiqueta => valor].
     * Es lo primero que mira quien recibe el documento, así que va en grande.
     */
    protected function cifras(array $datos): void
    {
        if ($datos === []) {
            return;
        }

        $this->reservar(20);

        $ancho  = $this->anchoUtil() / count($datos);
        $inicio = $this->GetY();

        // Primero los números, grandes
        $this->SetFont(self::FUENTE, 'B', 17);
        $this->SetTextColor(...self::CORP);
        $this->SetFillColor(...self::FONDO);
        foreach ($datos as $valor) {
            $this->Cell($ancho, 10, (string) $valor, 0, 0, 'C', true);
        }

        // Debajo, su etiqueta
        $this->SetXY(self::MARGEN, $inicio + 10);
        $this->SetFont(self::FUENTE, '', 8.5);
        $this->SetTextColor(...self::TENUE);
        foreach (array_keys($datos) as $etiqueta) {
            $this->Cell($ancho, 6, $etiqueta, 0, 0, 'C', true);
        }

        $this->Ln(6);
        $this->Ln(4);
    }

    /**
     * Abre una tabla. A partir de aquí, cada fila se pinta con fila() y la
     * cabecera se repite sola en las páginas siguientes hasta llamar a cerrarTabla().
     *
     * @param array $columnas [['etiqueta' => 'Folio', 'ancho' => 20, 'alineacion' => 'L'], ...]
     */
    protected function abrirTabla(array $columnas): void
    {
        // El salto se pide ANTES de registrar las columnas: si no, Header() dibujaría
        // la cabecera de la página nueva y la línea de abajo la repetiría.
        $this->reservar(self::FILA * 3);

        $this->columnas       = $columnas;
        $this->filasDibujadas = 0;

        $this->dibujarCabeceraTabla();
    }

    /**
     * Una fila de datos, en el mismo orden que las columnas.
     * El texto que no cabe se recorta con puntos suspensivos: así todas las filas
     * miden lo mismo y la tabla no se descuadra nunca.
     */
    protected function fila(array $valores): void
    {
        $this->SetFont(self::FUENTE, '', 8.5);
        $this->SetTextColor(...self::TINTA);
        $this->SetDrawColor(...self::LINEA);

        // Gris muy claro en las filas pares para poder seguirlas con la vista
        $rayada = $this->filasDibujadas % 2 === 1;
        $this->SetFillColor(...($rayada ? self::FONDO : self::BLANCO));

        foreach (array_values($this->columnas) as $i => $columna) {
            $this->Cell(
                $columna['ancho'],
                self::FILA,
                $this->recortar((string) ($valores[$i] ?? ''), $columna['ancho']),
                'B',
                0,
                $columna['alineacion'] ?? 'L',
                true
            );
        }

        $this->Ln();
        $this->filasDibujadas++;
    }

    /** Cierra la tabla: deja de repetir la cabecera al cambiar de página. */
    protected function cerrarTabla(): void
    {
        $this->columnas = [];
        $this->Ln(3);
    }

    private function dibujarCabeceraTabla(): void
    {
        $this->SetFont(self::FUENTE, 'B', 8.5);
        $this->SetTextColor(...self::BLANCO);
        $this->SetFillColor(...self::CORP);
        $this->SetDrawColor(...self::CORP);

        foreach ($this->columnas as $columna) {
            $this->Cell($columna['ancho'], self::FILA + 1, ' ' . $columna['etiqueta'], 0, 0, $columna['alineacion'] ?? 'L', true);
        }

        $this->Ln();
    }

    /** Ancho disponible entre márgenes, en milímetros. */
    protected function anchoUtil(): float
    {
        return $this->w - 2 * self::MARGEN;
    }

    /** Abre página nueva si lo que viene no cabe entero en lo que queda de la actual. */
    protected function reservar(float $altoNecesario): void
    {
        if ($this->GetY() + $altoNecesario > $this->h - self::PIE - 4) {
            $this->AddPage();
        }
    }

    /** Lo que falta se imprime como raya, nunca como hueco confuso. */
    protected function texto(?string $valor): string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? '—' : $valor;
    }

    /** Recorta el texto a lo que cabe en $ancho milímetros y le pone "…". */
    protected function recortar(string $texto, float $ancho): string
    {
        $disponible = $ancho - 2;   // un respiro a cada lado

        if ($texto === '' || $this->GetStringWidth($texto) <= $disponible) {
            return $texto;
        }

        while ($texto !== '' && $this->GetStringWidth($texto . '…') > $disponible) {
            $texto = mb_substr($texto, 0, -1);
        }

        return $texto . '…';
    }

    // Salida

    /**
     * Construye el documento (una sola vez, aunque se pida dos veces) y lo manda
     * al navegador como descarga. Después ya no se puede escribir nada más en la
     * respuesta: quien llama debe terminar la petición aquí.
     */
    public function descargar(): void
    {
        $this->generar();
        $this->Output('D', $this->nombreArchivo());
    }

    /** Igual que descargar(), pero el PDF se abre dentro del navegador. */
    public function mostrar(): void
    {
        $this->generar();
        $this->Output('I', $this->nombreArchivo());
    }

    /** Devuelve el PDF como cadena. Se usa en las pruebas, sin navegador de por medio. */
    public function contenidoBinario(): string
    {
        $this->generar();

        return $this->Output('S', $this->nombreArchivo());
    }

    private function generar(): void
    {
        if ($this->construido) {
            return;
        }

        $this->construido = true;

        $this->AddPage();
        $this->contenido();
    }
}
