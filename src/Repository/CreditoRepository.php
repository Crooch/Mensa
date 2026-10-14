<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class CreditoRepository extends Repository
{
    public function trovaPool(int $genitoreId, int $plessoId): ?array
    {
        return $this->uno(
            'SELECT * FROM pool_crediti WHERE genitore_id = :g AND plesso_id = :p',
            ['g' => $genitoreId, 'p' => $plessoId]
        );
    }

    /** Crea il pool alla prima movimentazione (un record per coppia genitore/plesso). */
    public function assicuraPool(int $genitoreId, int $plessoId): int
    {
        $pool = $this->trovaPool($genitoreId, $plessoId);
        if ($pool !== null) {
            return (int) $pool['id'];
        }
        return $this->inserisci('pool_crediti', [
            'genitore_id' => $genitoreId,
            'plesso_id'   => $plessoId,
            'saldo'       => 0,
        ]);
    }

    public function poolDelGenitore(int $genitoreId): array
    {
        return $this->tutti(
            'SELECT pc.*, p.nome AS plesso_nome, p.fido_buoni, p.tariffa_base_pasto
             FROM pool_crediti pc JOIN plessi p ON p.id = pc.plesso_id
             WHERE pc.genitore_id = :g ORDER BY p.nome',
            ['g' => $genitoreId]
        );
    }

    public function aggiornaSaldo(int $poolId, int $delta): void
    {
        $this->esegui('UPDATE pool_crediti SET saldo = saldo + :d WHERE id = :id', ['d' => $delta, 'id' => $poolId]);
    }

    public function registraMovimento(array $dati): int
    {
        return $this->inserisci('movimenti_crediti', $dati);
    }

    /**
     * Identificativo unico della richiesta di acquisto: usato in caso di retry
     * o risposta HTTP persa. Il vincolo UNIQUE DB e' l'arbitro della concorrenza.
     */
    public function acquistoPerRichiesta(string $richiestaId): ?array
    {
        return $this->uno(
            "SELECT m.*, pc.genitore_id, pc.plesso_id
             FROM movimenti_crediti m
             JOIN pool_crediti pc ON pc.id = m.pool_id
             WHERE m.richiesta_id = :id AND m.tipo = 'acquisto'",
            ['id' => $richiestaId]
        );
    }

    public function movimenti(int $genitoreId, ?string $tipo = null, int $limite = 200): array
    {
        $sql = 'SELECT m.*, p.nome AS plesso_nome, b.nome AS bambino_nome, b.cognome AS bambino_cognome,
                       u.username AS operatore
                FROM movimenti_crediti m
                JOIN pool_crediti pc ON pc.id = m.pool_id
                JOIN plessi p ON p.id = pc.plesso_id
                LEFT JOIN bambini b ON b.id = m.bambino_id
                LEFT JOIN utenti u ON u.id = m.utente_id
                WHERE pc.genitore_id = :g';
        $parametri = ['g' => $genitoreId];
        if ($tipo !== null) {
            $sql .= ' AND m.tipo = :t';
            $parametri['t'] = $tipo;
        }
        return $this->tutti($sql . ' ORDER BY m.data DESC, m.id DESC LIMIT ' . max(1, $limite), $parametri);
    }

    /** Consumo già registrato per bambino/data: evita doppi addebiti se il job rigira. */
    public function esisteConsumo(int $bambinoId, string $data): bool
    {
        return (bool) $this->valore(
            "SELECT COUNT(*) FROM movimenti_crediti WHERE bambino_id = :b AND data = :d AND tipo = 'consumo_pasto'",
            ['b' => $bambinoId, 'd' => $data]
        );
    }

    /** Pool oltre fido: base per l'escalation debito (mai per bloccare il pasto). */
    public function poolOltreFido(int $plessoId): array
    {
        return $this->tutti(
            'SELECT pc.*, g.nome AS genitore_nome, g.cognome AS genitore_cognome, g.email AS genitore_email,
                    p.nome AS plesso_nome, p.fido_buoni, p.dirigente_email, p.email_protocollo
             FROM pool_crediti pc
             JOIN genitori g ON g.id = pc.genitore_id
             JOIN plessi p ON p.id = pc.plesso_id
             WHERE pc.plesso_id = :p AND g.cancellato_logicamente = 0 AND pc.saldo <= -p.fido_buoni',
            ['p' => $plessoId]
        );
    }

    /** Elenco sintetico di tutte le situazioni creditizie (vista operatore). */
    public function situazioneGenerale(): array
    {
        return $this->tutti(
            'SELECT pc.id, pc.saldo, g.id AS genitore_id, g.nome AS genitore_nome, g.cognome AS genitore_cognome,
                    g.telefono, p.id AS plesso_id, p.nome AS plesso_nome, p.fido_buoni
             FROM pool_crediti pc
             JOIN genitori g ON g.id = pc.genitore_id
             JOIN plessi p ON p.id = pc.plesso_id
             WHERE g.cancellato_logicamente = 0
             ORDER BY pc.saldo ASC'
        );
    }
}
