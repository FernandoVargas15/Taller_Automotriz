<?php
?>

<section class="tarjeta-acceso" aria-labelledby="titulo-consulta">

    <h1 class="tarjeta-acceso__titulo" id="titulo-consulta">Consulta tu auto</h1>
    <p class="tarjeta-acceso__nota">Escribe el folio y el teléfono de tu comprobante para ver el avance.</p>

    <?php if ($aviso !== null): ?>
        <div class="tarjeta-acceso__aviso tarjeta-acceso__aviso--<?= e($aviso['tipo']) ?>" role="alert">
            <?= e($aviso['mensaje']) ?>
        </div>
    <?php endif; ?>

    <form class="formulario-acceso" method="post" action="<?= e(url('/consulta')) ?>">
        <input type="hidden" name="_token" value="<?= e(Sesion::tokenCsrf()) ?>">

        <!-- Folio -->
        <div class="campo-acceso">
            <label class="campo-acceso__etiqueta" for="folio">Folio de tu comprobante</label>
            <div class="campo-acceso__linea">
                <svg class="campo-acceso__icono" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6M8 13h8M8 17h5"/>
                </svg>
                <input
                    class="campo-acceso__control"
                    type="text"
                    id="folio"
                    name="folio"
                    value="<?= e($viejo['folio'] ?? '') ?>"
                    placeholder="TA-00001"
                    style="text-transform: uppercase"
                    autocomplete="off"
                    autofocus
                    required>
            </div>
        </div>

        <!-- Teléfono -->
        <div class="campo-acceso">
            <label class="campo-acceso__etiqueta" for="telefono">Teléfono que dejaste</label>
            <div class="campo-acceso__linea">
                <svg class="campo-acceso__icono" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .3 1.9.6 2.8a2 2 0 0 1-.4 2.1L8 9.8a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.4c.9.3 1.8.5 2.8.6a2 2 0 0 1 1.7 2z"/>
                </svg>
                <input
                    class="campo-acceso__control"
                    type="tel"
                    id="telefono"
                    name="telefono"
                    value="<?= e($viejo['telefono'] ?? '') ?>"
                    placeholder="55 1234 5678"
                    autocomplete="tel"
                    required>
            </div>
        </div>

        <button class="boton-acceso" type="submit">
            Ver mi auto
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>

    </form>
</section>
