<?php

declare(strict_types=1);

/**
 * Cutover contabile dei BUONI PASTO a T0 sulla base esistente.
 *
 * Questa operazione azzera ESCLUSIVAMENTE i saldi del pool e i movimenti
 * dei buoni, conservando gli ID dei pool e tutti gli altri dati dell'app.
 * Conservare fuori dall'app un backup protetto per obblighi di audit/contabilità.
 *
 * Uso prima del cutover: php bin/reset_ticket_t0.php --dry-run
 * Uso SOLO dopo backup e con app in manutenzione e job disabilitato:
 * php bin/reset_ticket_t0.php --execute --t0=YYYY-MM-DD --backup=/var/backups/mensa/FILE.sql.gz
 *
 * T0 deve coincidere con il giorno di esecuzione in Europe/Rome.
 * Non usare mai questo script su staging o con `bin/seed.php`.
 */
if (PHP_SAPI !== 'cli') {
    exit(2);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$args = array_slice($argv, 1);
$dryRun = $args === ['--dry-run'];
$execute = in_array('--execute', $args, true);
$values = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--t0=')) {
        $values['t0'] = substr($arg, 5);
    } elseif (str_starts_with($arg, '--backup=')) {
        $values['backup'] = substr($arg, 9);
    } elseif (!in_array($arg, ['--dry-run', '--execute'], true)) {
        fwrite(STDERR, "Parametro non riconosciuto.\n");
        exit(2);
    }
}
if (!$dryRun && (!$execute || count($args) !== 3
    || !isset($values['t0'], $values['backup']))) {
    fwrite(STDERR, "Uso: --dry-run OPPURE --execute --t0=YYYY-MM-DD --backup=/var/backups/mensa/FILE.sql.gz\n");
    exit(2);
}
$dbName = (string) Config::get('db.database');
$ci = getenv('CI') === 'true' && getenv('MENSA_TEST_DATABASE') === 'mensa_ci'
    && $dbName === 'mensa_ci';
if ($dbName !== 'mensa_db' && !$ci) {
    fwrite(STDERR, "STOP: database non autorizzato per il T0.\n");
    exit(2);
}

$pdo = Database::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$marker = $pdo->query(
    "SELECT valore FROM impostazioni WHERE chiave = 'contabilita_t0'"
)->fetchColumn();

$statistiche = static function (PDO $db): array {
    return [
        'pool' => (int) $db->query('SELECT COUNT(*) FROM pool_crediti')->fetchColumn(),
        'movimenti' => (int) $db->query('SELECT COUNT(*) FROM movimenti_crediti')->fetchColumn(),
        'saldo_totale' => (int) $db->query('SELECT COALESCE(SUM(saldo), 0) FROM pool_crediti')->fetchColumn(),
    ];
};

if ($dryRun) {
    $s = $statistiche($pdo);
    echo "DRY-RUN (nessuna scrittura): DB=$dbName; pool={$s['pool']}; ",
         "movimenti={$s['movimenti']}; somma_saldi={$s['saldo_totale']}; ",
         "T0_precedente=", ($marker === false ? 'nessuno' : (string) $marker), "\n";
    exit(0);
}

$inManutenzione = Config::get('app.maintenance_mode', false) === true
    || is_file(APP_ROOT . '/storage/maintenance.flag');
if (!$inManutenzione || Config::schedulerAbilitato()) {
    fwrite(STDERR, "STOP: app.maintenance_mode deve essere true e scheduler_enabled false.\n");
    exit(2);
}
$t0 = (string) $values['t0'];
$oggi = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $t0)
    || !checkdate((int) substr($t0, 5, 2), (int) substr($t0, 8, 2), (int) substr($t0, 0, 4))
    || $t0 !== $oggi) {
    fwrite(STDERR, "STOP: la data T0 deve essere la data odierna dell'app ($oggi).\n");
    exit(2);
}
$backup = (string) $values['backup'];
$reale = realpath($backup);
if ($reale === false
    || !($ci ? str_starts_with($reale, '/tmp/mensa-t0-ci-')
             : str_starts_with($reale, '/var/backups/mensa/'))
    || !is_file($reale) || !is_readable($reale)
    || (int) filesize($reale) < 1024
    || !str_ends_with($reale, '.sql.gz')) {
    fwrite(STDERR, "STOP: backup SQL compresso mancante/non accessibile sotto /var/backups/mensa/.\n");
    exit(2);
}
if ($marker !== false) {
    fwrite(STDERR, "STOP: T0 già registrato; non si esegue un secondo azzeramento.\n");
    exit(2);
}
// Se altre tabelle fossero state aggiunte con riferimenti ai movimenti, il
// reset non deve disabilitare le foreign key né violare l'integrità.
$dipendenze = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
       AND REFERENCED_TABLE_NAME = 'movimenti_crediti'"
)->fetchColumn();
if ($dipendenze !== 0) {
    fwrite(STDERR, "STOP: esistono foreign key verso movimenti_crediti; richiede analisi.\n");
    exit(2);
}

$lock = (int) $pdo->query("SELECT GET_LOCK('mensa:ticket:t0', 0)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDERR, "STOP: un altro reset T0 risulta in corso.\n");
    exit(2);
}
try {
    $pdo->beginTransaction();
    $esistente = $pdo->query(
        "SELECT valore FROM impostazioni WHERE chiave = 'contabilita_t0' FOR UPDATE"
    )->fetchColumn();
    if ($esistente !== false) {
        throw new RuntimeException('T0 già registrato: procedura interrotta.');
    }

    // Unica transazione InnoDB. Non eliminare i pool: i loro ID rimangono.
    // Non modificare utenti, anagrafiche, presenze, job, tariffe, email o foto.
    $prima = $statistiche($pdo);
    $pdo->exec('DELETE FROM movimenti_crediti');
    $pdo->exec('UPDATE pool_crediti SET saldo = 0');
    $stmt = $pdo->prepare(
        "INSERT INTO impostazioni (chiave, valore) VALUES ('contabilita_t0', :t0)"
    );
    $stmt->execute(['t0' => $t0]);

    $dopo = $statistiche($pdo);
    if ($dopo['movimenti'] !== 0 || $dopo['saldo_totale'] !== 0
        || $dopo['pool'] !== $prima['pool']) {
        throw new RuntimeException('Controllo contabile T0 non superato.');
    }
    $pdo->commit();
    echo "T0 COMPLETATO: data=$t0; pool_conservati={$dopo['pool']}; ",
         "movimenti_precedenti={$prima['movimenti']}; movimenti_attuali=0; ",
         "somma_saldi_attuale=0.\n";
    echo "Backup antecedente: $reale (conservare fuori dal sito).\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'RESET NON COMPLETATO: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('mensa:ticket:t0')");
}
