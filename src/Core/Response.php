<?php

declare(strict_types=1);

namespace App\Core;

/** Risposte JSON: unico formato di uscita del layer API. */
final class Response
{
    public static function json($dati, int $codice = 200): void
    {
        http_response_code($codice);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function ok($dati = null, array $extra = []): void
    {
        self::json(array_merge(['ok' => true, 'dati' => $dati], $extra));
    }

    public static function errore(int $codice, string $messaggio, array $dettagli = []): void
    {
        self::json(['ok' => false, 'errore' => $messaggio, 'dettagli' => $dettagli], $codice);
    }
}
