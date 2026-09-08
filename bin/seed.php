<?php

declare(strict_types=1);

/**
 * Dati di esempio realistici per i test funzionali.
 *
 * Uso:  php bin/seed.php
 * ATTENZIONE: svuota le tabelle applicative e le ripopola.
 *
 * Contenuto: 3 plessi con cutoff diversi, 7 classi, 6 tipologie di menu,
 * 10 famiglie con 15 alunni, override tariffari, acquisti di buoni,
 * 10 giornate di presenze storiche (con un'assenza di massa) e relativi
 * consumi, feedback e un menu pubblicato per oggi.
 */

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'movimenti_crediti', 'pool_crediti', 'override_tariffe', 'feedback_pasti', 'menu_giornalieri',
    'presenze_giornaliere', 'assenze_massa', 'assegnazioni_classe', 'bambini', 'genitori',
    'classi_sezioni', 'log_solleciti_inviati', 'modelli_email_sollecito', 'destinatari_fissi_email',
    'esecuzioni_job', 'api_token', 'utenti', 'plessi', 'tipologie_menu', 'impostazioni',
] as $tabella) {
    $pdo->exec("TRUNCATE TABLE {$tabella}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

/** Inserimento generico con ritorno dell'id. */
$inserisci = static function (string $tabella, array $dati) use ($pdo): int {
    $colonne = array_keys($dati);
    $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $tabella, implode(',', $colonne), ':' . implode(',:', $colonne));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dati);
    return (int) $pdo->lastInsertId();
};

$hash = static fn (string $password): string => password_hash($password, PASSWORD_DEFAULT);
$annoScolastico = '2025/2026';
$inizioAnno = '2025-09-15';

// ---------------------------------------------------------------- impostazioni
foreach ([
    'email_protocollo' => 'protocollo@comune.caltavuturo.pa.it',
    'smtp_host'        => '',
    'smtp_port'        => '587',
    'smtp_security'    => 'tls',
    'smtp_username'    => '',
    'smtp_password'    => '',
    'smtp_from'        => 'mensa@comune.caltavuturo.pa.it',
    'smtp_from_name'   => 'Comune di Caltavuturo - Mensa scolastica',
] as $chiave => $valore) {
    $inserisci('impostazioni', ['chiave' => $chiave, 'valore' => $valore]);
}

// ---------------------------------------------------------------- plessi
$plessi = [];
foreach ([
    ['Scuola dell\'Infanzia - Via Verdi', 2.50, 10, '09:00:00', 'dirigente@iccaltavuturo.edu.it'],
    ['Scuola Primaria - Plesso Centrale', 3.00, 15, '09:30:00', 'dirigente@iccaltavuturo.edu.it'],
    ['Scuola Secondaria di I grado', 3.50, 20, '10:00:00', 'dirigente@iccaltavuturo.edu.it'],
] as [$nome, $tariffa, $fido, $cutoff, $dirigente]) {
    $plessi[] = $inserisci('plessi', [
        'nome'               => $nome,
        'tariffa_base_pasto' => $tariffa,
        'fido_buoni'         => $fido,
        'cutoff_orario'      => $cutoff,
        'dirigente_email'    => $dirigente,
        'email_protocollo'   => null,
    ]);
}

// ---------------------------------------------------------------- tipologie menu
$tipologie = [];
foreach ([
    'Standard', 'Intollerante al glutine', 'Vegano', 'Allergia al pistacchio',
    'Dieta in bianco (temporanea)', 'Senza lattosio e uova',
] as $nome) {
    $tipologie[$nome] = $inserisci('tipologie_menu', ['nome' => $nome]);
}

