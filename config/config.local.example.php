<?php
/** Copiare in config.local.php e valorizzare con i dati dell'hosting. */
return [
    'app' => [
        'debug'         => false,
        'base_path'     => '',
        // Abilitare solo dopo verifica dati, calendario, cutoff, cron e Comune.
        'scheduler_enabled' => false,
        // Necessaria solo se il cron invoca /scheduler via HTTP.
        'scheduler_key' => 'una-chiave-lunga-e-casuale',
    ],
    'db' => [
        'host'     => 'localhost',
        'database' => 'nome_db',
        'username' => 'utente_db',
        'password' => 'password_db',
    ],
];
