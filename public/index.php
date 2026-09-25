<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$rutas = [
    '/' => [InicioController::class, 'index'],
    '/login' => [AuthController::class, 'login'],
    '/salir' => [AuthController::class, 'salir'],

    // Cliente: sin cuenta, entra con folio + teléfono
    '/consulta' => [ConsultaController::class, 'index'],
    '/consulta/estado' => [ConsultaController::class, 'estado'],
    '/consulta/salir' => [ConsultaController::class, 'salir'],

    // Taller: mecánico y hojalatero / pintor, solo sus autos asignados
    '/taller' => [TallerController::class, 'index'],
    '/taller/vehiculo' => [TallerController::class, 'vehiculo'],  // ?id=  GET detalle / POST reporte
    '/taller/vehiculo/area' => [TallerController::class, 'area'],  // POST

    // Mostrador: administrador y asesor de servicio
    '/vehiculos' => [VehiculoController::class, 'index'],     // ?q= buscador
    '/vehiculos/nuevo' => [VehiculoController::class, 'nuevo'],     // ?desde= para reingreso
    '/vehiculos/editar' => [VehiculoController::class, 'editar'],    // ?id=
    '/vehiculos/entregar' => [VehiculoController::class, 'entregar'],  // POST
    '/vehiculos/eliminar' => [VehiculoController::class, 'eliminar'],  // POST, solo administrador
    '/vehiculos/bitacora' => [BitacoraController::class, 'index'],     // ?id=
    '/vehiculos/bitacora/eliminar' => [BitacoraController::class, 'eliminar'],  // POST, solo administrador
    '/reportes' => [ReporteController::class, 'index'],   // ?periodo=dia|semana|mes&fecha=
    '/reportes/pdf' => [ReporteController::class, 'pdf'],     // mismos parámetros, salida en PDF

    // Solo administrador
    '/admin' => [PanelController::class, 'index'],
    '/admin/usuarios' => [UsuarioController::class, 'index'],
    '/admin/usuarios/nuevo' => [UsuarioController::class, 'nuevo'],
    '/admin/usuarios/editar' => [UsuarioController::class, 'editar'],    // ?id=
    '/admin/usuarios/estado' => [UsuarioController::class, 'estado'],    // POST
    '/admin/usuarios/sesion' => [UsuarioController::class, 'sesion'],    // POST, libera la sesión activa
    '/admin/usuarios/eliminar' => [UsuarioController::class, 'eliminar'],  // POST
];

$ruta = ruta_actual();

try {
    if (!isset($rutas[$ruta])) {
        http_response_code(404);
        echo '<!doctype html><meta charset="utf-8"><title>No encontrado</title>';
        echo '<p style="font:16px system-ui;margin:4rem auto;max-width:44rem;text-align:center">';
        echo 'Página no encontrada. <a href="' . e(url()) . '">Volver al inicio</a></p>';
        exit;
    }

    [$controlador, $metodo] = $rutas[$ruta];
    (new $controlador())->$metodo();

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