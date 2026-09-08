<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Mailer;
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
        private PlessoRepository $plessi
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

    /** Nomi dei figli attivi nel plesso alla data (per il placeholder {alunno}). */
    private function nomiFigli(int $genitoreId, int $plessoId, string $data): string
    {
        $righe = $this->crediti->movimenti($genitoreId, 'consumo_pasto', 50);
        $nomi = [];
        foreach ($righe as $riga) {
            if ($riga['bambino_nome'] !== null) {
                $nomi[$riga['bambino_nome'] . ' ' . $riga['bambino_cognome']] = true;
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

    /** Invio di prova della configurazione SMTP (funzione Admin). */
    public function inviaEmailDiProva(string $destinatario): void
    {
        $impostazioni = $this->comunicazioni->impostazioni();
        $mailer = new Mailer($this->configurazioneSmtp($impostazioni));
        $mailer->invia([$destinatario], [], 'Test configurazione email - Mensa Caltavuturo', "Email di prova inviata dal sistema di gestione mensa scolastica.\n");
    }

    public function logSolleciti(): array
    {
        return $this->comunicazioni->logSolleciti();
    }
}
