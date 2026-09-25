<?php

// formulario para agregar un reporte (texto + foto opcional) a la bitácora.

?>
<section class="tarjeta" aria-labelledby="titulo-nuevo">

    <header class="tarjeta__cabecera">
        <h2 class="tarjeta__titulo" id="titulo-nuevo">Agregar reporte</h2>
        <p class="tarjeta__nota">Qué se hizo o qué se encontró. Queda registrado en <?= e($vehiculo['area_nombre']) ?>.</p>
    </header>

    <?php if (Vehiculo::estaEntregado($vehiculo)): ?>

        <p class="vacio"><?= e(Vehiculo::MENSAJE_ENTREGADO) ?></p>

    <?php else: ?>

        <form method="post" action="<?= e($accion) ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= Bitacora::FOTO_MAX_BYTES ?>">

            <div class="bloque">
                <div class="campos">

                    <div class="campo">
                        <label class="campo__etiqueta" for="descripcion">
                            Reporte <span class="campo__requerido">*</span>
                        </label>
                        <textarea class="campo__control <?= isset($errores['descripcion']) ? 'es-invalido' : '' ?>"
                                  id="descripcion" name="descripcion" rows="4"
                                  maxlength="<?= Bitacora::DESCRIPCION_MAX ?>"
                                  placeholder="Se desmontó la defensa trasera y se detectó óxido en el soporte." required><?= e($viejo['descripcion'] ?? '') ?></textarea>
                        <?php if (isset($errores['descripcion'])): ?>
                            <p class="campo__error"><?= e($errores['descripcion']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="campo">
                        <label class="campo__etiqueta" for="foto">Foto</label>
                        <input class="campo__control campo__control--archivo <?= isset($errores['foto']) ? 'es-invalido' : '' ?>"
                               type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                        <?php if (isset($errores['foto'])): ?>
                            <p class="campo__error"><?= e($errores['foto']) ?></p>
                        <?php else: ?>
                            <p class="campo__ayuda">JPG, PNG o WebP, máximo 5 MB. Se necesita al menos una foto para marcar el auto como Terminado.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <div class="bloque bloque--acciones">
                <button class="boton boton--primario" type="submit">Agregar a la bitácora</button>
            </div>

        </form>

    <?php endif; ?>
</section>
