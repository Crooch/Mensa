<?php

declare(strict_types=1);

// Test privacy isolato: nessun invio email, nessuna connessione al DB reale.
if (getenv('CI') !== 'true' || getenv('MENSA_TEST_DATABASE') !== 'mensa_ci') {
    fwrite(STDERR, "Usare solo il database sintetico mensa_ci della CI.\n");
    exit(2);
}
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Service\SollecitoService;

Config::inizializza([
    'db' => [
        'host' => '127.0.0.1', 'port' => 3306, 'database' => 'mensa_ci',
        'username' => 'root', 'password' => (string) getenv('MYSQL_PWD'),
        'charset' => 'utf8mb4',
    ],
]);
$pdo = Database::pdo();
$pdo->exec("INSERT INTO plessi (nome) VALUES ('CI privacy scuola A')");
$plessoA = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO plessi (nome) VALUES ('CI privacy scuola B')");
$plessoB = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO tipologie_menu (nome) VALUES ('CI privacy tipo')");
$tipo = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO genitori (nome, cognome, telefono)
            VALUES ('Famiglia', 'Privacy CI', '0000000000')");
$genitore = (int) $pdo->lastInsertId();

$inserisciClasse = $pdo->prepare(
    'INSERT INTO classi_sezioni (plesso_id, nome, anno_scolastico)
     VALUES (:plesso, :nome, :anno)'
);
$anno = ((int) date('n') >= 9 ? (int) date('Y') : (int) date('Y') - 1);
$annoScolastico = $anno . '/' . ($anno + 1);
$inserisciClasse->execute([
    'plesso' => $plessoA, 'nome' => 'CI A', 'anno' => $annoScolastico,
]);
$classeA = (int) $pdo->lastInsertId();
$inserisciClasse->execute([
    'plesso' => $plessoB, 'nome' => 'CI B', 'anno' => $annoScolastico,
]);
$classeB = (int) $pdo->lastInsertId();

$creaBambino = $pdo->prepare(
    'INSERT INTO bambini (nome, cognome, genitore_id, tipologia_menu_id)
     VALUES (:nome, :cognome, :genitore, :tipo)'
);
$creaBambino->execute([
    'nome' => 'Alunna', 'cognome' => 'Uno', 'genitore' => $genitore, 'tipo' => $tipo,
]);
$figliaA = (int) $pdo->lastInsertId();
$creaBambino->execute([
    'nome' => 'Alunno', 'cognome' => 'Due', 'genitore' => $genitore, 'tipo' => $tipo,
]);
$figlioB = (int) $pdo->lastInsertId();

$assegna = $pdo->prepare(
    'INSERT INTO assegnazioni_classe (bambino_id, classe_sezione_id, data_inizio)
     VALUES (:bambino, :classe, :inizio)'
);
$inizio = (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d');
$assegna->execute(['bambino' => $figliaA, 'classe' => $classeA, 'inizio' => $inizio]);
$assegna->execute(['bambino' => $figlioB, 'classe' => $classeB, 'inizio' => $inizio]);

$servizio = (new Container())->solleciti();
$metodo = new ReflectionMethod(SollecitoService::class, 'nomiFigli');
$oggi = date('Y-m-d');
$nomiA = (string) $metodo->invoke($servizio, $genitore, $plessoA, $oggi);
$nomiB = (string) $metodo->invoke($servizio, $genitore, $plessoB, $oggi);
if ($nomiA !== 'Alunna Uno' || $nomiB !== 'Alunno Due') {
    throw new RuntimeException('Il sollecito espone nomi di minori di un plesso diverso.');
}

$nomiEstraneo = (string) $metodo->invoke($servizio, $genitore + 10000, $plessoA, $oggi);
if ($nomiEstraneo !== '-') {
    throw new RuntimeException('I nomi sono accessibili a un genitore estraneo.');
}
echo "OK: placeholder alunno limitato al genitore e al plesso alla data corrente.\n";
