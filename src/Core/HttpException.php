<?php

declare(strict_types=1);

namespace App\Core;

/** Errore applicativo con codice HTTP: gestito dal front controller. */
class HttpException extends \RuntimeException
{
    private array $dettagli;

    public function __construct(int $codice, string $messaggio, array $dettagli = [])
    {
        parent::__construct($messaggio, $codice);
        $this->dettagli = $dettagli;
    }

    public function dettagli(): array
    {
        return $this->dettagli;
    }

    public static function nonTrovato(string $m = 'Risorsa non trovata'): self
    {
        return new self(404, $m);
    }

    public static function nonValido(string $m, array $dettagli = []): self
    {
        return new self(422, $m, $dettagli);
    }

    public static function nonAutenticato(string $m = 'Autenticazione richiesta'): self
    {
        return new self(401, $m);
    }

    public static function nonAutorizzato(string $m = 'Permessi insufficienti'): self
    {
        return new self(403, $m);
    }
}
