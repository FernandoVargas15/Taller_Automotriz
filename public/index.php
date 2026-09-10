<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    (new UsuarioController())->manejarPeticion();
} catch (Throwable $e) {
    http_response_code(500);

    echo '<!doctype html><meta charset="utf-8">';
    echo '<title>Error de la aplicación</title>';
    echo '<div style="font:16px/1.6 system-ui;max-width:44rem;margin:4rem auto;padding:0 1.5rem">';
    echo '<h1 style="color:#b91c1c">Error de la aplicación</h1>';
    echo '<p><strong>' . htmlspecialchars(get_class($e), ENT_QUOTES) . '</strong></p>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
    echo '<p style="color:#64748b;font-size:.875rem">'
       . htmlspecialchars($e->getFile(), ENT_QUOTES) . ':' . $e->getLine() . '</p>';
    echo '</div>';
}