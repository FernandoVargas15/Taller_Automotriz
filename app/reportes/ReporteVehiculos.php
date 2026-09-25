<?php
declare(strict_types=1);

// REPORTE: vehículos recibidos en un periodo (día, semana o mes)

final class ReporteVehiculos extends ReportePdf
{
    /**
     * Columnas de la tabla. Los anchos suman el ancho útil de una hoja carta
     * horizontal (279.4 mm menos los dos márgenes de 12 mm).
     */
    private const COLUMNAS = [
        ['etiqueta' => 'Folio',       'ancho' => 20.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Ingreso',     'ancho' => 30.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Cliente',     'ancho' => 46.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Teléfono',    'ancho' => 26.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Vehículo',    'ancho' => 58.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Estado',      'ancho' => 26.0, 'alineacion' => 'L'],
        ['etiqueta' => 'Responsable', 'ancho' => 49.4, 'alineacion' => 'L'],
    ];

    /**
     * @param Periodo $periodo  El día, la semana o el mes del reporte
     * @param array   $vehiculos Filas de v_vehiculos recibidas en ese periodo
     */
    public function __construct(
        private readonly Periodo $periodo,
        private readonly array $vehiculos
    ) {
        // Horizontal: la tabla lleva siete columnas y en vertical no se leerían
        parent::__construct('L');
    }

    protected function titulo(): string
    {
        return 'Reporte de vehículos recibidos';
    }

    protected function subtitulo(): string
    {
        return ucfirst($this->periodo->titulo()) . '  ·  ' . $this->periodo->rangoCorto();
    }

    public function nombreArchivo(): string
    {
        return 'vehiculos-' . $this->periodo->tipo . '-' . $this->periodo->claveArchivo() . '.pdf';
    }

    /** El cuerpo del documento: cifras, reparto por área y el detalle. */
    protected function contenido(): void
    {
        $this->resumen();

        if ($this->vehiculos === []) {
            $this->seccion('Detalle');
            $this->parrafo('No se recibió ningún vehículo en este periodo.');
            return;
        }

        $this->porArea();
        $this->detalle();
        $this->cierre();
    }

    // Bloques del documento

    /** Las cifras grandes de arriba: lo primero que se mira. */
    private function resumen(): void
    {
        $entregados = $this->contar(static fn (array $v): bool => (int) $v['area_id'] === Area::ENTREGADO);
        $terminados = $this->contar(static fn (array $v): bool => (int) $v['area_id'] === Area::TERMINADO);
        $total      = count($this->vehiculos);

        $this->cifras([
            'Recibidos'      => $total,
            'En proceso'     => $total - $terminados - $entregados,
            'Terminados'     => $terminados,
            'Ya entregados'  => $entregados,
        ]);
    }

    /** En qué área quedó cada auto de los que entraron, con su barra de proporción. */
    private function porArea(): void
    {
        $this->seccion('Cómo quedaron los vehículos recibidos');

        $total = count($this->vehiculos);

        foreach ($this->conteoPorArea() as $area => $cuantos) {
            $porcentaje = $cuantos / $total;

            $this->SetFont(self::FUENTE, '', 9);
            $this->SetTextColor(...self::TINTA);
            $this->Cell(45, 5.5, $area, 0, 0, 'L');

            $this->SetTextColor(...self::TENUE);
            $this->Cell(24, 5.5, $cuantos . ' (' . round($porcentaje * 100) . '%)', 0, 0, 'L');

            // Barra proporcional, como la del panel de la web
            $largo = 120 * $porcentaje;
            $y     = $this->GetY() + 1.6;

            $this->SetFillColor(...self::FONDO);
            $this->Rect($this->GetX(), $y, 120, 2.6, 'F');

            if ($largo > 0) {
                $this->SetFillColor(...self::CORP);
                $this->Rect($this->GetX(), $y, $largo, 2.6, 'F');
            }

            $this->Ln(5.5);
        }

        $this->Ln(3);
    }

    /** La tabla con un renglón por vehículo. */
    private function detalle(): void
    {
        $this->seccion('Detalle de los ' . count($this->vehiculos) . ' vehículos recibidos');

        $this->abrirTabla(self::COLUMNAS);

        foreach ($this->vehiculos as $vehiculo) {
            $this->fila([
                $vehiculo['folio'],
                fecha($vehiculo['creado_en']),
                $vehiculo['cliente_nombre'],
                $vehiculo['cliente_telefono'],
                $this->descripcionVehiculo($vehiculo),
                $vehiculo['area_nombre'],
                $this->texto($vehiculo['asignado_nombre']),
            ]);
        }

        $this->cerrarTabla();
    }

    /** Pie del reporte: de dónde salen los datos y desde cuándo. */
    private function cierre(): void
    {
        $this->reservar(14);

        $this->SetFont(self::FUENTE, '', 8);
        $this->SetTextColor(...self::TENUE);
        $this->MultiCell(
            $this->anchoUtil(),
            4.5,
            'Se incluyen los vehículos cuya fecha de ingreso cae dentro del periodo '
            . $this->periodo->rangoCorto() . '. El estado y el responsable son los actuales, '
            . 'no los que tenían al momento de ingresar.',
            0,
            'L'
        );
    }

    // Cálculos

    /** [nombre de área => cuántos autos], en el orden del proceso. */
    private function conteoPorArea(): array
    {
        $conteo = [];

        foreach ($this->vehiculos as $vehiculo) {
            $clave           = (int) $vehiculo['area_id'];
            $conteo[$clave] ??= ['nombre' => $vehiculo['area_nombre'], 'total' => 0];
            $conteo[$clave]['total']++;
        }

        ksort($conteo);   // Recepción, Mecánica, Pintura, Terminado, Entregado

        return array_column($conteo, 'total', 'nombre');
    }

    /** Cuántos vehículos cumplen la condición. */
    private function contar(callable $condicion): int
    {
        return count(array_filter($this->vehiculos, $condicion));
    }

    /** "Ford Mustang 2019 · Rojo · ABC-123" */
    private function descripcionVehiculo(array $vehiculo): string
    {
        $partes = [trim($vehiculo['marca'] . ' ' . $vehiculo['modelo'] . ' ' . ($vehiculo['anio'] ?? ''))];

        foreach (['color', 'placas'] as $campo) {
            if ($vehiculo[$campo] !== null && $vehiculo[$campo] !== '') {
                $partes[] = $vehiculo[$campo];
            }
        }

        return implode(' · ', $partes);
    }
}
