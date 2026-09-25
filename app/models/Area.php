<?php
declare(strict_types=1);

// MODELO Area — catálogo de por dónde pasa un auto

final class Area
{
    public const RECEPCION = 1;
    public const MECANICA  = 2;
    public const PINTURA   = 3;
    public const TERMINADO = 4;
    public const ENTREGADO = 5;

    // Todo auto entra por recepción
    public const POR_DEFECTO = self::RECEPCION;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::obtenerInstancia()->conexion();
    }

    /** Catálogo completo en orden del proceso; alimenta los <select>. */
    public function todos(): array
    {
        return $this->db->query('SELECT id, nombre FROM areas ORDER BY id')->fetchAll();
    }

    /** ¿El área existe? El <select> se puede manipular desde el navegador. */
    public function existe(int $id): bool
    {
        $sentencia = $this->db->prepare('SELECT 1 FROM areas WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return $sentencia->fetchColumn() !== false;
    }
}
