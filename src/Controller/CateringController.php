<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;

/**
 * API area Catering: report pasti (sola lettura), pubblicazione menu, feedback.
 * Nessun endpoint di quest'area espone dati anagrafici, economici o di credito.
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

    /** Upload multipart: descrizione + foto facoltativa. */
    public function pubblicaMenu(Request $r, array $utente): array
    {
        $plessoId = (int) ($_POST['plesso_id'] ?? 0);
        $tipologiaId = (int) ($_POST['tipologia_menu_id'] ?? 0);
        $data = (string) ($_POST['data'] ?? date('Y-m-d'));
        $descrizione = isset($_POST['descrizione']) && $_POST['descrizione'] !== '' ? (string) $_POST['descrizione'] : null;

        $id = $this->c->catering()->pubblicaMenu(
            $plessoId,
            $tipologiaId,
            $data,
            $descrizione,
            $_FILES['foto'] ?? null,
            (int) $utente['id']
        );
        return ['id' => $id];
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
