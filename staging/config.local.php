<?php
// Utilizzato SOLO dal container staging: i valori provengono dalle variabili Docker.
if (getenv('MENSA_STAGING_ONLY') !== '1' || getenv('MENSA_STAGING_DB_NAME') !== 'mensa_staging') {
    throw new RuntimeException('Non usare la configurazione di staging in produzione');
}
return [
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Rome',
        'scheduler_key' => 'staging_only_no_external_scheduler',
    ],
    'db' => [
        'host' => (string) getenv('MENSA_STAGING_DB_HOST'),
        'database' => 'mensa_staging',
        'username' => (string) getenv('MENSA_STAGING_DB_USER'),
        'password' => (string) getenv('MENSA_STAGING_DB_PASS'),
        'port' => 3306,
        'charset' => 'utf8mb4',
    ],
];
