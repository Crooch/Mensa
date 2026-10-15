<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
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
        return $this->c->job()->eseguiScheduler($r->query('data'));
    }

    /** "Esegui ora" per un singolo plesso (test/recupero manuale). */
    public function eseguiPlesso(Request $r, array $utente): array
    {
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
}
