<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router minimale con pattern del tipo /api/bambini/{id}.
 * I controller restano sottili: il router li invoca passando la Request.
 */
final class Router
{
    /** @var array<int, array{metodo:string, pattern:string, azione:callable, ruoli:array}> */
    private array $rotte = [];

    public function aggiungi(string $metodo, string $pattern, callable $azione, array $ruoli = []): void
    {
        $this->rotte[] = [
            'metodo'  => strtoupper($metodo),
            'pattern' => $pattern,
            'azione'  => $azione,
            'ruoli'   => $ruoli,
        ];
    }

    public function get(string $p, callable $a, array $r = []): void
    {
        $this->aggiungi('GET', $p, $a, $r);
    }

    public function post(string $p, callable $a, array $r = []): void
    {
        $this->aggiungi('POST', $p, $a, $r);
    }

    public function put(string $p, callable $a, array $r = []): void
    {
        $this->aggiungi('PUT', $p, $a, $r);
    }

    public function delete(string $p, callable $a, array $r = []): void
    {
        $this->aggiungi('DELETE', $p, $a, $r);
    }

    /** @return array{azione:callable, ruoli:array, parametri:array} */
    public function risolvi(string $metodo, string $percorso): array
    {
        $percorsoTrovato = false;
        foreach ($this->rotte as $rotta) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $rotta['pattern']) . '$#i';
            if (preg_match($regex, $percorso, $m) === 1) {
                $percorsoTrovato = true;
                if ($rotta['metodo'] === $metodo) {
                    $parametri = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                    return ['azione' => $rotta['azione'], 'ruoli' => $rotta['ruoli'], 'parametri' => $parametri];
                }
            }
        }
        throw new HttpException($percorsoTrovato ? 405 : 404, $percorsoTrovato ? 'Metodo non consentito' : 'Endpoint non trovato');
    }
}
