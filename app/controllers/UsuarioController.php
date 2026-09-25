<?php
declare(strict_types=1);

// CONTROLADOR - gestión del personal del taller

final class UsuarioController extends Controlador
{
    private const LISTA = '/admin/usuarios';

    private Usuario $usuarios;
    private Rol $roles;
    private SesionActiva $sesiones;

    public function __construct()
    {
        $this->requerirAdmin();

        $this->usuarios = new Usuario();
        $this->roles    = new Rol();
        $this->sesiones = new SesionActiva();
    }

    /** GET admin/usuarios: tabla de todo el personal con sus acciones */
    public function index(): void
    {
        $this->render('usuarios/index', [
            'usuarios' => $this->usuarios->todos(),
            'total'    => $this->usuarios->contar(),
            // Quién está dentro ahora mismo: [usuario_id => datos de su sesión]
            'sesiones' => $this->sesiones->abiertas(),
            'aviso'    => Sesion::sacar('aviso'),
        ]);
    }

    /** GET admin/usuarios/nuevo: formulario de alta. POST: procesa el alta y redirige (así F5 no duplica). */
    public function nuevo(): void
    {
        if ($this->esPost()) {
            $this->guardar(null);
        }

        $this->render('usuarios/formulario', [
            'usuario' => null,
            'roles'   => $this->roles->todos(),
            'aviso'   => Sesion::sacar('aviso'),
            'errores' => Sesion::sacar('errores') ?? [],
            'viejo'   => Sesion::sacar('viejo')   ?? [],
        ]);
    }

    /** GET admin/usuarios/editar?id=: formulario con los datos actuales. POST: guarda los cambios. */
    public function editar(): void
    {
        $id      = $this->idPedido(self::LISTA);
        $usuario = $this->usuarios->buscar($id);

        if ($usuario === null) {
            $this->avisar('error', 'Ese usuario ya no existe.');
            $this->redirigir(self::LISTA);
        }

        if ($this->esPost()) {
            $this->guardar($usuario);
        }

        $this->render('usuarios/formulario', [
            'usuario' => $usuario,
            'roles'   => $this->roles->todos(),
            'aviso'   => Sesion::sacar('aviso'),
            'errores' => Sesion::sacar('errores') ?? [],
            // Sin intento previo, el formulario se llena con lo que hay en la BD
            'viejo'   => Sesion::sacar('viejo') ?? [
                'nombre' => $usuario['nombre'],
                'correo' => $usuario['correo'],
                'rol_id' => $usuario['rol_id'],
            ],
        ]);
    }

    /** POST admin/usuarios/estado: activa o desactiva la cuenta (el empleado que renuncia pierde el acceso). */
    public function estado(): void
    {
        $this->soloPost();
        $this->requerirCsrf(self::LISTA);

        $usuario = $this->usuarioAjeno();
        $activo  = !$usuario['activo'];

        $this->usuarios->cambiarEstado($usuario['id'], $activo);

        // Desactivar a alguien que está dentro debe sacarlo ya, no en su próxima visita
        if (!$activo) {
            $this->sesiones->cerrar($usuario['id']);
        }

        $this->avisar(
            'exito',
            $activo ? 'Cuenta de %s reactivada.' : 'Cuenta de %s desactivada. Ya no podrá iniciar sesión.',
            $usuario['nombre']
        );

        $this->redirigir(self::LISTA);
    }

    /**
     * POST admin/usuarios/sesion: libera la sesión de un empleado.
     * Sirve para el caso real de "cerró el navegador sin salir y ahora no puede
     * volver a entrar". Como una sesión abierta no caduca por tiempo, esta es la
     * ÚNICA forma de destrabar esa cuenta.
     */
    public function sesion(): void
    {
        $this->soloPost();
        $this->requerirCsrf(self::LISTA);

        $usuario = $this->usuarioAjeno();

        $this->sesiones->cerrar($usuario['id']);
        $this->avisar('exito', 'Sesión de %s liberada. Ya puede entrar desde cualquier dispositivo.', $usuario['nombre']);

        $this->redirigir(self::LISTA);
    }

