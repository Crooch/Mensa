<?php

declare(strict_types=1);

// Solo il DB effimero di CI, all'interno di una transazione con rollback.
if (getenv('CI') !== 'true' || getenv('MENSA_TEST_DATABASE') !== 'mensa_ci') {
    fwrite(STDERR, "Test riservato al DB isolato mensa_ci.\n");
    exit(2);
}
$config = require dirname(__DIR__) . '/bootstrap.php';
$config['db']['database'] = 'mensa_ci';
$config['db']['password'] = (string) getenv('MYSQL_PWD');
App\Core\Config::inizializza($config);
$pdo = App\Core\Database::pdo();
$pdo->beginTransaction();
try {
    $plessi = $pdo->query('SELECT nome, id FROM plessi')->fetchAll(PDO::FETCH_KEY_PAIR);
    $infanzia = (int) $plessi["Scuola dell'Infanzia - Via Catania"];
    $secondaria = (int) $plessi['Scuola Secondaria di I grado'];
    $pdo->exec("INSERT INTO tipologie_menu (nome) VALUES ('Standard'), ('Senza glutine')");
    $tipologie = $pdo->query('SELECT nome, id FROM tipologie_menu')->fetchAll(PDO::FETCH_KEY_PAIR);
    $standard = (int) $tipologie['Standard'];
    $senza = (int) $tipologie['Senza glutine'];

    $repo = new App\Repository\MenuSettimanaleRepository($pdo);
    $service = (new App\Core\Container())->catering();

    $giorni = ['1' => "Primo: pasta\nSecondo: pollo", '2' => 'Riso',
        '3' => '', '4' => 'Verdure', '5' => 'Pesce', '6' => '', '7' => ''];
    $fotoStorica = 'menu/settimane/foto-versione-originale.jpg';
    $fotoNuova = 'menu/settimane/foto-versione-nuova.png';
    $id = $repo->crea('Settimana A', $standard, $giorni, '2026-10-19', 0,
        ['1' => $fotoStorica]);
    $repo->assegna([$infanzia, $secondaria], $standard, $id, '2026-10-19', 0);

    foreach ([$infanzia, $secondaria] as $plesso) {
        $righe = $service->menuDelGiorno('2026-10-19', $plesso);
        if (count($righe) !== 1 ||
            $righe[0]['descrizione'] !== $giorni['1'] ||
            $righe[0]['origine'] !== 'settimanale' ||
            $righe[0]['foto_path'] !== $fotoStorica) {
            throw new RuntimeException('Il menu assegnato non compare su entrambi i plessi');
        }
    }
    if ($service->menuDelGiorno('2026-10-15', $infanzia) !== []) {
        throw new RuntimeException('Assegnazione applicata retroattivamente');
    }
    if ($service->menuDelGiorno('2026-10-20', $secondaria) !== []) {
        throw new RuntimeException('Il modello non rispetta il calendario del plesso');
    }

    $nuovi = $giorni;
    $nuovi['1'] = 'Primo: fusilli';
    $repo->modifica($id, 'Settimana A aggiornata', $nuovi, '2026-10-26', 0,
        ['1' => $fotoNuova]);
    $storico = $service->menuDelGiorno('2026-10-19', $infanzia);
    if (count($storico) !== 1 || $storico[0]['descrizione'] !== $giorni['1'] ||
        $storico[0]['modello_nome'] !== 'Settimana A' ||
        $storico[0]['foto_path'] !== $fotoStorica) {
        throw new RuntimeException('La revisione ha riscritto un menu storico');
    }
    $futuro = $service->menuDelGiorno('2026-10-26', $infanzia);
    if (count($futuro) !== 1 || $futuro[0]['descrizione'] !== 'Primo: fusilli' ||
        $futuro[0]['modello_nome'] !== 'Settimana A aggiornata' ||
        $futuro[0]['foto_path'] !== $fotoNuova) {
        throw new RuntimeException('La revisione non vale dalle date future');
    }

    // Togliere una foto in una revisione non fa scomparire quella storica.
    $repo->modifica($id, 'Settimana A senza foto', $nuovi, '2026-11-02', 0, []);
    if ($service->menuDelGiorno('2026-10-19', $secondaria)[0]['foto_path'] !== $fotoStorica ||
        $service->menuDelGiorno('2026-10-26', $secondaria)[0]['foto_path'] !== $fotoNuova ||
        $service->menuDelGiorno('2026-11-02', $secondaria)[0]['foto_path'] !== null) {
        throw new RuntimeException('La rimozione di una foto futura ha danneggiato lo storico');
    }

    $secondo = $repo->crea('Settimana B', $standard,
        ['1' => 'Menu B', '2' => '', '3' => '', '4' => '',
         '5' => '', '6' => '', '7' => ''], '2026-10-26', 0);
    $repo->assegna([$infanzia], $standard, $secondo, '2026-10-26', 0);
    if ($service->menuDelGiorno('2026-10-26', $infanzia)[0]['descrizione'] !== 'Menu B' ||
        $service->menuDelGiorno('2026-10-26', $secondaria)[0]['descrizione'] !== 'Primo: fusilli' ||
        $service->menuDelGiorno('2026-10-19', $infanzia)[0]['descrizione'] !== $giorni['1']) {
        throw new RuntimeException('Cambio di assegnazione ha modificato plessi o storia precedenti');
    }

    // Il menu del singolo giorno prevale, ma soltanto sulla combinazione scelta.
    $giornaliero = new App\Repository\MenuGiornalieroRepository($pdo);
    $giornaliero->salva($infanzia, $standard, '2026-10-26', 'Eccezione del 26 ottobre', null, null);
    if ($service->menuDelGiorno('2026-10-26', $infanzia)[0]['descrizione'] !== 'Eccezione del 26 ottobre' ||
        $service->menuDelGiorno('2026-10-26', $secondaria)[0]['descrizione'] !== 'Primo: fusilli') {
        throw new RuntimeException('Eccezione giornaliera non isolata dal modello');
    }

    // La duplicazione è indipendente dall'originale ed eventualmente di altro tipo.
    $copia = $service->salvaMenuSettimanale(null, 'Copia da modificare', $senza, $giorni, 0);
    $nuovaCopia = $service->duplicaMenuSettimanale($copia, 'Copia senza glutine', $senza, 0);
    if ($nuovaCopia === $copia || $repo->modello($nuovaCopia, '2099-01-01')['giorni']['1'] !== $giorni['1']) {
        throw new RuntimeException('La duplicazione non è indipendente');
    }
    // La duplicazione dal form invia descrizioni modificate e id del modello origine:
    // deve copiare i riferimenti fotografici senza copiare o sovrascrivere file.
    $copiaConFoto = $repo->crea('Modello fotografico', $standard, $giorni,
        (new DateTimeImmutable('tomorrow'))->format('Y-m-d'), 0, ['1' => $fotoStorica]);
    $nuovaConFoto = $service->salvaMenuSettimanale(null, 'Copia con foto', $senza,
        ['1' => 'Menu personalizzato'], 0, [], [], [], $copiaConFoto);
    if ($repo->modello($nuovaConFoto, '2099-01-01')['foto_giorni']['1'] !== $fotoStorica ||
        $repo->modello($nuovaConFoto, '2099-01-01')['giorni']['1'] !== 'Menu personalizzato') {
        throw new RuntimeException('La duplicazione da form non mantiene le foto');
    }
    $service->salvaMenuSettimanale($nuovaConFoto, 'Copia senza foto', $senza,
        ['1' => 'Menu personalizzato'], 0, [], ['1']);
    if (!empty($repo->modello($nuovaConFoto, '2099-01-01')['foto_giorni']['1']) ||
        $repo->modello($copiaConFoto, '2099-01-01')['foto_giorni']['1'] !== $fotoStorica) {
        throw new RuntimeException('Rimuovere la foto della copia modifica il modello originale');
    }
    $service->salvaMenuSettimanale($nuovaCopia, 'Nuova copia', $senza,
        ['1' => 'Pizza senza glutine'], 0);
    if ($repo->modello($copia, '2099-01-01')['giorni']['1'] !== $giorni['1']) {
        throw new RuntimeException('La modifica di una copia ha alterato l’originale');
    }

    // Un giornaliero pubblicato prima dell'assegnazione resta prioritario.
    $giornaliero->salva($secondaria, $standard, '2026-10-19',
        'Eccezione pubblicata prima del nuovo modello', null, null);
    $modelloSuccessivo = $repo->crea('Modello dopo il giornaliero', $standard,
        $giorni, '2026-10-19', 0, ['1' => $fotoNuova]);
    $repo->assegna([$secondaria], $standard, $modelloSuccessivo, '2026-10-19', 0);
    $precedenza = $service->menuDelGiorno('2026-10-19', $secondaria);
    if (count($precedenza) !== 1 ||
        $precedenza[0]['descrizione'] !== 'Eccezione pubblicata prima del nuovo modello' ||
        $precedenza[0]['origine'] !== 'giornaliero' || $precedenza[0]['foto_path'] !== null) {
        throw new RuntimeException('Il nuovo settimanale ha sovrascritto il giornaliero preesistente');
    }

    // Un giornaliero senza fotografia non eredita automaticamente quella del
    // settimanale: l'eccezione sostituisce tutta la scheda del giorno.
    $giornaliero->salva($infanzia, $standard, '2026-10-19',
        'Eccezione manuale senza foto', null, null);
    $precedenzaFoto = $service->menuDelGiorno('2026-10-19', $infanzia);
    if (count($precedenzaFoto) !== 1 ||
        $precedenzaFoto[0]['descrizione'] !== 'Eccezione manuale senza foto' ||
        $precedenzaFoto[0]['foto_path'] !== null) {
        throw new RuntimeException('La foto del settimanale e stata unita al giornaliero senza foto');
    }

    // Giorno di servizio futuro (o oggi) per i test del servizio Catering.
    // Il calendario della secondaria prevede lunedi e giovedi dal 19/10/2026.
    $giornoTest = new DateTimeImmutable(max('2026-10-22', date('Y-m-d')));
    $calendario = (new App\Core\Container())->calendario();
    while (!$calendario->previsto($secondaria, $giornoTest->format('Y-m-d'))['attivo']) {
        $giornoTest = $giornoTest->modify('+1 day');
    }
    $dataTest = $giornoTest->format('Y-m-d');
    $numeroGiorno = (string) $giornoTest->format('N');
    $giorniTest = [$numeroGiorno => 'Pesce'];
    $modelloTest = $repo->crea('Pesce fotografato', $standard, $giorniTest,
        $dataTest, 0, [$numeroGiorno => $fotoStorica]);
    $repo->assegna([$secondaria], $standard, $modelloTest, $dataTest, 0);
    $pdo->exec("INSERT INTO utenti (ruolo, username, password_hash)
        VALUES ('catering', 'catering.foto.ci', 'hash-test')");
    $autoreTest = (int) $pdo->lastInsertId();

    $previsto = $service->menuDelGiorno($dataTest, $secondaria);
    if (count($previsto) !== 1 || $previsto[0]['foto_path'] !== $fotoStorica) {
        throw new RuntimeException('Il modello settimanale valido non viene mostrato');
    }
    $idGiornaliero = $service->pubblicaMenu($secondaria, $standard, $dataTest, 'Pollo', null, $autoreTest);
    $rigaGiornaliera = $giornaliero->trovaPerChiave($secondaria, $standard, $dataTest);
    $override = $service->menuDelGiorno($dataTest, $secondaria);
    if ((int) $rigaGiornaliera['id'] !== $idGiornaliero ||
        $rigaGiornaliera['foto_path'] !== null ||
        $override[0]['descrizione'] !== 'Pollo' ||
        $override[0]['ha_modello_settimanale'] !== true ||
        $override[0]['foto_path'] !== null) {
        throw new RuntimeException('La pubblicazione giornaliera non ha sostituito tutto il modello');
    }
    $service->pubblicaMenu($secondaria, $standard, $dataTest, 'Pollo arrosto', null, $autoreTest);
    if ($giornaliero->trovaPerChiave($secondaria, $standard, $dataTest)['foto_path'] !== null) {
        throw new RuntimeException('La modifica del giornaliero ha aggiunto la foto del modello');
    }

    // Ripristino: elimina solo l'override per una giornata non consolidata.
    $service->ripristinaMenuSettimanale($secondaria, $standard, $dataTest);
    $ripristinato = $service->menuDelGiorno($dataTest, $secondaria);
    if ($giornaliero->trovaPerChiave($secondaria, $standard, $dataTest) !== null ||
        count($ripristinato) !== 1 ||
        $ripristinato[0]['descrizione'] !== 'Pesce' ||
        $ripristinato[0]['foto_path'] !== $fotoStorica ||
        $ripristinato[0]['origine'] !== 'settimanale') {
        throw new RuntimeException('Ripristino giornaliero non ha recuperato il modello e la sua foto');
    }

    try {
        $service->ripristinaMenuSettimanale($secondaria, $standard, $dataTest);
        throw new RuntimeException('Ripristino ripetuto senza eccezione accettato');
    } catch (App\Core\HttpException $e) {
        if ($e->getCode() !== 422) throw $e;
    }

    // Eccezione senza modello: deve comparire fra le programmate e
    // potersi annullare, senza toccare altre date o tipologie.
    $dataSenzaModello = $dataTest;
    $service->pubblicaMenu($secondaria, $senza, $dataSenzaModello,
        'Risotto senza glutine', null, $autoreTest);
    $programmate = $service->variazioniProgrammate();
    $trovata = false;
    foreach ($programmate['righe'] as $voce) {
        if ((int) $voce['plesso_id'] === $secondaria &&
            (int) $voce['tipologia_menu_id'] === $senza &&
            $voce['data'] === $dataSenzaModello) {
            $trovata = true;
            if (!$voce['modificabile'] || $voce['ha_modello_settimanale'] ||
                $voce['descrizione'] !== 'Risotto senza glutine') {
                throw new RuntimeException('Elenco variazioni programmate inesatto');
            }
        }
    }
    if (!$trovata) throw new RuntimeException('La variazione futura non compare in elenco');
    $service->annullaVariazioneGiornaliera($secondaria, $senza, $dataSenzaModello);
    if ($giornaliero->trovaPerChiave($secondaria, $senza, $dataSenzaModello) !== null) {
        throw new RuntimeException('La variazione senza modello non e stata annullata');
    }

    // Cutoff locale: oggi dopo l'orario limite il backend deve rifiutare
    // sia la pubblicazione sia la cancellazione anche con chiamate API dirette.
    $oggiTest = date('Y-m-d');
    $pdo->prepare('UPDATE plessi SET cutoff_orario = ? WHERE id = ?')
        ->execute(['00:00:00', $secondaria]);
    try {
        $service->pubblicaMenu($secondaria, $standard, $oggiTest,
            'Pubblicazione tardiva', null, $autoreTest);
        throw new RuntimeException('Menu di oggi modificato dopo il cutoff');
    } catch (App\Core\HttpException $e) {
        if ($e->getCode() !== 422) throw $e;
    }
    $giornaliero->salva($secondaria, $senza, $oggiTest,
        'Variazione gia pubblicata', null, $autoreTest);
    try {
        $service->annullaVariazioneGiornaliera($secondaria, $senza, $oggiTest);
        throw new RuntimeException('Annullamento consentito dopo il cutoff');
    } catch (App\Core\HttpException $e) {
        if ($e->getCode() !== 422) throw $e;
    }
    if ($giornaliero->trovaPerChiave($secondaria, $senza, $oggiTest) === null) {
        throw new RuntimeException('L annullamento tardivo ha eliminato il menu');
    }
    // Per il futuro il cutoff odierno non blocca la programmazione.
    $service->pubblicaMenu($secondaria, $senza, $dataSenzaModello,
        'Programmazione futura valida', null, $autoreTest);
    $service->annullaVariazioneGiornaliera($secondaria, $senza, $dataSenzaModello);

    // Paginazione: 25 variazioni sintetiche su date distinte.
    // La pagina 1 parte dalla data piu' lontana, senza duplicare le altre.
    $basePagine = new DateTimeImmutable($dataTest);
    $dateCreate = [];
    for ($i = 1; $i <= 25; $i++) {
        $dataP = $basePagine->modify('+' . $i . ' days')->format('Y-m-d');
        $giornaliero->salva($secondaria, $senza, $dataP,
            'Menu programmato ' . $i, null, $autoreTest);
        $dateCreate[] = $dataP;
    }
    $prima = $service->variazioniProgrammate(1);
    $seconda = $service->variazioniProgrammate(2);
    $terza = $service->variazioniProgrammate(3);
    if ($prima['per_pagina'] !== 10 || count($prima['righe']) !== 10 ||
        count($seconda['righe']) !== 10 || $prima['pagina'] !== 1 ||
        $seconda['pagina'] !== 2 || $prima['totale'] < 25 ||
        $prima['pagine'] < 3) {
        throw new RuntimeException('Dimensione delle pagine o totale delle variazioni errato');
    }
    $rigaPiuLontana = $prima['righe'][0];
    if ($rigaPiuLontana['data'] !== end($dateCreate)) {
        throw new RuntimeException('La prima pagina non parte dalla data piu lontana');
    }
    $viste = [];
    foreach ([$prima, $seconda, $terza] as $blocco) {
        $precedenteData = null;
        foreach ($blocco['righe'] as $voce) {
            if ($precedenteData !== null && $voce['data'] > $precedenteData) {
                throw new RuntimeException('Ordinamento delle date non decrescente');
            }
            if (in_array((int) $voce['id'], $viste, true)) {
                throw new RuntimeException('Una variazione compare in piu pagine');
            }
            $viste[] = (int) $voce['id'];
            $precedenteData = $voce['data'];
        }
    }
    $troppoLontana = $service->variazioniProgrammate(99999);
    if ($troppoLontana['pagina'] !== $troppoLontana['pagine'] ||
        count($troppoLontana['righe']) > 10) {
        throw new RuntimeException('Pagina non valida non riportata all ultima pagina');
    }
    // Dopo la cancellazione dei menu piu' lontani l'ultima pagina scompare
    // e la richiesta fuori intervallo viene riportata alla pagina esistente.
    foreach ($dateCreate as $dataP) {
        $giornaliero->rimuoviPerChiave($secondaria, $senza, $dataP);
    }
    $dopoCancellazione = $service->variazioniProgrammate(99999);
    if ($dopoCancellazione['pagina'] !== $dopoCancellazione['pagine'] ||
        $dopoCancellazione['totale'] !==
            $giornaliero->contaProgrammati(date('Y-m-d'))) {
        throw new RuntimeException('Paginazione non aggiornata dopo cancellazione');
    }
    echo "OK: 10 variazioni per pagina, ordine futuro-decrescente, nessun duplicato e pagina valida dopo cancellazione.\n";

    // In una giornata non prevista non si crea un giornaliero, e le vecchie
    // pubblicazioni non vengono proposte come menu attivi.
    $senzaServizio = $giornoTest->modify('next tuesday')->format('Y-m-d');
    if ($calendario->previsto($secondaria, $senzaServizio)['attivo']) {
        throw new RuntimeException('La giornata di test senza mensa risulta attiva');
    }
    try {
        $service->pubblicaMenu($secondaria, $standard, $senzaServizio, 'Menu non previsto', null, $autoreTest);
        throw new RuntimeException('Pubblicazione consentita in giornata senza servizio');
    } catch (App\Core\HttpException $e) {
        if ($e->getCode() !== 422) throw $e;
    }
    $giornaliero->salva($secondaria, $standard, $senzaServizio, 'Vecchia pubblicazione', null, $autoreTest);
    if ($service->menuDelGiorno($senzaServizio, $secondaria) !== []) {
        throw new RuntimeException('Il giornaliero non attivo risulta proposto come menu futuro');
    }

    // Blocca aggiornamenti manuali di giorni già trascorsi.
    try {
        $service->pubblicaMenu($infanzia, $standard, '2020-10-19', 'Cambio storico', null, 0);
        throw new RuntimeException('Modifica di un giorno storico consentita');
    } catch (App\Core\HttpException $e) {
        if ($e->getCode() !== 422) throw $e;
    }

    echo "OK: modelli settimanali, duplicazione, plessi e tipologie isolate, override e storico immutato.\n";
    $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
