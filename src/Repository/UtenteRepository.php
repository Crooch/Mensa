<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

final class UtenteRepository extends Repository
{
    public function trovaPerUsername(string $username): ?array
    {
        return $this->uno('SELECT * FROM utenti WHERE username = :u LIMIT 1', ['u' => $username]);
    }

    public function trova(int $id): ?array
    {
        return $this->uno('SELECT * FROM utenti WHERE id = :id', ['id' => $id]);
    }

    /** Risolve l'utente da un token non scaduto e aggiorna l'ultimo utilizzo. */
    public function trovaDaToken(string $token): ?array
    {
        $riga = $this->uno(
            'SELECT u.* FROM api_token t JOIN utenti u ON u.id = t.utente_id
             WHERE t.token = :t AND t.scade_il > NOW() AND u.attivo = 1',
            ['t' => $token]
        );
        if ($riga !== null) {
            $this->esegui('UPDATE api_token SET ultimo_uso = NOW() WHERE token = :t', ['t' => $token]);
        }
        return $riga;
    }

    public function creaToken(int $utenteId, int $oreValidita): string
    {
        $token = bin2hex(random_bytes(32));
        $this->inserisci('api_token', [
            'utente_id' => $utenteId,
            'token'     => $token,
            'scade_il'  => (new \DateTimeImmutable("+{$oreValidita} hours"))->format('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public function revocaToken(string $token): void
    {
        $this->esegui('DELETE FROM api_token WHERE token = :t', ['t' => $token]);
    }

    public function crea(array $dati): int
    {
        return $this->inserisci('utenti', $dati);
    }

    public function modifica(int $id, array $dati): void
    {
        $this->aggiorna('utenti', $id, $dati);
    }

    public function elenco(): array
    {
        return $this->tutti(
            'SELECT u.id, u.ruolo, u.username, u.nome_completo, u.plesso_id, u.attivo, p.nome AS plesso_nome
             FROM utenti u LEFT JOIN plessi p ON p.id = u.plesso_id
             ORDER BY u.ruolo, u.username'
        );
    }

    public function elencoPerRuolo(string $ruolo): array
    {
        return $this->tutti('SELECT id, username, nome_completo FROM utenti WHERE ruolo = :r AND attivo = 1 ORDER BY username', ['r' => $ruolo]);
    }
}
