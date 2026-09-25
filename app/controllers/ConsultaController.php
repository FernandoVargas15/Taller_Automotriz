<?php
declare(strict_types=1);

// Controlador consulta pública del cliente (no hay cuenta ni contraseña, entra con el folio de su comprobante y su teléfono)

final class ConsultaController extends Controlador
{
    /** Auto que esta sesión ya comprobó; así el estado no lleva datos en la URL. */
    private const AUTO = 'consulta_auto';

    /** GET consulta: formulario de acceso. POST: comprueba folio + teléfono. */
    public function index(): void
    {
        if ($this->esPost()) {
            $this->comprobar();
        }

        // Al volver al formulario se cierra la consulta anterior
        Sesion::sacar(self::AUTO);

        $this->render('consulta/index', [
            'aviso' => Sesion::sacar('aviso'),
            'viejo' => Sesion::sacar('viejo') ?? [],
        ], 'auth');
    }

    /** GET consulta/estado: avance del auto comprobado. */
    public function estado(): void
    {
        $id       = Sesion::leer(self::AUTO);
        $vehiculo = es_uuid($id) ? (new Vehiculo())->buscar($id) : null;

        if ($vehiculo === null) {
            $this->avisar('error', 'Escribe de nuevo tu folio y tu teléfono.');
            $this->redirigir('/consulta');
        }

        $this->render('consulta/estado', [
            'css'      => ['bitacora'],
            'vehiculo' => $vehiculo,
            'entradas' => (new Bitacora())->porVehiculo($vehiculo['id']),
            // El layout lo usa para saludar al cliente en la barra superior
            'cliente'  => $vehiculo['cliente_nombre'],
        ]);
    }

    /** GET consulta/estado/pdf: el cliente se descarga la bitácora de su auto como comprobante. */
    public function pdf(): void
    {
        $vehiculo = $this->autoComprobado();

        try {
            (new ReporteBitacora($vehiculo, (new Bitacora())->porVehiculo($vehiculo['id'])))->descargar();
        } catch (Throwable $e) {
            $this->avisar('error', 'No se pudo generar el PDF: ' . $e->getMessage());
            $this->redirigir('/consulta/estado');
        }

        exit;
    }

    /** GET consulta/salir: cierra la consulta y vuelve al formulario. */
    public function salir(): void
    {
        Sesion::sacar(self::AUTO);
        $this->redirigir('/consulta');
    }

    /** Los dos datos deben coincidir; así nadie ve el auto de otro adivinando folios. */
    private function comprobar(): never
    {
        $this->requerirCsrf('/consulta');

        $folio    = trim((string) ($_POST['folio'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $vehiculo = (new Vehiculo())->buscarPorFolioYTelefono($folio, $telefono);

        if ($vehiculo === null) {
            Sesion::guardar('viejo', ['folio' => $folio, 'telefono' => $telefono]);
            $this->avisar('error', 'No encontramos un auto con ese folio y teléfono. Revisa tu comprobante.');
            $this->redirigir('/consulta');
        }

        Sesion::guardar(self::AUTO, $vehiculo['id']);
        $this->redirigir('/consulta/estado');
    }
}
