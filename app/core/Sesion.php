<?php
declare(strict_types=1);

/**
 * Envuelve $_SESSION: quién está autenticado, token CSRF y avisos de un solo uso.
 * Es el único lugar del proyecto que toca $_SESSION.
 */

final class Sesion
{
    private const USUARIO = 'usuario';
    private const CSRF    = 'csrf';

    private function __construct() {}

    // Autenticación

    /** Guarda al usuario en la sesión (nunca su hash) y regenera el id contra fijación de sesión. */
    public static function iniciar(array $usuario): void
    {
        session_regenerate_id(true);

        $_SESSION[self::USUARIO] = [
            'id'         => $usuario['id'],
            'nombre'     => $usuario['nombre'],
            'correo'     => $usuario['correo'],
            'rol_id'     => (int) $usuario['rol_id'],
            'rol_nombre' => $usuario['rol_nombre'],
        ];

        // Queda apuntada como LA sesión de esta cuenta; cualquier otra deja de valer
        (new SesionActiva())->abrir($usuario['id'], session_id());
    }

    /**
     * REGLA DE ACCESO ÚNICO: ¿esta sesión sigue siendo la registrada para la cuenta?
     * Devuelve false si alguien entró desde otro dispositivo o si el administrador
     * liberó la sesión. Nunca por tiempo: una sesión abierta no caduca sola.
     * De paso anota la hora de actividad.
     */
    public static function sigueVigente(): bool
    {
        $usuario = self::usuario();

        if ($usuario === null) {
            return false;
        }

        return (new SesionActiva())->refrescar($usuario['id'], session_id());
    }

    public static function usuario(): ?array
    {
        return $_SESSION[self::USUARIO] ?? null;
    }

    /** Refresca datos del usuario en sesión (p. ej. tras editar su propio nombre). */
    public static function actualizarUsuario(array $cambios): void
    {
        if (isset($_SESSION[self::USUARIO])) {
            $_SESSION[self::USUARIO] = array_merge($_SESSION[self::USUARIO], $cambios);
        }
    }

    public static function autenticado(): bool
    {
        return isset($_SESSION[self::USUARIO]);
    }

    public static function rolId(): ?int
    {
        return isset($_SESSION[self::USUARIO]) ? (int) $_SESSION[self::USUARIO]['rol_id'] : null;
    }

    public static function esAdmin(): bool
    {
        return self::rolId() === Rol::ADMINISTRADOR;
    }

    /** ¿El rol de quien entró está entre estos? Rol::DE_TALLER, Rol::DE_MOSTRADOR... */
    public static function tieneRol(array $roles): bool
    {
        return in_array(self::rolId(), $roles, true);
    }

    /**
     * Salida normal ("Cerrar sesión"): además de vaciar la sesión, libera la
     * cuenta para que su dueño pueda entrar desde otro navegador enseguida.
     */
    public static function cerrar(): void
    {
        $usuario = self::usuario();

        if ($usuario !== null) {
            (new SesionActiva())->cerrar($usuario['id']);
        }

        self::cerrarSoloAqui();
    }

    /**
     * Cierra la sesión de ESTE navegador sin tocar la tabla.
     * Se usa cuando la cuenta ya se abrió en otro dispositivo: la fila de
     * "sesiones" es del otro, y borrarla lo expulsaría a él también.
     */
    public static function cerrarSoloAqui(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();

        // Sesión nueva y vacía: hace falta para dejar el aviso que leerá el login
        // ("cerramos tu sesión porque...") y para que el formulario tenga token CSRF.
        session_start();
        session_regenerate_id(true);
    }

    // CSRF

    /** Token único por sesión; se mete como campo oculto en cada formulario. */
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION[self::CSRF])) {
            $_SESSION[self::CSRF] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF];
    }

    public static function csrfValido(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION[self::CSRF])
            && hash_equals($_SESSION[self::CSRF], $token);
    }

    // Avisos de un solo uso (sobreviven a una redirección y luego se borran)

    public static function guardar(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    /** Lee sin borrar (lo que sí hace sacar). */
    public static function leer(string $clave): mixed
    {
        return $_SESSION[$clave] ?? null;
    }

    public static function sacar(string $clave): mixed
    {
        $valor = $_SESSION[$clave] ?? null;
        unset($_SESSION[$clave]);

        return $valor;
    }
}
