<?php
declare(strict_types=1);

/**
 * Lee el archivo .env para que los datos de conexión no vivan en el código.
 */
final class Config
{
    private static array $valores = [];
    private static bool $cargado = false;

    // Constructor privado: la clase es solo estática, nunca se instancia.
    private function __construct() {}

    /** Lee y parsea el .env una sola vez. */
    public static function cargar(string $rutaEnv): void
    {
        if (self::$cargado) {
            return;
        }

        if (!is_file($rutaEnv)) {
            throw new RuntimeException("No se encontró el archivo .env en: {$rutaEnv}");
        }

        $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lineas === false) {
            throw new RuntimeException("No se pudo leer el .env en: {$rutaEnv}");
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);

            // Quita las comillas que envuelven al valor: APP_NOMBRE="Mi App"
            if (strlen($valor) >= 2) {
                $ini = $valor[0];
                $fin = $valor[strlen($valor) - 1];

                if (($ini === '"' && $fin === '"') || ($ini === "'" && $fin === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            self::$valores[$clave] = $valor;
        }

        self::$cargado = true;
    }

    /** Valor del .env, o $porDefecto si falta o está vacío. */
    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $valor = self::$valores[$clave] ?? null;

        return ($valor === null || $valor === '') ? $porDefecto : $valor;
    }

    /** Igual que obtener(), pero como entero (DB_PORT, HASH_COSTO). */
    public static function entero(string $clave, int $porDefecto): int
    {
        $valor = self::obtener($clave);

        return ($valor !== null && is_numeric($valor)) ? (int) $valor : $porDefecto;
    }

    /** Valor obligatorio: si falta, avisa de inmediato en vez de fallar después. */
    public static function requerido(string $clave): string
    {
        $valor = self::obtener($clave);

        if ($valor === null) {
            throw new RuntimeException("Falta la variable '{$clave}' en el .env.");
        }

        return $valor;
    }

    // Igual que en Database: la clase no se instancia, ni se copia, ni se deserializa
    private function __clone() {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Config no se puede deserializar: es una clase estática.');
    }
}