// ---------------------------------------------------------------- utenze di servizio
$utenteAdmin = $inserisci('utenti', ['ruolo' => 'admin', 'username' => 'admin', 'password_hash' => $hash('Admin2026!'), 'nome_completo' => 'Amministratore di sistema']);
$utenteOperatore = $inserisci('utenti', ['ruolo' => 'operatore', 'username' => 'operatore', 'password_hash' => $hash('Operatore2026!'), 'nome_completo' => 'Ufficio scuola - Rosa Militello']);
$utentiScuola = [];
foreach ([0 => 'scuola.infanzia', 1 => 'scuola.primaria', 2 => 'scuola.secondaria'] as $indice => $username) {
    $utentiScuola[$indice] = $inserisci('utenti', [
        'ruolo' => 'scuola', 'username' => $username, 'password_hash' => $hash('Scuola2026!'),
        'nome_completo' => 'Segreteria ' . $username, 'plesso_id' => $plessi[$indice],
    ]);
}
$inserisci('utenti', ['ruolo' => 'catering', 'username' => 'catering', 'password_hash' => $hash('Catering2026!'), 'nome_completo' => 'Ditta di ristorazione Madonie']);

// ---------------------------------------------------------------- classi/sezioni
$classi = [];
foreach ([
    [0, 'Sezione A'], [0, 'Sezione B'],
    [1, '1ª A'], [1, '3ª B'], [1, '5ª A'],
    [2, '1ª A'], [2, '2ª B'],
] as $indice => [$plessoIndice, $nome]) {
    $classi[$indice] = [
        'id'      => $inserisci('classi_sezioni', ['plesso_id' => $plessi[$plessoIndice], 'nome' => $nome, 'anno_scolastico' => $annoScolastico]),
        'plesso'  => $plessi[$plessoIndice],
        'plessoI' => $plessoIndice,
    ];
}

// ---------------------------------------------------------------- famiglie e alunni
$famiglie = [
    ['Giuseppe', 'Lo Bianco', '3331002001', 'g.lobianco@example.it', [['Marco', 2, 'Standard'], ['Sara', 0, 'Intollerante al glutine']]],
    ['Maria', 'Curcio', '3331002002', 'm.curcio@example.it', [['Antonio', 3, 'Standard']]],
    ['Salvatore', 'Ferrara', '3331002003', null, [['Chiara', 0, 'Standard'], ['Luca', 4, 'Vegano']]],
    ['Rosa', 'Militello', '3331002004', 'r.militello@example.it', [['Elena', 5, 'Standard']]],
    ['Antonino', 'Gervasi', '3331002005', 'a.gervasi@example.it', [['Giulia', 1, 'Allergia al pistacchio'], ['Davide', 3, 'Standard']]],
    ['Carmela', 'Panzarella', '3331002006', null, [['Noemi', 2, 'Dieta in bianco (temporanea)']]],
    ['Vincenzo', 'Di Marco', '3331002007', 'v.dimarco@example.it', [['Simone', 6, 'Standard']]],
    ['Angela', 'Sciortino', '3331002008', 'a.sciortino@example.it', [['Alessia', 4, 'Senza lattosio e uova'], ['Matteo', 1, 'Standard']]],
    ['Filippo', 'Rinaldi', '3331002009', 'f.rinaldi@example.it', [['Gaia', 5, 'Standard']]],
    ['Concetta', 'Vaccaro', '3331002010', null, [['Riccardo', 6, 'Standard'], ['Martina', 2, 'Standard']]],
];

$bambini = [];   // id => ['genitore' => id, 'plesso' => id, 'classe' => id, 'tipologia' => id]
$genitori = [];

foreach ($famiglie as $indice => [$nome, $cognome, $telefono, $email, $figli]) {
    $username = strtolower(str_replace([' ', '\''], '', $cognome)) . '.' . strtolower($nome[0]);
    $utenteId = $inserisci('utenti', [
        'ruolo' => 'genitore', 'username' => $username, 'password_hash' => $hash('Genitore2026!'),
        'nome_completo' => "{$cognome} {$nome}",
    ]);
    $genitoreId = $inserisci('genitori', [
        'utente_id' => $utenteId, 'nome' => $nome, 'cognome' => $cognome,
        'telefono' => $telefono, 'email' => $email,
    ]);
    $genitori[$indice] = ['id' => $genitoreId, 'username' => $username];

    foreach ($figli as [$nomeFiglio, $classeIndice, $tipologia]) {
        $bambinoId = $inserisci('bambini', [
            'nome' => $nomeFiglio, 'cognome' => $cognome,
            'genitore_id' => $genitoreId, 'tipologia_menu_id' => $tipologie[$tipologia],
        ]);
        $inserisci('assegnazioni_classe', [
            'bambino_id' => $bambinoId, 'classe_sezione_id' => $classi[$classeIndice]['id'], 'data_inizio' => $inizioAnno,
        ]);
        $bambini[$bambinoId] = [
            'genitore' => $genitoreId,
            'plesso'   => $classi[$classeIndice]['plesso'],
            'classe'   => $classi[$classeIndice]['id'],
        ];
    }
}

