<?php
$esCliente = ($cliente ?? null) !== null;

// 1. Pestañas del rol. El cliente y el login no tienen.
$pestanas = match ($esCliente ? null : ($usuarioActual === null ? null : (int) $usuarioActual['rol_id'])) {
    // El administrador ve todo
    Rol::ADMINISTRADOR => [
        '/admin'          => 'Panel',
        '/admin/usuarios' => 'Usuarios',
        '/vehiculos'      => 'Vehículos',
        '/reportes'       => 'Reportes',
    ],
    // El asesor atiende al cliente: los vehículos y su reporte por periodo
    Rol::ASESOR => [
        '/vehiculos' => 'Vehículos',
        '/reportes'  => 'Reportes',
    ],
    // El taller trabaja lo que le asignaron
    Rol::MECANICO, Rol::HOJALATERO => [
        '/taller' => 'Mis autos',
    ],
    default => [],
};

// La activa es la de prefijo más largo que coincida: /admin/usuarios/nuevo → "Usuarios"
$rutaActual    = ruta_actual();
$pestanaActiva = null;
foreach (array_keys($pestanas) as $ruta) {
    if ($rutaActual === $ruta || str_starts_with($rutaActual, $ruta . '/')) {
        $pestanaActiva = $ruta;
    }
}

// 2. Quién entró: mismo bloque visual para el personal y para el cliente
$identidad = match (true) {
    $esCliente => [
        'nombre' => $cliente,
        'rol'    => 'Cliente',
        'salir'  => '/consulta/salir',
        'texto'  => 'Salir',
    ],
    $usuarioActual !== null => [
        'nombre' => $usuarioActual['nombre'],
        'rol'    => $usuarioActual['rol_nombre'],
        'salir'  => '/salir',
        'texto'  => 'Cerrar sesión',
    ],
    default => null,
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="stylesheet" href="<?= e(css('app')) ?>">
    <?php foreach ($css as $hoja): ?>
        <link rel="stylesheet" href="<?= e(css($hoja)) ?>">
    <?php endforeach; ?>
</head>
<body>

    <header class="encabezado">
        <div class="encabezado__interior">

            <span class="encabezado__marca"><?= e(Config::obtener('APP_NOMBRE', 'Taller Automotriz')) ?></span>

            <?php if ($pestanas !== []): ?>
                <nav class="pestanas" aria-label="Secciones">
                    <?php foreach ($pestanas as $ruta => $nombre): ?>
                        <a class="pestanas__enlace <?= $ruta === $pestanaActiva ? 'es-activa' : '' ?>"
                           href="<?= e(url($ruta)) ?>"
                           <?= $ruta === $pestanaActiva ? 'aria-current="page"' : '' ?>><?= e($nombre) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if ($identidad !== null): ?>
                <div class="usuario">
                    <span class="usuario__avatar" aria-hidden="true"><?= e(iniciales($identidad['nombre'])) ?></span>
                    <span class="usuario__datos">
                        <span class="usuario__nombre"><?= e($identidad['nombre']) ?></span>
                        <span class="usuario__rol"><?= e($identidad['rol']) ?></span>
                    </span>
                    <a class="usuario__salir" href="<?= e(url($identidad['salir'])) ?>" title="<?= e($identidad['texto']) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                        </svg>
                        <span><?= e($identidad['texto']) ?></span>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </header>

    <main class="contenido">
        <?= $contenido ?>
    </main>

</body>
</html>
