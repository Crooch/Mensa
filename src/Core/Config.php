<?php

declare(strict_types=1);

namespace App\Core;

/** Accesso in sola lettura alla configurazione, con notazione a punti. */
final class Config
{
    private static array $valori = [];

    public static function inizializza(array $valori): void
    {
        self::$valori = $valori;
    }

    /** @param mixed $default */
    public static function get(string $chiave, $default = null)
    {
        $nodo = self::$valori;
        foreach (explode('.', $chiave) as $parte) {
            if (!is_array($nodo) || !array_key_exists($parte, $nodo)) {
                return $default;
            }
            $nodo = $nodo[$parte];
        }
        return $nodo;
    }
}
