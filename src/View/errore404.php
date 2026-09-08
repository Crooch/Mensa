<?php
$titoloPagina = 'Pagina non trovata';
$ruoloPagina = '';
$scriptPagina = 'api';
require __DIR__ . '/partials/testa.php';
?>
<h1>Pagina non trovata</h1>
<p>La pagina richiesta non esiste. <a href="<?= $basePath ?>/login">Torna all'accesso</a>.</p>
<?php require __DIR__ . '/partials/piede.php'; ?>
