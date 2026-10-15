<?php

declare(strict_types=1);

// Test di integrazione: eseguito solo sul database sintetico della CI.
// Non deve mai utilizzare un database del Comune o inviare comunicazioni reali.
if (getenv('CI') !== 'true' || getenv('MENSA_TEST_DATABASE') !== 'mensa_ci') {
    fwrite(STDERR, "Il test richiede il database isolato mensa_ci della CI.\n");
    exit(2);
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;

Config::inizializza([
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306, 'database' => 'mensa_ci',
        'username' => 'root', 'password' => (string) getenv('MYSQL_PWD'),
        'charset' => 'utf8mb4',
    ],
]);

$pdo = Database::pdo();
$oggi = date('Y-m-d');

// Plesso nuovo senza famiglie; apertura straordinaria per rendere il test
// indipendente dal giorno della settimana e dall'avvio del servizio reale.
$pdo->exec("INSERT INTO plessi (nome, cutoff_orario)
            VALUES ('CI recupero job interrotto', '00:00:00')");
$plesso = (int) $pdo->lastInsertId();
$eccezione = $pdo->prepare(
    'INSERT INTO eccezioni_calendario
       (plesso_id, giorno, servizio_attivo, motivazione)
     VALUES (:plesso, :oggi, 1, :motivo)'
);
$eccezione->execute([
    'plesso' => $plesso,
    'oggi' => $oggi,
    'motivo' => 'Apertura fittizia per test CI',
]);

$inserisci = $pdo->prepare(
    "INSERT INTO esecuzioni_job (plesso_id, data, esito, dettaglio, concluso_il)
     VALUES (:plesso, :oggi, 'errore', :dettaglio, NOW())"
);
$inserisci->execute([
    'plesso' => $plesso,
    'oggi' => $oggi,
    'dettaglio' => '{"messaggio":"errore simulato"}',
]);
$id = (int) $pdo->lastInsertId();

$conteggioPrima = (int) $pdo->query('SELECT COUNT(*) FROM movimenti_crediti')->fetchColumn();
$c = new Container();
for ($tentativo = 1; $tentativo <= 2; ++$tentativo) {
    $risultati = $c->job()->eseguiScheduler();
    $trovati = array_values(array_filter(
        $risultati,
        static fn (array $r): bool => (int) ($r['plesso_id'] ?? 0) === $plesso
    ));
    if (count($trovati) !== 1 || $trovati[0]['stato'] !== 'errore_precedente') {
        throw new RuntimeException('Un errore precedente non deve essere ritentato automaticamente.');
    }
}
$stato = $pdo->prepare('SELECT esito, dettaglio, concluso_il FROM esecuzioni_job WHERE id = :id');
$stato->execute(['id' => $id]);
$registrato = $stato->fetch(PDO::FETCH_ASSOC);

if ($registrato['esito'] !== 'errore'
    || $registrato['dettaglio'] !== '{"messaggio":"errore simulato"}'
    || $registrato['concluso_il'] === null) {
    throw new RuntimeException('Il retry automatico ha alterato il record di errore.');
}
$conteggioDopo = (int) $pdo->query('SELECT COUNT(*) FROM movimenti_crediti')->fetchColumn();
if ($conteggioDopo !== $conteggioPrima) {
    throw new RuntimeException('Lo scheduler ha registrato movimenti inattesi.');
}
echo "OK: stato errore preservato, due passaggi scheduler senza recupero automatico.\n";
