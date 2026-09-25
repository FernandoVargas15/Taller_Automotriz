<?php
declare(strict_types=1);

/**
 * Base de las pantallas que giran alrededor de un auto y su bitácora
 * (administrador y mecánico): los dos modelos y lo que ambos hacen igual.
 */
abstract class ControladorBitacora extends Controlador
{
    protected Vehiculo $vehiculos;
    protected Bitacora $bitacora;

    public function __construct()
    {
        $this->vehiculos = new Vehiculo();
        $this->bitacora  = new Bitacora();
    }

    /** El auto del id recibido; si ya no existe, aviso y de vuelta a $lista. */
    protected function vehiculoPedido(string $lista): array
    {
        $vehiculo = $this->vehiculos->buscar($this->idPedido($lista));

        if ($vehiculo === null) {
            $this->avisar('error', 'Ese vehículo ya no existe.');
            $this->redirigir($lista);
        }

        return $vehiculo;
    }

    /**
     * Valida y guarda un reporte en el área donde está el auto.
     * REGLA DE FLUJO: si ya se entregó, la bitácora está cerrada.
     */
    protected function agregarReporte(array $vehiculo, string $volverA): never
    {
        $this->requerirCsrf($volverA);

        if (Vehiculo::estaEntregado($vehiculo)) {
            $this->avisar('error', Vehiculo::MENSAJE_ENTREGADO);
            $this->redirigir($volverA);
        }

        $datos = [
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'area_id'     => (int) $vehiculo['area_id'],
        ];
        $foto = $_FILES['foto'] ?? null;

        $errores = $this->bitacora->validar($datos, $foto);

        if ($errores !== []) {
            Sesion::guardar('errores', $errores);
            Sesion::guardar('viejo', $datos);
            $this->avisar('error', 'Revisa los campos marcados.');
            $this->redirigir($volverA);
        }

        try {
            $this->bitacora->crear(
                $vehiculo['id'],
                Sesion::usuario()['id'],
                $datos['area_id'],
                $datos['descripcion'],
                $foto
            );
            $this->avisar('exito', 'Reporte agregado a la bitácora.');
        } catch (PDOException | RuntimeException $e) {
            Sesion::guardar('viejo', $datos);
            $this->avisar('error', 'No se pudo guardar el reporte: ' . $e->getMessage());
        }

        $this->redirigir($volverA);
    }
}
