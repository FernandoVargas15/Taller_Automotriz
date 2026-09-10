<?php
declare(strict_types=1);

// MODELO Usuario — valida, encripta y consulta la tabla "usuarios", es el único lugar del proyecto donde se escribe SQL sobre usuarios
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

    // Validacion
    public function validar(array $datos): array
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
        } elseif ($this->correoExiste($correo)) {
            $errores['correo'] = 'Ese correo ya está registrado.';
        }

        if ($contrasena === '') {
            $errores['contrasena'] = 'La contraseña es obligatoria.';
        } elseif (mb_strlen($contrasena) < self::PASSWORD_MIN) {
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

    /** Usuarios del más reciente al más antiguo */
    public function todos(int $limite = 100): array
    {
        $sql = 'SELECT id, nombre, correo, password_hash, rol_id, rol_nombre, creado_en
                FROM v_usuarios
                ORDER BY creado_en DESC
                LIMIT :limite';

        $sentencia = $this->db->prepare($sql);
        $sentencia->bindValue(':limite', $limite, PDO::PARAM_INT);
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /** ¿Ya existe ese correo? Ignora mayúsculas */
    public function correoExiste(string $correo): bool
    {
        $sentencia = $this->db->prepare(
            'SELECT 1 FROM usuarios WHERE LOWER(correo) = LOWER(:correo)'
        );
        $sentencia->execute([':correo' => trim($correo)]);

        return $sentencia->fetchColumn() !== false;
    }

    /** Total de registros */
    public function contar(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }
}
