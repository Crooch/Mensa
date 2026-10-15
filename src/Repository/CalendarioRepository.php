<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

/** Configurazione settimanale ed eccezioni, senza modificare gli storici. */
final class CalendarioRepository extends Repository
{
    public function configurazione(int $plessoId): ?array
    {
        return $this->uno(
            'SELECT * FROM calendari_servizio WHERE plesso_id = :plesso',
            ['plesso' => $plessoId]
        );
    }

    public function salvaConfigurazione(int $plessoId, array $dati): void
    {
        $campi = [
            'data_inizio', 'data_fine', 'lunedi', 'martedi', 'mercoledi',
            'giovedi', 'venerdi', 'sabato', 'domenica', 'escludi_festivita_nazionali'
        ];
        $parametri = ['plesso_id' => $plessoId];
        foreach ($campi as $campo) {
            $parametri[$campo] = $dati[$campo];
        }
        $colonne = implode(', ', array_keys($parametri));
        $segnaposto = implode(', ', array_map(static fn (string $c): string => ':' . $c, array_keys($parametri)));
        $aggiornamenti = implode(', ', array_map(
            static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $campi
        ));
        $this->esegui(
            "INSERT INTO calendari_servizio ({$colonne}) VALUES ({$segnaposto}) ON DUPLICATE KEY UPDATE {$aggiornamenti}",
            $parametri
        );
    }

    public function eccezione(int $plessoId, string $data): ?array
    {
        // Quando due intervalli condividono soltanto un confine, per quella
        // singola data prevale quello che comincia più tardi.
        return $this->uno(
            'SELECT * FROM eccezioni_calendario
             WHERE plesso_id = :plesso AND giorno <= :data_inizio
               AND COALESCE(giorno_fine, giorno) >= :data_fine
             ORDER BY giorno DESC, id DESC LIMIT 1',
            ['plesso' => $plessoId, 'data_inizio' => $data, 'data_fine' => $data]
        );
    }

    public function eccezioni(int $plessoId): array
    {
        return $this->tutti(
            'SELECT id, giorno, COALESCE(giorno_fine, giorno) AS giorno_fine,
                    servizio_attivo, motivazione
             FROM eccezioni_calendario WHERE plesso_id = :plesso
             ORDER BY giorno DESC, id DESC LIMIT 150',
            ['plesso' => $plessoId]
        );
    }

    public function trovaPeriodo(int $plessoId, int $periodoId): ?array
    {
        return $this->uno(
            'SELECT *, COALESCE(giorno_fine, giorno) AS fine_effettiva
             FROM eccezioni_calendario WHERE plesso_id = :plesso AND id = :id',
            ['plesso' => $plessoId, 'id' => $periodoId]
        );
    }

    /** Restituisce gli intervalli candidati a sovrapporsi, senza bloccare o scrivere. */
    public function periodiIntersecanti(int $plessoId, string $inizio, string $fine, ?int $escludiId = null): array
    {
        $sql = 'SELECT id, giorno, COALESCE(giorno_fine, giorno) AS giorno_fine
                FROM eccezioni_calendario
                WHERE plesso_id = :plesso AND giorno <= :fine
                  AND COALESCE(giorno_fine, giorno) >= :inizio';
        $parametri = ['plesso' => $plessoId, 'fine' => $fine, 'inizio' => $inizio];
        if ($escludiId !== null) {
            $sql .= ' AND id <> :escludi_id';
            $parametri['escludi_id'] = $escludiId;
        }
        return $this->tutti($sql, $parametri);
    }

    public function salvaPeriodo(int $plessoId, string $inizio, string $fine, bool $attivo, string $motivo, ?int $id = null): int
    {
        $valori = [
            'plesso_id' => $plessoId, 'giorno' => $inizio,
            'giorno_fine' => $fine === $inizio ? null : $fine,
            'servizio_attivo' => $attivo ? 1 : 0, 'motivazione' => $motivo
        ];
        if ($id !== null) {
            unset($valori['plesso_id']);
            $this->aggiorna('eccezioni_calendario', $id, $valori);
            return $id;
        }
        return $this->inserisci('eccezioni_calendario', $valori);
    }

    public function cancellaPeriodo(int $plessoId, int $id): void
    {
        $this->esegui(
            'DELETE FROM eccezioni_calendario WHERE plesso_id = :plesso AND id = :id',
            ['plesso' => $plessoId, 'id' => $id]
        );
    }

    /** Serializza modifiche dello stesso calendario fra richieste simultanee. */
    public function acquisisciLockPeriodi(int $plessoId): bool
    {
        return (int) $this->valore('SELECT GET_LOCK(:chiave, 5)', [
            'chiave' => 'mensa:periodi:' . $plessoId
        ]) === 1;
    }

    public function rilasciaLockPeriodi(int $plessoId): void
    {
        $this->valore('SELECT RELEASE_LOCK(:chiave)', [
            'chiave' => 'mensa:periodi:' . $plessoId
        ]);
    }

    public function salvaEccezione(int $plessoId, string $data, bool $attivo, string $motivo): void
    {
        $this->esegui(
            'INSERT INTO eccezioni_calendario (plesso_id, giorno, servizio_attivo, motivazione)
             VALUES (:plesso, :giorno, :attivo, :motivazione)
             ON DUPLICATE KEY UPDATE servizio_attivo = VALUES(servizio_attivo),
                                     motivazione = VALUES(motivazione)',
            ['plesso' => $plessoId, 'giorno' => $data, 'attivo' => $attivo ? 1 : 0, 'motivazione' => $motivo]
        );
    }

    public function cancellaEccezione(int $plessoId, string $data): void
    {
        $this->esegui(
            'DELETE FROM eccezioni_calendario WHERE plesso_id = :plesso AND giorno = :giorno',
            ['plesso' => $plessoId, 'giorno' => $data]
        );
    }
}