// Esempio di cambio classe storicizzato: il primo alunno cambia sezione a gennaio.
$primoBambino = array_key_first($bambini);
$pdo->prepare('UPDATE assegnazioni_classe SET data_fine = :df WHERE bambino_id = :b AND data_fine IS NULL')
    ->execute(['df' => '2026-01-14', 'b' => $primoBambino]);
$inserisci('assegnazioni_classe', [
    'bambino_id' => $primoBambino, 'classe_sezione_id' => $classi[4]['id'], 'data_inizio' => '2026-01-15',
]);
$bambini[$primoBambino]['classe'] = $classi[4]['id'];
$bambini[$primoBambino]['plesso'] = $classi[4]['plesso'];

// ---------------------------------------------------------------- override tariffari
$inserisci('override_tariffe', [
    'genitore_id' => $genitori[2]['id'], 'plesso_id' => $plessi[0], 'tariffa' => 1.20,
    'data_inserimento' => '2025-10-01', 'motivazione' => 'Agevolazione servizi sociali - delibera G.C. n. 84/2025',
    'utente_id' => $utenteOperatore,
]);
$inserisci('override_tariffe', [
    'genitore_id' => $genitori[5]['id'], 'plesso_id' => $plessi[1], 'tariffa' => 0.00,
    'data_inserimento' => '2025-11-12', 'motivazione' => 'Esenzione totale - relazione assistente sociale prot. 4521',
    'utente_id' => $utenteOperatore,
]);
$inserisci('override_tariffe', [
    'genitore_id' => $genitori[2]['id'], 'plesso_id' => $plessi[0], 'tariffa' => 1.80,
    'data_inserimento' => '2026-02-01', 'motivazione' => 'Rideterminazione ISEE 2026 - delibera G.C. n. 12/2026',
    'utente_id' => $utenteOperatore,
]);

// ---------------------------------------------------------------- pool crediti e acquisti
$pool = []; // "genitore-plesso" => id
$assicuraPool = static function (int $genitoreId, int $plessoId) use (&$pool, $inserisci): int {
    $chiave = $genitoreId . '-' . $plessoId;
    return $pool[$chiave] ??= $inserisci('pool_crediti', ['genitore_id' => $genitoreId, 'plesso_id' => $plessoId, 'saldo' => 0]);
};
$aggiornaSaldo = static function (int $poolId, int $delta) use ($pdo): void {
    $pdo->prepare('UPDATE pool_crediti SET saldo = saldo + :d WHERE id = :id')->execute(['d' => $delta, 'id' => $poolId]);
};
$tariffaAttiva = static function (int $genitoreId, int $plessoId, string $data) use ($pdo): float {
    $stmt = $pdo->prepare('SELECT tariffa FROM override_tariffe WHERE genitore_id = :g AND plesso_id = :p AND data_inserimento <= :d ORDER BY data_inserimento DESC, id DESC LIMIT 1');
    $stmt->execute(['g' => $genitoreId, 'p' => $plessoId, 'd' => $data]);
    $override = $stmt->fetchColumn();
    if ($override !== false) {
        return (float) $override;
    }
    $stmt = $pdo->prepare('SELECT tariffa_base_pasto FROM plessi WHERE id = :p');
    $stmt->execute(['p' => $plessoId]);
    return (float) $stmt->fetchColumn();
};

// Coppie (genitore, plesso) effettivamente in uso
$coppie = [];
foreach ($bambini as $dati) {
    $coppie[$dati['genitore'] . '-' . $dati['plesso']] = [$dati['genitore'], $dati['plesso']];
}

