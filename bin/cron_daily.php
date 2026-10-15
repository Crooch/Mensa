<?php

declare(strict_types=1);

// Scheduler automatico: da eseguire con PHP CLI, tutti i giorni ogni 5 minuti.
// Ogni plesso viene elaborato una sola volta *dopo il proprio cutoff*.
// Installazione/abilitazione del cron: docs/RILASCIO_JOB_2026-10.md
//
// NOTA SICUREZZA: il deploy del codice NON abilita lo scheduler.
// Impostare app.scheduler_enabled === true ESPLICITAMENTE nel file locale
// config/config.local.php, dopo collaudo, calendario e autorizzazione.
// Il comando --check non interroga o modifica il database.
//
// Esempio crontab (utente mensa):
// */5 * * * * /usr/bin/php /srv/www/mensa/app/bin/cron_daily.php >> /srv/www/mensa/app/storage/cron.log 2>&1

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(2);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Config;

if (($argv[1] ?? '') === '--check' && count($argv) === 2) {
    echo 'Scheduler: ',
        Config::get('app.scheduler_enabled', false) === true ? 'ABILITATO' : 'DISABILITATO',
        PHP_EOL;
    echo 'Fuso applicativo: ', date_default_timezone_get(), PHP_EOL;
    echo 'Data odierna: ', date('Y-m-d'), PHP_EOL;
    echo 'DB configurato: ', (string) Config::get('db.database'), PHP_EOL;
    echo "Nessun job o accesso al database e' stato eseguito.\n";
    exit(0);
}

// Non consentire argomenti, inclusa una data del passato: la pianificazione
// automatica deve riguardare unicamente il giorno corrente.
if (count($argv) !== 1) {
    fwrite(STDERR, "Argomenti non consentiti: usare --check per la sola diagnostica.\n");
    exit(2);
}

if (Config::get('app.scheduler_enabled', false) !== true) {
    fwrite(STDERR, "Scheduler disabilitato da config locale: nessun addebito eseguito.\n");
    exit(2);
}

try {
    $container = new App\Core\Container();
    $risultati = $container->job()->eseguiScheduler();
    $errore = false;

    echo date('c'), ' scheduler mensa', PHP_EOL;
    foreach ($risultati as $riga) {
        echo '  - ', $riga['plesso'] ?? '?', ': ', $riga['stato'];
        if (isset($riga['consumi'])) {
            echo sprintf(
                ' (presenti %d, assenti %d, consumi %d, solleciti inviati %d)',
                $riga['presenze']['presente'] ?? 0,
                $riga['presenze']['assente'] ?? 0,
                $riga['consumi'],
                $riga['solleciti']['inviati'] ?? 0
            );
        }
        echo PHP_EOL;

        // Il cron deve rendere riconoscibili le chiusure da riconciliare.
        // Non le ritenta automaticamente: la service restituisce
        // errore_precedente, preservando i movimenti gia' eseguiti.
        if (in_array($riga['stato'] ?? '', ['errore', 'errore_precedente'], true)) {
            $errore = true;
        }
    }
    exit($errore ? 1 : 0);
} catch (\Throwable $e) {
    error_log('[mensa cron] Eccezione nello scheduler: ' . $e->getMessage());
    exit(1);
}
