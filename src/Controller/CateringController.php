<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/**
 * API area Catering: report pasti (sola lettura), pubblicazione menu, feedback.
 * Nessun endpoint di quest'area espone dati economici o di credito.
 * Il report pasti include i soli nominativi necessari alle richieste pasta in bianco.
 */
final class CateringController
{
    public function __construct(private Container $c)
    {
    }

    public function report(Request $r, array $utente): array
    {
        $plessoId = $utente['ruolo'] === 'catering' && $utente['plesso_id'] !== null
            ? (int) $utente['plesso_id']
            : ($r->query('plesso_id') !== null ? (int) $r->query('plesso_id') : null);

        return $this->c->catering()->report((string) ($r->query('data') ?? date('Y-m-d')), $plessoId);
    }

    public function menu(Request $r, array $utente): array
    {
        return $this->c->catering()->menuDelGiorno(
            (string) ($r->query('data') ?? date('Y-m-d')),
            $r->query('plesso_id') !== null ? (int) $r->query('plesso_id') : null
        );
    }

    /** Elenco delle variazioni programmate dalla data odierna in avanti. */
    public function variazioniProgrammate(Request $r, array $utente): array
    {
        $pagina = $r->query('pagina');
        return $this->c->catering()->variazioniProgrammate(
            max(1, min(100000, (int) ($pagina ?? 1)))
        );
    }

    /** Annulla una variazione, ripristinando il modello ove disponibile. */
    public function annullaMenuGiornaliero(Request $r, array $utente): array
    {
        $this->c->catering()->annullaVariazioneGiornaliera(
            (int) $r->obbligatorio('plesso_id'),
            (int) $r->obbligatorio('tipologia_menu_id'),
            (string) $r->obbligatorio('data')
        );
        return ['annullato' => true];
    }

    /** Upload multipart: descrizione + foto facoltativa. */
    public function pubblicaMenu(Request $r, array $utente): array
    {
        $plessoId = (int) ($_POST['plesso_id'] ?? 0);
        $tipologiaId = (int) ($_POST['tipologia_menu_id'] ?? 0);
        $data = (string) ($_POST['data'] ?? date('Y-m-d'));
        $descrizione = isset($_POST['descrizione']) && $_POST['descrizione'] !== '' ? (string) $_POST['descrizione'] : null;
        $rimuoviFoto = (string) ($_POST['rimuovi_foto'] ?? '0') === '1';

        $id = $this->c->catering()->pubblicaMenu(
            $plessoId,
            $tipologiaId,
            $data,
            $descrizione,
            $_FILES['foto'] ?? null,
            (int) $utente['id'],
            $rimuoviFoto
        );
        return ['id' => $id];
    }

    /** Torna alla programmazione settimanale, solo per un'eccezione non consolidata. */
    public function ripristinaMenuSettimanale(Request $r, array $utente): array
    {
        $this->c->catering()->ripristinaMenuSettimanale(
            (int) $r->obbligatorio('plesso_id'),
            (int) $r->obbligatorio('tipologia_menu_id'),
            (string) $r->obbligatorio('data')
        );
        return ['ripristinato' => true];
    }

    /** Catalogo di settimane tipo riutilizzabili, una tipologia per modello. */
    public function menuSettimanali(Request $r, array $utente): array
    {
        return $this->c->catering()->elencoMenuSettimanali();
    }

    /** Compatibile con le chiamate JSON già presenti e con multipart foto. */
    private function giorniMenu(Request $r): array
    {
        $valore = $r->obbligatorio('giorni');
        if (is_string($valore)) {
            $valore = json_decode($valore, true);
        }
        if (!is_array($valore)) {
            throw \App\Core\HttpException::nonValido('I giorni del menu non sono validi');
        }
        return $valore;
    }

    private function rimozioniFoto(Request $r): array
    {
        $valore = $r->input('rimuovi_foto', []);
        if (is_string($valore)) $valore = json_decode($valore, true);
        if (!is_array($valore)) {
            throw \App\Core\HttpException::nonValido('Elenco delle foto da rimuovere non valido');
        }
        return $valore;
    }

    private function fileGiorni(): array
    {
        $file = [];
        for ($i = 1; $i <= 7; $i++) {
            if (isset($_FILES['foto_giorno_' . $i])) {
                $file[(string) $i] = $_FILES['foto_giorno_' . $i];
            }
        }
        return $file;
    }

    public function salvaMenuSettimanale(Request $r, array $utente): array
    {
        $id = $this->c->catering()->salvaMenuSettimanale(
            null, (string) $r->obbligatorio('nome'),
            (int) $r->obbligatorio('tipologia_menu_id'),
            $this->giorniMenu($r), (int) $utente['id'],
            $this->fileGiorni(), $this->rimozioniFoto($r), [],
            ($r->input('duplica_da_id') !== null ? (int) $r->input('duplica_da_id') : null)
        );
        return ['id' => $id];
    }

    public function modificaMenuSettimanale(Request $r, array $utente): array
    {
        $id = (int) $r->parametro('id');
        return ['id' => $this->c->catering()->salvaMenuSettimanale(
            $id, (string) $r->obbligatorio('nome'),
            (int) $r->obbligatorio('tipologia_menu_id'),
            $this->giorniMenu($r), (int) $utente['id'],
            $this->fileGiorni(), $this->rimozioniFoto($r)
        )];
    }

    public function duplicaMenuSettimanale(Request $r, array $utente): array
    {
        return ['id' => $this->c->catering()->duplicaMenuSettimanale(
            (int) $r->parametro('id'), (string) $r->obbligatorio('nome'),
            (int) $r->obbligatorio('tipologia_menu_id'),
            (int) $utente['id']
        )];
    }

    public function assegnazioniMenuSettimanali(Request $r, array $utente): array
    {
        return $this->c->catering()->assegnazioniMenuSettimanali();
    }

    public function assegnaMenuSettimanale(Request $r, array $utente): array
    {
        $id = $r->input('menu_id');
        return $this->c->catering()->assegnaMenuSettimanale(
            $id === null ? null : (int) $id,
            (int) $r->obbligatorio('tipologia_menu_id'),
            (array) $r->obbligatorio('plesso_ids'),
            (int) $utente['id']
        );
    }

    public function feedback(Request $r, array $utente): array
    {
        return $this->c->feedback()->elencoCatering([
            'data_da'      => $r->query('data_da'),
            'data_a'       => $r->query('data_a'),
            'plesso_id'    => $r->query('plesso_id'),
            'classe_id'    => $r->query('classe_id'),
            'tipologia_id' => $r->query('tipologia_id'),
        ]);
    }
}
