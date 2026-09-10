<?php
declare(strict_types=1);

/**
 * Arranque de la aplicación: rutas, autocarga de clases, .env y sesión.
 */

define('RUTA_RAIZ', dirname(__DIR__));   // .../Taller_Automotriz
define('RUTA_APP',  __DIR__);            // .../Taller_Automotriz/app

// Autocarga: al usar la clase Usuario, busca e incluye app/models/Usuario.php
spl_autoload_register(static function (string $clase): void {
    foreach (['config', 'models', 'controllers'] as $carpeta) {
        $ruta = RUTA_APP . '/' . $carpeta . '/' . $clase . '.php';

        if (is_file($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

Config::cargar(RUTA_RAIZ . '/.env');

date_default_timezone_set('America/Mexico_City');

// La sesión guarda el aviso de "registrado correctamente" y el token CSRF
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
