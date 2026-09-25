<?php
$publica = true;
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">
            <?= e($vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?><?= $vehiculo['anio'] !== null ? ' ' . e($vehiculo['anio']) : '' ?>
        </h1>
        <p class="pagina__nota">
            Hola, <?= e($vehiculo['cliente_nombre']) ?>. Este es el avance de tu auto con folio <?= e($vehiculo['folio']) ?>.
        </p>
    </div>
</div>

<?php if (Vehiculo::estaEntregado($vehiculo)): ?>
    <div class="aviso aviso--exito" role="status">
        <p class="aviso__mensaje">Tu auto ya fue entregado. ¡Gracias por tu confianza!</p>
    </div>
<?php elseif ((int) $vehiculo['area_id'] === Area::TERMINADO): ?>
    <div class="aviso aviso--exito" role="status">
        <p class="aviso__mensaje">Tu auto está <strong>listo</strong>. Ya puedes pasar a recogerlo.</p>
    </div>
<?php endif; ?>

<div class="bitacora">

    <aside class="bitacora__lateral">
        <?php require RUTA_APP . '/views/parciales/ficha_vehiculo.php'; ?>
    </aside>

    <?php require RUTA_APP . '/views/parciales/linea_tiempo.php'; ?>

</div>
