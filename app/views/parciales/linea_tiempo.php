<?php

// línea de tiempo de un auto, la misma para administrador, mecánico y cliente

$puedeEliminar ??= false;
?>
<section class="tarjeta" aria-labelledby="titulo-linea">
    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <h2 class="tarjeta__titulo" id="titulo-linea">Línea de tiempo</h2>
        <span class="contador"><?= count($entradas) ?> <?= count($entradas) === 1 ? 'entrada' : 'entradas' ?></span>
    </header>

    <?php if ($entradas === []): ?>

        <p class="vacio">Todavía no hay reportes de este auto.</p>

    <?php else: ?>

        <ol class="linea">
            <?php foreach ($entradas as $entrada): ?>
                <li class="linea__entrada">
                    <span class="linea__punto linea__punto--area<?= e($entrada['area_id']) ?>" aria-hidden="true"></span>

                    <div class="linea__contenido">
                        <div class="linea__cabecera">
                            <div>
                                <span class="etiqueta etiqueta--area<?= e($entrada['area_id']) ?>"><?= e($entrada['area_nombre']) ?></span>
                                <span class="linea__fecha"><?= e(fecha($entrada['creado_en'])) ?></span>
                            </div>

                            <?php if ($puedeEliminar): ?>
                                <form method="post" action="<?= e(url('/vehiculos/bitacora/eliminar')) ?>"
                                      onsubmit="return confirm('¿Eliminar este reporte<?= $entrada['foto'] !== null ? ' y su foto' : '' ?>? Esta acción no se puede deshacer.')">
                                    <input type="hidden" name="_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="id" value="<?= e($entrada['id']) ?>">
                                    <button class="boton boton--pequeno boton--peligro" type="submit">
                                        Eliminar <?= $entrada['foto'] !== null ? 'foto/reporte' : 'reporte' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <p class="linea__texto"><?= nl2br(e($entrada['descripcion'])) ?></p>

                        <?php if ($entrada['foto'] !== null): ?>
                            <a class="linea__foto" href="<?= e(url($entrada['foto'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($entrada['foto'])) ?>" alt="Foto del reporte" loading="lazy">
                            </a>
                        <?php endif; ?>

                        <p class="linea__autor">
                            <?php if ($entrada['usuario_nombre'] !== null): ?>
                                <?= e($entrada['usuario_nombre']) ?> · <?= e($entrada['usuario_rol']) ?>
                            <?php else: ?>
                                Personal del taller
                            <?php endif; ?>
                        </p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

    <?php endif; ?>
</section>