    /** POST admin/usuarios/eliminar: borrado definitivo. Sus autos quedan sin asignar. */
    public function eliminar(): void
    {
        $this->soloPost();
        $this->requerirCsrf(self::LISTA);

        $usuario = $this->usuarioAjeno();

        $this->usuarios->eliminar($usuario['id']);
        $this->avisar('exito', 'Usuario %s eliminado.', $usuario['nombre']);

        $this->redirigir(self::LISTA);
    }

    /**
     * Valida y guarda lo que llegó por POST. Con $actual = null es un alta;
     * con un usuario, una edición. Siempre termina redirigiendo.
     */
    private function guardar(?array $actual): never
    {
        $esAlta   = $actual === null;
        $volverA  = $esAlta ? '/admin/usuarios/nuevo' : '/admin/usuarios/editar?id=' . $actual['id'];
        $esPropio = !$esAlta && $actual['id'] === Sesion::usuario()['id'];

        $this->requerirCsrf($volverA);

        $datos = [
            'nombre'     => trim((string) ($_POST['nombre'] ?? '')),
            'correo'     => trim((string) ($_POST['correo'] ?? '')),
            'contrasena' => (string) ($_POST['contrasena'] ?? ''),
            // El administrador no puede quitarse su propio rol
            'rol_id'     => $esPropio ? Rol::ADMINISTRADOR : (int) ($_POST['rol_id'] ?? Rol::POR_DEFECTO),
        ];

        // Se devuelven los datos escritos (nunca las contraseñas)
        $viejo = [
            'nombre' => $datos['nombre'],
            'correo' => $datos['correo'],
            'rol_id' => $datos['rol_id'],
        ];

        // Quien valida es el Modelo, no el Controlador
        $errores = $this->usuarios->validar($datos, $actual['id'] ?? null);

        if ($errores !== []) {
            Sesion::guardar('errores', $errores);
            Sesion::guardar('viejo', $viejo);
            $this->avisar('error', 'Revisa los campos marcados.');
            $this->redirigir($volverA);
        }

        try {
            if ($esAlta) {
                $this->usuarios->crear(
                    $datos['nombre'],
                    $datos['correo'],
                    $datos['contrasena'],
                    $datos['rol_id']
                );
                $this->avisar('exito', 'Usuario %s registrado correctamente.', $datos['nombre']);
            } else {
                $this->usuarios->actualizar(
                    $actual['id'],
                    $datos['nombre'],
                    $datos['correo'],
                    $datos['rol_id'],
                    $datos['contrasena']
                );

                // Si el admin se editó a sí mismo, la cabecera debe reflejarlo de inmediato
                if ($esPropio) {
                    Sesion::actualizarUsuario(['nombre' => $datos['nombre'], 'correo' => $datos['correo']]);
                }

                $this->avisar('exito', 'Cambios de %s guardados.', $datos['nombre']);
            }
        } catch (PDOException $e) {
            Sesion::guardar('viejo', $viejo);
            $this->avisar('error', 'No se pudo guardar el usuario: ' . $e->getMessage());
            $this->redirigir($volverA);
        }

        $this->redirigir(self::LISTA);
    }

    /** El usuario del id recibido, siempre que exista y no sea el propio administrador. */
    private function usuarioAjeno(): array
    {
        $id      = $this->idPedido(self::LISTA);
        $usuario = $this->usuarios->buscar($id);

        if ($usuario === null) {
            $this->avisar('error', 'Ese usuario ya no existe.');
            $this->redirigir(self::LISTA);
        }

        if ($usuario['id'] === Sesion::usuario()['id']) {
            $this->avisar('error', 'No puedes desactivar ni eliminar tu propia cuenta.');
            $this->redirigir(self::LISTA);
        }

        return $usuario;
    }

    /** Las acciones destructivas solo se aceptan por POST (un enlace GET no basta). */
    private function soloPost(): void
    {
        if (!$this->esPost()) {
            $this->redirigir(self::LISTA);
        }
    }
}