$dataAcquisto = date('Y-m-d', strtotime('-20 days'));
$indiceCoppia = 0;
foreach ($coppie as [$genitoreId, $plessoId]) {
    // Un genitore su tre non ricarica: servira' a testare l'escalation oltre fido.
    $buoni = [30, 12, 0][$indiceCoppia % 3];
    $indiceCoppia++;
    $poolId = $assicuraPool($genitoreId, $plessoId);
    if ($buoni === 0) {
        // Riporto del debito dal vecchio sistema cartaceo: porta il pool oltre il fido,
        // condizione utile per verificare l'escalation informativa quotidiana.
        $stmt = $pdo->prepare('SELECT fido_buoni FROM plessi WHERE id = :p');
        $stmt->execute(['p' => $plessoId]);
        $fido = (int) $stmt->fetchColumn();
        $inserisci('movimenti_crediti', [
            'pool_id' => $poolId, 'tipo' => 'rettifica_manuale', 'delta' => -($fido + 2),
            'data' => $dataAcquisto, 'utente_id' => $utenteOperatore,
            'note' => 'Riporto debito pregresso dai quadernetti cartacei',
        ]);
        $aggiornaSaldo($poolId, -($fido + 2));
        continue;
    }
    $tariffa = $tariffaAttiva($genitoreId, $plessoId, $dataAcquisto);
    $inserisci('movimenti_crediti', [
        'pool_id' => $poolId, 'tipo' => 'acquisto', 'delta' => $buoni, 'data' => $dataAcquisto,
        'importo_euro' => round($buoni * $tariffa, 2), 'utente_id' => $utenteOperatore,
        'note' => 'Incasso allo sportello',
    ]);
    $aggiornaSaldo($poolId, $buoni);
}

// ---------------------------------------------------------------- presenze storiche
/** Ultime 10 giornate lavorative escluso oggi (il job di oggi si esegue dalla UI). */
$giornate = [];
$cursore = new DateTimeImmutable('yesterday');
while (count($giornate) < 10) {
    if ((int) $cursore->format('N') <= 5) {
        $giornate[] = $cursore->format('Y-m-d');
    }
    $cursore = $cursore->modify('-1 day');
}
$giornate = array_reverse($giornate);

// Assenza di massa (gita) sulla classe "3ª B" nella quinta giornata dello storico.
$dataGita = $giornate[4];
$inserisci('assenze_massa', [
    'classe_sezione_id' => $classi[3]['id'], 'data' => $dataGita,
    'motivo' => 'Uscita didattica al Parco delle Madonie', 'utente_id' => $utentiScuola[1],
]);

$idBambini = array_keys($bambini);
foreach ($giornate as $indiceGiorno => $data) {
    foreach ($bambini as $bambinoId => $dati) {
        $inGita = $dati['classe'] === $classi[3]['id'] && $data === $dataGita;
        $default = $inGita ? 'assente' : 'presente';

        // Alcune assenze dichiarate dalle famiglie, distribuite in modo deterministico.
        $dichiarazione = null;
        if (($bambinoId + $indiceGiorno) % 7 === 0) {
            $dichiarazione = 'assente';
        }
        // Un bambino della gita partecipa comunque al pasto: eccezione dichiarata.
        if ($inGita && $bambinoId % 2 === 0) {
            $dichiarazione = 'presente';
        }

        $stato = $dichiarazione ?? $default;
        $inserisci('presenze_giornaliere', [
            'bambino_id'              => $bambinoId,
            'data'                    => $data,
            'default_calcolato'       => $default,
            'dichiarazione_genitore'  => $dichiarazione,
            'dichiarazione_timestamp' => $dichiarazione !== null ? $data . ' 07:45:00' : null,
            'dichiarazione_utente_id' => null,
            'stato_effettivo'         => $stato,
            'congelato'               => 1,
            'consumo_registrato'      => $stato === 'presente' ? 1 : 0,
        ]);

        if ($stato !== 'presente') {
            continue;
        }
        $poolId = $assicuraPool($dati['genitore'], $dati['plesso']);
        $inserisci('movimenti_crediti', [
            'pool_id' => $poolId, 'tipo' => 'consumo_pasto', 'delta' => -1, 'data' => $data,
            'bambino_id' => $bambinoId, 'utente_id' => null, 'note' => 'Consumo automatico post-cutoff',
        ]);
        $aggiornaSaldo($poolId, -1);
    }
}

