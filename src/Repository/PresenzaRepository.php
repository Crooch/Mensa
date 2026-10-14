<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class PresenzaRepository extends Repository
{
    // ---------------- assenze di massa ----------------

    public function assenzaMassa(int $classeSezioneId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM assenze_massa WHERE classe_sezione_id = :c AND data = :d',
            ['c' => $classeSezioneId, 'd' => $data]
        );
    }

    public function creaAssenzaMassa(array $dati): int
    {
        return $this->inserisci('assenze_massa', $dati);
    }

    public function elencoAssenzeMassa(?int $plessoId, string $dataDa, string $dataA): array
    {
        $sql = 'SELECT am.*, cs.nome AS classe_nome, p.nome AS plesso_nome, u.username AS inserita_da
                FROM assenze_massa am
                JOIN classi_sezioni cs ON cs.id = am.classe_sezione_id
                JOIN plessi p ON p.id = cs.plesso_id
                LEFT JOIN utenti u ON u.id = am.utente_id
                WHERE am.data BETWEEN :da AND :a';
        $parametri = ['da' => $dataDa, 'a' => $dataA];
        if ($plessoId !== null) {
            $sql .= ' AND p.id = :p';
            $parametri['p'] = $plessoId;
        }
        return $this->tutti($sql . ' ORDER BY am.data DESC, p.nome, cs.nome', $parametri);
    }

    // ---------------- presenze giornaliere ----------------

    public function trovaPresenza(int $bambinoId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM presenze_giornaliere WHERE bambino_id = :b AND data = :d',
            ['b' => $bambinoId, 'd' => $data]
        );
    }

    public function creaPresenza(array $dati): int
    {
        return $this->inserisci('presenze_giornaliere', $dati);
    }

    public function modificaPresenza(int $id, array $dati): void
    {
        $this->aggiorna('presenze_giornaliere', $id, $dati);
    }

    public function calendarioBambino(int $bambinoId, string $dataDa, string $dataA): array
    {
        return $this->tutti(
            'SELECT pg.*, ud.username AS dichiarato_da, uo.username AS override_da
             FROM presenze_giornaliere pg
             LEFT JOIN utenti ud ON ud.id = pg.dichiarazione_utente_id
             LEFT JOIN utenti uo ON uo.id = pg.override_utente_id
             WHERE pg.bambino_id = :b AND pg.data BETWEEN :da AND :a
             ORDER BY pg.data',
            ['b' => $bambinoId, 'da' => $dataDa, 'a' => $dataA]
        );
    }

    /**
     * Report per plesso, classe/sezione e tipologia alimentare.
     * Anche prima del primo job, gli alunni senza record sono presenti per
     * default, salvo un'assenza di massa della rispettiva classe.
     */
    public function reportPasti(string $data, ?int $plessoId = null): array
    {
        $sql = "SELECT p.id AS plesso_id, p.nome AS plesso_nome,
                       cs.id AS classe_id, cs.nome AS classe_nome,
                       t.id AS tipologia_id, t.nome AS tipologia_nome,
                       COUNT(*) AS pasti
                FROM assegnazioni_classe ac
                JOIN bambini b ON b.id = ac.bambino_id AND b.cancellato_logicamente = 0
                JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
                JOIN plessi p ON p.id = cs.plesso_id AND p.cancellato_logicamente = 0
                JOIN tipologie_menu t ON t.id = b.tipologia_menu_id
                LEFT JOIN presenze_giornaliere pg ON pg.bambino_id = b.id AND pg.data = :data
                LEFT JOIN assenze_massa am ON am.classe_sezione_id = cs.id AND am.data = :data
                WHERE ac.data_inizio <= :data AND (ac.data_fine IS NULL OR ac.data_fine >= :data)
                  AND COALESCE(pg.stato_effettivo, IF(am.id IS NULL, 'presente', 'assente')) = 'presente'";
        $parametri = ['data' => $data];
        if ($plessoId !== null) {
            $sql .= ' AND p.id = :plesso';
            $parametri['plesso'] = $plessoId;
        }
        return $this->tutti($sql .
            ' GROUP BY p.id, p.nome, cs.id, cs.nome, t.id, t.nome
              ORDER BY p.nome, cs.nome, t.nome', $parametri);
    }

    /** Flag opzionale per bambino/data; non modifica la tipologia dietetica. */
    public function richiestaPastaBianco(int $bambinoId, string $data): ?array
    {
        return $this->uno(
            'SELECT * FROM richieste_pasta_bianco WHERE bambino_id = :b AND data = :d',
            ['b' => $bambinoId, 'd' => $data]
        );
    }

    public function salvaPastaBianco(int $bambinoId, string $data, bool $richiesta, int $utenteId): void
    {
        $this->esegui(
            'INSERT INTO richieste_pasta_bianco (bambino_id, data, richiesta, utente_id)
             VALUES (:b, :d, :r, :u)
             ON DUPLICATE KEY UPDATE richiesta = VALUES(richiesta), utente_id = VALUES(utente_id)',
            ['b' => $bambinoId, 'd' => $data, 'r' => $richiesta ? 1 : 0, 'u' => $utenteId]
        );
    }

    /**
     * Solo le richieste dei bambini presenti nel giorno e nel plesso.
     * Non vengono esposti nominativi di genitori o informazioni contabili.
     */
    public function elencoPastaBianco(string $data, ?int $plessoId = null): array
    {
        $sql = "SELECT p.id AS plesso_id, b.id AS bambino_id,
                       b.nome AS nome, b.cognome AS cognome,
                       cs.id AS classe_id, cs.nome AS classe_nome
                FROM richieste_pasta_bianco rp
                JOIN bambini b ON b.id = rp.bambino_id AND b.cancellato_logicamente = 0
                JOIN assegnazioni_classe ac ON ac.bambino_id = b.id
                     AND ac.data_inizio <= :data AND (ac.data_fine IS NULL OR ac.data_fine >= :data)
                JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
                JOIN plessi p ON p.id = cs.plesso_id AND p.cancellato_logicamente = 0
                LEFT JOIN presenze_giornaliere pg ON pg.bambino_id = b.id AND pg.data = :data
                LEFT JOIN assenze_massa am ON am.classe_sezione_id = cs.id AND am.data = :data
                WHERE rp.data = :data AND rp.richiesta = 1
                  AND COALESCE(pg.stato_effettivo, IF(am.id IS NULL, 'presente', 'assente')) = 'presente'";
        $parametri = ['data' => $data];
        if ($plessoId !== null) {
            $sql .= ' AND p.id = :plesso';
            $parametri['plesso'] = $plessoId;
        }
        return $this->tutti($sql . ' ORDER BY p.nome, cs.nome, b.cognome, b.nome', $parametri);
    }

    /** Dettaglio per classe del report (utile alla scuola, non al catering). */
    public function dettaglioGiornoClasse(int $classeSezioneId, string $data): array
    {
        return $this->tutti(
            'SELECT b.id AS bambino_id, b.nome, b.cognome, t.nome AS tipologia_nome,
                    pg.id AS presenza_id, pg.default_calcolato, pg.dichiarazione_genitore,
                    pg.dichiarazione_timestamp, pg.override_scuola, pg.override_motivazione,
                    pg.stato_effettivo, pg.fuori_cutoff, pg.congelato
             FROM bambini b
             JOIN tipologie_menu t ON t.id = b.tipologia_menu_id
             JOIN assegnazioni_classe ac ON ac.bambino_id = b.id
                  AND ac.data_inizio <= :data AND (ac.data_fine IS NULL OR ac.data_fine >= :data)
             LEFT JOIN presenze_giornaliere pg ON pg.bambino_id = b.id AND pg.data = :data
             WHERE ac.classe_sezione_id = :classe AND b.cancellato_logicamente = 0
             ORDER BY b.cognome, b.nome',
            ['classe' => $classeSezioneId, 'data' => $data]
        );
    }
}
