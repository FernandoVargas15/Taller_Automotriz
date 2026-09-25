<?php

// VISTA (solo administrador): personal del taller con sus acciones
 
$token = Sesion::tokenCsrf();
?>

<div class="pagina__cabecera">
    <div>
        <h1 class="pagina__titulo">Gestión de usuarios</h1>
        <p class="pagina__nota">
            Mecánicos y administradores con acceso al sistema.
            Cada cuenta solo puede estar abierta en un dispositivo a la vez:
            si alguien cerró el navegador sin salir, libera su sesión desde aquí.
        </p>
    </div>
    <div class="pagina__acciones">
        <a class="boton boton--primario" href="<?= e(url('/admin/usuarios/nuevo')) ?>">+ Agregar nuevo empleado</a>
    </div>
</div>

<?php require RUTA_APP . '/views/parciales/aviso.php'; ?>

<section class="tarjeta" aria-labelledby="titulo-lista">

    <header class="tarjeta__cabecera tarjeta__cabecera--fila">
        <h2 class="tarjeta__titulo" id="titulo-lista">Personal registrado</h2>
        <span class="contador"><?= e($total) ?> <?= $total === 1 ? 'registro' : 'registros' ?></span>
    </header>

    <?php if ($usuarios === []): ?>

        <p class="vacio">Todavía no hay usuarios registrados.</p>

    <?php else: ?>

        <div class="tabla__envoltorio">
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Sesión</th>
                        <th scope="col">Registrado</th>
                        <th scope="col" class="celda--acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $fila): ?>
                        <?php
                        $esPropio = $fila['id'] === $usuarioActual['id'];
                        // REGLA DE ACCESO ÚNICO: como mucho una sesión por cuenta, o ninguna
                        $sesion = $sesiones[$fila['id']] ?? null;
                        ?>
                        <tr class="<?= $fila['activo'] ? '' : 'fila--inactiva' ?>">
                            <td class="celda--fuerte">
                                <?= e($fila['nombre']) ?>
                                <?php if ($esPropio): ?>
                                    <span class="celda__nota">(tú)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="etiqueta etiqueta--rol<?= e($fila['rol_id']) ?>">
                                    <?= e($fila['rol_nombre']) ?>
                                </span>
                            </td>
                            <td><?= e($fila['correo']) ?></td>
                            <td>
                                <span class="estado <?= $fila['activo'] ? 'estado--activo' : 'estado--inactivo' ?>">
                                    <?= $fila['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sesion !== null): ?>
                                    <span class="estado estado--activo"
                                          title="Entró el <?= e(fecha($sesion['iniciada_en'])) ?>. Última actividad: <?= e(fecha($sesion['ultima_actividad'])) ?>">
                                        En línea
                                    </span>
                                    <span class="celda__nota">desde <?= e(fecha($sesion['iniciada_en'], 'H:i')) ?></span>
                                <?php else: ?>
                                    <span class="celda--tenue">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="celda--tenue"><?= e(fecha($fila['creado_en'])) ?></td>
                            <td class="celda--acciones">
                                <div class="acciones">

                                    <a class="boton boton--pequeno boton--suave"
                                       href="<?= e(url('/admin/usuarios/editar?id=' . $fila['id'])) ?>">Editar</a>

                                    <?php if (!$esPropio): ?>
                                        <?php if ($sesion !== null): ?>
                                            <form method="post" action="<?= e(url('/admin/usuarios/sesion')) ?>"
                                                  onsubmit="return confirm('¿Cerrar la sesión de <?= e(addslashes($fila['nombre'])) ?>? Podrá volver a entrar desde cualquier dispositivo.')">
                                                <input type="hidden" name="_token" value="<?= e($token) ?>">
                                                <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                                                <button class="boton boton--pequeno boton--suave" type="submit">Cerrar sesión</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" action="<?= e(url('/admin/usuarios/estado')) ?>">
                                            <input type="hidden" name="_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                                            <button class="boton boton--pequeno boton--suave" type="submit">
                                                <?= $fila['activo'] ? 'Desactivar' : 'Reactivar' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?= e(url('/admin/usuarios/eliminar')) ?>"
                                              onsubmit="return confirm('¿Eliminar definitivamente a <?= e(addslashes($fila['nombre'])) ?>? Sus autos quedarán sin asignar.')">
                                            <input type="hidden" name="_token" value="<?= e($token) ?>">
                                            <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                                            <button class="boton boton--pequeno boton--peligro" type="submit">Eliminar</button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>
