<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <link rel="stylesheet" href="<?= e(css('acceso')) ?>">
</head>
<body class="auth">

    <div class="auth__fondo" style="background-image: url('<?= e(url('/img/fondo-login.jpg')) ?>')" aria-hidden="true"></div>

    <main class="auth__centro">
        <?= $contenido ?>
    </main>

</body>
</html>
