<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi &middot; <?= htmlspecialchars($titoloApp) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.13.2/dist/css/bootstrap-italia.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/app.css">
</head>
<body class="pagina-accesso" data-base="<?= $basePath ?>">
<div class="accesso">
    <div class="accesso__marchio">
        <span class="stemma stemma--grande" aria-hidden="true">CV</span>
        <h1>Mensa Scolastica</h1>
        <p>Comune di Caltavuturo &middot; Provincia di Palermo</p>
        <ul>
            <li>Buoni pasto digitali, senza bigliettini cartacei</li>
            <li>Presenze, assenze e diete sempre allineate con la scuola</li>
            <li>Il pasto non viene mai negato: il debito genera solo segnalazioni agli uffici</li>
        </ul>
    </div>
    <div class="accesso__modulo">
        <h2>Accedi al servizio</h2>
        <p class="accesso__nota">Le credenziali sono rilasciate dall'ufficio comunale. Non è prevista registrazione autonoma.</p>
        <form id="form-accesso" novalidate>
            <label for="username">Nome utente</label>
            <input type="text" id="username" name="username" autocomplete="username" required data-testid="login-username-input">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required data-testid="login-password-input">
            <button type="submit" class="btn btn-primary w-100" data-testid="login-submit-button">Accedi</button>
            <p class="errore-accesso" id="errore-accesso" data-testid="login-error" hidden></p>
        </form>
    </div>
</div>
<script src="<?= $basePath ?>/assets/js/api.js"></script>
<script src="<?= $basePath ?>/assets/js/login.js"></script>
</body>
</html>
