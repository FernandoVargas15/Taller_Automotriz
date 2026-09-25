<?php
declare(strict_types=1);

// CONTROLADOR - reportes del taller. Mostrador: administrador y asesor de servicio 

final class ReporteController extends Controlador
{
    private const PANTALLA = '/reportes';

    private Vehiculo $vehiculos;

    public function __construct()
    {
        // Mismo permiso que el listado de vehículos: el reporte no enseña ningún
        // dato que el mostrador no vea ya en /vehiculos, solo lo resume por fechas.
        // El taller no entra: solo ve los autos que tiene asignados.
        $this->requerirRol(Rol::DE_MOSTRADOR);

        $this->vehiculos = new Vehiculo();
    }

    /** GET reportes?periodo=&fecha=: elige el periodo y muestra lo que saldrá en el PDF. */
    public function index(): void
    {
        $periodo = $this->periodoPedido();

        $this->render('reportes/index', [
            'css'       => ['reportes'],
            'periodo'   => $periodo,
            'vehiculos' => $this->vehiculos->recibidosEntre($periodo),
            'aviso'     => Sesion::sacar('aviso'),
        ]);
    }

    /**
     * GET reportes/pdf?periodo=&fecha=: el mismo corte, ya en PDF.
     * Se muestra dentro del visor del navegador en lugar de descargarse a ciegas;
     * desde ahí el administrador decide si lo guarda o lo imprime.
     */
    public function pdf(): void
    {
        $periodo   = $this->periodoPedido();
        $vehiculos = $this->vehiculos->recibidosEntre($periodo);

        try {
            (new ReporteVehiculos($periodo, $vehiculos))->mostrar();
        } catch (Throwable $e) {
            // Mejor un aviso claro que un archivo PDF corrupto
            $this->avisar('error', 'No se pudo generar el PDF: ' . $e->getMessage());
            $this->redirigir(self::PANTALLA . '?' . $this->consulta($periodo));
        }

        exit;
    }

    /**
     * El periodo que pide la URL. Periodo::desde() ya se encarga de los valores
     * inventados (?periodo=hola), así que aquí no hace falta validar nada más.
     */
    private function periodoPedido(): Periodo
    {
        return Periodo::desde($_GET['periodo'] ?? null, $_GET['fecha'] ?? null);
    }

    /** Los mismos parámetros, para volver a la pantalla sin perder la selección. */
    private function consulta(Periodo $periodo): string
    {
        return http_build_query([
            'periodo' => $periodo->tipo,
            'fecha'   => $periodo->fechaFormulario(),
        ]);
    }
}
