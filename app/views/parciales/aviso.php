<?php

if (($aviso ?? null) === null) {
    return;
}

$textoAviso = e($aviso['mensaje']);

if (isset($aviso['destacado'])) {
    $textoAviso = sprintf($textoAviso, '<strong>' . e($aviso['destacado']) . '</strong>');
}
?>
<div class="aviso aviso--<?= e($aviso['tipo']) ?>" role="alert">
    <p class="aviso__mensaje"><?= $textoAviso ?></p>
</div>
