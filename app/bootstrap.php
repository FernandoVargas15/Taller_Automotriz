<?php
declare(strict_types=1);

/**
 * Arranque de la aplicación: rutas, autocarga de clases, .env y sesión.
 */

define('RUTA_RAIZ', dirname(__DIR__));   // .../Taller_Automotriz
define('RUTA_APP',  __DIR__);            // .../Taller_Automotriz/app

// Autocarga: al usar la clase Usuario, busca e incluye app/models/Usuario.php
spl_autoload_register(static function (string $clase): void {
    // tFPDF es una librería externa (LGPL) y vive aparte del código propio, en app/libs
    if ($clase === 'tFPDF') {
        require_once RUTA_APP . '/libs/tfpdf/tfpdf.php';
        return;
    }

    foreach (['config', 'core', 'models', 'controllers', 'reportes'] as $carpeta) {
        $ruta = RUTA_APP . '/' . $carpeta . '/' . $clase . '.php';

        if (is_file($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

Config::cargar(RUTA_RAIZ . '/.env');

date_default_timezone_set('America/Mexico_City');

// La sesión guarda al usuario autenticado, el token CSRF y los avisos de un solo uso.
//
// Este es un sistema de mostrador: el personal lo deja abierto toda la jornada y
// pasa ratos largos sin tocarlo (atendiendo a un cliente, revisando un auto). Con
// los 24 minutos que PHP trae de fábrica, al volver se encontrarían la sesión
// caída sin haber hecho nada mal. Se amplía a 12 horas para que la sesión dure lo
// que dura un turno de trabajo.
const SESION_DURACION = 12 * 60 * 60;   // 12 horas en segundos

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) SESION_DURACION);

    session_set_cookie_params([
        'lifetime' => 0,          // la cookie muere al cerrar el navegador
        'httponly' => true,       // JavaScript no puede leerla
        'samesite' => 'Lax',      // no viaja desde otros sitios
    ]);

    session_start();
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Iniciales para el avatar: "Fernando Vargas" → "FV", "Ana" → "A". Máximo 2. */
function iniciales(string $nombre): string
{
    $palabras = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letras   = '';

    foreach (array_slice($palabras, 0, 2) as $palabra) {
        $letras .= mb_substr($palabra, 0, 1);
    }

    return mb_strtoupper($letras);
}

/**
 * ¿Tiene forma de UUID? Los ids llegan por la URL y cualquiera puede escribir
 * lo que sea; PostgreSQL lanza error si se le consulta un uuid mal formado.
 */
function es_uuid(mixed $valor): bool
{
    return is_string($valor)
        && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1;
}

/** Fecha de PostgreSQL → "17/09/2026 22:15" */
function fecha(?string $marca, string $formato = 'd/m/Y H:i'): string
{
    if ($marca === null || $marca === '') {
        return '';
    }

    return date($formato, strtotime($marca));
}

/**
 * Prefijo bajo el que vive la app: '/taller' si está en localhost:8888/taller/,
 * '' si es la raíz del servidor. Sale de dónde está index.php.
 */
function base_url(): string
{
    return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/');
}

/**
 * URL absoluta de una ruta de la app: url('login') → /taller/login
 * Sin ruta devuelve la raíz, que decide a dónde mandar según el rol.
 * Sirve igual para archivos estáticos: url('css/estilos.css').
 */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

/**
 * URL de una hoja de estilos con la fecha del archivo al final:
 * css('app') → /taller/css/app.css?v=1758...
 * Así, al editar el CSS, el navegador descarga la versión nueva en lugar de
 * seguir mostrando la que tiene guardada en caché.
 */
function css(string $nombre): string
{
    $ruta    = RUTA_RAIZ . '/public/css/' . $nombre . '.css';
    $version = is_file($ruta) ? (string) filemtime($ruta) : '';

    return url('/css/' . $nombre . '.css') . ($version === '' ? '' : '?v=' . $version);
}

/** Ruta pedida sin el prefijo de la app ni la query: /taller/admin/usuarios → /admin/usuarios */
function ruta_actual(): string
{
    $ruta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = base_url();

    if ($base !== '' && str_starts_with($ruta, $base)) {
        $ruta = substr($ruta, strlen($base));
    }

    // /index.php explícito cuenta como la raíz
    if ($ruta === '/index.php') {
        $ruta = '/';
    }

    // Sin barra final: /admin/usuarios/ y /admin/usuarios son la misma ruta
    return '/' . trim($ruta, '/');
}
