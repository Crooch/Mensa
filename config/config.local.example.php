<?php
/** Copiare in config.local.php e valorizzare con i dati dell'hosting. */
return [
    'app' => [
        'debug'         => false,
        'base_path'     => '',
        'scheduler_key' => 'una-chiave-lunga-e-casuale',
    ],
    'db' => [
        'host'     => 'localhost',
        'database' => 'nome_db',
        'username' => 'utente_db',
        'password' => 'password_db',
    ],
];
