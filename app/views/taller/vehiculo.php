<?php

// VISTA (mecánico y hojalatero / pintor): detalle de un auto asignado

$token     = Sesion::tokenCsrf();
$accion    = url('/taller/vehiculo?id=' . $vehiculo['id']);
$entregado = Vehiculo::estaEntregado($vehiculo);
?>

<?php // Cabecera: qué auto es y cómo volver a la lista ?>
<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">
            <?= e($vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?><?= $vehiculo['anio'] !== null ? ' ' . e($vehiculo['anio']) : '' ?>
        </h1>
        <p class="pagina__nota">Folio <?= e($vehiculo['folio']) ?>. Todo lo que registres aquí lo verá el cliente en su consulta.</p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--suave" href="<?= e(url('/taller')) ?>">← Volver a mis autos</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<div class="bitacora">

    <aside class="bitacora__lateral">

        <?php require RUTA_APP . '/views/parciales/ficha_vehiculo.php'; ?>

        <?php // Avance del proceso: Recepción → Mecánica → Pintura → Terminado ?>
        <?php if (!$entregado): ?>
            <section class="tarjeta" aria-labelledby="titulo-area">
                <header class="tarjeta__cabecera">
                    <h2 class="tarjeta__titulo" id="titulo-area">Mover de área</h2>
                    <p class="tarjeta__nota">Para marcarlo Terminado, la bitácora necesita al menos una foto.</p>
                </header>

                <form method="post" action="<?= e(url('/taller/vehiculo/area')) ?>">
                    <input type="hidden" name="_token" value="<?= e($token) ?>">
                    <input type="hidden" name="id" value="<?= e($vehiculo['id']) ?>">

                    <div class="bloque">
                        <div class="campos">
                            <div class="campo">
                                <label class="campo__etiqueta" for="area_id">Área</label>
                                <select class="campo__control" id="area_id" name="area_id">
                                    <?php foreach ($areas as $id => $nombre): ?>
                                        <option value="<?= e($id) ?>" <?= $id === (int) $vehiculo['area_id'] ? 'selected' : '' ?>>
                                            <?= e($nombre) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bloque bloque--acciones">
                        <button class="boton boton--primario" type="submit">Cambiar área</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php require RUTA_APP . '/views/parciales/formulario_reporte.php'; ?>

    </aside>

    <?php require RUTA_APP . '/views/parciales/linea_tiempo.php'; ?>

</div>
