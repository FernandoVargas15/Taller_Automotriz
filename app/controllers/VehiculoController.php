<?php
declare(strict_types=1);

// CONTROLADOR - control general de vehículos, administrador y el asesor de servicio

final class VehiculoController extends ControladorBitacora
{
    private const LISTA = '/vehiculos';

    private const CAMPOS = [
        'cliente_nombre', 'cliente_telefono', 'marca', 'modelo',
        'anio', 'color', 'placas', 'area_id', 'asignado_a',
    ];

    public function __construct()
    {
        $this->requerirRol(Rol::DE_MOSTRADOR);

        parent::__construct();
    }

    /** GET vehiculos[?q=]: tabla con todos los autos registrados, con buscador */
    public function index(): void
    {
        $busqueda  = trim((string) ($_GET['q'] ?? ''));
        $vehiculos = $this->vehiculos->todos($busqueda);

        $this->render('vehiculos/index', [
            'vehiculos' => $vehiculos,
            'busqueda'  => $busqueda,
            'total'     => $this->vehiculos->contar(),
            // Solo el administrador ve el botón de eliminar
            'esAdmin'   => Sesion::esAdmin(),
            'aviso'     => Sesion::sacar('aviso'),
        ]);
    }

    /**
     * GET vehiculos/nuevo: formulario de ingreso. POST: registra y redirige.
     * Con ?desde=<id> es un reingreso: el mismo auto vuelve al taller y el formulario
     * llega rellenado con el cliente y el vehículo; solo se completa lo nuevo.
     */
    public function nuevo(): void
    {
        if ($this->esPost()) {
            $this->guardar(null);
        }

        $viejo    = Sesion::sacar('viejo');
        $origen   = null;
        $desdeId  = $_GET['desde'] ?? null;

        if ($viejo === null && es_uuid($desdeId)) {
            $origen = $this->vehiculos->buscar($desdeId);
        }

        if ($viejo === null) {
            $viejo = $origen !== null
                ? array_intersect_key($origen, array_flip(['cliente_nombre', 'cliente_telefono', 'marca', 'modelo', 'anio', 'color', 'placas']))
                  + ['area_id' => Area::POR_DEFECTO, 'asignado_a' => '']
                : ['area_id' => Area::POR_DEFECTO];
        }

        $this->renderFormulario(null, $viejo, $origen);
    }

    /** POST vehiculos/entregar: el cliente se lleva el auto. Solo desde Terminado. */
    public function entregar(): void
    {
        if (!$this->esPost()) {
            $this->redirigir(self::LISTA);
        }

        $this->requerirCsrf(self::LISTA);

        $vehiculo = $this->vehiculoPedido(self::LISTA);

        if ((int) $vehiculo['area_id'] !== Area::TERMINADO) {
            $this->avisar('error', 'Solo se puede entregar un auto que esté en Terminado.');
            $this->redirigir(self::LISTA);
        }

        $this->vehiculos->entregar($vehiculo['id']);

        // Queda registrado en la línea de tiempo, como cualquier otro paso
        $this->bitacora->crear(
            $vehiculo['id'],
            Sesion::usuario()['id'],
            Area::ENTREGADO,
            'Vehículo entregado al cliente.',
            null
        );

        $this->avisar('exito', 'Vehículo %s entregado. Ya no cuenta como en taller.', $vehiculo['folio']);
        $this->redirigir(self::LISTA);
    }

    /** GET vehiculos/editar?id=: corrige datos capturados mal. POST: guarda. */
    public function editar(): void
    {
        $vehiculo = $this->vehiculoPedido(self::LISTA);

        if ($this->esPost()) {
            $this->guardar($vehiculo);
        }

        // Sin intento previo, el formulario se llena con lo que hay en la BD
        $viejo = Sesion::sacar('viejo') ?? array_intersect_key($vehiculo, array_flip(self::CAMPOS));

        $this->renderFormulario($vehiculo, $viejo);
    }

    /** POST vehiculos/eliminar: borrado definitivo del auto, su bitácora y sus fotos. Solo el administrador. */
    public function eliminar(): void
    {
        if (!$this->esPost()) {
            $this->redirigir(self::LISTA);
        }

        $this->requerirAdmin();
        $this->requerirCsrf(self::LISTA);

        $vehiculo = $this->vehiculoPedido(self::LISTA);

        // Las filas de la bitácora las borra la BD en cascada; los archivos hay que quitarlos antes
        $this->bitacora->borrarFotosDeVehiculo($vehiculo['id']);
        $this->vehiculos->eliminar($vehiculo['id']);

        $this->avisar('exito', 'Vehículo %s eliminado junto con su bitácora.', $vehiculo['folio']);
        $this->redirigir(self::LISTA);
    }

    /** $origen: el auto anterior cuando es un reingreso (solo para avisarlo en la vista). */
    private function renderFormulario(?array $vehiculo, array $viejo, ?array $origen = null): void
    {
        $this->render('vehiculos/formulario', [
            'vehiculo'   => $vehiculo,
            'origen'     => $origen,
            'areas'      => (new Area())->todos(),
            'asignables' => (new Usuario())->asignables(),
            'aviso'      => Sesion::sacar('aviso'),
            'errores'    => Sesion::sacar('errores') ?? [],
            'viejo'      => $viejo,
        ]);
    }

    /** Valida y guarda lo que llegó por POST; alta si $actual es null, edición si no. */
    private function guardar(?array $actual): never
    {
        $esAlta  = $actual === null;
        $volverA = $esAlta ? '/vehiculos/nuevo' : '/vehiculos/editar?id=' . $actual['id'];

        $this->requerirCsrf($volverA);

        $datos = [];
        foreach (self::CAMPOS as $campo) {
            $datos[$campo] = trim((string) ($_POST[$campo] ?? ''));
        }

        $errores = $this->vehiculos->validar($datos, $actual);

        if ($errores !== []) {
            Sesion::guardar('errores', $errores);
            Sesion::guardar('viejo', $datos);
            $this->avisar('error', 'Revisa los campos marcados.');
            $this->redirigir($volverA);
        }

        try {
            if ($esAlta) {
                $id       = $this->vehiculos->crear($datos);
                $vehiculo = $this->vehiculos->buscar($id);
                $this->avisar('exito', 'Vehículo registrado con folio %s.', $vehiculo['folio'] ?? '');
            } else {
                $this->vehiculos->actualizar($actual['id'], $datos);
                $this->avisar('exito', 'Cambios del vehículo %s guardados.', $actual['folio']);
            }
        } catch (PDOException $e) {
            Sesion::guardar('viejo', $datos);
            $this->avisar('error', 'No se pudo guardar el vehículo: ' . $e->getMessage());
            $this->redirigir($volverA);
        }

        $this->redirigir(self::LISTA);
    }

}
