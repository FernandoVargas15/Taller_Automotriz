<?php

// VISTA (administrador y asesor de servicio): control general de vehículos
 
$token = Sesion::tokenCsrf();
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">Control general de vehículos</h1>
        <p class="pagina__nota">Todos los autos registrados, sin importar en qué área estén. Los entregados van al final.</p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--primario" href="<?= e(url('/vehiculos/nuevo')) ?>">+ Registrar vehículo</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-lista">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <div>
            <h2 class="tarjeta__titulo" id="titulo-lista">Vehículos registrados</h2>
            <p class="tarjeta__nota">
                <?php if ($busqueda !== ''): ?>
                    <?= count($vehiculos) ?> de <?= e($total) ?> coinciden con «<?= e($busqueda) ?>»
                <?php else: ?>
                    <?= e($total) ?> <?= $total === 1 ? 'registro' : 'registros' ?>
                <?php endif; ?>
            </p>
        </div>

        <?php // Buscador: sirve para encontrar un auto que ya vino antes y reingresarlo ?>
        <form class="buscador" method="get" action="<?= e(url('/vehiculos')) ?>" role="search">
            <input class="campo__control buscador__control" type="search" name="q"
                   value="<?= e($busqueda) ?>" placeholder="Folio, cliente, placas, marca…" aria-label="Buscar vehículo">
            <button class="boton boton--suave" type="submit">Buscar</button>
            <?php if ($busqueda !== ''): ?>
                <a class="boton boton--suave" href="<?= e(url('/vehiculos')) ?>">Limpiar</a>
            <?php endif; ?>
        </form>
    </header>

    <?php if ($vehiculos === []): ?>

        <p class="vacio">
            <?= $busqueda !== '' ? 'Ningún vehículo coincide con la búsqueda.' : 'Todavía no hay vehículos registrados.' ?>
        </p>

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
                        <th scope="col" class="celda--acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehiculos as $auto): ?>
                        <?php
                            $terminado = (int) $auto['area_id'] === Area::TERMINADO;
                            $entregado = (int) $auto['area_id'] === Area::ENTREGADO;
                        ?>
                        <tr class="<?= $entregado ? 'fila--inactiva' : '' ?>">
                            <td class="mono"><?= e($auto['folio']) ?></td>
                            <td>
                                <span class="celda--fuerte"><?= e($auto['cliente_nombre']) ?></span>
                                <span class="celda__linea celda--tenue"><?= e($auto['cliente_telefono']) ?></span>
                            </td>
                            <td>
                                <?= e($auto['marca']) ?> <?= e($auto['modelo']) ?>
                                <?php if ($auto['anio'] !== null): ?>
                                    <?= e($auto['anio']) ?>
                                <?php endif; ?>
                                <span class="celda__linea celda--tenue">
                                    <?= e(implode(' · ', array_filter([$auto['color'], $auto['placas']]))) ?>
                                </span>
                            </td>
                            <td>
                                <span class="etiqueta etiqueta--area<?= e($auto['area_id']) ?>"><?= e($auto['area_nombre']) ?></span>
                                <?php if ($entregado && $auto['entregado_en'] !== null): ?>
                                    <span class="celda__linea celda--tenue"><?= e(fecha($auto['entregado_en'], 'd/m/Y')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($auto['asignado_nombre'] !== null): ?>
                                    <?= e($auto['asignado_nombre']) ?>
                                    <span class="celda__linea celda--tenue"><?= e($auto['asignado_rol']) ?></span>
                                <?php else: ?>
                                    <span class="celda--tenue"><?= $entregado ? '—' : 'Sin asignar' ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="celda--tenue"><?= e(fecha($auto['creado_en'])) ?></td>
                            <td class="celda--acciones">
                                <div class="acciones">

                                    <?php if ($terminado): ?>
                                        <form method="post" action="<?= e(url('/vehiculos/entregar')) ?>"
                                              onsubmit="return confirm('¿Marcar el <?= e($auto['marca'] . ' ' . $auto['modelo']) ?> (<?= e($auto['folio']) ?>) como entregado al cliente?')">
                                            <input type="hidden" name="_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id" value="<?= e($auto['id']) ?>">
                                            <button class="boton boton--pequeno boton--primario" type="submit">Entregar</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($entregado): ?>
                                        <a class="boton boton--pequeno boton--primario"
                                           href="<?= e(url('/vehiculos/nuevo?desde=' . $auto['id'])) ?>"
                                           title="Vuelve al taller: nuevo folio con los datos del cliente y del auto ya llenos">Reingresar</a>
                                    <?php endif; ?>

                                    <a class="boton boton--pequeno boton--suave"
                                       href="<?= e(url('/vehiculos/bitacora?id=' . $auto['id'])) ?>">Ver bitácora</a>

                                    <a class="boton boton--pequeno boton--suave"
                                       href="<?= e(url('/vehiculos/editar?id=' . $auto['id'])) ?>">Editar</a>

                                    <?php if ($esAdmin): ?>
                                        <form method="post" action="<?= e(url('/vehiculos/eliminar')) ?>"
                                              onsubmit="return confirm('¿Eliminar el vehículo <?= e($auto['folio']) ?> y toda su bitácora? Esta acción no se puede deshacer.')">
                                            <input type="hidden" name="_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id" value="<?= e($auto['id']) ?>">
                                            <button class="boton boton--pequeno boton--peligro" type="submit">Eliminar</button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
