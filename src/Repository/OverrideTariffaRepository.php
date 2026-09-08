<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class OverrideTariffaRepository extends Repository
{
    /**
     * Override attivo = record con data_inserimento massima <= data di riferimento
     * per la coppia (genitore, plesso). Nessuna data_fine: la storia e' la sequenza.
     */
    public function attivoAllaData(int $genitoreId, int $plessoId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM override_tariffe
             WHERE genitore_id = :g AND plesso_id = :p AND data_inserimento <= :d
             ORDER BY data_inserimento DESC, id DESC LIMIT 1',
            ['g' => $genitoreId, 'p' => $plessoId, 'd' => $data]
        );
    }

    public function storicoGenitore(int $genitoreId): array
    {
        return $this->tutti(
            'SELECT o.*, p.nome AS plesso_nome, u.username AS inserito_da
             FROM override_tariffe o
             JOIN plessi p ON p.id = o.plesso_id
             LEFT JOIN utenti u ON u.id = o.utente_id
             WHERE o.genitore_id = :g
             ORDER BY o.data_inserimento DESC, o.id DESC',
            ['g' => $genitoreId]
        );
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('override_tariffe', $dati);
    }
}
