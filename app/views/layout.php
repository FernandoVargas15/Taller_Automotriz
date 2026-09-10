<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <header class="encabezado">
        <div class="contenedor encabezado__interior">
            <div>
                <h1 class="encabezado__titulo"><?= e($titulo) ?></h1>
            </div>
        </div>
    </header>

    <main class="contenedor">
        <?= $contenido ?>
    </main>

</body>
</html>
