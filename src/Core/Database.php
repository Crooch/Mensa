<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Connessione PDO condivisa (una per richiesta). */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::get('db.host'),
            (int) Config::get('db.port', 3306),
            Config::get('db.database'),
            Config::get('db.charset', 'utf8mb4')
        );

        self::$pdo = new PDO($dsn, Config::get('db.username'), Config::get('db.password'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statement emulate: consente di riusare lo stesso segnaposto
            // nominato piu' volte nella stessa query (es. :data nei join storicizzati).
            // Il charset e' dichiarato nel DSN, quindi l'escaping resta corretto.
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]);

        return self::$pdo;
    }

    public static function inTransazione(callable $operazione)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $risultato = $operazione($pdo);
            $pdo->commit();
            return $risultato;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
