<?php

// VISTA (administrador y asesor): bitácora de un auto, con formulario para agregar reportes y línea de tiempo de los existentes

$token  = Sesion::tokenCsrf();
$accion = url('/vehiculos/bitacora?id=' . $vehiculo['id']);
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">
            Bitácora de <?= e($vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?><?= $vehiculo['anio'] !== null ? ' ' . e($vehiculo['anio']) : '' ?>
        </h1>
        <p class="pagina__nota">Folio <?= e($vehiculo['folio']) ?>. Lo mismo que ve el cliente; aquí puedes borrar fotos borrosas o reportes con errores.</p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--suave" href="<?= e(url('/vehiculos')) ?>">← Volver a vehículos</a>
        <a class="boton boton--suave" href="<?= e(url('/vehiculos/editar?id=' . $vehiculo['id'])) ?>">Editar auto</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<div class="bitacora">

    <aside class="bitacora__lateral">
        <?php require RUTA_APP . '/views/parciales/ficha_vehiculo.php'; ?>
        <?php require RUTA_APP . '/views/parciales/formulario_reporte.php'; ?>
    </aside>

    <?php $puedeEliminar = $esAdmin; ?>
    <?php require RUTA_APP . '/views/parciales/linea_tiempo.php'; ?>

</div>
