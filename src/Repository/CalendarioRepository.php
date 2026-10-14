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
        return $this->uno(
            'SELECT * FROM eccezioni_calendario WHERE plesso_id = :plesso AND giorno = :giorno',
            ['plesso' => $plessoId, 'giorno' => $data]
        );
    }

    public function eccezioni(int $plessoId): array
    {
        return $this->tutti(
            'SELECT id, giorno, servizio_attivo, motivazione
             FROM eccezioni_calendario WHERE plesso_id = :plesso ORDER BY giorno DESC LIMIT 150',
            ['plesso' => $plessoId]
        );
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
