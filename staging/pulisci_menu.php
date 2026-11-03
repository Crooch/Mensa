<?php

declare(strict_types=1);

/**
 * Pulizia mirata dei soli menu di prova dello staging locale.
 *
 * Anteprima: docker compose -f compose.staging.yml exec -T app php staging/pulisci_menu.php
 * Esecuzione: docker compose -f compose.staging.yml exec -T app php staging/pulisci_menu.php --apply --confirm=mensa_staging
 *
 * Non usare per la produzione. Non modifica utenti, bambini, presenze,
 * calendario, crediti, movimenti o feedback.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Disponibile soltanto da CLI');
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$argomenti = array_slice($argv, 1);
$ammessi = ['--preview', '--apply', '--confirm=mensa_staging'];
foreach ($argomenti as $argomento) {
    if (!in_array($argomento, $ammessi, true)) {
        fwrite(STDERR, "Argomento non riconosciuto. Usa --preview oppure --apply --confirm=mensa_staging.\n");
        exit(2);
    }
}
$esegui = in_array('--apply', $argomenti, true);
if ($esegui && !in_array('--confirm=mensa_staging', $argomenti, true)) {
    fwrite(STDERR, "Per applicare la pulizia serve --confirm=mensa_staging.\n");
    exit(2);
}

// Guardie esplicite: nessun accesso a mensa_db ne' a un host di produzione.
if (getenv('MENSA_STAGING_ONLY') !== '1'
    || getenv('MENSA_STAGING_DB_NAME') !== 'mensa_staging'
    || getenv('MENSA_STAGING_DB_HOST') !== 'database'
    || Config::get('db.database') !== 'mensa_staging'
    || Config::get('db.host') !== 'database') {
    fwrite(STDERR, "Operazione rifiutata: questo strumento funziona solo su mensa_staging nel Docker locale.\n");
    exit(2);
}

$cartellaAttesa = realpath(dirname(__DIR__) . '/storage/menu');
$cartellaFoto = realpath((string) Config::get('storage.menu_dir'));
if ($cartellaAttesa === false || $cartellaFoto !== $cartellaAttesa) {
    fwrite(STDERR, "Operazione rifiutata: cartella foto staging non verificata.\n");
    exit(2);
}

$pdo = Database::pdo();
$tabelle = [
    'menu_giornalieri',
    'menu_settimanali_assegnazioni',
    'menu_settimanali_revisioni',
    'menu_settimanali',
];
echo "Database di collaudo: mensa_staging\n";
echo "Record dei menu (tutte le righe in queste tabelle sono considerate di prova):\n";
foreach ($tabelle as $tabella) {
    $conteggio = (int) $pdo->query('SELECT COUNT(*) FROM ' . $tabella)->fetchColumn();
    echo ' - ' . $tabella . ': ' . $conteggio . "\n";
}
echo "Modelli presenti:\n";
foreach ($pdo->query('SELECT id, nome FROM menu_settimanali ORDER BY id DESC LIMIT 20')
    ->fetchAll(PDO::FETCH_ASSOC) as $modello) {
    echo ' - #' . $modello['id'] . ' ' . $modello['nome'] . "\n";
}

if (!$esegui) {
    echo "ANTEPRIMA: nulla e' stato cancellato. Per applicare usa --apply --confirm=mensa_staging.\n";
    exit(0);
}

// Si raccolgono solo le fotografie effettivamente referenziate dai record
// prima di cancellarli. La cancellazione dei file avviene solo dopo il commit.
$percorsiFoto = [];
foreach ($pdo->query('SELECT foto_path FROM menu_giornalieri WHERE foto_path IS NOT NULL')
    ->fetchAll(PDO::FETCH_COLUMN) as $pathFoto) {
    $percorsiFoto[(string) $pathFoto] = true;
}
foreach ($pdo->query('SELECT foto_json FROM menu_settimanali_revisioni WHERE foto_json IS NOT NULL')
    ->fetchAll(PDO::FETCH_COLUMN) as $json) {
    $giorni = json_decode((string) $json, true);
    if (!is_array($giorni)) continue;
    foreach ($giorni as $pathFoto) {
        if (is_string($pathFoto) && $pathFoto !== '') $percorsiFoto[$pathFoto] = true;
    }
}

$pdo->beginTransaction();
try {
    // Le assegnazioni e le revisioni hanno FK verso menu_settimanali.
    // Le altre tabelle dell'applicazione rimangono intatte.
    foreach ($tabelle as $tabella) {
        $pdo->exec('DELETE FROM ' . $tabella);
    }
    $pdo->commit();
} catch (Throwable $errore) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $errore;
}

$cancellati = 0;
$nonRimossi = 0;
foreach (array_keys($percorsiFoto) as $pathFoto) {
    // Mai accettare percorsi arbitrari dal database: solo nomi generati dall'app.
    if (!preg_match('~^menu/(?:settimane/[a-f0-9]{32}|[0-9]+_[0-9]+/[0-9]{4}-[0-9]{2}-[0-9]{2})\.(?:jpg|png|webp)$~', $pathFoto)) {
        $nonRimossi++;
        continue;
    }
    $file = realpath(dirname($cartellaFoto) . '/' . $pathFoto);
    if ($file === false) continue; // File gia' assente.
    if (!str_starts_with($file, $cartellaFoto . DIRECTORY_SEPARATOR) || !is_file($file)) {
        $nonRimossi++;
        continue;
    }
    if (@unlink($file)) {
        $cancellati++;
    } else {
        $nonRimossi++;
    }
}

echo "Pulizia completata: cancellati solo i menu di prova e le relative assegnazioni/revisioni.\n";
echo "Fotografie referenziate eliminate: {$cancellati}. Da verificare manualmente: {$nonRimossi}.\n";
echo "Anagrafiche, calendario, presenze, crediti e movimenti non sono stati toccati.\n";
