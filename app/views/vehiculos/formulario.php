<?php

// VISTA (administrador y asesor de servicio): ingreso o corrección de un vehículo

$esAlta      = $vehiculo === null;
$esReingreso = $esAlta && $origen !== null;
$accion      = $esAlta ? url('/vehiculos/nuevo') : url('/vehiculos/editar?id=' . $vehiculo['id']);

/** Clase del control según si el campo trae error */
$control = static fn (string $campo): string =>
    'campo__control' . (isset($errores[$campo]) ? ' es-invalido' : '');
?>

<?php // Cabecera: qué se está haciendo y cómo volver ?>
<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">
            <?php if ($esReingreso): ?>
                Reingresar <?= e($origen['marca'] . ' ' . $origen['modelo']) ?>
            <?php elseif ($esAlta): ?>
                Registrar vehículo
            <?php else: ?>
                Editar <?= e($vehiculo['marca'] . ' ' . $vehiculo['modelo']) ?>
            <?php endif; ?>
        </h1>
        <p class="pagina__nota">
            <?php if ($esReingreso): ?>
                Ya vino antes con el folio <?= e($origen['folio']) ?>. Los datos vienen llenos; revísalos y completa el resto. Se generará un folio nuevo.
            <?php elseif ($esAlta): ?>
                El folio se genera solo; con él y su teléfono el cliente podrá consultar el avance.
            <?php else: ?>
                Corrige cualquier dato capturado mal: color, modelo, área o persona asignada.
            <?php endif; ?>
        </p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--suave" href="<?= e(url('/vehiculos')) ?>">← Volver a vehículos</a>
        <?php if (!$esAlta): ?>
            <a class="boton boton--suave" href="<?= e(url('/vehiculos/bitacora?id=' . $vehiculo['id'])) ?>">Ver bitácora</a>
        <?php endif; ?>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-formulario">

    <header class="tarjeta__cabecera">
        <h2 class="tarjeta__titulo" id="titulo-formulario">Datos del ingreso</h2>
    </header>

    <form method="post" action="<?= e($accion) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Sesion::tokenCsrf()) ?>">

        <?php // Bloque 1: a quién pertenece el auto ?>
        <div class="bloque">
            <h3 class="bloque__titulo">Cliente</h3>
            <p class="bloque__nota">Con el folio y este teléfono consultará el avance de su auto.</p>

            <div class="campos">

                <div class="campo">
                    <label class="campo__etiqueta" for="cliente_nombre">
                        Nombre <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('cliente_nombre') ?>" type="text" id="cliente_nombre" name="cliente_nombre"
                           value="<?= e($viejo['cliente_nombre'] ?? '') ?>" maxlength="<?= Vehiculo::CLIENTE_MAX ?>"
                           placeholder="María López" autocomplete="off" required>
                    <?php if (isset($errores['cliente_nombre'])): ?>
                        <p class="campo__error"><?= e($errores['cliente_nombre']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="cliente_telefono">
                        Teléfono <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('cliente_telefono') ?>" type="tel" id="cliente_telefono" name="cliente_telefono"
                           value="<?= e($viejo['cliente_telefono'] ?? '') ?>" maxlength="<?= Vehiculo::TELEFONO_MAX ?>"
                           placeholder="55 1234 5678" autocomplete="off" required>
                    <?php if (isset($errores['cliente_telefono'])): ?>
                        <p class="campo__error"><?= e($errores['cliente_telefono']) ?></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php // Bloque 2: cómo se identifica el auto ?>
        <div class="bloque">
            <h3 class="bloque__titulo">Vehículo</h3>
            <p class="bloque__nota">Marca y modelo son obligatorios; el resto ayuda a reconocerlo.</p>

            <div class="campos">

                <div class="campo">
                    <label class="campo__etiqueta" for="marca">
                        Marca <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('marca') ?>" type="text" id="marca" name="marca"
                           value="<?= e($viejo['marca'] ?? '') ?>" maxlength="<?= Vehiculo::MARCA_MAX ?>"
                           placeholder="Ford" required>
                    <?php if (isset($errores['marca'])): ?>
                        <p class="campo__error"><?= e($errores['marca']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="modelo">
                        Modelo <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('modelo') ?>" type="text" id="modelo" name="modelo"
                           value="<?= e($viejo['modelo'] ?? '') ?>" maxlength="<?= Vehiculo::MODELO_MAX ?>"
                           placeholder="Mustang" required>
                    <?php if (isset($errores['modelo'])): ?>
                        <p class="campo__error"><?= e($errores['modelo']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="anio">Año</label>
                    <input class="<?= $control('anio') ?>" type="number" id="anio" name="anio"
                           value="<?= e($viejo['anio'] ?? '') ?>" min="<?= Vehiculo::ANIO_MIN ?>" max="<?= date('Y') + 1 ?>"
                           placeholder="1965" inputmode="numeric">
                    <?php if (isset($errores['anio'])): ?>
                        <p class="campo__error"><?= e($errores['anio']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="color">Color</label>
                    <input class="<?= $control('color') ?>" type="text" id="color" name="color"
                           value="<?= e($viejo['color'] ?? '') ?>" maxlength="<?= Vehiculo::COLOR_MAX ?>"
                           placeholder="Rojo">
                    <?php if (isset($errores['color'])): ?>
                        <p class="campo__error"><?= e($errores['color']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="placas">Placas</label>
                    <input class="<?= $control('placas') ?>" type="text" id="placas" name="placas"
                           value="<?= e($viejo['placas'] ?? '') ?>" maxlength="<?= Vehiculo::PLACAS_MAX ?>"
                           placeholder="ABC-123-D" style="text-transform: uppercase">
                    <?php if (isset($errores['placas'])): ?>
                        <p class="campo__error"><?= e($errores['placas']) ?></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php // Bloque 3: dónde está el auto y quién lo trabaja ?>
        <div class="bloque">
            <h3 class="bloque__titulo">Trabajo</h3>
            <p class="bloque__nota">El área se puede ir cambiando después desde la bitácora.</p>

            <div class="campos">

                <div class="campo">
                    <label class="campo__etiqueta" for="area_id">
                        Área actual <span class="campo__requerido">*</span>
                    </label>
                    <select class="<?= $control('area_id') ?>" id="area_id" name="area_id" required>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?= e($area['id']) ?>"
                                <?= (int) ($viejo['area_id'] ?? Area::POR_DEFECTO) === (int) $area['id'] ? 'selected' : '' ?>>
                                <?= e($area['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['area_id'])): ?>
                        <p class="campo__error"><?= e($errores['area_id']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="asignado_a">Asignado a</label>
                    <select class="<?= $control('asignado_a') ?>" id="asignado_a" name="asignado_a">
                        <option value="">Sin asignar</option>
                        <?php foreach ($asignables as $persona): ?>
                            <option value="<?= e($persona['id']) ?>"
                                <?= ($viejo['asignado_a'] ?? '') === $persona['id'] ? 'selected' : '' ?>>
                                <?= e($persona['nombre']) ?> &middot; <?= e($persona['rol_nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['asignado_a'])): ?>
                        <p class="campo__error"><?= e($errores['asignado_a']) ?></p>
                    <?php elseif ($asignables === []): ?>
                        <p class="campo__ayuda">Aún no hay mecánicos ni hojalateros activos para asignar.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php // Pie: guardar o cancelar ?>
        <div class="bloque bloque--acciones">
            <button class="boton boton--primario" type="submit">
                <?= $esReingreso ? 'Reingresar vehículo' : ($esAlta ? 'Registrar vehículo' : 'Guardar cambios') ?>
            </button>
            <a class="boton boton--suave" href="<?= e(url('/vehiculos')) ?>">Cancelar</a>
        </div>

    </form>
</section>
