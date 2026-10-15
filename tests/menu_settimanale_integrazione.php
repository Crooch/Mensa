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

    // Pubblicazione tramite servizio (non direttamente tramite Repository):
    // anche se il modello ha una foto, l'override deve creare Pollo senza foto.
    $oggi = date('Y-m-d');
    $numeroGiorno = (string) (new DateTimeImmutable($oggi))->format('N');
    $giorniOggi = [$numeroGiorno => 'Pesce'];
    $modelloOggi = $repo->crea('Pesce fotografato', $standard, $giorniOggi,
        $oggi, 0, [$numeroGiorno => $fotoStorica]);
    $repo->assegna([$secondaria], $standard, $modelloOggi, $oggi, 0);
    $idGiornaliero = $service->pubblicaMenu($secondaria, $standard, $oggi, 'Pollo', null, 0);
    $rigaGiornaliera = $giornaliero->trovaPerChiave($secondaria, $standard, $oggi);
    if ((int) $rigaGiornaliera['id'] !== $idGiornaliero ||
        $rigaGiornaliera['descrizione'] !== 'Pollo' ||
        $rigaGiornaliera['foto_path'] !== null ||
        $repo->modello($modelloOggi, $oggi)['foto_giorni'][$numeroGiorno] !== $fotoStorica) {
        throw new RuntimeException('La pubblicazione giornaliera ha ereditato la foto settimanale');
    }
    $service->pubblicaMenu($secondaria, $standard, $oggi, 'Pollo arrosto', null, 0);
    if ($giornaliero->trovaPerChiave($secondaria, $standard, $oggi)['foto_path'] !== null) {
        throw new RuntimeException('La modifica di un giornaliero senza foto ne ha aggiunta una');
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
