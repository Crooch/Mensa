<?php
/**
 * Configurazione applicativa.
 *
 * Su hosting condiviso: copiare config.local.example.php in config.local.php e
 * valorizzare i parametri del database. config.local.php sovrascrive i valori
 * qui definiti (merge superficiale) e non va versionato.
 */

$config = [
    'app' => [
        'nome'      => 'Mensa Scolastica - Comune di Caltavuturo',
        'base_path' => '',          // sottocartella di pubblicazione, es. '/mensa'
        'debug'     => false,
        'timezone'  => 'Europe/Rome',
        // Permette un cutover contabile a T0 senza fermare altri siti della VPS.
        // Quando true blocca ogni richiesta web all'app Mensa (503).
        'maintenance_mode' => false,
        // Lo scheduler e' disattivato dopo ogni nuovo deploy fino ad attivazione
        // esplicita nell'override locale (config.local.php, mai versionato).
        // Vale per bin/cron_daily.php e /scheduler?key=...
        'scheduler_enabled' => false,
        // Prima giornata a saldo zero: nessun invio massivo di solleciti.
        // Attivare separatamente solo dopo conferma con il Comune.
        'solleciti_automatici_enabled' => false,
        // Senza chiave locale casuale l'endpoint HTTP non e' accessibile.
        'scheduler_key' => '',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'mensa_caltavuturo',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'auth' => [
        'token_durata_ore' => 12,
    ],
    'storage' => [
        // Percorso assoluto calcolato a runtime con DIRECTORY_SEPARATOR (portabile Linux/Windows)
        'menu_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'menu',
        'max_upload_bytes' => 4 * 1024 * 1024,
    ],
];

$locale = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($locale)) {
    $override = require $locale;
    foreach ($override as $sezione => $valori) {
        $config[$sezione] = array_merge($config[$sezione] ?? [], $valori);
    }
}

return $config;
