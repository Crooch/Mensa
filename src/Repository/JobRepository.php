<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

/** Tracciamento delle esecuzioni del job giornaliero (idempotenza per plesso/data). */
final class JobRepository extends Repository
{
    public function esecuzione(int $plessoId, string $data): ?array
    {
        return $this->uno('SELECT * FROM esecuzioni_job WHERE plesso_id = :p AND data = :d', ['p' => $plessoId, 'd' => $data]);
    }

    /**
     * Lock MariaDB per plesso/data: serializza cron e comando manuale anche
     * quando vengono eseguiti su processi PHP differenti.
     */
    public function acquisisciLock(int $plessoId, string $data): bool
    {
        $chiave = 'mensa:job:' . $plessoId . ':' . $data;
        return (int) $this->valore('SELECT GET_LOCK(:chiave, 0)', ['chiave' => $chiave]) === 1;
    }

    public function rilasciaLock(int $plessoId, string $data): void
    {
        $chiave = 'mensa:job:' . $plessoId . ':' . $data;
        $this->valore('SELECT RELEASE_LOCK(:chiave)', ['chiave' => $chiave]);
    }

    public function apri(int $plessoId, string $data): int
    {
        $esistente = $this->esecuzione($plessoId, $data);
        if ($esistente !== null) {
            if (in_array($esistente['esito'], ['ok', 'in_corso'], true)) {
                throw new \LogicException('Job già concluso o in esecuzione per questo plesso e data');
            }
            $this->aggiorna('esecuzioni_job', (int) $esistente['id'], [
                'esito'       => 'in_corso',
                'avviato_il'  => date('Y-m-d H:i:s'),
                'concluso_il' => null,
            ]);
            return (int) $esistente['id'];
        }
        return $this->inserisci('esecuzioni_job', ['plesso_id' => $plessoId, 'data' => $data]);
    }

    public function chiudi(int $id, string $esito, array $dettaglio): void
    {
        $this->aggiorna('esecuzioni_job', $id, [
            'esito'       => $esito,
            'dettaglio'   => json_encode($dettaglio, JSON_UNESCAPED_UNICODE),
            'concluso_il' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ultimeEsecuzioni(int $limite = 30): array
    {
        return $this->tutti(
            'SELECT e.*, p.nome AS plesso_nome FROM esecuzioni_job e
             JOIN plessi p ON p.id = e.plesso_id
             ORDER BY e.data DESC, e.id DESC LIMIT ' . max(1, $limite)
        );
    }
}
