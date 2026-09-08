<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class BambinoRepository extends Repository
{
    private const SELECT_BASE = '
        SELECT b.*, t.nome AS tipologia_nome,
               g.nome AS genitore_nome, g.cognome AS genitore_cognome,
               g.telefono AS genitore_telefono, g.email AS genitore_email,
               ac.id AS assegnazione_id, cs.id AS classe_sezione_id, cs.nome AS classe_nome,
               cs.anno_scolastico, p.id AS plesso_id, p.nome AS plesso_nome,
               p.cutoff_orario, p.fido_buoni
        FROM bambini b
        JOIN genitori g ON g.id = b.genitore_id
        JOIN tipologie_menu t ON t.id = b.tipologia_menu_id
        LEFT JOIN assegnazioni_classe ac
               ON ac.bambino_id = b.id AND ac.data_inizio <= :data
              AND (ac.data_fine IS NULL OR ac.data_fine >= :data)
        LEFT JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
        LEFT JOIN plessi p ON p.id = cs.plesso_id';

    /** Bambini con classe/plesso attivi alla data indicata (storicizzazione). */
    public function elencoPerGenitore(int $genitoreId, string $data, bool $includiCancellati = true): array
    {
        $sql = self::SELECT_BASE . ' WHERE b.genitore_id = :g';
        if (!$includiCancellati) {
            $sql .= ' AND b.cancellato_logicamente = 0';
        }
        return $this->tutti($sql . ' ORDER BY b.cognome, b.nome', ['g' => $genitoreId, 'data' => $data]);
    }

    public function trova(int $id, string $data): ?array
    {
        return $this->uno(self::SELECT_BASE . ' WHERE b.id = :id', ['id' => $id, 'data' => $data]);
    }

    public function elencoPerClasse(int $classeSezioneId, string $data): array
    {
        return $this->tutti(
            self::SELECT_BASE . ' WHERE cs.id = :classe AND b.cancellato_logicamente = 0 ORDER BY b.cognome, b.nome',
            ['classe' => $classeSezioneId, 'data' => $data]
        );
    }

    /** Bambini attivi in un plesso alla data: usato dal job giornaliero. */
    public function elencoPerPlesso(int $plessoId, string $data): array
    {
        return $this->tutti(
            self::SELECT_BASE . ' WHERE p.id = :plesso AND b.cancellato_logicamente = 0 ORDER BY cs.nome, b.cognome',
            ['plesso' => $plessoId, 'data' => $data]
        );
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('bambini', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('bambini', $id, $dati);
    }

    public function cancellaLogicamente(int $id): void
    {
        $this->aggiorna('bambini', $id, [
            'cancellato_logicamente' => 1,
            'data_cancellazione'     => date('Y-m-d H:i:s'),
        ]);
    }
}
