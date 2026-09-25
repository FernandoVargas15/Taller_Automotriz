<?php

// VISTA (solo administrador): alta o edición de un usuario.

$esAlta   = $usuario === null;
$esPropio = !$esAlta && $usuario['id'] === $usuarioActual['id'];
$accion   = $esAlta ? url('/admin/usuarios/nuevo') : url('/admin/usuarios/editar?id=' . $usuario['id']);

/** Clase del control según si el campo trae error */
$control = static fn (string $campo): string =>
    'campo__control' . (isset($errores[$campo]) ? ' es-invalido' : '');
?>

<?php // Cabecera: qué se está haciendo y cómo volver ?>
<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo"><?= $esAlta ? 'Agregar nuevo empleado' : 'Editar a ' . e($usuario['nombre']) ?></h1>
        <p class="pagina__nota">
            <?= $esAlta
                ? 'Se le crea una cuenta con la que entrará al sistema. Los clientes no se registran aquí.'
                : 'Cambia su rol, corrige sus datos o asígnale una contraseña nueva.' ?>
        </p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--suave" href="<?= e(url('/admin/usuarios')) ?>">← Volver a usuarios</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-formulario">

    <header class="tarjeta__cabecera">
        <h2 class="tarjeta__titulo" id="titulo-formulario">Datos de la cuenta</h2>
    </header>

    <form method="post" action="<?= e($accion) ?>" novalidate>
        <input type="hidden" name="_token" value="<?= e(Sesion::tokenCsrf()) ?>">

        <div class="bloque">
            <div class="campos">

                <div class="campo">
                    <label class="campo__etiqueta" for="nombre">
                        Nombre completo <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('nombre') ?>" type="text" id="nombre" name="nombre"
                           value="<?= e($viejo['nombre'] ?? '') ?>" maxlength="<?= Usuario::NOMBRE_MAX ?>"
                           placeholder="Fernando Vargas" autocomplete="name" required>
                    <?php if (isset($errores['nombre'])): ?>
                        <p class="campo__error"><?= e($errores['nombre']) ?></p>
                    <?php else: ?>
                        <p class="campo__ayuda">Como aparecerá en la bitácora de cada auto.</p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="correo">
                        Correo electrónico <span class="campo__requerido">*</span>
                    </label>
                    <input class="<?= $control('correo') ?>" type="email" id="correo" name="correo"
                           value="<?= e($viejo['correo'] ?? '') ?>" maxlength="<?= Usuario::CORREO_MAX ?>"
                           placeholder="correo@ejemplo.com" autocomplete="email" required>
                    <?php if (isset($errores['correo'])): ?>
                        <p class="campo__error"><?= e($errores['correo']) ?></p>
                    <?php else: ?>
                        <p class="campo__ayuda">Con este correo inicia sesión.</p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="contrasena">
                        <?= $esAlta ? 'Contraseña' : 'Nueva contraseña' ?>
                        <?php if ($esAlta): ?><span class="campo__requerido">*</span><?php endif; ?>
                    </label>
                    <input class="<?= $control('contrasena') ?>" type="password" id="contrasena" name="contrasena"
                           minlength="<?= Usuario::PASSWORD_MIN ?>" placeholder="••••••••"
                           autocomplete="new-password" <?= $esAlta ? 'required' : '' ?>>
                    <?php if (isset($errores['contrasena'])): ?>
                        <p class="campo__error"><?= e($errores['contrasena']) ?></p>
                    <?php else: ?>
                        <p class="campo__ayuda">
                            <?= $esAlta ? '' : 'Déjala vacía para conservar la actual. ' ?>Mínimo <?= Usuario::PASSWORD_MIN ?> caracteres.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="campo">
                    <label class="campo__etiqueta" for="rol_id">
                        Rol <span class="campo__requerido">*</span>
                    </label>
                    <select class="<?= $control('rol_id') ?>" id="rol_id" name="rol_id"
                            <?= $esPropio ? 'disabled' : 'required' ?>>
                        <?php foreach ($roles as $unRol): ?>
                            <option value="<?= e($unRol['id']) ?>"
                                <?= (int) ($viejo['rol_id'] ?? Rol::POR_DEFECTO) === (int) $unRol['id'] ? 'selected' : '' ?>>
                                <?= e($unRol['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['rol_id'])): ?>
                        <p class="campo__error"><?= e($errores['rol_id']) ?></p>
                    <?php elseif ($esPropio): ?>
                        <p class="campo__ayuda">No puedes cambiar tu propio rol.</p>
                    <?php else: ?>
                        <p class="campo__ayuda">El mecánico y el hojalatero solo ven los autos que les asignes.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php // Pie: guardar o cancelar ?>
        <div class="bloque bloque--acciones">
            <button class="boton boton--primario" type="submit">
                <?= $esAlta ? 'Registrar usuario' : 'Guardar cambios' ?>
            </button>
            <a class="boton boton--suave" href="<?= e(url('/admin/usuarios')) ?>">Cancelar</a>
        </div>

    </form>
</section>
