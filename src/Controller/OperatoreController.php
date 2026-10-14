<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/** API area Operatore comunale (ereditata anche dall'Admin). */
final class OperatoreController
{
    public function __construct(private Container $c)
    {
    }

    public function cercaFamiglie(Request $r, array $utente): array
    {
        return $this->c->anagrafica()->cercaGenitori(
            $r->query('q'),
            (string) $r->query('includi_cancellati') === '1'
        );
    }

    public function schedaFamiglia(Request $r, array $utente): array
    {
        return $this->c->anagrafica()->schedaFamiglia((int) $r->parametro('id'));
    }

    public function creaGenitore(Request $r, array $utente): array
    {
        return ['id' => $this->c->anagrafica()->creaGenitore($r->corpo())];
    }

    public function modificaGenitore(Request $r, array $utente): array
    {
        $this->c->anagrafica()->modificaGenitore((int) $r->parametro('id'), $r->corpo());
        return ['aggiornato' => true];
    }

    public function cancellaGenitore(Request $r, array $utente): array
    {
        $this->c->anagrafica()->cancellaGenitore((int) $r->parametro('id'));
        return ['cancellato_logicamente' => true];
    }

    public function creaBambino(Request $r, array $utente): array
    {
        return ['id' => $this->c->anagrafica()->creaBambino($r->corpo())];
    }

    public function modificaBambino(Request $r, array $utente): array
    {
        $this->c->anagrafica()->modificaBambino((int) $r->parametro('id'), $r->corpo());
        return ['aggiornato' => true];
    }

    public function cancellaBambino(Request $r, array $utente): array
    {
        $this->c->anagrafica()->cancellaBambino((int) $r->parametro('id'));
        return ['cancellato_logicamente' => true];
    }

    /** Cambio classe con storicizzazione automatica dell'assegnazione precedente. */
    public function assegnaClasse(Request $r, array $utente): array
    {
        $id = $this->c->anagrafica()->assegnaClasse(
            (int) $r->parametro('id'),
            (int) $r->obbligatorio('classe_sezione_id'),
            $r->input('data_inizio')
        );
        return ['assegnazione_id' => $id];
    }

    public function storicoClassi(Request $r, array $utente): array
    {
        return $this->c->anagrafica()->storicoClassi((int) $r->parametro('id'));
    }

    /** Preview della tariffa applicata (per il form acquisto: importo non modificabile). */
    public function tariffaAttiva(Request $r, array $utente): array
    {
        return $this->c->tariffe()->tariffaAttiva(
            (int) $r->query('genitore_id'),
            (int) $r->query('plesso_id'),
            $r->query('data')
        );
    }

    public function acquistaBuoni(Request $r, array $utente): array
    {
        return $this->c->creditoService()->acquistaBuoni(
            (int) $r->obbligatorio('genitore_id'),
            (int) $r->obbligatorio('plesso_id'),
            (int) $r->obbligatorio('numero_buoni'),
            (int) $utente['id'],
            $r->input('note'),
            $r->input('richiesta_id') !== null ? (string) $r->input('richiesta_id') : null
        );
    }

    public function rettificaCrediti(Request $r, array $utente): array
    {
        return ['id' => $this->c->creditoService()->rettifica(
            (int) $r->obbligatorio('genitore_id'),
            (int) $r->obbligatorio('plesso_id'),
            (int) $r->obbligatorio('delta'),
            (string) $r->obbligatorio('note'),
            (int) $utente['id']
        )];
    }

    public function creaOverrideTariffa(Request $r, array $utente): array
    {
        return ['id' => $this->c->tariffe()->registraOverride(
            (int) $r->obbligatorio('genitore_id'),
            (int) $r->obbligatorio('plesso_id'),
            (float) $r->obbligatorio('tariffa'),
            (string) $r->obbligatorio('motivazione'),
            (int) $utente['id'],
            $r->input('data_inserimento')
        )];
    }

    /** Situazione crediti di tutte le famiglie, ordinata dai saldi peggiori. */
    public function situazioneCrediti(Request $r, array $utente): array
    {
        return $this->c->creditoService()->situazioneGenerale();
    }

    public function logSolleciti(Request $r, array $utente): array
    {
        return $this->c->solleciti()->logSolleciti();
    }
}
