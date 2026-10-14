<?php

declare(strict_types=1);

// Test di integrazione su DB CI ISOLATO mensa_ci; non eseguire su produzione.
// Esempio: MENSA_TEST_DATABASE=mensa_ci MYSQL_PWD=... php tests/report_integrazione.php
if (getenv('MENSA_TEST_DATABASE') !== 'mensa_ci' || getenv('CI') !== 'true') {
    fwrite(STDERR, "Test consentito solo nel database sintetico di CI.\n");
    exit(2);
}

require dirname(__DIR__) . '/src/Core/Repository.php';
require dirname(__DIR__) . '/src/Repository/PresenzaRepository.php';

use App\Repository\PresenzaRepository;

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=mensa_ci;charset=utf8mb4',
    'root',
    (string) getenv('MYSQL_PWD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => true]
);
$pdo->beginTransaction();
try {
    $plessi = $pdo->query('SELECT id, nome FROM plessi')->fetchAll(PDO::FETCH_KEY_PAIR);
    $infanzia = array_search("Scuola dell'Infanzia - Via Catania", $plessi, true);
    $secondaria = array_search('Scuola Secondaria di I grado', $plessi, true);
    if ($infanzia === false || $secondaria === false) {
        throw new RuntimeException('Dati di partenza CI non configurati');
    }

    $pdo->exec("INSERT INTO tipologie_menu (nome) VALUES ('Menu standard')");
    $menu = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO genitori (nome, cognome, telefono) VALUES ('Genitore', 'Test', '0000000000')");
    $genitore = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO classi_sezioni (plesso_id, nome, anno_scolastico) VALUES (?, ?, ?)');
    $classi = [];
    foreach (['A', 'B', 'C', 'D'] as $nome) {
        $stmt->execute([(int) $infanzia, $nome, '2026/2027']);
        $classi[$nome] = (int) $pdo->lastInsertId();
    }
    $stmt->execute([(int) $secondaria, '1A', '2026/2027']);
    $classeSecondaria = (int) $pdo->lastInsertId();

    $stmtB = $pdo->prepare('INSERT INTO bambini (nome, cognome, genitore_id, tipologia_menu_id) VALUES (?, ?, ?, ?)');
    $stmtA = $pdo->prepare('INSERT INTO assegnazioni_classe (bambino_id, classe_sezione_id, data_inizio) VALUES (?, ?, ?)');
    $figli = [];
    foreach ([
        ['Mario', 'Rossi', $classi['A']],
        ['Lucia', 'Bianchi', $classi['A']],
        ['Gianni', 'Verdi', $classi['B']],
        ['Elena', 'Neri', $classi['C']],
        ['Sofia', 'Blu', $classeSecondaria],
    ] as [$nome, $cognome, $classe]) {
        $stmtB->execute([$nome, $cognome, $genitore, $menu]);
        $id = (int) $pdo->lastInsertId();
        $figli[$nome] = $id;
        $stmtA->execute([$id, $classe, '2026-10-01']);
    }

    // Lucia assente per dichiarazione; tutta la classe C assente per assenza di massa.
    $stmt = $pdo->prepare(
        "INSERT INTO presenze_giornaliere (bambino_id, data, dichiarazione_genitore, stato_effettivo)
         VALUES (?, '2026-10-19', 'assente', 'assente')"
    );
    $stmt->execute([$figli['Lucia']]);
    $stmt = $pdo->prepare("INSERT INTO assenze_massa (classe_sezione_id, data, motivo) VALUES (?, '2026-10-19', 'Chiusura classe')");
    $stmt->execute([$classi['C']]);

    // La richiesta di Lucia non deve figurare nel report, essendo assente.
    $stmt = $pdo->prepare(
        "INSERT INTO richieste_pasta_bianco (bambino_id, data, richiesta) VALUES (?, '2026-10-19', 1)"
    );
    foreach (['Mario', 'Lucia', 'Gianni'] as $nome) {
        $stmt->execute([$figli[$nome]]);
    }

    $repo = new PresenzaRepository($pdo);
    $righe = $repo->reportPasti('2026-10-19', (int) $infanzia);
    $perSezione = [];
    foreach ($righe as $riga) {
        $perSezione[$riga['classe_nome']] = (int) $riga['pasti'];
    }
    if ($perSezione !== ['A' => 1, 'B' => 1, 'C' => 0, 'D' => 0]) {
        throw new RuntimeException('Conteggi per sezione non corretti: ' . json_encode($perSezione));
    }

    // Una classe vuota di anno scolastico precedente non deve apparire.
    $stmtClasseStorica = $pdo->prepare(
        'INSERT INTO classi_sezioni (plesso_id, nome, anno_scolastico) VALUES (?, ?, ?)'
    );
    $stmtClasseStorica->execute([(int) $infanzia, 'Sezione storica', '2025/2026']);
    $righeConStorico = $repo->reportPasti('2026-10-19', (int) $infanzia);
    if (count($righeConStorico) !== count($righe)) {
        throw new RuntimeException('Classe storica vuota inclusa erroneamente nel report');
    }

    $speciali = $repo->elencoPastaBianco('2026-10-19', (int) $infanzia);
    if (count($speciali) !== 2 ||
        array_column($speciali, 'nome') !== ['Mario', 'Gianni']) {
        throw new RuntimeException('Elenco pasta in bianco non coerente con le presenze');
    }

    echo "OK: sezioni A=1, B=1, C=0, D=0 (senza alunni); pasta in bianco Mario e Gianni; Lucia assente esclusa.\n";
    // Regressione gita: anche una presenza individuale gia salvata non
    // puo generare pasti o richieste di pasta in bianco per la classe.
    $stmt = $pdo->prepare(
        "INSERT INTO presenze_giornaliere (bambino_id, data, default_calcolato,
            dichiarazione_genitore, override_scuola, stato_effettivo)
         VALUES (?, '2026-10-20', 'presente', 'presente', 'presente', 'presente')"
    );
    $stmt->execute([$figli['Mario']]);
    $pdo->prepare(
        "INSERT INTO richieste_pasta_bianco (bambino_id, data, richiesta)
         VALUES (?, '2026-10-20', 1)"
    )->execute([$figli['Mario']]);
    $pdo->prepare(
        "INSERT INTO assenze_massa (classe_sezione_id, data, motivo)
         VALUES (?, '2026-10-20', 'Gita scolastica')"
    )->execute([$classi['A']]);
    $righeGita = $repo->reportPasti('2026-10-20', (int) $infanzia);
    $perSezioneGita = [];
    foreach ($righeGita as $riga) {
        $perSezioneGita[$riga['classe_nome']] =
            ($perSezioneGita[$riga['classe_nome']] ?? 0) + (int) $riga['pasti'];
    }
    if (($perSezioneGita['A'] ?? -1) !== 0 || ($perSezioneGita['B'] ?? -1) !== 1) {
        throw new RuntimeException('La gita non ha azzerato i pasti della sezione A: ' .
            json_encode($perSezioneGita));
    }
    if (count($repo->elencoPastaBianco('2026-10-20', (int) $infanzia)) !== 0) {
        throw new RuntimeException('Pasta in bianco inclusa in una giornata di gita');
    }
    $persistito = $pdo->prepare(
        "SELECT stato_effettivo FROM presenze_giornaliere
         WHERE bambino_id = ? AND data = '2026-10-20'"
    );
    $persistito->execute([$figli['Mario']]);
    if ($persistito->fetchColumn() !== 'presente') {
        throw new RuntimeException('La lettura del report non deve sovrascrivere i dati storici');
    }
    echo "OK: gita annulla pasti e pasta in bianco anche con una presenza precedente salvata.\n";

    $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
