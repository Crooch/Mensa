<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Base per i repository: incapsula l'accesso a PDO (nessun SQL fuori da qui). */
abstract class Repository
{
    protected PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::pdo();
    }

    protected function tutti(string $sql, array $parametri = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametri);
        return $stmt->fetchAll();
    }

    protected function uno(string $sql, array $parametri = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametri);
        $riga = $stmt->fetch();
        return $riga === false ? null : $riga;
    }

    /** @return mixed */
    protected function valore(string $sql, array $parametri = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametri);
        return $stmt->fetchColumn();
    }

    protected function esegui(string $sql, array $parametri = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametri);
        return $stmt->rowCount();
    }

    protected function inserisci(string $tabella, array $dati): int
    {
        $colonne = array_keys($dati);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabella,
            implode(', ', $colonne),
            implode(', ', array_map(static fn ($c) => ':' . $c, $colonne))
        );
        $this->esegui($sql, $dati);
        return (int) $this->db->lastInsertId();
    }

    protected function aggiorna(string $tabella, int $id, array $dati): int
    {
        if ($dati === []) {
            return 0;
        }
        $set = implode(', ', array_map(static fn ($c) => $c . ' = :' . $c, array_keys($dati)));
        $dati['__id'] = $id;
        return $this->esegui("UPDATE {$tabella} SET {$set} WHERE id = :__id", $dati);
    }
}
