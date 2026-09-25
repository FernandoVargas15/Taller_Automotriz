<?php
declare(strict_types=1);

// MODELO Usuario — valida, encripta y consulta la tabla "usuarios"

final class Usuario
{
    public const NOMBRE_MIN   = 3;
    public const NOMBRE_MAX   = 100;
    public const CORREO_MAX   = 150;
    public const PASSWORD_MIN = 8;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia()->conexion();
    }

    // Hashing Convierte la contraseña en un hash bcrypt
    public static function encriptar(string $contrasenaPlana): string
    {
        $costo = Config::entero('HASH_COSTO', 12);
        $costo = max(10, min(15, $costo)); // se acota por seguridad

        // SE HASHEA LA CONTRASEÑA
        return password_hash($contrasenaPlana, PASSWORD_BCRYPT, ['cost' => $costo]);
    }

    /**
     * Validacion. En alta ($idActual = null) la contraseña es obligatoria;
     * al editar es opcional (vacía = se conserva la actual) y el correo puede
     * seguir siendo el suyo.
     */
    public function validar(array $datos, ?string $idActual = null): array
    {
        $errores = [];

        $nombre     = trim((string) ($datos['nombre'] ?? ''));
        $correo     = trim((string) ($datos['correo'] ?? ''));
        $contrasena = (string) ($datos['contrasena'] ?? '');
        $rolId      = (int)    ($datos['rol_id'] ?? 0);

        if ($nombre === '') {
            $errores['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($nombre) < self::NOMBRE_MIN) {
            $errores['nombre'] = 'El nombre debe tener al menos ' . self::NOMBRE_MIN . ' caracteres.';
        } elseif (mb_strlen($nombre) > self::NOMBRE_MAX) {
            $errores['nombre'] = 'El nombre no puede pasar de ' . self::NOMBRE_MAX . ' caracteres.';
        }

        if ($correo === '') {
            $errores['correo'] = 'El correo es obligatorio.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'El correo no tiene un formato válido.';
        } elseif (mb_strlen($correo) > self::CORREO_MAX) {
            $errores['correo'] = 'El correo no puede pasar de ' . self::CORREO_MAX . ' caracteres.';
        } elseif ($this->correoExiste($correo, $idActual)) {
            $errores['correo'] = 'Ese correo ya está registrado.';
        }

        if ($contrasena === '' && $idActual === null) {
            $errores['contrasena'] = 'La contraseña es obligatoria.';
        } elseif ($contrasena !== '' && mb_strlen($contrasena) < self::PASSWORD_MIN) {
            $errores['contrasena'] = 'La contraseña debe tener al menos ' . self::PASSWORD_MIN . ' caracteres.';
        }

        // Se comprueba contra la tabla roles
        if (!(new Rol())->existe($rolId)) {
            $errores['rol_id'] = 'Selecciona un rol válido.';
        }

        return $errores;
    }

    // Consultas Inserta un usuario y devuelve el UUID que generó PostgreSQL y se encripta la contraseña
    public function crear(string $nombre, string $correo, string $contrasenaPlana, int $rolId): string
    {
        $sql = 'INSERT INTO usuarios (nombre, correo, password_hash, rol_id)
                VALUES (:nombre, :correo, :password_hash, :rol_id)
                RETURNING id';

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute([
            ':nombre'        => trim($nombre),
            ':correo'        => trim($correo),
            ':password_hash' => self::encriptar($contrasenaPlana),
            ':rol_id'        => $rolId,
        ]);

        return (string) $sentencia->fetchColumn();
    }

    /** Cambia nombre, correo y rol; la contraseña solo si se mandó una nueva. */
    public function actualizar(string $id, string $nombre, string $correo, int $rolId, string $contrasenaPlana = ''): void
    {
        $parametros = [
            ':id'     => $id,
            ':nombre' => trim($nombre),
            ':correo' => trim($correo),
            ':rol_id' => $rolId,
        ];

        $sql = 'UPDATE usuarios SET nombre = :nombre, correo = :correo, rol_id = :rol_id';

        if ($contrasenaPlana !== '') {
            $sql .= ', password_hash = :password_hash';
            $parametros[':password_hash'] = self::encriptar($contrasenaPlana);
        }

        $this->db->prepare($sql . ' WHERE id = :id')->execute($parametros);
    }

    /** Activa o desactiva la cuenta. Un inactivo no puede iniciar sesión. */
    public function cambiarEstado(string $id, bool $activo): void
    {
        $sentencia = $this->db->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
        $sentencia->bindValue(':activo', $activo, PDO::PARAM_BOOL);
        $sentencia->bindValue(':id', $id);
        $sentencia->execute();
    }

    /** Borrado definitivo. Sus autos quedan sin asignar y sus reportes sin autor (lo resuelve la BD). */
    public function eliminar(string $id): void
    {
        $this->db->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $id]);
    }

    /** Un usuario por id, sin su hash. Null si no existe. */
    public function buscar(string $id): ?array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, nombre, correo, rol_id, rol_nombre, activo, creado_en
             FROM v_usuarios
             WHERE id = :id'
        );
        $sentencia->execute([':id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** Usuarios del más reciente al más antiguo */
    public function todos(int $limite = 100): array
    {
        $sql = 'SELECT id, nombre, correo, rol_id, rol_nombre, activo, creado_en
                FROM v_usuarios
                ORDER BY creado_en DESC
                LIMIT :limite';

        $sentencia = $this->db->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /** Personal activo al que se le puede asignar un auto: mecánicos y hojalateros. */
    public function asignables(): array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, nombre, rol_id, rol_nombre
             FROM v_usuarios
             WHERE activo AND rol_id IN (:mecanico, :hojalatero)
             ORDER BY rol_id, nombre'
        );
        $sentencia->execute([
            ':mecanico'   => Rol::MECANICO,
            ':hojalatero' => Rol::HOJALATERO,
        ]);

        return $sentencia->fetchAll();
    }

    /**
     * Login: devuelve el usuario (sin hash) si el correo existe y la contraseña coincide;
     * null en cualquier otro caso. No distingue "correo no existe" de "contraseña mal"
     * para no revelar qué correos están registrados. Incluye 'activo' para que el
     * controlador rechace a los desactivados.
     */
    public function autenticar(string $correo, string $contrasenaPlana): ?array
    {
        $sentencia = $this->db->prepare(
            'SELECT id, nombre, correo, password_hash, rol_id, rol_nombre, activo
             FROM v_usuarios
             WHERE LOWER(correo) = LOWER(:correo)'
        );
        $sentencia->execute([':correo' => trim($correo)]);
        $fila = $sentencia->fetch();

        if ($fila === false || !password_verify($contrasenaPlana, $fila['password_hash'])) {
            return null;
        }

        unset($fila['password_hash']);

        return $fila;
    }

    /** ¿Ya existe ese correo en otro usuario? Ignora mayúsculas */
    public function correoExiste(string $correo, ?string $exceptoId = null): bool
    {
        $sql        = 'SELECT 1 FROM usuarios WHERE LOWER(correo) = LOWER(:correo)';
        $parametros = [':correo' => trim($correo)];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros[':id'] = $exceptoId;
        }

        $sentencia = $this->db->prepare($sql);
        $sentencia->execute($parametros);

        return $sentencia->fetchColumn() !== false;
    }

    /** Total de registros */
    public function contar(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }

    /**
     * Cuántas personas activas hay de cada rol. Alimenta la barra del panel:
     * [['id', 'nombre', 'total'], ...]
     */
    public function porRol(): array
    {
        return $this->db->query(
            'SELECT r.id, r.nombre, COUNT(u.id) FILTER (WHERE u.activo) AS total
             FROM roles r
             LEFT JOIN usuarios u ON u.rol_id = r.id
             GROUP BY r.id, r.nombre
             ORDER BY r.id'
        )->fetchAll();
    }

    /** Empleados con acceso vigente */
    public function contarActivos(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios WHERE activo')->fetchColumn();
    }
}
