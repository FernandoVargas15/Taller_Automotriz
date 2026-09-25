<?php
declare(strict_types=1);

// Controlador para la autenticación de usuarios

final class AuthController extends Controlador
{
    private SesionActiva $sesiones;

    public function __construct()
    {
        $this->sesiones = new SesionActiva();
    }

    /** GET: formulario. POST: intenta entrar. */
    public function login(): void
    {
        if (Sesion::autenticado()) {
            $this->redirigir();
        }

        if ($this->esPost()) {
            $this->entrar();
        }

        $this->render('auth/login', [
            'titulo'  => Config::obtener('APP_NOMBRE', 'Taller Automotriz'),
            'aviso'   => Sesion::sacar('aviso'),
            'errores' => Sesion::sacar('errores') ?? [],
            'viejo'   => Sesion::sacar('viejo')   ?? [],
        ], 'auth');
    }

    private function entrar(): never
    {
        $this->requerirCsrf('/login');

        $correo     = trim((string) ($_POST['correo'] ?? ''));
        $contrasena = (string) ($_POST['contrasena'] ?? '');

        $errores = [];
        if ($correo === '') {
            $errores['correo'] = 'Escribe tu correo.';
        }
        if ($contrasena === '') {
            $errores['contrasena'] = 'Escribe tu contraseña.';
        }

        $usuario = $errores === [] ? (new Usuario())->autenticar($correo, $contrasena) : null;

        if ($usuario === null) {
            Sesion::guardar('errores', $errores);
            Sesion::guardar('viejo', ['correo' => $correo]);
            Sesion::guardar('aviso', [
                'tipo'    => 'error',
                'mensaje' => $errores === [] ? 'Correo o contraseña incorrectos.' : 'Revisa los campos marcados.',
            ]);
            $this->redirigir('/login');
        }

        // Cuenta desactivada por el administrador: credenciales correctas, pero sin acceso
        if (!$usuario['activo']) {
            Sesion::guardar('viejo', ['correo' => $correo]);
            Sesion::guardar('aviso', [
                'tipo'    => 'error',
                'mensaje' => 'Tu cuenta está desactivada. Habla con el administrador.',
            ]);
            $this->redirigir('/login');
        }

        // REGLA DE ACCESO ÚNICO: una cuenta, una sesión
        $this->exigirCuentaLibre($usuario);

        Sesion::iniciar($usuario);
        $this->redirigir();   // InicioController decide el panel según el rol
    }

    /**
     * REGLA DE ACCESO ÚNICO: si la cuenta ya está abierta en otro navegador o
     * dispositivo, no se deja entrar. Hay que salir allá, o pedir al administrador
     * que libere la sesión: no caduca sola con el tiempo.
     *
     * Excepción razonable: si quien pide entrar es ESTE mismo navegador (mismo id
     * de sesión), no es una segunda sesión sino la misma, y se le deja pasar.
     */
    private function exigirCuentaLibre(array $usuario): void
    {
        $abierta = $this->sesiones->deUsuario($usuario['id']);

        if ($abierta === null || hash_equals($abierta['token'], SesionActiva::token(session_id()))) {
            return;
        }

        Sesion::guardar('viejo', ['correo' => $usuario['correo']]);
        Sesion::guardar('aviso', [
            'tipo'    => 'error',
            'mensaje' => 'Esta cuenta ya tiene la sesión iniciada en otro dispositivo.',
        ]);
        $this->redirigir('/login');
    }

    public function salir(): void
    {
        Sesion::cerrar();
        $this->redirigir('/login');
    }
}
