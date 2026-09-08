<?php
/**
 * Router per il server integrato di PHP (solo sviluppo/verifica):
 *   php -S 0.0.0.0:8080 -t public bin/router_dev.php
 * In produzione (Apache su hosting condiviso) si usa public/.htaccess.
 */
$percorso = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $percorso);
if ($percorso !== '/' && is_file($file)) {
    return false; // file statico servito direttamente
}
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
