<?php

declare(strict_types=1);

// Solo dati sintetici sul database temporaneo della CI.
if (getenv('CI') !== 'true' || getenv('MENSA_TEST_DATABASE') !== 'mensa_ci') {
    fwrite(STDERR, "Questo test richiede il database isolato mensa_ci della CI.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\HttpException;

Config::inizializza([
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306, 'database' => 'mensa_ci',
        'username' => 'root', 'password' => (string) getenv('MYSQL_PWD'), 'charset' => 'utf8mb4',
    ],
]);
$pdo = Database::pdo();
$c = new Container();

function verifica(bool $condizione, string $messaggio): void
{
    if (!$condizione) {
        throw new RuntimeException($messaggio);
    }
    echo "OK: {$messaggio}\n";
}

function rifiutata(callable $operazione, string $messaggio): void
{
    try {
        $operazione();
    } catch (HttpException $e) {
        verifica($e->getCode() === 422 || $e->getCode() === 403 || $e->getCode() === 409, $messaggio);
        return;
    }
    throw new RuntimeException('Modifica non bloccata: ' . $messaggio);
}

$plesso = (int) $pdo->query("SELECT id FROM plessi WHERE nome LIKE 'Scuola dell%Infanzia%' LIMIT 1")->fetchColumn();
verifica($plesso > 0, 'plesso sintetico esistente');
$pdo->exec("UPDATE calendari_servizio SET data_inizio = '2020-01-01' WHERE plesso_id = {$plesso}");
$pdo->exec("UPDATE plessi SET cutoff_orario = '00:00:00' WHERE id = {$plesso}");
$pdo->exec("INSERT INTO tipologie_menu (nome) VALUES ('Dieta prova sicurezza')");
$tipo = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO utenti (ruolo, username, password_hash, plesso_id)
            VALUES ('genitore', 'famiglia_sicurezza', 'hash_di_prova', NULL)");
$utente = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO utenti (ruolo, username, password_hash, plesso_id)
            VALUES ('scuola', 'scuola_sicurezza', 'hash_di_prova', {$plesso})");
$scuola = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO genitori (utente_id, nome, cognome, telefono)
            VALUES ({$utente}, 'Genitore', 'Sicurezza', '0000000000')");
$genitore = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO genitori (nome, cognome, telefono)
            VALUES ('Altro', 'Nucleo', '0000000000')");
$altro = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO classi_sezioni (plesso_id, nome, anno_scolastico)
            VALUES ({$plesso}, 'PROVA-SEC', '2026/2027')");
$classe = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO bambini (nome, cognome, genitore_id, tipologia_menu_id)
            VALUES ('Alice', 'Sicurezza', {$genitore}, {$tipo})");
$bambino = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO assegnazioni_classe (bambino_id, classe_sezione_id, data_inizio)
            VALUES ({$bambino}, {$classe}, '2020-01-01')");

$prossimoLunedi = (new DateTimeImmutable('next monday'))->format('Y-m-d');
$servizio = $c->presenzaService();
$r = $servizio->dichiaraGenitore($bambino, $prossimoLunedi, 'assente', $utente);
verifica($r['stato_effettivo'] === 'assente', 'assenza futura accettata');
$servizio->dichiaraGenitore($bambino, $prossimoLunedi, 'assente', $utente);
$count = (int) $pdo->query("SELECT COUNT(*) FROM log_operazioni_presenze WHERE bambino_id={$bambino}")->fetchColumn();
verifica($count === 1, 'invio duplicato non crea nuovi eventi audit');
$r = $servizio->dichiaraGenitore($bambino, $prossimoLunedi, 'presente', $utente);
verifica($r['stato_effettivo'] === 'presente', 'modifica futura libera');
$count = (int) $pdo->query("SELECT COUNT(*) FROM log_operazioni_presenze WHERE bambino_id={$bambino}")->fetchColumn();
verifica($count === 2, 'storico append-only conserva entrambe le modifiche');
rifiutata(fn () => $servizio->dichiaraGenitore($bambino, '2020-01-01', 'assente', $utente),
    'giornata passata non modificabile');
rifiutata(fn () => $servizio->dichiaraGenitore($bambino, '2026-10-32', 'assente', $utente),
    'data inesistente non valida');
rifiutata(fn () => $c->anagrafica()->bambinoDelGenitore($bambino, $altro),
    'un altro genitore non puo intervenire sul bambino');

// Una utenza scuola senza plesso assegnato non deve poter interrogare tutte le classi.
$controllerScuola = new App\Controller\ScuolaController($c);
rifiutata(fn () => $controllerScuola->classi(new App\Core\Request(), [
    'ruolo' => 'scuola', 'plesso_id' => null,
]), 'utenza scuola senza plesso non autorizzata');

$pasta = $servizio->pastaBiancoGenitore($bambino, $prossimoLunedi, true, $utente);
verifica($pasta['richiesta'] === true, 'richiesta pasta futura accettata');
$servizio->pastaBiancoGenitore($bambino, $prossimoLunedi, false, $utente);
$count = (int) $pdo->query("SELECT COUNT(*) FROM log_operazioni_presenze WHERE bambino_id={$bambino}")->fetchColumn();
verifica($count === 4, 'richiesta e annullamento pasta registrati senza movimento economico');

// Regressione: una dichiarazione tardiva preesistente non deve diventare
// valida soltanto perche' il job viene eseguito dopo il cutoff.
$pdo->exec("UPDATE presenze_giornaliere
            SET dichiarazione_genitore='assente', fuori_cutoff=1, stato_effettivo='presente'
            WHERE bambino_id={$bambino} AND data='{$prossimoLunedi}'");
$servizio->congelaPlesso($plesso, $prossimoLunedi);
$statoCongelato = $pdo->query(
    "SELECT stato_effettivo FROM presenze_giornaliere
     WHERE bambino_id={$bambino} AND data='{$prossimoLunedi}'"
)->fetchColumn();
verifica($statoCongelato === 'presente', 'congelamento ignora dichiarazioni tardive pregresse');

$oggi = date('Y-m-d');
$giorno = (int) date('N');
if ($giorno <= 5) {
    rifiutata(fn () => $servizio->dichiaraGenitore($bambino, $oggi, 'assente', $utente),
        'assenza odierna post-cutoff rifiutata');
    rifiutata(fn () => $servizio->pastaBiancoGenitore($bambino, $oggi, true, $utente),
        'richiesta pasta odierna post-cutoff rifiutata');

    $pdo->exec("INSERT INTO bambini (nome, cognome, genitore_id, tipologia_menu_id)
                VALUES ('Bruno', 'Sicurezza', {$genitore}, {$tipo})");
    $bambinoServito = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO assegnazioni_classe (bambino_id, classe_sezione_id, data_inizio)
                VALUES ({$bambinoServito}, {$classe}, '2020-01-01')");
    $pdo->exec("INSERT INTO presenze_giornaliere
                (bambino_id, data, dichiarazione_genitore, stato_effettivo, congelato)
                VALUES ({$bambinoServito}, '{$oggi}', 'assente', 'assente', 1)");
    $pdo->exec("INSERT INTO esecuzioni_job (plesso_id, data, esito)
                VALUES ({$plesso}, '{$oggi}', 'ok')");

    $servizio->overrideScuola($bambinoServito, $oggi, 'presente', 'Pasto realmente erogato', $scuola, $plesso);
    $servizio->overrideScuola($bambinoServito, $oggi, 'presente', 'Conferma della verifica', $scuola, $plesso);
    $movimenti = (int) $pdo->query(
        "SELECT COUNT(*) FROM movimenti_crediti WHERE bambino_id={$bambinoServito} AND tipo='consumo_pasto'"
    )->fetchColumn();
    verifica($movimenti === 1, 'pasto scolastico addebitato una sola volta');
    $servizio->overrideScuola($bambinoServito, $oggi, 'assente', 'Segnalazione successiva', $scuola, $plesso);
    $movimenti = (int) $pdo->query(
        "SELECT COUNT(*) FROM movimenti_crediti WHERE bambino_id={$bambinoServito} AND tipo='consumo_pasto'"
    )->fetchColumn();
    verifica($movimenti === 1, 'assenza successiva non annulla il movimento contabile');

    $saldo = (int) $pdo->query("SELECT saldo FROM pool_crediti WHERE genitore_id={$genitore} AND plesso_id={$plesso}")->fetchColumn();
    verifica($saldo === -1, 'saldo contabile coerente con il pasto servito');
} else {
    echo "NOTA: scenari del giorno odierno post-cutoff saltati perche oggi e weekend.\n";
}

// Il lock per plesso/data deve essere effettivamente esclusivo tra connessioni.
$repo = $c->jobRepo();
verifica($repo->acquisisciLock($plesso, $prossimoLunedi), 'lock del plesso acquisito');
$secondaConnessione = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=mensa_ci;charset=utf8mb4',
    'root', (string) getenv('MYSQL_PWD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$chiave = 'mensa:job:' . $plesso . ':' . $prossimoLunedi;
$stmt = $secondaConnessione->prepare('SELECT GET_LOCK(?, 0)');
$stmt->execute([$chiave]);
verifica((int) $stmt->fetchColumn() === 0, 'seconda connessione non supera il lock');
$repo->rilasciaLock($plesso, $prossimoLunedi);
echo "Test sicurezza dichiarazioni conclusi.\n";
