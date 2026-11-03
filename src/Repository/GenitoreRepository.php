<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class GenitoreRepository extends Repository
{
    public function trova(int $id): ?array
    {
        return $this->uno(
            'SELECT g.*, u.username, u.attivo AS utente_attivo
             FROM genitori g LEFT JOIN utenti u ON u.id = g.utente_id
             WHERE g.id = :id',
            ['id' => $id]
        );
    }

    public function trovaPerUtente(int $utenteId): ?array
    {
        return $this->uno('SELECT * FROM genitori WHERE utente_id = :u LIMIT 1', ['u' => $utenteId]);
    }

    /** Ricerca per cognome/nome/telefono/email; include i cancellati solo se richiesto. */
    public function cerca(?string $testo, bool $includiCancellati = false): array
    {
        $sql = 'SELECT g.*, u.username FROM genitori g LEFT JOIN utenti u ON u.id = g.utente_id WHERE 1=1';
        $parametri = [];
        if (!$includiCancellati) {
            $sql .= ' AND g.cancellato_logicamente = 0';
        }
        if ($testo !== null && $testo !== '') {
            $sql .= ' AND (g.cognome LIKE :q OR g.nome LIKE :q OR g.telefono LIKE :q OR g.email LIKE :q)';
            $parametri['q'] = '%' . $testo . '%';
        }
        return $this->tutti($sql . ' ORDER BY g.cognome, g.nome LIMIT 200', $parametri);
    }

    /** Elenco paginato completo; ordinamento stabile anche in presenza di omonimie. */
    public function pagina(string $testo, string $stato, int $pagina, int $perPagina): array
    {
        $where = ' WHERE 1=1';
        $parametri = [];
        if ($stato !== 'tutte') {
            $where .= ' AND g.cancellato_logicamente = :cancellato';
            $parametri['cancellato'] = $stato === 'disattivate' ? 1 : 0;
        }
        if ($testo !== '') {
            $where .= " AND (g.cognome LIKE :q ESCAPE '!' OR g.nome LIKE :q ESCAPE '!'"
                . " OR CONCAT(g.cognome, ' ', g.nome) LIKE :q ESCAPE '!'"
                . " OR CONCAT(g.nome, ' ', g.cognome) LIKE :q ESCAPE '!'"
                . " OR g.telefono LIKE :q ESCAPE '!' OR g.email LIKE :q ESCAPE '!')";
            $parametri['q'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $testo) . '%';
        }
        $totale = (int) $this->valore('SELECT COUNT(*) FROM genitori g' . $where, $parametri);
        $pagine = max(1, (int) ceil($totale / $perPagina));
        $pagina = min($pagina, $pagine);
        $offset = ($pagina - 1) * $perPagina;
        // Limite e offset provengono esclusivamente da interi validati dal servizio.
        $righe = $this->tutti('SELECT g.id, g.nome, g.cognome, g.telefono, g.email, g.cancellato_logicamente'
            . ' FROM genitori g' . $where . ' ORDER BY g.cognome, g.nome, g.id'
            . ' LIMIT ' . $perPagina . ' OFFSET ' . $offset, $parametri);
        return ['famiglie' => $righe, 'totale' => $totale, 'pagina' => $pagina,
            'per_pagina' => $perPagina, 'pagine' => $pagine];
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('genitori', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('genitori', $id, $dati);
    }

    public function cancellaLogicamente(int $id): void
    {
        $this->aggiorna('genitori', $id, [
            'cancellato_logicamente' => 1,
            'data_cancellazione'     => date('Y-m-d H:i:s'),
        ]);
    }
}
