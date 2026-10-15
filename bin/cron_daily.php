<?php

declare(strict_types=1);

// Job giornaliero da schedulare via cron (o Utility di pianificazione su Windows).
//
// Esempio crontab, ogni 5 minuti, TUTTI i giorni (anche weekend):
// le aperture straordinarie possono prevalere sulle chiusure del calendario.
// L'applicazione decide scuola per scuola se contabilizzare dopo il cutoff.
//   [*]/5 * * * * /usr/bin/php /percorso/app/bin/cron_daily.php >> /percorso/app/storage/cron.log 2>&1
// (sostituire [*] con l'asterisco)
//
// Se l'hosting non consente cron su CLI, usare l'URL:
//   https://dominio/scheduler?key=CHIAVE_CONFIGURATA

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

$container = new App\Core\Container();
$data = $argv[1] ?? null;

$risultati = $container->job()->eseguiScheduler($data);

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
}
