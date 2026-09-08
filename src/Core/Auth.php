<?php

declare(strict_types=1);

namespace App\Core;

use App\Repository\UtenteRepository;

/**
 * Autenticazione a token (Bearer). Nessuna sessione PHP nel layer API:
 * cosi' le stesse API sono consumabili da una futura app nativa.
 */
final class Auth
{
    private ?array $utente = null;

    public function __construct(private UtenteRepository $utenti)
    {
    }

    /** Risolve l'utente corrente dal token; null se assente/scaduto. */
    public function utenteDa(Request $richiesta): ?array
    {
        if ($this->utente !== null) {
            return $this->utente;
        }
        $token = $richiesta->tokenBearer();
        if ($token === null || $token === '') {
            return null;
        }
        $this->utente = $this->utenti->trovaDaToken($token);
        return $this->utente;
    }

    /** @param array<int,string> $ruoliAmmessi */
    public function autorizza(Request $richiesta, array $ruoliAmmessi): array
    {
        $utente = $this->utenteDa($richiesta);
        if ($utente === null) {
            throw HttpException::nonAutenticato();
        }
        if ($ruoliAmmessi !== [] && !in_array($utente['ruolo'], $ruoliAmmessi, true)) {
            throw HttpException::nonAutorizzato();
        }
        return $utente;
    }
}
