<?php

// VISTA (mecánico y hojalatero / pintor): los autos que le asignaron.

$enTaller = array_filter($vehiculos, static fn (array $v): bool => !Vehiculo::estaEntregado($v));
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">Mis autos</h1>
        <p class="pagina__nota">
            Hola, <?= e(explode(' ', trim($usuarioActual['nombre']))[0]) ?>.
            Tienes <?= count($enTaller) ?> <?= count($enTaller) === 1 ? 'auto' : 'autos' ?> en el taller.
        </p>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-lista">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <h2 class="tarjeta__titulo" id="titulo-lista">Autos asignados</h2>
        <span class="contador"><?= count($vehiculos) ?> en total</span>
    </header>

    <?php if ($vehiculos === []): ?>

        <p class="vacio">Todavía no tienes autos asignados. El asesor o el administrador te los asignan al registrarlos.</p>

    <?php else: ?>

        <div class="tabla__envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Vehículo</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Área actual</th>
                        <th scope="col">Ingreso</th>
                        <th scope="col" class="celda--acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehiculos as $auto): ?>
                        <?php $entregado = Vehiculo::estaEntregado($auto); ?>
                        <tr class="<?= $entregado ? 'fila--inactiva' : '' ?>">
                            <td class="mono"><?= e($auto['folio']) ?></td>
                            <td>
                                <span class="celda--fuerte"><?= e($auto['marca']) ?> <?= e($auto['modelo']) ?><?= $auto['anio'] !== null ? ' ' . e($auto['anio']) : '' ?></span>
                                <span class="celda__linea celda--tenue">
                                    <?= e(implode(' · ', array_filter([$auto['color'], $auto['placas']]))) ?>
                                </span>
                            </td>
                            <td><?= e($auto['cliente_nombre']) ?></td>
                            <td>
                                <span class="etiqueta etiqueta--area<?= e($auto['area_id']) ?>"><?= e($auto['area_nombre']) ?></span>
                            </td>
                            <td class="celda--tenue"><?= e(fecha($auto['creado_en'])) ?></td>
                            <td class="celda--acciones">
                                <div class="acciones">
                                    <a class="boton boton--pequeno <?= $entregado ? 'boton--suave' : 'boton--primario' ?>"
                                       href="<?= e(url('/taller/vehiculo?id=' . $auto['id'])) ?>">
                                        <?= $entregado ? 'Ver historial' : 'Trabajar' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
