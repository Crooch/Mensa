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
