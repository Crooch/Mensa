<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/** Controller sottile: valida gli input minimi e delega ai servizi. */
final class AuthController
{
    public function __construct(private Container $c)
    {
    }

    public function login(Request $r): array
    {
        return $this->c->auth()->login((string) $r->obbligatorio('username'), (string) $r->obbligatorio('password'));
    }

    public function logout(Request $r, array $utente): array
    {
        $token = $r->tokenBearer();
        if ($token !== null) {
            $this->c->auth()->logout($token);
        }
        return ['disconnesso' => true];
    }

    public function profilo(Request $r, array $utente): array
    {
        return $this->c->auth()->profilo($utente);
    }

    public function cambiaPassword(Request $r, array $utente): array
    {
        $this->c->auth()->cambiaPassword((int) $utente['id'], (string) $r->obbligatorio('password'));
        return ['aggiornata' => true];
    }
}
