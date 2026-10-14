<?php

declare(strict_types=1);

/**
 * Dati totalmente sintetici per il browser di collaudo.
 * Guardie multiple: non puo' operare su mensa_db o altri DB di produzione.
 * Non esegue TRUNCATE, DELETE o sovrascritture: se trova utenti, non modifica nulla.
 */
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

if (getenv('MENSA_STAGING_ONLY') !== '1'
    || getenv('MENSA_STAGING_DB_NAME') !== 'mensa_staging'
    || Config::get('db.database') !== 'mensa_staging') {
    throw new RuntimeException('Seed riservato esclusivamente a mensa_staging');
}
$pdo = Database::pdo();
if ((int) $pdo->query('SELECT COUNT(*) FROM utenti')->fetchColumn() > 0) {
    echo "Staging: utenti gia presenti, seed non rieseguito.\n";
    exit(0);
}

$aggiungi = static function (string $tabella, array $dati) use ($pdo): int {
    $colonne = array_keys($dati);
    $nomi = implode(', ', $colonne);
    $segnaposto = implode(', ', array_fill(0, count($colonne), '?'));
    $query = $pdo->prepare("INSERT INTO {$tabella} ({$nomi}) VALUES ({$segnaposto})");
    $query->execute(array_values($dati));
    return (int) $pdo->lastInsertId();
};

$pdo->beginTransaction();
try {
    $infanzia = $aggiungi('plessi', [
        'nome' => "Scuola dell'Infanzia - Via Catania",
        'tariffa_base_pasto' => 2.50, 'fido_buoni' => 10, 'cutoff_orario' => '09:15:00',
    ]);
    $primaria = $aggiungi('plessi', [
        'nome' => 'Scuola Primaria - senza servizio',
        'tariffa_base_pasto' => 2.50, 'fido_buoni' => 10, 'cutoff_orario' => '09:15:00',
        'cancellato_logicamente' => 1,
    ]);
    $secondaria = $aggiungi('plessi', [
        'nome' => 'Scuola Secondaria di I grado',
        'tariffa_base_pasto' => 2.50, 'fido_buoni' => 10, 'cutoff_orario' => '09:15:00',
    ]);
    foreach ([
        [$infanzia, 1, 1, 1, 1, 1],
        [$secondaria, 1, 0, 0, 1, 0],
    ] as [$plesso, $lun, $mar, $mer, $gio, $ven]) {
        $aggiungi('calendari_servizio', [
            'plesso_id' => $plesso, 'data_inizio' => '2026-10-19',
            'lunedi' => $lun, 'martedi' => $mar, 'mercoledi' => $mer,
            'giovedi' => $gio, 'venerdi' => $ven, 'sabato' => 0, 'domenica' => 0,
        ]);
    }

    $standard = $aggiungi('tipologie_menu', ['nome' => 'Standard']);
    $senzaGlutine = $aggiungi('tipologie_menu', ['nome' => 'Senza glutine']);
    $classi = [];
    foreach (['A', 'B', 'C', 'D'] as $nome) {
        $classi[$nome] = $aggiungi('classi_sezioni', [
            'plesso_id' => $infanzia, 'nome' => 'Sezione ' . $nome, 'anno_scolastico' => '2026/2027',
        ]);
    }
    $classeSecondaria = $aggiungi('classi_sezioni', [
        'plesso_id' => $secondaria, 'nome' => 'Classe 1A', 'anno_scolastico' => '2026/2027',
    ]);

    // Credenziali dimostrative valide SOLO in locale su 127.0.0.1:18080.
    $hash = password_hash('Collaudo2026!', PASSWORD_DEFAULT);
    $admin = $aggiungi('utenti', [
        'ruolo' => 'admin', 'username' => 'admin.staging',
        'password_hash' => $hash, 'nome_completo' => 'Amministratore di prova',
    ]);
    $aggiungi('utenti', [
        'ruolo' => 'operatore', 'username' => 'operatore.staging',
        'password_hash' => $hash, 'nome_completo' => 'Operatore di prova',
    ]);
    $aggiungi('utenti', [
        'ruolo' => 'catering', 'username' => 'catering.staging',
        'password_hash' => $hash, 'nome_completo' => 'Catering di prova',
    ]);
    $aggiungi('utenti', [
        'ruolo' => 'scuola', 'username' => 'scuola.infanzia.staging',
        'password_hash' => $hash, 'nome_completo' => 'Scuola infanzia prova',
        'plesso_id' => $infanzia,
    ]);
    $genitore1Utente = $aggiungi('utenti', [
        'ruolo' => 'genitore', 'username' => 'genitore.staging',
        'password_hash' => $hash, 'nome_completo' => 'Genitore fittizio',
    ]);
    $genitore2Utente = $aggiungi('utenti', [
        'ruolo' => 'genitore', 'username' => 'genitore2.staging',
        'password_hash' => $hash, 'nome_completo' => 'Altro genitore fittizio',
    ]);

    $genitore1 = $aggiungi('genitori', [
        'utente_id' => $genitore1Utente, 'nome' => 'Genitore',
        'cognome' => 'Fittizio', 'telefono' => '0000000000',
    ]);
    $genitore2 = $aggiungi('genitori', [
        'utente_id' => $genitore2Utente, 'nome' => 'Altro',
        'cognome' => 'Fittizio', 'telefono' => '0000000000',
    ]);
    foreach ([
        ['Mario', 'Testuno', $genitore1, $standard, $classi['A']],
        ['Lucia', 'Testdue', $genitore1, $senzaGlutine, $classi['A']],
        ['Gianni', 'Testtre', $genitore2, $standard, $classi['B']],
        ['Elena', 'Testquattro', $genitore2, $standard, $classi['C']],
        ['Sara', 'Testcinque', $genitore2, $standard, $classeSecondaria],
    ] as [$nome, $cognome, $genitoreId, $menu, $classe]) {
        $bambino = $aggiungi('bambini', [
            'nome' => $nome, 'cognome' => $cognome,
            'genitore_id' => $genitoreId, 'tipologia_menu_id' => $menu,
        ]);
        $aggiungi('assegnazioni_classe', [
            'bambino_id' => $bambino,
            'classe_sezione_id' => $classe,
            'data_inizio' => '2026-10-01',
        ]);
    }
    $pool = $aggiungi('pool_crediti', [
        'genitore_id' => $genitore1, 'plesso_id' => $infanzia, 'saldo' => 5,
    ]);
    $aggiungi('movimenti_crediti', [
        'pool_id' => $pool, 'tipo' => 'acquisto',
        'delta' => 5, 'data' => '2026-10-14',
        'importo_euro' => 12.50, 'utente_id' => $admin,
        'note' => 'Cinque ticket sintetici di collaudo',
    ]);
    $pdo->commit();
    echo "Staging inizializzato: 3 plessi, sezioni A/B/C/D (D vuota), 5 alunni e utenze fittizie.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
