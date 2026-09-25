<?php
declare(strict_types=1);

// Controlador para la bitácora de un vehículo vista desde el mostrador (administrador y asesor de servicio)

final class BitacoraController extends ControladorBitacora
{
    private const LISTA = '/vehiculos';

    public function __construct()
    {
        $this->requerirRol(Rol::DE_MOSTRADOR);

        parent::__construct();
    }

    /** GET vehiculos/bitacora?id=: línea de tiempo del auto. POST: agrega un reporte. */
    public function index(): void
    {
        $vehiculo = $this->vehiculoPedido(self::LISTA);

        if ($this->esPost()) {
            $this->agregarReporte($vehiculo, '/vehiculos/bitacora?id=' . $vehiculo['id']);
        }

        $this->render('vehiculos/bitacora', [
            'css'      => ['bitacora'],
            'vehiculo' => $vehiculo,
            'entradas' => $this->bitacora->porVehiculo($vehiculo['id']),
            // Solo el administrador ve el botón de borrar en cada entrada
            'esAdmin'  => Sesion::esAdmin(),
            'aviso'    => Sesion::sacar('aviso'),
            'errores'  => Sesion::sacar('errores') ?? [],
            'viejo'    => Sesion::sacar('viejo')   ?? [],
        ]);
    }

    /** POST vehiculos/bitacora/eliminar: borra una entrada y su foto. Solo el administrador. */
    public function eliminar(): void
    {
        if (!$this->esPost()) {
            $this->redirigir(self::LISTA);
        }

        $this->requerirAdmin();
        $this->requerirCsrf(self::LISTA);

        $entrada = $this->bitacora->buscar($this->idPedido(self::LISTA));

        if ($entrada === null) {
            $this->avisar('error', 'Esa entrada ya no existe.');
            $this->redirigir(self::LISTA);
        }

        $this->bitacora->eliminar($entrada['id']);
        $this->avisar('exito', 'Entrada eliminada de la bitácora.');

        $this->redirigir('/vehiculos/bitacora?id=' . $entrada['vehiculo_id']);
    }
}
