<?php
declare(strict_types=1);

// PATRÓN SINGLETON
final class Database
{
    private static ?Database $instancia = null;
    private PDO $conexion;

    private function __construct()
    {
        $driver = Config::obtener('DB_DRIVER', 'pgsql');
        $host   = Config::obtener('DB_HOST', '127.0.0.1');
        $puerto = Config::entero('DB_PORT', 5432);
        $base   = Config::requerido('DB_NAME');
        $user   = Config::requerido('DB_USER');
        $pass   = Config::obtener('DB_PASSWORD', '');

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;connect_timeout=5',
            $driver, $host, $puerto, $base
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
            PDO::ATTR_EMULATE_PREPARES   => false,                  
        ];

        //  SE CONECTA A LA BASE DE DATOS
        $this->conexion = new PDO($dsn, $user, $pass, $opciones);

        $codificacion = Config::obtener('DB_CHARSET', 'UTF8');
        $this->conexion->exec("SET client_encoding TO '" . strtoupper($codificacion) . "'");
    }

    /** Crea el objeto la primera vez, después devuelve siempre el mismo */
    public static function obtenerInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    public function conexion(): PDO
    {
        return $this->conexion;
    }

    /* Versión de PostgreSQL */
    public function versionServidor(): string
    {
        return (string) $this->conexion->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /* Nombre de la base conectada */
    public function nombreBase(): string
    {
        return (string) $this->conexion->query('SELECT current_database()')->fetchColumn();
    }

    public function estaViva(): bool
    {
        try {
            $this->conexion->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    private function __clone() {}
    public function __wakeup(): void
    {
        throw new RuntimeException('No se permite deserializar un Singleton.');
    }
}
