<?php
declare(strict_types=1);

/**
 * CONTROLADOR - recibe la petición, le pide al Modelo que valide y guarde, y le entrega los datos a la Vista
 */
final class UsuarioController
{
    private Usuario $modeloUsuario;
    private Rol $modeloRol;

    /** Enrutador mínimo: GET muestra la página, POST procesa el formulario. */
    public function manejarPeticion(): void
    {
        $this->modeloUsuario = new Usuario();
        $this->modeloRol     = new Rol();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->registrar();
            return;
        }

        $this->index();
    }

    // Acciones

    /** GET: formulario y tabla de registrados */
    private function index(): void
    {
        $this->render('usuarios/registro', [
            'titulo'   => Config::obtener('APP_NOMBRE', 'Registro de Usuarios'),
            'usuarios' => $this->modeloUsuario->todos(),
            'roles'    => $this->modeloRol->todos(),
            'total'    => $this->modeloUsuario->contar(),
            'estado'   => $this->estadoDelSistema(),
            // Datos de un solo uso que dejó registrar() en la sesión
            'aviso'    => $this->sacarDeSesion('aviso'),
            'errores'  => $this->sacarDeSesion('errores') ?? [],
            'viejo'    => $this->sacarDeSesion('viejo')   ?? [],
        ]);
    }

    /**
     * POST: procesa el registro, recargar GET F5 evitar duplicados
     */
    private function registrar(): void
    {
        $datos = [
            'nombre'     => trim((string) ($_POST['nombre'] ?? '')),
            'correo'     => trim((string) ($_POST['correo'] ?? '')),
            'contrasena' => (string) ($_POST['contrasena'] ?? ''),
            'rol_id'     => (int)    ($_POST['rol_id'] ?? Rol::POR_DEFECTO),
        ];

        // Quien valida es el Modelo, no el Controlador
        $errores = $this->modeloUsuario->validar($datos);

        if ($errores !== []) {
            $this->guardarEnSesion('errores', $errores);
            // Se devuelven los datos escritos (nunca las contraseñas)
            $this->guardarEnSesion('viejo', [
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'rol_id' => $datos['rol_id'],
            ]);
            $this->guardarEnSesion('aviso', [
                'tipo'    => 'error',
                'mensaje' => 'Revisa los campos marcados.',
            ]);

            $this->redirigir();
        }

        try {
            $this->modeloUsuario->crear(
                $datos['nombre'],
                $datos['correo'],
                $datos['contrasena'],
                $datos['rol_id']
            );

            // El %s marca dónde va el nombre; la Vista lo pone en negrita
            $this->guardarEnSesion('aviso', [
                'tipo'      => 'exito',
                'mensaje'   => 'Usuario %s registrado correctamente.',
                'destacado' => $datos['nombre'],
            ]);
        } catch (PDOException $e) {
            $this->guardarEnSesion('aviso', [
                'tipo'    => 'error',
                'mensaje' => 'No se pudo guardar el usuario: ' . $e->getMessage(),
            ]);
            $this->guardarEnSesion('viejo', [
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'rol_id' => $datos['rol_id'],
            ]);
        }
        $this->redirigir();
    }

    //  Barra de estado Servidor web y Base de datos
    private function estadoDelSistema(): array
    {
        $db = Database::obtenerInstancia();
        return [
            'servidor' => [
                'conectado' => true,
                'software'  => $this->nombreDelServidor(),
                'host'      => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'php'       => PHP_VERSION,
            ],
            'base' => [
                'conectado' => $db->estaViva(),
                'motor'     => 'PostgreSQL',
                'version'   => $db->versionServidor(),
                'nombre'    => $db->nombreBase(),
                'host'      => Config::obtener('DB_HOST', '127.0.0.1'),
                'puerto'    => Config::entero('DB_PORT', 5432),
                'usuario'   => Config::obtener('DB_USER', ''),
            ],
        ];
    }

    private function nombreDelServidor(): string
    {
        $completo = $_SERVER['SERVER_SOFTWARE'] ?? 'Servidor web';
        return strtok($completo, '/ ') ?: $completo;
    }

    // Utilidades

    // Vista renderizada dentro del layout
    private function render(string $vista, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);

        $titulo ??= 'Registro de Usuarios';

        ob_start();
        require RUTA_APP . '/views/' . $vista . '.php';
        $contenido = ob_get_clean();

        require RUTA_APP . '/views/layout.php';
    }

    private function redirigir(): void
    {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    private function guardarEnSesion(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    /** Lee el valor y lo borra, para que el aviso aparezca una sola vez. */
    private function sacarDeSesion(string $clave): mixed
    {
        $valor = $_SESSION[$clave] ?? null;
        unset($_SESSION[$clave]);

        return $valor;
    }
}
