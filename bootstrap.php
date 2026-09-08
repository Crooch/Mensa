<?php
/**
 * Bootstrap: autoloader PSR-4 minimale (nessuna dipendenza da Composer, per
 * poter girare su hosting condiviso senza accesso a shell) + configurazione.
 */

declare(strict_types=1);

define('APP_ROOT', __DIR__);

spl_autoload_register(static function (string $classe): void {
    $prefisso = 'App\\';
    if (strncmp($classe, $prefisso, strlen($prefisso)) !== 0) {
        return;
    }
    $relativo = substr($classe, strlen($prefisso));
    $percorso = APP_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativo) . '.php';
    if (is_file($percorso)) {
        require $percorso;
    }
});

$config = require APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
App\Core\Config::inizializza($config);
date_default_timezone_set(App\Core\Config::get('app.timezone', 'Europe/Rome'));

if (App\Core\Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

return $config;
