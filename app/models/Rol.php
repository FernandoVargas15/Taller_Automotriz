<?php
declare(strict_types=1);

// MODELO Rol

final class Rol
{
    public const ADMINISTRADOR = 1;
    public const ASESOR        = 2;
    public const MECANICO      = 3;
    public const HOJALATERO    = 4;

    // El personal nuevo entra como asesor; el cliente no se registra aquí.
    public const POR_DEFECTO = self::ASESOR;

    /** Los que reciben autos asignados y trabajan en ellos. */
    public const DE_TALLER = [self::MECANICO, self::HOJALATERO];

    /** Los que atienden al cliente y manejan el alta de vehículos. */
    public const DE_MOSTRADOR = [self::ADMINISTRADOR, self::ASESOR];

    private PDO $db;

    public function __construct()
    {
        // No se hace "new PDO": se pide la conexión única al Singleton
        $this->db = Database::obtenerInstancia()->conexion();
    }

    /** Catálogo completo; alimenta el <select> del formulario. */
    public function todos(): array
    {
        return $this->db->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll();
    }

    /**
     * ¿El rol existe? Se comprueba porque cualquiera puede manipular
     * el <select> desde el navegador y mandar un id inventado.
     */
    public function existe(int $id): bool
    {
        $sentencia = $this->db->prepare('SELECT 1 FROM roles WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return $sentencia->fetchColumn() !== false;
    }

    /** Nombre legible del rol, o null si no existe. */
    public function nombre(int $id): ?string
    {
        $sentencia = $this->db->prepare('SELECT nombre FROM roles WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        $nombre = $sentencia->fetchColumn();

        return $nombre === false ? null : (string) $nombre;
    }
}
