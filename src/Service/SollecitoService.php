<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Mailer;
use App\Core\HttpException;
use App\Repository\BambinoRepository;
use App\Repository\ComunicazioneRepository;
use App\Repository\CreditoRepository;
use App\Repository\PlessoRepository;

/**
 * Escalation informativa del debito oltre fido.
 *
 * NON blocca mai il servizio mensa e non comunica nulla al catering: genera solo
 * un'email quotidiana verso famiglia (se ha email), protocollo comunale,
 * dirigente scolastico del plesso e lista fissa di destinatari in CC.
 * Il reinvio si ripete ogni giorno finche' la condizione persiste.
 */
final class SollecitoService
{
    public function __construct(
        private CreditoRepository $crediti,
        private ComunicazioneRepository $comunicazioni,
        private PlessoRepository $plessi,
        private BambinoRepository $bambini
    ) {
    }

    /**
     * Esegue la verifica dei debiti oltre fido per un plesso e invia i solleciti.
     *
     * @return array{controllati:int, inviati:int, errori:int, dettagli:array}
     */
    public function inviaSollecitiPlesso(int $plessoId, ?string $data = null): array
    {
        $data ??= date('Y-m-d');
        $plesso = $this->plessi->trova($plessoId);
        $impostazioni = $this->comunicazioni->impostazioni();
        $modello = $this->comunicazioni->modello($plessoId);
        $destinatariFissi = array_column($this->comunicazioni->destinatariFissi(), 'email');
        $mailer = new Mailer($this->configurazioneSmtp($impostazioni));

        $esito = ['controllati' => 0, 'inviati' => 0, 'errori' => 0, 'dettagli' => []];

        foreach ($this->crediti->poolOltreFido($plessoId) as $pool) {
            $esito['controllati']++;
            $genitoreId = (int) $pool['genitore_id'];

            // Un solo sollecito per genitore/plesso/giorno (evita duplicati se il job rigira).
            if ($this->comunicazioni->sollecitoGiaInviato($genitoreId, $plessoId, $data)) {
                $esito['dettagli'][] = ['genitore_id' => $genitoreId, 'stato' => 'gia_inviato_oggi'];
                continue;
            }
            if ($modello === null || (int) $modello['attivo'] !== 1) {
                $esito['dettagli'][] = ['genitore_id' => $genitoreId, 'stato' => 'modello_mancante'];
                continue;
            }

            $protocollo = $plesso['email_protocollo'] ?: ($impostazioni['email_protocollo'] ?? null);
            // L'assenza dell'email del genitore non blocca la segnalazione agli uffici.
            $a = array_filter([$pool['genitore_email'] ?: null, $protocollo]);
            $cc = array_filter(array_merge([$plesso['dirigente_email'] ?: null], $destinatariFissi));

            $sostituzioni = [
                '{genitore}' => trim($pool['genitore_cognome'] . ' ' . $pool['genitore_nome']),
                '{plesso}'   => (string) $pool['plesso_nome'],
                '{saldo}'    => (string) (int) $pool['saldo'],
                '{fido}'     => (string) (int) $pool['fido_buoni'],
                '{alunno}'   => $this->nomiFigli($genitoreId, $plessoId, $data),
                '{data}'     => date('d/m/Y', strtotime($data)),
            ];
            $oggetto = strtr($modello['oggetto'], $sostituzioni);
            $testo = strtr($modello['testo'], $sostituzioni);

            $log = [
                'genitore_id'            => $genitoreId,
                'plesso_id'              => $plessoId,
                'data_invio'             => $data,
                'saldo_al_momento_invio' => (int) $pool['saldo'],
                'destinatari'            => json_encode(['a' => array_values($a), 'cc' => array_values($cc)], JSON_UNESCAPED_UNICODE),
            ];

            try {
                $mailer->invia(array_values($a), array_values($cc), $oggetto, $testo);
                $log['esito'] = 'inviato';
                $esito['inviati']++;
            } catch (\Throwable $e) {
                $log['esito'] = 'errore';
                $log['errore'] = substr($e->getMessage(), 0, 500);
                $esito['errori']++;
            }
            $this->comunicazioni->registraSollecito($log);
            $esito['dettagli'][] = ['genitore_id' => $genitoreId, 'stato' => $log['esito']];
        }

        return $esito;
    }

