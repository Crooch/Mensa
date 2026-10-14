<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi &middot; <?= htmlspecialchars($titoloApp) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-italia@2.13.2/dist/css/bootstrap-italia.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/app.css?v=<?= is_file(APP_ROOT . '/public/assets/css/app.css') ? (int) filemtime(APP_ROOT . '/public/assets/css/app.css') : 0 ?>">
</head>
<body class="pagina-accesso" data-base="<?= $basePath ?>">
<div class="accesso">
    <div class="accesso__marchio">
        <span class="stemma stemma--grande" aria-hidden="true">CV</span>
        <h1>Mensa Scolastica</h1>
        <p>Comune di Caltavuturo &middot; Provincia di Palermo</p>
        <ul>
            <li>Controlla quanti buoni mensa hai a disposizione</li>
            <li>Comunica quando tuo figlio non mangerà a scuola</li>
            <li>Consulta le comunicazioni e i pasti previsti</li>
        </ul>
    </div>
    <div class="accesso__modulo">
        <h2>Accedi al servizio</h2>
        <p class="accesso__nota">Inserisci il nome utente e la password che ti ha dato il Comune. Se non li hai, rivolgiti agli uffici comunali.</p>
        <form id="form-accesso">
            <label for="username">Nome utente</label>
            <input type="text" id="username" name="username" autocomplete="username" required data-testid="login-username-input">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required data-testid="login-password-input">
            <button type="submit" class="btn btn-primary w-100" data-testid="login-submit-button">Accedi</button>
            <p class="errore-accesso" id="errore-accesso" role="alert" tabindex="-1" data-testid="login-error" hidden></p>
        </form>
    </div>
</div>
<script src="<?= $basePath ?>/assets/js/api.js?v=<?= is_file(APP_ROOT . '/public/assets/js/api.js') ? (int) filemtime(APP_ROOT . '/public/assets/js/api.js') : 0 ?>"></script>
<script src="<?= $basePath ?>/assets/js/login.js?v=<?= is_file(APP_ROOT . '/public/assets/js/login.js') ? (int) filemtime(APP_ROOT . '/public/assets/js/login.js') : 0 ?>"></script>
</body>
</html>
