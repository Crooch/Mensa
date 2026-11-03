<?php /** @var string $titoloApp, $basePath */ ?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titoloPagina ?? $titoloApp) ?></title>
    <!-- Design system della PA italiana. Se la CDN non e' raggiungibile, app.css
         replica palette istituzionale e componenti principali. -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.13.2/dist/css/bootstrap-italia.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/app.css?v=<?= is_file(APP_ROOT . '/public/assets/css/app.css') ? (int) filemtime(APP_ROOT . '/public/assets/css/app.css') : 0 ?>">
</head>
<body data-ruolo="<?= htmlspecialchars($ruoloPagina ?? '') ?>" data-base="<?= $basePath ?>">
<header class="intestazione-comune">
    <div class="contenitore">
        <div class="intestazione-comune__titolo">
            <span class="stemma" aria-hidden="true">CV</span>
            <div>
                <strong>Comune di Caltavuturo</strong>
                <span>Servizio di Mensa Scolastica</span>
            </div>
        </div>
        <nav class="intestazione-comune__azioni">
            <span id="utente-corrente" data-testid="utente-corrente"></span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-logout" data-testid="logout-button">Esci</button>
        </nav>
    </div>
</header>
<main class="contenitore contenuto">
