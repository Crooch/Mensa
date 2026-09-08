<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Repository\GenitoreRepository;
use App\Repository\UtenteRepository;

/**
 * Autenticazione: credenziali create dall'ufficio comunale, nessuna
 * auto-registrazione. Lo username e' una stringa libera univoca.
 */
final class AuthService
{
    public function __construct(
        private UtenteRepository $utenti,
        private GenitoreRepository $genitori,
        private int $durataTokenOre = 12
    ) {
    }

    /**
     * Verifica le credenziali ed emette un token di accesso.
     *
     * @return array{token:string, utente:array}
     */
    public function login(string $username, string $password): array
    {
        $utente = $this->utenti->trovaPerUsername(trim($username));
        if ($utente === null || (int) $utente['attivo'] !== 1 || !password_verify($password, $utente['password_hash'])) {
            throw new HttpException(401, 'Credenziali non valide');
        }

        $token = $this->utenti->creaToken((int) $utente['id'], $this->durataTokenOre);

        return ['token' => $token, 'utente' => $this->profilo($utente)];
    }

    public function logout(string $token): void
    {
        $this->utenti->revocaToken($token);
    }

    /** Profilo esposto alle API/frontend (mai l'hash della password). */
    public function profilo(array $utente): array
    {
        $profilo = [
            'id'            => (int) $utente['id'],
            'ruolo'         => $utente['ruolo'],
            'username'      => $utente['username'],
            'nome_completo' => $utente['nome_completo'],
            'plesso_id'     => $utente['plesso_id'] !== null ? (int) $utente['plesso_id'] : null,
        ];
        if ($utente['ruolo'] === 'genitore') {
            $genitore = $this->genitori->trovaPerUtente((int) $utente['id']);
            $profilo['genitore_id'] = $genitore !== null ? (int) $genitore['id'] : null;
        }
        return $profilo;
    }

    public function cambiaPassword(int $utenteId, string $nuova): void
    {
        if (strlen($nuova) < 8) {
            throw HttpException::nonValido('La password deve contenere almeno 8 caratteri');
        }
        $this->utenti->modifica($utenteId, ['password_hash' => password_hash($nuova, PASSWORD_DEFAULT)]);
    }

    /** Crea un'utenza applicativa (usata da Admin/Operatore). */
    public function creaUtente(string $ruolo, string $username, string $password, ?string $nomeCompleto = null, ?int $plessoId = null): int
    {
        $ruoliAmmessi = ['admin', 'operatore', 'scuola', 'catering', 'genitore'];
        if (!in_array($ruolo, $ruoliAmmessi, true)) {
            throw HttpException::nonValido('Ruolo non valido');
        }
        if (strlen($password) < 8) {
            throw HttpException::nonValido('La password deve contenere almeno 8 caratteri');
        }
        if ($this->utenti->trovaPerUsername($username) !== null) {
            throw HttpException::nonValido('Username già in uso');
        }
        return $this->utenti->crea([
            'ruolo'         => $ruolo,
            'username'      => trim($username),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nome_completo' => $nomeCompleto,
            'plesso_id'     => $plessoId,
            'attivo'        => 1,
        ]);
    }
}
