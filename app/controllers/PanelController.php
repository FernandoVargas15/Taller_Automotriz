<?php
declare(strict_types=1);

// CONTROLADOR - panel de inicio del administrador

final class PanelController extends Controlador
{
    public function __construct()
    {
        $this->requerirAdmin();
    }

    /** GET admin: estado del taller de un vistazo */
    public function index(): void
    {
        $vehiculos = new Vehiculo();
        $usuarios  = new Usuario();
        $resumen   = $vehiculos->resumen();

        $this->render('panel/index', [
            'porArea'     => $vehiculos->porArea(),
            'porRol'      => $usuarios->porRol(),
            'enTaller'    => $resumen['en_taller'],
            'entregados'  => $resumen['entregados'],
            'registrados' => $resumen['en_taller'] + $resumen['entregados'],
            'personal'    => $usuarios->contarActivos(),
            'recientes'   => $vehiculos->recientes(5),
            'aviso'       => Sesion::sacar('aviso'),
        ]);
    }
}
