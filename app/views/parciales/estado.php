<section class="estado" aria-label="Estado de los servicios">

    <!-- Servidor web -->
    <article class="estado__tarjeta <?= $estado['servidor']['conectado'] ? 'es-ok' : 'es-error' ?>">
        <header class="estado__cabecera">
            <span class="estado__punto" aria-hidden="true"></span>
            <h2 class="estado__titulo">Servidor web</h2>
            <span class="estado__insignia">
                <?= $estado['servidor']['conectado'] ? 'Conectado' : 'Sin conexión' ?>
            </span>
        </header>

        <dl class="estado__datos">
            <div>
                <dt>Software</dt>
                <dd><?= e($estado['servidor']['software']) ?></dd>
            </div>
            <div>
                <dt>Host</dt>
                <dd><?= e($estado['servidor']['host']) ?></dd>
            </div>
            <div>
                <dt>PHP</dt>
                <dd><?= e($estado['servidor']['php']) ?></dd>
            </div>
        </dl>
    </article>

    <!-- Base de datos -->
    <article class="estado__tarjeta <?= $estado['base']['conectado'] ? 'es-ok' : 'es-error' ?>">
        <header class="estado__cabecera">
            <span class="estado__punto" aria-hidden="true"></span>
            <h2 class="estado__titulo">Base de datos</h2>
            <span class="estado__insignia">
                <?= $estado['base']['conectado'] ? 'Conectada' : 'Sin conexión' ?>
            </span>
        </header>

        <dl class="estado__datos">
            <div>
                <dt>Motor</dt>
                <dd><?= e($estado['base']['motor']) ?> <?= e($estado['base']['version']) ?></dd>
            </div>
            <div>
                <dt>Base de datos</dt>
                <dd><?= e($estado['base']['nombre']) ?></dd>
            </div>
            <div>
                <dt>Host</dt>
                <dd><?= e($estado['base']['host']) ?>:<?= e($estado['base']['puerto']) ?></dd>
            </div>
            <div>
                <dt>Usuario</dt>
                <dd><?= e($estado['base']['usuario']) ?></dd>
            </div>
        </dl>
    </article>

</section>
