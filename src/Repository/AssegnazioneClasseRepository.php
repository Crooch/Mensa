<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class AssegnazioneClasseRepository extends Repository
{
    /** Storico completo delle assegnazioni di un bambino. */
    public function storicoBambino(int $bambinoId): array
    {
        return $this->tutti(
            'SELECT ac.*, cs.nome AS classe_nome, cs.anno_scolastico, p.nome AS plesso_nome, p.id AS plesso_id
             FROM assegnazioni_classe ac
             JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
             JOIN plessi p ON p.id = cs.plesso_id
             WHERE ac.bambino_id = :b
             ORDER BY ac.data_inizio DESC, ac.id DESC',
            ['b' => $bambinoId]
        );
    }

    /** Assegnazione attiva alla data indicata (nessun campo "classe attuale" sul bambino). */
    public function attivaAllaData(int $bambinoId, string $data): ?array
    {
        return $this->uno(
            'SELECT ac.*, cs.plesso_id, cs.nome AS classe_nome
             FROM assegnazioni_classe ac
             JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
             WHERE ac.bambino_id = :b AND ac.data_inizio <= :d AND (ac.data_fine IS NULL OR ac.data_fine >= :d)
             ORDER BY ac.data_inizio DESC LIMIT 1',
            ['b' => $bambinoId, 'd' => $data]
        );
    }

    public function chiudiCorrente(int $bambinoId, string $dataFine): void
    {
        $this->esegui(
            'UPDATE assegnazioni_classe SET data_fine = :df WHERE bambino_id = :b AND data_fine IS NULL',
            ['df' => $dataFine, 'b' => $bambinoId]
        );
    }

    public function crea(int $bambinoId, int $classeSezioneId, string $dataInizio): int
    {
        return $this->inserisci('assegnazioni_classe', [
            'bambino_id'        => $bambinoId,
            'classe_sezione_id' => $classeSezioneId,
            'data_inizio'       => $dataInizio,
        ]);
    }
}
