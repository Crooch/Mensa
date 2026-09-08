<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class PlessoRepository extends Repository
{
    public function elenco(bool $soloAttivi = true): array
    {
        $where = $soloAttivi ? 'WHERE cancellato_logicamente = 0' : '';
        return $this->tutti("SELECT * FROM plessi {$where} ORDER BY nome");
    }

    public function trova(int $id): ?array
    {
        return $this->uno('SELECT * FROM plessi WHERE id = :id', ['id' => $id]);
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('plessi', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('plessi', $id, $dati);
    }

    /** Cancellazione logica: i dati storici collegati restano consultabili. */
    public function cancellaLogicamente(int $id): void
    {
        $this->aggiorna('plessi', $id, [
            'cancellato_logicamente' => 1,
            'data_cancellazione'     => date('Y-m-d H:i:s'),
        ]);
    }
}
