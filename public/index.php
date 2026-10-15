<?php

declare(strict_types=1);

/**
 * Front controller unico: serve la web application (pagine HTML) e le API REST
 * sotto /api. Nessuna logica di business qui: solo routing, autorizzazione e
 * serializzazione della risposta.
 */

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

$richiesta = new Request();
$percorso = $richiesta->percorso();
$container = new Container();

// ---------------------------------------------------------------------
// Endpoint pubblico per lo scheduler invocabile da cron del pannello hosting:
// /scheduler?key=... (nessun token utente, chiave configurata in config)
// ---------------------------------------------------------------------
if ($percorso === '/scheduler') {
    $chiaveAttesa = (string) Config::get('app.scheduler_key');
    if (($richiesta->query('key') ?? '') !== $chiaveAttesa || $chiaveAttesa === '') {
        Response::errore(403, 'Chiave scheduler non valida');
        return;
    }
    try {
        Response::ok($container->job()->eseguiScheduler());
    } catch (\Throwable $e) {
        Response::errore(500, $e->getMessage());
    }
    return;
}

// ---------------------------------------------------------------------
// Foto dei menu servite da /storage (i file stanno fuori dalla document root
// solo per path logico; il controllo di accesso richiede un utente autenticato)
// ---------------------------------------------------------------------
if (strncmp($percorso, '/storage/', 9) === 0) {
    // Le fotografie possono riferirsi a minori e menu dietetici:
    // l'accesso e' consentito soltanto con una sessione applicativa valida.
    try {
        $container->autenticazione()->autorizza($richiesta, []);
    } catch (HttpException $e) {
        Response::errore($e->getCode(), $e->getMessage());
        return;
    }
    $relativo = substr($percorso, 9);
    if (str_contains($relativo, '..')) {
        Response::errore(400, 'Percorso non valido');
        return;
    }
    $base = dirname((string) Config::get('storage.menu_dir'));
    $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);
    if (!is_file($file)) {
        Response::errore(404, 'File non trovato');
        return;
    }
    $tipo = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $tipo);
    header('Cache-Control: private, max-age=3600');
    readfile($file);
    return;
}

// ---------------------------------------------------------------------
// API REST
// ---------------------------------------------------------------------
if (strncmp($percorso, '/api', 4) === 0) {
    header('X-Content-Type-Options: nosniff');
    if ($richiesta->metodo() === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        http_response_code(204);
        return;
    }

    /** @var App\Core\Router $router */
    $router = require APP_ROOT . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';

    try {
        $rotta = $router->risolvi($richiesta->metodo(), $percorso);
        $richiesta->impostaParametri($rotta['parametri']);

        if ($rotta['ruoli'] === []) {
            $risultato = ($rotta['azione'])($richiesta);
        } else {
            $utente = $container->autenticazione()->autorizza($richiesta, $rotta['ruoli']);
            $risultato = ($rotta['azione'])($richiesta, $utente);
        }
        Response::ok($risultato);
    } catch (HttpException $e) {
        Response::errore($e->getCode(), $e->getMessage(), $e->dettagli());
    } catch (\Throwable $e) {
        error_log('[mensa] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        Response::errore(500, Config::get('app.debug') ? $e->getMessage() : 'Errore interno del server');
    }
    return;
}

// ---------------------------------------------------------------------
// Web application: rendering dei template (il resto avviene via API + JS)
// ---------------------------------------------------------------------
$pagine = [
    '/'          => 'login',
    '/login'     => 'login',
    '/genitore'  => 'genitore',
    '/operatore' => 'operatore',
    '/scuola'    => 'scuola',
    '/catering'  => 'catering',
    '/admin'     => 'admin',
];

$vista = $pagine[$percorso] ?? null;
if ($vista === null) {
    http_response_code(404);
    $vista = 'errore404';
}

$basePath = (string) Config::get('app.base_path', '');
$titoloApp = (string) Config::get('app.nome');
require APP_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'View'
    . DIRECTORY_SEPARATOR . $vista . '.php';
