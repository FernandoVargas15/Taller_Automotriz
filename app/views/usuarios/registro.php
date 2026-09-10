<?php
/**
 * VISTA: barra de estado, formulario arriba y tabla de registrados
 */
?>

<?php require RUTA_APP . '/views/parciales/estado.php'; ?>

<?php // Mensaje de resultado ?>
<?php if ($aviso !== null): ?>
    <?php
        $textoAviso = e($aviso['mensaje']);

        if (isset($aviso['destacado'])) {
            $textoAviso = sprintf($textoAviso, '<strong>' . e($aviso['destacado']) . '</strong>');
        }
    ?>
    <div class="aviso aviso--<?= e($aviso['tipo']) ?>" role="alert">
        <p class="aviso__mensaje"><?= $textoAviso ?></p>
    </div>
<?php endif; ?>


<?php // Formulario de registro ?>
<section class="tarjeta" aria-labelledby="titulo-registro">

    <header class="tarjeta__cabecera">
        <h2 class="tarjeta__titulo" id="titulo-registro">Registrar usuario</h2
    </header>

    <form class="formulario" method="post" action="" novalidate>

        <div class="formulario__rejilla">

            <!-- Nombre -->
            <div class="campo">
                <label class="campo__etiqueta" for="nombre">
                    Nombre completo <span class="campo__requerido">*</span>
                </label>
                <input
                    class="campo__control <?= isset($errores['nombre']) ? 'es-invalido' : '' ?>"
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="<?= e($viejo['nombre'] ?? '') ?>"
                    maxlength="<?= Usuario::NOMBRE_MAX ?>"
                    placeholder="Fernando Vargas"
                    autocomplete="name"
                    required>
                <?php if (isset($errores['nombre'])): ?>
                    <p class="campo__error"><?= e($errores['nombre']) ?></p>
                <?php else: ?>
                    <p class="campo__ayuda">Mínimo <?= Usuario::NOMBRE_MIN ?> caracteres.</p>
                <?php endif; ?>
            </div>

            <!-- Correo -->
            <div class="campo">
                <label class="campo__etiqueta" for="correo">
                    Correo electrónico <span class="campo__requerido">*</span>
                </label>
                <input
                    class="campo__control <?= isset($errores['correo']) ? 'es-invalido' : '' ?>"
                    type="email"
                    id="correo"
                    name="correo"
                    value="<?= e($viejo['correo'] ?? '') ?>"
                    maxlength="<?= Usuario::CORREO_MAX ?>"
                    placeholder="correo@ejemplo.com"
                    autocomplete="email"
                    required>
                <?php if (isset($errores['correo'])): ?>
                    <p class="campo__error"><?= e($errores['correo']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Contraseña -->
            <div class="campo">
                <label class="campo__etiqueta" for="contrasena">
                    Contraseña <span class="campo__requerido">*</span>
                </label>
                <input
                    class="campo__control <?= isset($errores['contrasena']) ? 'es-invalido' : '' ?>"
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    minlength="<?= Usuario::PASSWORD_MIN ?>"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required>
                <?php if (isset($errores['contrasena'])): ?>
                    <p class="campo__error"><?= e($errores['contrasena']) ?></p>
                <?php else: ?>
                    <p class="campo__ayuda">Mínimo <?= Usuario::PASSWORD_MIN ?> caracteres.</p>
                <?php endif; ?>
            </div>

            <!-- Rol -->
            <div class="campo">
                <label class="campo__etiqueta" for="rol_id">
                    Rol <span class="campo__requerido">*</span>
                </label>
                <select
                    class="campo__control <?= isset($errores['rol_id']) ? 'es-invalido' : '' ?>"
                    id="rol_id"
                    name="rol_id"
                    required>
                    <?php foreach ($roles as $unRol): ?>
                        <?php
                            $seleccionado = (int) ($viejo['rol_id'] ?? Rol::POR_DEFECTO) === (int) $unRol['id'];
                        ?>
                        <option value="<?= e($unRol['id']) ?>" <?= $seleccionado ? 'selected' : '' ?>>
                            <?= e($unRol['id']) ?> &middot; <?= e($unRol['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['rol_id'])): ?>
                    <p class="campo__error"><?= e($errores['rol_id']) ?></p>
                <?php endif; ?>
            </div>

        </div>

        <div class="formulario__acciones">
            <button class="boton boton--primario" type="submit">Registrar usuario</button>
            <button class="boton boton--suave" type="reset">Limpiar</button>
        </div>

    </form>
</section>


<?php // Tabla de usuarios registrados ?>
<section class="tarjeta" aria-labelledby="titulo-lista">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <h2 class="tarjeta__titulo" id="titulo-lista">Usuarios registrados</h2>
        <span class="contador"><?= e($total) ?> <?= $total === 1 ? 'registro' : 'registros' ?></span>
    </header>

    <?php if ($usuarios === []): ?>

        <p class="vacio">
            Todavía no hay usuarios registrados.
        </p>

    <?php else: ?>

        <div class="tabla__envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Registrado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $fila): ?>
                        <tr>
                            <td class="celda--fuerte"><?= e($fila['nombre']) ?></td>
                            <td><?= e($fila['correo']) ?></td>
                            <td>
                                <span class="etiqueta etiqueta--rol<?= e($fila['rol_id']) ?>">
                                    <?= e($fila['rol_nombre']) ?>
                                </span>
                            </td>
                            <td class="celda--tenue">
                                <?= e(date('d/m/Y H:i', strtotime((string) $fila['creado_en']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
