<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\Container;
use App\Core\HttpException;
use App\Core\Request;

/** API del job giornaliero: scheduler, esecuzione forzata, storico esecuzioni. */
final class JobController
{
    public function __construct(private Container $c)
    {
    }

    /** Esegue lo scheduler valutando il cutoff di ciascun plesso. */
    public function scheduler(Request $r, array $utente): array
    {
        $this->richiediAttivazione();
        // Lo scheduler di tutte le scuole riguarda solo la data corrente.
        // Nessun addebito retroattivo massivo tramite parametri HTTP.
        return $this->c->job()->eseguiScheduler();
    }

    /** "Esegui ora" per un singolo plesso (test/recupero manuale). */
    public function eseguiPlesso(Request $r, array $utente): array
    {
        $this->richiediAttivazione();
        return $this->c->job()->eseguiPerPlesso((int) $r->parametro('id'), $r->input('data'));
    }

    /** Monitoraggio della chiusura odierna, nessun addebito. */
    public function stato(Request $r, array $utente): array
    {
        return $this->c->job()->statoGiornata();
    }

    public function esecuzioni(Request $r, array $utente): array
    {
        return $this->c->job()->ultimeEsecuzioni();
    }

    private function richiediAttivazione(): void
    {
        if (Config::get('app.scheduler_enabled', false) !== true) {
            throw new HttpException(503, 'Le chiusure giornaliere non sono ancora abilitate.');
        }
    }
}
