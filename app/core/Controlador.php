<?php
declare(strict_types=1);

/**
 * Lo que comparten todos los controladores: renderizar dentro del layout,
 * redirigir a una acción, exigir sesión o rol y leer ids de la petición.
 */
abstract class Controlador
{
    /**
     * Vista (views/<controlador>/<accion>.php) renderizada dentro de un layout (views/layouts/).
     * 'principal' lleva la cabecera con pestañas y usuario; 'auth' es la pantalla completa del login.
     * $datos['css'] lista hojas extra de public/css/ que necesita esa pantalla.
     */
    protected function render(string $vista, array $datos = [], string $layout = 'principal'): void
    {
        extract($datos, EXTR_SKIP);

        $titulo        ??= Config::obtener('APP_NOMBRE', 'Taller Automotriz');
        $css           ??= [];
        $usuarioActual   = Sesion::usuario();

        ob_start();
        require RUTA_APP . '/views/' . $vista . '.php';
        $contenido = ob_get_clean();

        require RUTA_APP . '/views/layouts/' . $layout . '.php';
    }

    /** Redirige a otra ruta de la app: redirigir('/login'). Sin ruta va a la raíz. */
    protected function redirigir(string $ruta = ''): never
    {
        header('Location: ' . url($ruta));
        exit;
    }

    protected function esPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Deja un aviso de un solo uso para la siguiente pantalla.
     * El %s del mensaje marca dónde va $destacado (la vista lo pone en negrita).
     */
    protected function avisar(string $tipo, string $mensaje, ?string $destacado = null): void
    {
        $aviso = ['tipo' => $tipo, 'mensaje' => $mensaje];

        if ($destacado !== null) {
            $aviso['destacado'] = $destacado;
        }

        Sesion::guardar('aviso', $aviso);
    }

    /**
     * Sin sesión → al login.
     *
     * REGLA DE ACCESO ÚNICO: además se comprueba en cada petición que esta siga
     * siendo la sesión registrada de la cuenta. Solo se sale de aquí por dos
     * motivos, nunca por tiempo: que la cuenta se haya abierto en otro
     * dispositivo, o que el administrador haya liberado la sesión.
     */
    protected function requerirSesion(): void
    {
        if (!Sesion::autenticado()) {
            $this->avisar('error', 'Inicia sesión para continuar.');
            $this->redirigir('/login');
        }

        if (!Sesion::sigueVigente()) {
            Sesion::cerrarSoloAqui();
            $this->avisar('error', 'Tu sesión se cerró: la cuenta se abrió en otro dispositivo.');
            $this->redirigir('/login');
        }
    }

    /**
     * Con sesión pero con un rol que no está en la lista → a su propio inicio.
     * requerirRol(Rol::DE_TALLER) deja pasar al mecánico y al hojalatero.
     */
    protected function requerirRol(array $roles): void
    {
        $this->requerirSesion();

        if (!Sesion::tieneRol($roles)) {
            $this->avisar('error', 'No tienes permiso para entrar a esa sección.');
            $this->redirigir();
        }
    }

    protected function requerirAdmin(): void
    {
        $this->requerirRol([Rol::ADMINISTRADOR]);
    }

    /** Todo POST debe traer el token del formulario; si no, se descarta y vuelve a $volverA. */
    protected function requerirCsrf(string $volverA): void
    {
        if (!Sesion::csrfValido($_POST['_token'] ?? null)) {
            $this->avisar('error', 'El formulario caducó. Inténtalo de nuevo.');
            $this->redirigir($volverA);
        }
    }

    /**
     * El id que viene en la petición: campo oculto del formulario (POST) o ?id= de la URL.
     * Si falta o no es un UUID, avisa y vuelve a $volverA.
     */
    protected function idPedido(string $volverA, string $campo = 'id'): string
    {
        $id = $_POST[$campo] ?? $_GET[$campo] ?? null;

        if (!es_uuid($id)) {
            $this->avisar('error', 'El registro que buscas no existe.');
            $this->redirigir($volverA);
        }

        return $id;
    }
}
