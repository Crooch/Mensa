<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/** API area Admin: configurazioni di sistema, utenze, email, job. */
final class AdminController
{
    public function __construct(private Container $c)
    {
    }

    /** Dati di riferimento comuni a piu' aree (plessi, tipologie, classi attive). */
    public function riferimenti(Request $r, array $utente): array
    {
        return [
            'plessi'    => $this->c->configurazione()->plessi(),
            'tipologie' => $this->c->configurazione()->tipologie(),
            'classi'    => $this->c->configurazione()->classi(),
        ];
    }

    // ---------------- plessi ----------------

    public function plessi(Request $r, array $utente): array
    {
        return $this->c->configurazione()->plessi((string) $r->query('includi_cancellati') !== '1');
    }

    public function creaPlesso(Request $r, array $utente): array
    {
        return ['id' => $this->c->configurazione()->creaPlesso($r->corpo())];
    }

    public function modificaPlesso(Request $r, array $utente): array
    {
        $this->c->configurazione()->modificaPlesso((int) $r->parametro('id'), $r->corpo());
        return ['aggiornato' => true];
    }

    public function cancellaPlesso(Request $r, array $utente): array
    {
        $this->c->configurazione()->cancellaPlesso((int) $r->parametro('id'));
        return ['cancellato_logicamente' => true];
    }

    // ---------------- calendario servizio mensa ----------------

    public function dettaglioCalendario(Request $r, array $utente): array
    {
        return $this->c->calendario()->dettaglio((int) $r->parametro('id'));
    }

    public function salvaCalendario(Request $r, array $utente): array
    {
        $this->c->calendario()->salvaConfigurazione((int) $r->parametro('id'), $r->corpo());
        return ['salvato' => true];
    }

    public function verificaCalendario(Request $r, array $utente): array
    {
        return $this->c->calendario()->previsto(
            (int) $r->parametro('id'),
            (string) $r->query('data', date('Y-m-d'))
        );
    }

    public function salvaEccezioneCalendario(Request $r, array $utente): array
    {
        $this->c->calendario()->salvaEccezione(
            (int) $r->parametro('id'), (string) $r->parametro('data'), $r->corpo()
        );
        return ['salvata' => true];
    }

    public function cancellaEccezioneCalendario(Request $r, array $utente): array
    {
        $this->c->calendario()->cancellaEccezione(
            (int) $r->parametro('id'), (string) $r->parametro('data')
        );
        return ['cancellata' => true];
    }

    // ---------------- classi ----------------

    public function classi(Request $r, array $utente): array
    {
        return $this->c->configurazione()->classi(
            $r->query('plesso_id') !== null ? (int) $r->query('plesso_id') : null,
            $r->query('anno_scolastico')
        );
    }

    public function creaClasse(Request $r, array $utente): array
    {
        return ['id' => $this->c->configurazione()->creaClasse($r->corpo())];
    }

    public function modificaClasse(Request $r, array $utente): array
    {
        $this->c->configurazione()->modificaClasse((int) $r->parametro('id'), $r->corpo());
        return ['aggiornato' => true];
    }

    public function cancellaClasse(Request $r, array $utente): array
    {
        $this->c->configurazione()->cancellaClasse((int) $r->parametro('id'));
        return ['cancellata_logicamente' => true];
    }

    // ---------------- tipologie menu ----------------

    public function tipologie(Request $r, array $utente): array
    {
        return $this->c->configurazione()->tipologie((string) $r->query('includi_cancellate') !== '1');
    }

    public function creaTipologia(Request $r, array $utente): array
    {
        return ['id' => $this->c->configurazione()->creaTipologia((string) $r->obbligatorio('nome'))];
    }

    public function modificaTipologia(Request $r, array $utente): array
    {
        $this->c->configurazione()->modificaTipologia((int) $r->parametro('id'), (string) $r->obbligatorio('nome'));
        return ['aggiornata' => true];
    }

    public function cancellaTipologia(Request $r, array $utente): array
    {
        $this->c->configurazione()->cancellaTipologia((int) $r->parametro('id'));
        return ['cancellata_logicamente' => true];
    }

    // ---------------- utenze ----------------

    public function utenti(Request $r, array $utente): array
    {
        return $this->c->utenti()->elenco();
    }

    public function creaUtente(Request $r, array $utente): array
    {
        $plessoId = $r->input('plesso_id');
        return ['id' => $this->c->auth()->creaUtente(
            (string) $r->obbligatorio('ruolo'),
            (string) $r->obbligatorio('username'),
            (string) $r->obbligatorio('password'),
            $r->input('nome_completo'),
            $plessoId !== null ? (int) $plessoId : null
        )];
    }

    public function modificaUtente(Request $r, array $utente): array
    {
        $id = (int) $r->parametro('id');
        $dati = [];
        if ($r->input('attivo') !== null) {
            $dati['attivo'] = (int) $r->input('attivo');
        }
        if ($r->input('nome_completo') !== null) {
            $dati['nome_completo'] = (string) $r->input('nome_completo');
        }
        if ($r->input('plesso_id') !== null) {
            $dati['plesso_id'] = (int) $r->input('plesso_id');
        }
        if ($dati !== []) {
            $this->c->utenti()->modifica($id, $dati);
        }
        if ($r->input('password') !== null) {
            $this->c->auth()->cambiaPassword($id, (string) $r->input('password'));
        }
        return ['aggiornato' => true];
    }

    // ---------------- email ----------------

    public function modelli(Request $r, array $utente): array
    {
        return $this->c->configurazione()->modelli();
    }

    public function salvaModello(Request $r, array $utente): array
    {
        $this->c->configurazione()->salvaModello(
            (int) $r->obbligatorio('plesso_id'),
            (string) $r->obbligatorio('oggetto'),
            (string) $r->obbligatorio('testo'),
            (bool) ($r->input('attivo') ?? true)
        );
        return ['salvato' => true];
    }

    public function destinatari(Request $r, array $utente): array
    {
        return $this->c->configurazione()->destinatariFissi();
    }

    public function creaDestinatario(Request $r, array $utente): array
    {
        return ['id' => $this->c->configurazione()->creaDestinatario(
            (string) $r->obbligatorio('etichetta'),
            (string) $r->obbligatorio('email')
        )];
    }

    public function modificaDestinatario(Request $r, array $utente): array
    {
        $this->c->configurazione()->modificaDestinatario((int) $r->parametro('id'), $r->corpo());
        return ['aggiornato' => true];
    }

    public function impostazioni(Request $r, array $utente): array
    {
        return $this->c->configurazione()->impostazioni();
    }

    public function salvaImpostazioni(Request $r, array $utente): array
    {
        $this->c->configurazione()->salvaImpostazioni($r->corpo());
        return ['salvate' => true];
    }

    public function emailDiProva(Request $r, array $utente): array
    {
        $this->c->solleciti()->inviaEmailDiProva((string) $r->obbligatorio('email'));
        return ['inviata' => true];
    }
}