    /**
     * Placeholder {alunno}: solo figli attualmente assegnati al plesso indicato,
     * alla data del sollecito. Non usare lo storico globale dei consumi:
     * includerebbe minori di altri plessi o non più assegnati.
     */
    private function nomiFigli(int $genitoreId, int $plessoId, string $data): string
    {
        $nomi = [];
        foreach ($this->bambini->elencoPerPlesso($plessoId, $data) as $riga) {
            if ((int) $riga['genitore_id'] !== $genitoreId) {
                continue;
            }
            $nome = trim((string) $riga['nome'] . ' ' . (string) $riga['cognome']);
            if ($nome !== '') {
                $nomi[$nome] = true;
            }
        }
        return $nomi === [] ? '-' : implode(', ', array_keys($nomi));
    }

    /** Configurazione SMTP presa dalle impostazioni (editabili da Admin). */
    private function configurazioneSmtp(array $impostazioni): array
    {
        return [
            'host'      => $impostazioni['smtp_host'] ?? '',
            'port'      => (int) ($impostazioni['smtp_port'] ?? 587),
            'security'  => $impostazioni['smtp_security'] ?? 'tls',
            'username'  => $impostazioni['smtp_username'] ?? '',
            'password'  => $impostazioni['smtp_password'] ?? '',
            'from'      => $impostazioni['smtp_from'] ?? ($impostazioni['smtp_username'] ?? ''),
            'from_name' => $impostazioni['smtp_from_name'] ?? Config::get('app.nome'),
        ];
    }

    public function automaticiAbilitati(): bool
    {
        return ImpostazioniEmail::automatici($this->comunicazioni->impostazioni());
    }

    /** Connessione, TLS e autenticazione, senza inviare messaggi. */
    public function verificaSmtp(): array
    {
        $impostazioni = $this->comunicazioni->impostazioni();
        ImpostazioniEmail::validaSmtp($impostazioni);
        try {
            (new Mailer($this->configurazioneSmtp($impostazioni)))->verifica();
        } catch (\RuntimeException $e) {
            throw HttpException::nonValido($e->getMessage());
        }
        return ['simulato' => Mailer::simulato(), 'verificata' => !Mailer::simulato()];
    }

    /** Solo destinatario esplicito e dati fittizi: nessun CC, nessun log di sollecito reale. */
    public function inviaEmailDiProva(string $destinatario, int $plessoId): array
    {
        $destinatario = trim($destinatario);
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) throw HttpException::nonValido('Indirizzo email di prova non valido.');
        $plesso = $this->plessi->trova($plessoId);
        $modello = $this->comunicazioni->modello($plessoId);
        if (!$plesso || !$modello) throw HttpException::nonValido('Selezionare una scuola con un modello salvato.');
        $impostazioni = $this->comunicazioni->impostazioni();
        ImpostazioniEmail::validaSmtp($impostazioni);
        $campioni = ['{genitore}' => 'Genitore di prova', '{alunno}' => 'Alunno di prova',
            '{plesso}' => $plesso['nome'], '{saldo}' => '-15', '{fido}' => '10', '{data}' => date('d/m/Y')];
        $oggetto = '[PROVA] '.strtr($modello['oggetto'], $campioni);
        $testo = strtr($modello['testo'], $campioni);
        try {
            (new Mailer($this->configurazioneSmtp($impostazioni)))->invia([$destinatario], [], $oggetto, $testo);
        } catch (\RuntimeException $e) {
            throw HttpException::nonValido($e->getMessage());
        }
        return ['inviata' => !Mailer::simulato(), 'simulato' => Mailer::simulato(),
            'destinatario' => $destinatario, 'oggetto' => $oggetto, 'testo' => $testo];
    }

    public function logSolleciti(): array
    {
        return $this->comunicazioni->logSolleciti();
    }
}
