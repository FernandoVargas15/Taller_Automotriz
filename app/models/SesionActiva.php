<?php
declare(strict_types=1);

/**
 * MODELO SesionActiva — la tabla "sesiones".
 *
 * REGLA DE ACCESO ÚNICO (el "singleton" de sesión que pidió el profesor):
 * una cuenta solo puede estar abierta en un navegador o dispositivo a la vez.
 * La tabla tiene UNIQUE en usuario_id, así que la regla la sostiene la base de
 * datos, no la confianza en el código.
 *
 * Se guarda el SHA-256 del id de sesión de PHP, nunca el id en claro, igual que
 * con las contraseñas: la tabla sirve para comparar, no para suplantar.
 *
 * Solo se guarda lo imprescindible: de quién es la sesión, su token y las dos
 * fechas que el administrador ve en pantalla. Ni IP ni navegador: eran datos
 * personales que no se usaban en ninguna parte, y lo que no se necesita no se
 * guarda.
 *
 * UNA SESIÓN ABIERTA NO CADUCA POR SÍ SOLA. Este es un sistema de mostrador que
 * se deja abierto toda la jornada; expulsar a alguien por no hacer clic durante
 * un rato sería molesto y no aporta seguridad real. La sesión termina cuando su
 * dueño pulsa "Cerrar sesión", cuando el administrador la libera, o cuando el
 * propio usuario entra de nuevo desde otro dispositivo.
 */
final class SesionActiva
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia()->conexion();
    }

    /** El id de sesión de PHP no se guarda en claro: se compara por su hash. */
    public static function token(string $idSesionPhp): string
    {
        return hash('sha256', $idSesionPhp);
    }

    // Consultas

    /**
     * La sesión abierta de un usuario, o null si no tiene ninguna.
     * Lo usa el login para decidir si deja entrar.
     */
    public function deUsuario(string $usuarioId): ?array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, usuario_id, token, iniciada_en, ultima_actividad
             FROM sesiones
             WHERE usuario_id = :usuario_id'
        );
        $sentencia->execute([':usuario_id' => $usuarioId]);

        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Registra la sesión recién abierta. Si el usuario tenía una fila, se
     * sobrescribe: el UPSERT deja siempre UNA sola sesión por cuenta.
     */
    public function abrir(string $usuarioId, string $idSesionPhp): void
    {
        $sql = 'INSERT INTO sesiones (usuario_id, token)
                VALUES (:usuario_id, :token)
                ON CONFLICT (usuario_id) DO UPDATE
                SET token            = EXCLUDED.token,
                    iniciada_en      = NOW(),
                    ultima_actividad = NOW()';

        $this->db->prepare($sql)->execute([
            ':usuario_id' => $usuarioId,
            ':token'      => self::token($idSesionPhp),
        ]);
    }

    /**
     * ¿Sigue siendo esta la sesión registrada del usuario? De paso le anota la
     * hora de actividad, así que una sola consulta comprueba y actualiza.
     *
     * Devuelve false solo si la cuenta se abrió en otro dispositivo o si el
     * administrador liberó la sesión. Nunca por tiempo: aquí no se caduca nada.
     */
    public function refrescar(string $usuarioId, string $idSesionPhp): bool
    {
        $sql = 'UPDATE sesiones
                SET ultima_actividad = NOW()
                WHERE usuario_id = :usuario_id
                  AND token = :token';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute([
            ':usuario_id' => $usuarioId,
            ':token'      => self::token($idSesionPhp),
        ]);

        return $sentencia->rowCount() === 1;
    }

    /** Libera la cuenta: al salir, al desactivarla o cuando el administrador la fuerza. */
    public function cerrar(string $usuarioId): void
    {
        $this->db->prepare('DELETE FROM sesiones WHERE usuario_id = :usuario_id')
                 ->execute([':usuario_id' => $usuarioId]);
    }

    /** Quién está dentro ahora mismo: [usuario_id => datos]. Alimenta la tabla del administrador. */
    public function abiertas(): array
    {
        $sentencia = $this->db->query(
            'SELECT usuario_id, iniciada_en, ultima_actividad FROM sesiones'
        );

        $porUsuario = [];

        foreach ($sentencia->fetchAll() as $fila) {
            $porUsuario[$fila['usuario_id']] = $fila;
        }

        return $porUsuario;
    }
}
