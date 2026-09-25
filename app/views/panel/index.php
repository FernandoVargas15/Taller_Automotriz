<?php

//VISTA (solo administrador): panel de inicio

$distribuciones = [
    [
        'nombre' => 'Autos en el taller',
        'total'  => $enTaller,
        'partes' => array_map(static fn (array $area): array => [
            'nombre' => $area['nombre'],
            'valor'  => (int) $area['total'],
            'color'  => 'area' . $area['id'],
        ], $porArea),
    ],
    [
        'nombre' => 'Autos registrados',
        'total'  => $registrados,
        'partes' => [
            ['nombre' => 'En el taller', 'valor' => $enTaller,   'color' => 'taller'],
            ['nombre' => 'Entregados',   'valor' => $entregados, 'color' => 'entregado'],
        ],
    ],
    [
        'nombre' => 'Personal activo',
        'total'  => $personal,
        'partes' => array_map(static fn (array $rol): array => [
            'nombre' => $rol['nombre'],
            'valor'  => (int) $rol['total'],
            'color'  => 'rol' . $rol['id'],
        ], $porRol),
    ],
];
?>

<?php // Cabecera: saludo y la acción principal ?>
<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">Hola, <?= e(explode(' ', trim($usuarioActual['nombre']))[0]) ?></h1>
        <p class="pagina__nota">Así va el taller hoy, <?= e(fecha(date('c'), 'd/m/Y')) ?>.</p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--primario" href="<?= e(url('/vehiculos/nuevo')) ?>">+ Registrar vehículo</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-taller">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <div>
            <h2 class="tarjeta__titulo" id="titulo-taller">Estado del taller</h2>
            <p class="tarjeta__nota">Cómo está repartido el trabajo ahora mismo.</p>
        </div>
        <div class="acciones">
            <a class="boton boton--pequeno boton--suave" href="<?= e(url('/reportes')) ?>">Reporte en PDF</a>
            <a class="boton boton--pequeno boton--suave" href="<?= e(url('/vehiculos')) ?>">Ver todos los vehículos</a>
        </div>
    </header>

    <?php // Las tres cifras, cada una con su barra y su leyenda ?>
    <div class="bloque distribuciones">
        <?php foreach ($distribuciones as $dato): ?>
            <div class="distribucion">

                <p class="distribucion__nombre"><?= e($dato['nombre']) ?></p>
                <p class="distribucion__total"><?= e($dato['total']) ?></p>

                <div class="carga">
                    <?php foreach ($dato['partes'] as $parte): ?>
                        <?php if ($parte['valor'] > 0): ?>
                            <span class="carga__parte carga__parte--<?= e($parte['color']) ?>"
                                  style="width: <?= round($parte['valor'] / max($dato['total'], 1) * 100, 2) ?>%"
                                  title="<?= e($parte['nombre']) ?>: <?= e($parte['valor']) ?>"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <ul class="leyenda">
                    <?php foreach ($dato['partes'] as $parte): ?>
                        <li class="leyenda__parte">
                            <span class="leyenda__punto leyenda__punto--<?= e($parte['color']) ?>" aria-hidden="true"></span>
                            <span class="leyenda__nombre"><?= e($parte['nombre']) ?></span>
                            <span class="leyenda__valor"><?= e($parte['valor']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>
        <?php endforeach; ?>
    </div>

    <?php // Últimos autos que entraron y siguen en el taller ?>
    <?php if ($recientes === []): ?>

        <p class="vacio">Todavía no ha ingresado ningún vehículo.</p>

    <?php else: ?>

        <div class="tabla__envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Vehículo</th>
                        <th scope="col">Área actual</th>
                        <th scope="col">Asignado a</th>
                        <th scope="col">Ingreso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recientes as $auto): ?>
                        <tr>
                            <td class="mono"><?= e($auto['folio']) ?></td>
                            <td class="celda--fuerte"><?= e($auto['cliente_nombre']) ?></td>
                            <td>
                                <?= e($auto['marca']) ?> <?= e($auto['modelo']) ?>
                                <?php if ($auto['anio'] !== null): ?>
                                    <span class="celda__nota"><?= e($auto['anio']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="etiqueta etiqueta--area<?= e($auto['area_id']) ?>"><?= e($auto['area_nombre']) ?></span>
                            </td>
                            <td>
                                <?php if ($auto['asignado_nombre'] !== null): ?>
                                    <?= e($auto['asignado_nombre']) ?>
                                <?php else: ?>
                                    <span class="celda--tenue">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="celda--tenue"><?= e(fecha($auto['creado_en'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
