<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class TipologiaMenuRepository extends Repository
{
    public function elenco(bool $soloAttive = true): array
    {
        $where = $soloAttive ? 'WHERE cancellata_logicamente = 0' : '';
        return $this->tutti("SELECT * FROM tipologie_menu {$where} ORDER BY nome");
    }

    public function trova(int $id): ?array
    {
        return $this->uno('SELECT * FROM tipologie_menu WHERE id = :id', ['id' => $id]);
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('tipologie_menu', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('tipologie_menu', $id, $dati);
    }

    public function cancellaLogicamente(int $id): void
    {
        $this->aggiorna('tipologie_menu', $id, [
            'cancellata_logicamente' => 1,
            'data_cancellazione'     => date('Y-m-d H:i:s'),
        ]);
    }
}
