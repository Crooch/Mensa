<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class ClasseSezioneRepository extends Repository
{
    public function elenco(?int $plessoId = null, ?string $annoScolastico = null, bool $soloAttive = true): array
    {
        $sql = 'SELECT c.*, p.nome AS plesso_nome FROM classi_sezioni c JOIN plessi p ON p.id = c.plesso_id WHERE 1=1';
        $parametri = [];
        if ($soloAttive) {
            $sql .= ' AND c.cancellata_logicamente = 0';
        }
        if ($plessoId !== null) {
            $sql .= ' AND c.plesso_id = :plesso';
            $parametri['plesso'] = $plessoId;
        }
        if ($annoScolastico !== null) {
            $sql .= ' AND c.anno_scolastico = :anno';
            $parametri['anno'] = $annoScolastico;
        }
        return $this->tutti($sql . ' ORDER BY p.nome, c.anno_scolastico DESC, c.nome', $parametri);
    }

    public function trova(int $id): ?array
    {
        return $this->uno(
            'SELECT c.*, p.nome AS plesso_nome, p.cutoff_orario, p.fido_buoni
             FROM classi_sezioni c JOIN plessi p ON p.id = c.plesso_id WHERE c.id = :id',
            ['id' => $id]
        );
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('classi_sezioni', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('classi_sezioni', $id, $dati);
    }

    public function cancellaLogicamente(int $id): void
    {
        $this->aggiorna('classi_sezioni', $id, [
            'cancellata_logicamente' => 1,
            'data_cancellazione'     => date('Y-m-d H:i:s'),
        ]);
    }
}
