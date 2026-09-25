<?php

$contacto = Config::obtener('APP_CONTACTO', 'admin@gmail.com');
?>

<section class="tarjeta-acceso" aria-labelledby="titulo-login">

    <h1 class="tarjeta-acceso__titulo" id="titulo-login">Iniciar sesión</h1>
    <p class="tarjeta-acceso__nota">Acceso al panel del personal de <?= e($titulo) ?>.</p>

    <?php if ($aviso !== null): ?>
        <div class="tarjeta-acceso__aviso tarjeta-acceso__aviso--<?= e($aviso['tipo']) ?>" role="alert">
            <?= e($aviso['mensaje']) ?>
        </div>
    <?php endif; ?>

    <form class="formulario-acceso" method="post" action="<?= e(url('/login')) ?>">
        <input type="hidden" name="_token" value="<?= e(Sesion::tokenCsrf()) ?>">

        <!-- Correo -->
        <div class="campo-acceso">
            <label class="campo-acceso__etiqueta" for="correo">Correo electrónico</label>
            <div class="campo-acceso__linea <?= isset($errores['correo']) ? 'es-invalido' : '' ?>">
                <svg class="campo-acceso__icono" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>
                </svg>
                <input
                    class="campo-acceso__control"
                    type="email"
                    id="correo"
                    name="correo"
                    value="<?= e($viejo['correo'] ?? '') ?>"
                    placeholder="tu@correo.com"
                    autocomplete="username"
                    autofocus
                    required>
            </div>
            <?php if (isset($errores['correo'])): ?>
                <p class="campo-acceso__error"><?= e($errores['correo']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Contraseña -->
        <div class="campo-acceso">
            <label class="campo-acceso__etiqueta" for="contrasena">Contraseña</label>
            <div class="campo-acceso__linea <?= isset($errores['contrasena']) ? 'es-invalido' : '' ?>">
                <svg class="campo-acceso__icono" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <input
                    class="campo-acceso__control"
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    placeholder="••••••••••"
                    autocomplete="current-password"
                    required>
            </div>
            <?php if (isset($errores['contrasena'])): ?>
                <p class="campo-acceso__error"><?= e($errores['contrasena']) ?></p>
            <?php endif; ?>
        </div>

        <div class="formulario-acceso__opciones">
            <a class="enlace-acceso" href="mailto:<?= e($contacto) ?>?subject=Olvid%C3%A9%20mi%20contrase%C3%B1a">¿Olvidaste tu contraseña?</a>
        </div>

        <button class="boton-acceso" type="submit">
            Iniciar Sesión
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>

    </form>
</section>
