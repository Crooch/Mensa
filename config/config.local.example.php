<?php
/** Copiare in config.local.php e valorizzare con i dati dell'hosting. */
return [
    'app' => [
        'debug'         => false,
        'base_path'     => '',
        // Abilitare solo dopo verifica dati, calendario, cutoff, cron e Comune.
        'scheduler_enabled' => false,
        // Necessaria solo se il cron invoca /scheduler via HTTP.
        // Solo per l'endpoint HTTP: impostare una chiave casuale >= 32
        // caratteri, mai un segnaposto noto. Per PHP CLI lasciare vuoto.
        'scheduler_key' => '',
    ],
    'db' => [
        'host'     => 'localhost',
        'database' => 'nome_db',
        'username' => 'utente_db',
        'password' => 'password_db',
    ],
];
