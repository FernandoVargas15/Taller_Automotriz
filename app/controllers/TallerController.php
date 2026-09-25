<?php
declare(strict_types=1);

// CONTROLADOR - panel del personal de taller (mecánico y hojalatero / pintor)

final class TallerController extends ControladorBitacora
{
    private const LISTA = '/taller';

    public function __construct()
    {
        $this->requerirRol(Rol::DE_TALLER);

        parent::__construct();
    }

    /** GET taller: mis autos */
    public function index(): void
    {
        $this->render('taller/index', [
            'vehiculos' => $this->vehiculos->porMecanico(Sesion::usuario()['id']),
            'aviso'     => Sesion::sacar('aviso'),
        ]);
    }

    /** GET taller/vehiculo?id=: detalle con bitácora. POST: agrega un reporte. */
    public function vehiculo(): void
    {
        $vehiculo = $this->vehiculoAsignado();

        if ($this->esPost()) {
            $this->agregarReporte($vehiculo, '/taller/vehiculo?id=' . $vehiculo['id']);
        }

        $this->render('taller/vehiculo', [
            'css'      => ['bitacora'],
            'vehiculo' => $vehiculo,
            'entradas' => $this->bitacora->porVehiculo($vehiculo['id']),
            'areas'    => $this->areasDeTrabajo(),
            'aviso'    => Sesion::sacar('aviso'),
            'errores'  => Sesion::sacar('errores') ?? [],
            'viejo'    => Sesion::sacar('viejo')   ?? [],
        ]);
    }

    /** POST taller/vehiculo/area: mueve el auto por el proceso (Recepción → ... → Terminado). */
    public function area(): void
    {
        if (!$this->esPost()) {
            $this->redirigir(self::LISTA);
        }

        $this->requerirCsrf(self::LISTA);

        $vehiculo = $this->vehiculoAsignado();
        $volverA  = '/taller/vehiculo?id=' . $vehiculo['id'];
        $areaId   = (int) ($_POST['area_id'] ?? 0);
        $areas    = $this->areasDeTrabajo();

        // REGLA DE FLUJO
        if (Vehiculo::estaEntregado($vehiculo)) {
            $this->avisar('error', Vehiculo::MENSAJE_ENTREGADO);
            $this->redirigir($volverA);
        }

        // El mecánico no entrega: eso lo hace el administrador cuando el cliente recoge el auto
        if (!isset($areas[$areaId])) {
            $this->avisar('error', 'Selecciona un área válida.');
            $this->redirigir($volverA);
        }

        if ($areaId === (int) $vehiculo['area_id']) {
            $this->redirigir($volverA);
        }

        // REGLA DE EVIDENCIA
        if ($areaId === Area::TERMINADO && !$this->vehiculos->puedeTerminar($vehiculo['id'])) {
            $this->avisar('error', Vehiculo::MENSAJE_SIN_EVIDENCIA);
            $this->redirigir($volverA);
        }

        $this->vehiculos->cambiarArea($vehiculo['id'], $areaId);

        // El cambio queda en la línea de tiempo para que el cliente lo vea
        $this->bitacora->crear(
            $vehiculo['id'],
            Sesion::usuario()['id'],
            $areaId,
            $areaId === Area::TERMINADO
                ? 'Trabajo terminado. El auto está listo para entregarse.'
                : 'El auto pasa a ' . $areas[$areaId] . '.',
            null
        );

        $this->avisar('exito', 'El auto ahora está en %s.', $areas[$areaId]);
        $this->redirigir($volverA);
    }

    /**
     * REGLA DE ASIGNACIÓN: el auto del id recibido, solo si existe y está asignado a este
     * quien entró. Si no, aviso y de vuelta a su lista (no se revela si el auto existe).
     */
    private function vehiculoAsignado(): array
    {
        $vehiculo = $this->vehiculos->buscar($this->idPedido(self::LISTA));

        if ($vehiculo === null || !Vehiculo::asignadoA($vehiculo, Sesion::usuario()['id'])) {
            $this->avisar('error', 'Ese auto no está asignado a ti.');
            $this->redirigir(self::LISTA);
        }

        return $vehiculo;
    }

    /** Áreas a las que el taller puede mover un auto: todas menos Entregado. [id => nombre] */
    private function areasDeTrabajo(): array
    {
        $areas = [];

        foreach ((new Area())->todos() as $area) {
            if ((int) $area['id'] !== Area::ENTREGADO) {
                $areas[(int) $area['id']] = $area['nombre'];
            }
        }

        return $areas;
    }
}
