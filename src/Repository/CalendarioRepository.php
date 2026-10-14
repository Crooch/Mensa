<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

/** Configurazione settimanale e giornate eccezionali per ogni plesso. */
final class CalendarioRepository extends Repository
{
    public function configurazione(int $plessoId): ?array
    {
        return $this->uno(
            'SELECT * FROM calendari_servizio WHERE plesso_id = :plesso',
            ['plesso' => $plessoId]
        );
    }

    public function eccezione(int $plessoId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM eccezioni_calendario WHERE plesso_id = :plesso AND giorno = :giorno',
            ['plesso' => $plessoId, 'giorno' => $data]
        );
    }
}
