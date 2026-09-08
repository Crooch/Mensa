<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/**
 * API area Scuola: assenze di massa e verifica/override delle presenze.
 * Le utenze "scuola" sono limitate al proprio plesso (utenti.plesso_id);
 * Admin/Operatore possono operare su tutti i plessi.
 */
final class ScuolaController
{
    public function __construct(private Container $c)
    {
    }

    private function plessoVincolato(array $utente): ?int
    {
        return $utente['ruolo'] === 'scuola' && $utente['plesso_id'] !== null ? (int) $utente['plesso_id'] : null;
    }

    public function classi(Request $r, array $utente): array
    {
        $plessoId = $this->plessoVincolato($utente) ?? ($r->query('plesso_id') !== null ? (int) $r->query('plesso_id') : null);
        return $this->c->classi()->elenco($plessoId, $r->query('anno_scolastico'));
    }

    public function vistaGiornaliera(Request $r, array $utente): array
    {
        return $this->c->presenzaService()->vistaClasse(
            (int) $r->parametro('id'),
            (string) ($r->query('data') ?? date('Y-m-d')),
            $this->plessoVincolato($utente)
        );
    }

    public function assenzaMassa(Request $r, array $utente): array
    {
        return ['id' => $this->c->presenzaService()->dichiaraAssenzaMassa(
            (int) $r->obbligatorio('classe_sezione_id'),
            (string) $r->obbligatorio('data'),
            (string) $r->obbligatorio('motivo'),
            (int) $utente['id'],
            $this->plessoVincolato($utente)
        )];
    }

    public function elencoAssenzeMassa(Request $r, array $utente): array
    {
        $plessoId = $this->plessoVincolato($utente) ?? ($r->query('plesso_id') !== null ? (int) $r->query('plesso_id') : null);
        return $this->c->presenzaService()->elencoAssenzeMassa(
            $plessoId,
            (string) ($r->query('data_da') ?? date('Y-m-d', strtotime('-30 days'))),
            (string) ($r->query('data_a') ?? date('Y-m-d', strtotime('+30 days')))
        );
    }

    /** Override motivato della presenza di un singolo alunno. */
    public function override(Request $r, array $utente): array
    {
        $esito = $this->c->presenzaService()->overrideScuola(
            (int) $r->obbligatorio('bambino_id'),
            (string) $r->obbligatorio('data'),
            (string) $r->obbligatorio('stato'),
            (string) $r->obbligatorio('motivazione'),
            (int) $utente['id'],
            $this->plessoVincolato($utente)
        );
        return [
            'stato_effettivo' => $esito['stato_effettivo'],
            'fuori_cutoff'    => (int) $esito['fuori_cutoff'] === 1,
            'effetto'         => $esito['effetto'],
        ];
    }
}