// ---------------------------------------------------------------- feedback e menu
$commenti = [
    'Pasta al pomodoro molto gradita.',
    'Il pesce era poco condito secondo mia figlia.',
    'Porzioni giuste, verdura fresca.',
    'Oggi non ha mangiato il contorno.',
    'Ottima la frutta di stagione.',
];
foreach (array_slice($giornate, -5) as $indice => $data) {
    foreach (array_slice($idBambini, 0, 6) as $posizione => $bambinoId) {
        if (($posizione + $indice) % 2 !== 0) {
            continue;
        }
        $stmt = $pdo->prepare('SELECT tipologia_menu_id FROM bambini WHERE id = :b');
        $stmt->execute(['b' => $bambinoId]);
        $inserisci('feedback_pasti', [
            'bambino_id'        => $bambinoId,
            'data'              => $data,
            'tipologia_menu_id' => (int) $stmt->fetchColumn(),
            'voto'              => 3 + (($posizione + $indice) % 3),
            'commento'          => $commenti[($posizione + $indice) % count($commenti)],
        ]);
    }
}

$inserisci('menu_giornalieri', [
    'plesso_id' => $plessi[1], 'tipologia_menu_id' => $tipologie['Standard'], 'data' => date('Y-m-d'),
    'descrizione' => 'Pasta al pomodoro e basilico, bocconcini di pollo al forno, carote julienne, pane, mela.',
]);
$inserisci('menu_giornalieri', [
    'plesso_id' => $plessi[1], 'tipologia_menu_id' => $tipologie['Intollerante al glutine'], 'data' => date('Y-m-d'),
    'descrizione' => 'Pasta di riso al pomodoro, bocconcini di pollo (panatura senza glutine), carote, pane senza glutine, mela.',
]);

// ---------------------------------------------------------------- email: destinatari e modelli
foreach ([
    ['Ufficio scuola', 'ufficioscuola@comune.caltavuturo.pa.it'],
    ['Assessore alla pubblica istruzione', 'assessore.istruzione@comune.caltavuturo.pa.it'],
    ['Assistente sociale', 'servizisociali@comune.caltavuturo.pa.it'],
] as [$etichetta, $email]) {
    $inserisci('destinatari_fissi_email', ['etichetta' => $etichetta, 'email' => $email, 'attivo' => 1]);
}

$testoModello = <<<TESTO
Gentile {genitore},

la posizione relativa al servizio di mensa scolastica presso {plesso} risulta alla data del {data}
con un saldo di {saldo} buoni pasto, oltre il fido concesso di {fido} buoni.

Alunni interessati: {alunno}

Si invita a regolarizzare la posizione presso l'ufficio comunale competente.
Si ricorda che il servizio mensa NON viene sospeso: la presente comunicazione ha finalità informativa
e viene inviata quotidianamente agli uffici competenti finché la posizione non risulta regolarizzata.

Comune di Caltavuturo - Ufficio Pubblica Istruzione
TESTO;

foreach ($plessi as $plessoId) {
    $inserisci('modelli_email_sollecito', [
        'plesso_id' => $plessoId,
        'oggetto'   => 'Mensa scolastica {plesso} - posizione debitoria oltre fido ({saldo} buoni)',
        'testo'     => $testoModello,
        'attivo'    => 1,
    ]);
}

// ---------------------------------------------------------------- riepilogo
echo "Seed completato.\n";
echo '- Plessi: ', count($plessi), ' | Classi: ', count($classi), ' | Tipologie: ', count($tipologie), "\n";
echo '- Famiglie: ', count($famiglie), ' | Alunni: ', count($bambini), "\n";
echo '- Giornate di presenze storicizzate: ', count($giornate), " (con un'assenza di massa)\n";
echo "\nCredenziali di test:\n";
echo "  admin / Admin2026!\n  operatore / Operatore2026!\n";
echo "  scuola.infanzia | scuola.primaria | scuola.secondaria / Scuola2026!\n";
echo "  catering / Catering2026!\n";
foreach ($genitori as $g) {
    echo '  ', $g['username'], " / Genitore2026!\n";
}
