<?php

// ficha del auto (folio, cliente, vehículo, responsable, fechas)

$publica ??= false;
?>
<section class="tarjeta" aria-labelledby="titulo-ficha">
    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <h2 class="tarjeta__titulo" id="titulo-ficha">Ficha</h2>
        <span class="etiqueta etiqueta--area<?= e($vehiculo['area_id']) ?>"><?= e($vehiculo['area_nombre']) ?></span>
    </header>

    <dl class="ficha">
        <div class="ficha__fila">
            <dt>Folio</dt>
            <dd class="mono"><?= e($vehiculo['folio']) ?></dd>
        </div>
        <div class="ficha__fila">
            <dt>Cliente</dt>
            <dd>
                <?= e($vehiculo['cliente_nombre']) ?>
                <?php if (!$publica): ?>
                    <span class="ficha__sub"><?= e($vehiculo['cliente_telefono']) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="ficha__fila">
            <dt>Vehículo</dt>
            <dd>
                <?= e($vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?><?= $vehiculo['anio'] !== null ? ' ' . e($vehiculo['anio']) : '' ?>
                <?php if ($vehiculo['color'] !== null || $vehiculo['placas'] !== null): ?>
                    <span class="ficha__sub"><?= e(implode(' · ', array_filter([$vehiculo['color'], $vehiculo['placas']]))) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="ficha__fila">
            <dt>Asignado a</dt>
            <dd>
                <?php if ($vehiculo['asignado_nombre'] !== null): ?>
                    <?= e($vehiculo['asignado_nombre']) ?>
                    <span class="ficha__sub"><?= e($vehiculo['asignado_rol']) ?></span>
                <?php else: ?>
                    <span class="celda--tenue"><?= Vehiculo::estaEntregado($vehiculo) ? '—' : 'Sin asignar' ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="ficha__fila">
            <dt>Ingreso</dt>
            <dd><?= e(fecha($vehiculo['creado_en'])) ?></dd>
        </div>
        <?php if ($vehiculo['entregado_en'] !== null): ?>
            <div class="ficha__fila">
                <dt>Entrega</dt>
                <dd><?= e(fecha($vehiculo['entregado_en'])) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
</section>
