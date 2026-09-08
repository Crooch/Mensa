<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Repository;

/** Configurazioni di comunicazione: impostazioni, destinatari CC, modelli, log solleciti. */
final class ComunicazioneRepository extends Repository
{
    // ---------------- impostazioni globali (SMTP, protocollo) ----------------

    public function impostazioni(): array
    {
        $valori = [];
        foreach ($this->tutti('SELECT chiave, valore FROM impostazioni') as $riga) {
            $valori[$riga['chiave']] = $riga['valore'];
        }
        return $valori;
    }

    public function salvaImpostazione(string $chiave, ?string $valore): void
    {
        $this->esegui(
            'INSERT INTO impostazioni (chiave, valore) VALUES (:c, :v)
             ON DUPLICATE KEY UPDATE valore = VALUES(valore)',
            ['c' => $chiave, 'v' => $valore]
        );
    }

    // ---------------- destinatari fissi in CC (lista unica) ----------------

    public function destinatariFissi(bool $soloAttivi = true): array
    {
        $where = $soloAttivi ? 'WHERE attivo = 1' : '';
        return $this->tutti("SELECT * FROM destinatari_fissi_email {$where} ORDER BY etichetta");
    }

    public function creaDestinatario(array $dati): int
    {
        return $this->inserisci('destinatari_fissi_email', $dati);
    }

    public function modificaDestinatario(int $id, array $dati): void
    {
        $this->aggiorna('destinatari_fissi_email', $id, $dati);
    }

    // ---------------- modelli email di sollecito (uno per plesso) ----------------

    public function modello(int $plessoId): ?array
    {
        return $this->uno('SELECT * FROM modelli_email_sollecito WHERE plesso_id = :p', ['p' => $plessoId]);
    }

    public function modelli(): array
    {
        return $this->tutti(
            'SELECT m.*, p.nome AS plesso_nome FROM modelli_email_sollecito m
             JOIN plessi p ON p.id = m.plesso_id ORDER BY p.nome'
        );
    }

    public function salvaModello(int $plessoId, string $oggetto, string $testo, int $attivo): void
    {
        $this->esegui(
            'INSERT INTO modelli_email_sollecito (plesso_id, oggetto, testo, attivo)
             VALUES (:p, :o, :t, :a)
             ON DUPLICATE KEY UPDATE oggetto = VALUES(oggetto), testo = VALUES(testo), attivo = VALUES(attivo)',
            ['p' => $plessoId, 'o' => $oggetto, 't' => $testo, 'a' => $attivo]
        );
    }

    // ---------------- log solleciti ----------------

    public function sollecitoGiaInviato(int $genitoreId, int $plessoId, string $data): bool
    {
        return (bool) $this->valore(
            'SELECT COUNT(*) FROM log_solleciti_inviati WHERE genitore_id = :g AND plesso_id = :p AND data_invio = :d',
            ['g' => $genitoreId, 'p' => $plessoId, 'd' => $data]
        );
    }

    public function registraSollecito(array $dati): int
    {
        return $this->inserisci('log_solleciti_inviati', $dati);
    }

    public function logSolleciti(int $limite = 200): array
    {
        return $this->tutti(
            'SELECT l.*, g.nome AS genitore_nome, g.cognome AS genitore_cognome, p.nome AS plesso_nome
             FROM log_solleciti_inviati l
             JOIN genitori g ON g.id = l.genitore_id
             JOIN plessi p ON p.id = l.plesso_id
             ORDER BY l.data_invio DESC, l.id DESC LIMIT ' . max(1, $limite)
        );
    }
}
