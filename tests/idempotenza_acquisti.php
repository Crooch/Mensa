<?php

declare(strict_types=1);

// Test eseguito soltanto sul database temporaneo della CI.
if (getenv('CI') !== 'true' || getenv('MENSA_TEST_DATABASE') !== 'mensa_ci') {
    fwrite(STDERR, "Il test e' consentito solo nel database CI isolato.\n");
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
$plesso = (int) $pdo->query('SELECT id FROM plessi ORDER BY id LIMIT 1')->fetchColumn();
if ($plesso < 1) {
    throw new RuntimeException('Manca il plesso sintetico.');
}
$pdo->exec("INSERT INTO utenti (ruolo, username, password_hash) VALUES ('operatore', 'operatore_idempotente_ci', 'hash_sintetico')");
$operatore = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO genitori (nome, cognome, telefono) VALUES ('Famiglia', 'Test Idempotenza', '0000000000')");
$genitore = (int) $pdo->lastInsertId();
$chiave = 'test-acquisto-idempotente-2026-001';

$prima = $c->creditoService()->acquistaBuoni($genitore, $plesso, 3, $operatore, 'test in CI', $chiave);
$seconda = $c->creditoService()->acquistaBuoni($genitore, $plesso, 3, $operatore, 'test in CI', $chiave);
if ($prima['movimento_id'] !== $seconda['movimento_id'] || !$seconda['gia_registrato']) {
    throw new RuntimeException('Retry non idempotente: movimento diverso.');
}
$pool = $c->crediti()->trovaPool($genitore, $plesso);
if ((int) $pool['saldo'] !== 3) {
    throw new RuntimeException('Saldo erroneamente aumentato due volte.');
}
$stmt = $pdo->prepare('SELECT COUNT(*) FROM movimenti_crediti WHERE richiesta_id = ?');
$stmt->execute([$chiave]);
if ((int) $stmt->fetchColumn() !== 1) {
    throw new RuntimeException('Sono presenti movimenti duplicati.');
}
try {
    $c->creditoService()->acquistaBuoni($genitore, $plesso, 10, $operatore, 'dati diversi', $chiave);
    throw new RuntimeException('Chiave duplicata con importo diverso accettata.');
} catch (HttpException $e) {
    if ($e->getCode() !== 422) {
        throw $e;
    }
}
$chiaveRettifica = 'test-rettifica-idempotente-2026-002';
$primaRettifica = $c->creditoService()->rettifica(
    $genitore, $plesso, -2, 'Correzione sintetica in CI', $operatore, $chiaveRettifica
);
$secondaRettifica = $c->creditoService()->rettifica(
    $genitore, $plesso, -2, 'Correzione sintetica in CI', $operatore, $chiaveRettifica
);
if ($primaRettifica !== $secondaRettifica) {
    throw new RuntimeException('Rettifica ripetuta con movimento diverso.');
}
$pool = $c->crediti()->trovaPool($genitore, $plesso);
if ((int) $pool['saldo'] !== 1) {
    throw new RuntimeException('Saldo modificato due volte dalla rettifica.');
}
try {
    $c->creditoService()->rettifica($genitore, $plesso, -3, 'Dati diversi', $operatore, $chiaveRettifica);
    throw new RuntimeException('Token rettifica riutilizzato con dati diversi.');
} catch (HttpException $e) {
    if ($e->getCode() !== 422) throw $e;
}
echo "OK: acquisto e rettifica ripetuti non duplicano movimenti o variazioni di saldo.\n";
