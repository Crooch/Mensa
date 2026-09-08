<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class FeedbackRepository extends Repository
{
    public function crea(array $dati): int
    {
        return $this->inserisci('feedback_pasti', $dati);
    }

    public function perGenitore(int $genitoreId, int $limite = 50): array
    {
        return $this->tutti(
            'SELECT f.*, b.nome AS bambino_nome, b.cognome AS bambino_cognome, t.nome AS tipologia_nome
             FROM feedback_pasti f
             JOIN bambini b ON b.id = f.bambino_id
             JOIN tipologie_menu t ON t.id = f.tipologia_menu_id
             WHERE b.genitore_id = :g
             ORDER BY f.data DESC, f.id DESC LIMIT ' . max(1, $limite),
            ['g' => $genitoreId]
        );
    }

    /**
     * Elenco per il catering: nessun dato anagrafico della famiglia, solo
     * iniziali dell'alunno, classe/plesso e tipologia (minimizzazione dati).
     */
    public function perCatering(array $filtri): array
    {
        $sql = "SELECT f.id, f.data, f.voto, f.commento, f.creato_il,
                       CONCAT(LEFT(b.nome,1), '. ', LEFT(b.cognome,1), '.') AS alunno_iniziali,
                       t.nome AS tipologia_nome, cs.nome AS classe_nome, p.nome AS plesso_nome,
                       p.id AS plesso_id, cs.id AS classe_id, t.id AS tipologia_id
                FROM feedback_pasti f
                JOIN bambini b ON b.id = f.bambino_id
                JOIN tipologie_menu t ON t.id = f.tipologia_menu_id
                LEFT JOIN assegnazioni_classe ac ON ac.bambino_id = b.id
                     AND ac.data_inizio <= f.data AND (ac.data_fine IS NULL OR ac.data_fine >= f.data)
                LEFT JOIN classi_sezioni cs ON cs.id = ac.classe_sezione_id
                LEFT JOIN plessi p ON p.id = cs.plesso_id
                WHERE 1=1";
        $parametri = [];
        if (!empty($filtri['data_da'])) {
            $sql .= ' AND f.data >= :da';
            $parametri['da'] = $filtri['data_da'];
        }
        if (!empty($filtri['data_a'])) {
            $sql .= ' AND f.data <= :a';
            $parametri['a'] = $filtri['data_a'];
        }
        if (!empty($filtri['plesso_id'])) {
            $sql .= ' AND p.id = :p';
            $parametri['p'] = (int) $filtri['plesso_id'];
        }
        if (!empty($filtri['classe_id'])) {
            $sql .= ' AND cs.id = :c';
            $parametri['c'] = (int) $filtri['classe_id'];
        }
        if (!empty($filtri['tipologia_id'])) {
            $sql .= ' AND t.id = :t';
            $parametri['t'] = (int) $filtri['tipologia_id'];
        }
        return $this->tutti($sql . ' ORDER BY f.data DESC, f.id DESC LIMIT 300', $parametri);
    }
}
