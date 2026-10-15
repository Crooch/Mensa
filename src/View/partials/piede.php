</main>
<footer class="piede">
    <div class="contenitore">
        <span>Comune di Caltavuturo (PA) &middot; Servizio mensa scolastica</span>
        <span>Servizio sperimentale &middot; PagoPA e SPID/CIE previsti come evoluzioni future</span>
    </div>
</footer>
<div id="avvisi" class="avvisi" aria-live="polite"></div>
<?php
// Al rilascio il browser deve scaricare il JavaScript nuovo: evita script
// vecchi dalla cache dopo le modifiche ai report e alle date.
$apiJsPath = APP_ROOT . '/public/assets/js/api.js';
$paginaJsPath = APP_ROOT . '/public/assets/js/' . $scriptPagina . '.js';
$apiJsVersione = is_file($apiJsPath) ? (int) filemtime($apiJsPath) : 0;
$paginaJsVersione = is_file($paginaJsPath) ? (int) filemtime($paginaJsPath) : 0;
?>
<script src="<?= $basePath ?>/assets/js/api.js?v=<?= $apiJsVersione ?>"></script>
<?php if (($scriptPagina ?? '') === 'catering'):
    $fotoScript = APP_ROOT . '/public/assets/js/menu_foto.js';
    $fotoVersione = is_file($fotoScript) ? (int) filemtime($fotoScript) : 0;
?>
<script src="<?= $basePath ?>/assets/js/menu_foto.js?v=<?= $fotoVersione ?>"></script>
<?php endif; ?>
<script src="<?= $basePath ?>/assets/js/<?= htmlspecialchars($scriptPagina) ?>.js?v=<?= $paginaJsVersione ?>"></script>
<?php if (in_array($ruoloPagina ?? '', ['genitore', 'operatore', 'admin'], true)):
    $tabelleScript = APP_ROOT . '/public/assets/js/tabelle_compatte.js';
    $tabelleVersione = is_file($tabelleScript) ? (int) filemtime($tabelleScript) : 0;
?>
<script src="<?= $basePath ?>/assets/js/tabelle_compatte.js?v=<?= $tabelleVersione ?>"></script>
<?php endif; ?>
<?php if (!empty($scriptExtraPagina)):
    $percorsoExtra = APP_ROOT . '/public/assets/js/' . $scriptExtraPagina . '.js';
    $versioneExtra = is_file($percorsoExtra) ? (int) filemtime($percorsoExtra) : 0;
?>
<script src="<?= $basePath ?>/assets/js/<?= htmlspecialchars($scriptExtraPagina) ?>.js?v=<?= $versioneExtra ?>"></script>
<?php endif; ?>
</body>
</html>
