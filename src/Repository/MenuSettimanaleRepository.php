<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;
use PDO;
use RuntimeException;

/** Modelli di settimana tipo con revisioni e assegnazioni efficaci per data.
 * Le modifiche sono sempre append-only: la ricostruzione dello storico
 * dipende dalla revisione e dall'assegnazione vigenti nel giorno richiesto. */
final class MenuSettimanaleRepository extends Repository
{
    public function modello(int $id, string $data): ?array
    {
        $modello = $this->uno('SELECT m.*, t.nome AS tipologia_nome
            FROM menu_settimanali m JOIN tipologie_menu t ON t.id = m.tipologia_menu_id
            WHERE m.id = :id', ['id' => $id]);
        if ($modello === null) return null;
        $rev = $this->uno('SELECT id, nome, giorni_json, valido_dal FROM menu_settimanali_revisioni
            WHERE menu_id = :id AND valido_dal <= :data
            ORDER BY valido_dal DESC, id DESC LIMIT 1', ['id' => $id, 'data' => $data]);
        $modello['giorni'] = $rev ? json_decode((string) $rev['giorni_json'], true) : [];
        if ($rev !== null && $rev['nome'] !== null) $modello['nome'] = $rev['nome'];
        $modello['revisione_id'] = $rev['id'] ?? null;
        $modello['valido_dal'] = $rev['valido_dal'] ?? null;
        return $modello;
    }

    public function elenco(string $data): array
    {
        $righe = $this->tutti('SELECT id FROM menu_settimanali ORDER BY id DESC');
        return array_map(fn ($r) => $this->modello((int) $r['id'], $data), $righe);
    }

    public function crea(string $nome, int $tipo, array $giorni, string $dal, int $autore): int
    {
        $transazione = !$this->db->inTransaction();
        if ($transazione) $this->db->beginTransaction();
        try {
            $id = $this->inserisci('menu_settimanali', [
                'nome' => $nome, 'tipologia_menu_id' => $tipo
            ]);
            $this->salvaRevisione($id, $nome, $giorni, $dal, $autore);
            if ($transazione) $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($transazione) $this->db->rollBack();
            throw $e;
        }
    }

    public function modifica(int $id, string $nome, array $giorni, string $dal, int $autore): void
    {
        $transazione = !$this->db->inTransaction();
        if ($transazione) $this->db->beginTransaction();
        try {
            $this->aggiorna('menu_settimanali', $id, ['nome' => $nome]);
            $this->salvaRevisione($id, $nome, $giorni, $dal, $autore);
            if ($transazione) $this->db->commit();
        } catch (\Throwable $e) {
            if ($transazione) $this->db->rollBack();
            throw $e;
        }
    }

    private function salvaRevisione(int $id, string $nome, array $giorni, string $dal, int $autore): void
    {
        $this->inserisci('menu_settimanali_revisioni', [
            'menu_id' => $id,
            'nome' => $nome,
            'valido_dal' => $dal,
            'giorni_json' => json_encode($giorni, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'utente_id' => $autore > 0 ? $autore : null,
        ]);
    }

    /** Ogni assegnazione sostituisce la precedente soltanto dalla data indicata.
     * menu_id NULL disattiva il modello senza cancellare le vecchie assegnazioni. */
    public function assegna(array $plessi, int $tipo, ?int $menuId, string $dal, int $autore): void
    {
        $transazione = !$this->db->inTransaction();
        if ($transazione) $this->db->beginTransaction();
        try {
            foreach ($plessi as $plessoId) {
                $this->inserisci('menu_settimanali_assegnazioni', [
                    'plesso_id' => (int) $plessoId, 'tipologia_menu_id' => $tipo,
                    'menu_id' => $menuId, 'valido_dal' => $dal, 'utente_id' => $autore > 0 ? $autore : null
                ]);
            }
            if ($transazione) $this->db->commit();
        } catch (\Throwable $e) {
            if ($transazione) $this->db->rollBack();
            throw $e;
        }
    }

    /** Ultima assegnazione valida, una per coppia plesso+tipologia. */
    public function assegnazioni(string $data, ?int $plessoId = null): array
    {
        $sql = 'SELECT a.*, p.nome AS plesso_nome, t.nome AS tipologia_nome,
                       m.nome AS modello_nome
                FROM menu_settimanali_assegnazioni a
                JOIN plessi p ON p.id = a.plesso_id
                JOIN tipologie_menu t ON t.id = a.tipologia_menu_id
                LEFT JOIN menu_settimanali m ON m.id = a.menu_id
                WHERE a.valido_dal <= :data';
        $args = ['data' => $data];
        if ($plessoId !== null) {
            $sql .= ' AND a.plesso_id = :plesso';
            $args['plesso'] = $plessoId;
        }
        $righe = $this->tutti($sql . ' ORDER BY a.valido_dal DESC, a.id DESC', $args);
        $viste = [];
        foreach ($righe as $riga) {
            $chiave = $riga['plesso_id'] . ':' . $riga['tipologia_menu_id'];
            if (!isset($viste[$chiave])) $viste[$chiave] = $riga;
        }
        return array_values($viste);
    }

    /** Elenco delle date passate sempre ricostruibile senza sovrascritture. */
    public function giorniDiSettimana(string $data, ?int $plessoId = null): array
    {
        $giorno = (string) (new \DateTimeImmutable($data))->format('N');
        $risultato = [];
        $cache = [];
        foreach ($this->assegnazioni($data, $plessoId) as $assegnazione) {
            $id = (int) ($assegnazione['menu_id'] ?? 0);
            if ($id === 0) continue;
            if (!array_key_exists($id, $cache)) $cache[$id] = $this->modello($id, $data);
            $modello = $cache[$id];
            if (!$modello) continue;
            $descrizione = trim((string) ($modello['giorni'][$giorno] ?? ''));
            if ($descrizione === '') continue;
            $risultato[] = [
                'id' => 'modello-' . $id . '-' . $assegnazione['plesso_id'] . '-' . $data,
                'plesso_id' => (int) $assegnazione['plesso_id'],
                'tipologia_menu_id' => (int) $assegnazione['tipologia_menu_id'],
                'data' => $data, 'descrizione' => $descrizione, 'foto_path' => null,
                'plesso_nome' => $assegnazione['plesso_nome'],
                'tipologia_nome' => $assegnazione['tipologia_nome'],
                'origine' => 'settimanale', 'modello_nome' => $modello['nome']
            ];
        }
        return $risultato;
    }
}
