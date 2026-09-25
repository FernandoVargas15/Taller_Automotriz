<?php

// VISTA (administrador y asesor de servicio): elige el periodo y genera el reporte de vehículos recibidos

$total      = count($vehiculos);
$entregados = 0;
$terminados = 0;

foreach ($vehiculos as $fila) {
    if ((int) $fila['area_id'] === Area::ENTREGADO) {
        $entregados++;
    } elseif ((int) $fila['area_id'] === Area::TERMINADO) {
        $terminados++;
    }
}

// Los mismos parámetros que mira el controlador, para el enlace de descarga
$consulta = http_build_query([
    'periodo' => $periodo->tipo,
    'fecha'   => $periodo->fechaFormulario(),
]);
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">Reportes</h1>
        <p class="pagina__nota">Vehículos recibidos en el taller, por día, por semana o por mes.</p>
    </div>
    <div class="pagina__acciones">
        <?php // Se abre en otra pestaña: así queda el PDF a la vista y esta pantalla no se pierde ?>
        <a class="boton boton--primario" href="<?= e(url('/reportes/pdf?' . $consulta)) ?>"
           target="_blank" rel="noopener">
            Ver PDF
        </a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<?php // Elegir el corte: primero el tipo de periodo, luego la fecha de referencia ?>
<section class="tarjeta" aria-labelledby="titulo-periodo">

    <header class="tarjeta__cabecera">
        <h2 class="tarjeta__titulo" id="titulo-periodo">Periodo del reporte</h2>
        <p class="tarjeta__nota">
            Elige el tipo de corte y una fecha; el reporte toma el día, la semana (de lunes a domingo)
            o el mes completo al que pertenece esa fecha.
        </p>
    </header>

    <form method="get" action="<?= e(url('/reportes')) ?>" id="filtro-periodo">
        <div class="bloque">
            <div class="campos">

                <div class="campo">
                    <span class="campo__etiqueta">Tipo de corte</span>
                    <div class="opciones">
                        <?php foreach (Periodo::OPCIONES as $valor => $nombre): ?>
                            <label class="opcion <?= $valor === $periodo->tipo ? 'es-activa' : '' ?>">
                                <input type="radio" name="periodo" value="<?= e($valor) ?>"
                                       <?= $valor === $periodo->tipo ? 'checked' : '' ?>>
                                <?= e($nombre) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="fecha">Fecha de referencia</label>
                    <input class="campo__control" type="date" id="fecha" name="fecha"
                           value="<?= e($periodo->fechaFormulario()) ?>"
                           max="<?= e(date('Y-m-d')) ?>">
                    <p class="campo__ayuda">Cualquier día dentro del periodo que quieres consultar.</p>
                </div>

            </div>
        </div>

        <div class="bloque bloque--acciones">
            <?php // Con JavaScript el filtro se aplica solo; sin él, este botón lo envía a mano ?>
            <noscript>
                <button class="boton boton--primario" type="submit">Ver periodo</button>
            </noscript>
            <a class="boton boton--suave" href="<?= e(url('/reportes')) ?>">Volver al mes actual</a>
        </div>
    </form>

</section>

<script>
    // Al elegir el tipo de corte o cambiar la fecha, el formulario se envía solo.
    // Si el navegador no ejecuta JavaScript, sigue funcionando con el botón del <noscript>.
    (function () {
        var filtro = document.getElementById('filtro-periodo');

        if (!filtro) {
            return;
        }

        filtro.addEventListener('change', function (evento) {
            if (evento.target.name === 'periodo' || evento.target.name === 'fecha') {
                filtro.submit();
            }
        });
    })();
</script>

<?php // Vista previa: exactamente lo que va a salir impreso ?>
<section class="tarjeta" aria-labelledby="titulo-previa">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <div>
            <h2 class="tarjeta__titulo" id="titulo-previa">
                <?= e(ucfirst($periodo->titulo())) ?>
            </h2>
            <p class="tarjeta__nota"><?= e($periodo->rangoCorto()) ?></p>
        </div>
        <span class="contador"><?= e($total) ?> <?= $total === 1 ? 'vehículo' : 'vehículos' ?></span>
    </header>

    <?php if ($total === 0): ?>

        <p class="vacio">No se recibió ningún vehículo en este periodo. Prueba con otra fecha u otro tipo de corte.</p>

    <?php else: ?>

        <div class="bloque cifras">
            <div class="cifra">
                <span class="cifra__valor"><?= e($total) ?></span>
                <span class="cifra__nombre">Recibidos</span>
            </div>
            <div class="cifra">
                <span class="cifra__valor"><?= e($total - $terminados - $entregados) ?></span>
                <span class="cifra__nombre">En proceso</span>
            </div>
            <div class="cifra">
                <span class="cifra__valor"><?= e($terminados) ?></span>
                <span class="cifra__nombre">Terminados</span>
            </div>
            <div class="cifra">
                <span class="cifra__valor"><?= e($entregados) ?></span>
                <span class="cifra__nombre">Ya entregados</span>
            </div>
        </div>

        <div class="tabla__envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Ingreso</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Vehículo</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehiculos as $fila): ?>
                        <tr>
                            <td class="mono"><?= e($fila['folio']) ?></td>
                            <td class="celda--tenue"><?= e(fecha($fila['creado_en'])) ?></td>
                            <td class="celda--fuerte">
                                <?= e($fila['cliente_nombre']) ?>
                                <span class="celda__nota"><?= e($fila['cliente_telefono']) ?></span>
                            </td>
                            <td>
                                <?= e($fila['marca'] . ' ' . $fila['modelo']) ?><?= $fila['anio'] !== null ? ' ' . e($fila['anio']) : '' ?>
                                <?php if ($fila['placas'] !== null): ?>
                                    <span class="celda__nota"><?= e($fila['placas']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="etiqueta etiqueta--area<?= e($fila['area_id']) ?>"><?= e($fila['area_nombre']) ?></span>
                            </td>
                            <td>
                                <?php if ($fila['asignado_nombre'] !== null): ?>
                                    <?= e($fila['asignado_nombre']) ?>
                                <?php else: ?>
                                    <span class="celda--tenue">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
