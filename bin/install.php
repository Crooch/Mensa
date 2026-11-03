<?php

declare(strict_types=1);

/**
 * Installazione dello schema database.
 *
 * Uso:  php bin/install.php
 * Crea il database (se l'utente ha i permessi) ed esegue le migration in
 * database/migrations in ordine alfabetico. Solo per database nuovo e vuoto:
 * alcune migrazioni includono inizializzazioni specifiche di Caltavuturo.
 * NON usare come scorciatoia per aggiornare il database comunale esistente:
 * seguire la procedura documentata, su copia isolata e con backup verificato.
 */

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Config;

$dbName = (string) Config::get('db.database');
$dsnServer = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::get('db.host'), (int) Config::get('db.port', 3306));

try {
    $pdo = new PDO($dsnServer, Config::get('db.username'), Config::get('db.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `{$dbName}` disponibile.\n";
} catch (PDOException $e) {
    // Su molti hosting condivisi il database esiste gia' e non e' creabile da codice.
    echo "Creazione database saltata ({$e->getMessage()}). Si assume che esista già.\n";
}

$pdo = App\Core\Database::pdo();
$cartella = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$file = glob($cartella . DIRECTORY_SEPARATOR . '*.sql');
sort($file);

foreach ($file as $percorso) {
    echo 'Eseguo ', basename($percorso), ' ... ';
    $pdo->exec((string) file_get_contents($percorso));
    echo "ok\n";
}

echo "Schema installato. ATTENZIONE: bin/seed.php cancella i dati delle tabelle; usarlo soltanto su un database dimostrativo isolato.\n";
