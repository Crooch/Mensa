<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Client SMTP minimale via stream socket (nessuna dipendenza esterna, quindi
 * installabile su hosting condiviso senza Composer). Supporta STARTTLS/SSL e
 * AUTH LOGIN, che coprono le configurazioni tipiche (Aruba, Gmail, ecc.).
 * Se la configurazione SMTP non e' presente, ricade su mail() di PHP.
 */
final class Mailer
{
    /**
     * @param array<int,string> $a  destinatari principali
     * @param array<int,string> $cc destinatari in copia conoscenza
     */
    public function __construct(private array $smtp)
    {
    }

    public function invia(array $a, array $cc, string $oggetto, string $testo): void
    {
        // Collaudo sintetico: blocca ogni invio reale, anche se in UI si
        // inseriscono per errore indirizzi o parametri SMTP.
        if (getenv('MENSA_STAGING_ONLY') === '1'
            && Config::get('db.database') === 'mensa_staging') {
            return;
        }
        $a = array_values(array_unique(array_filter($a)));
        $cc = array_values(array_unique(array_filter($cc)));
        if ($a === [] && $cc === []) {
            throw new \RuntimeException('Nessun destinatario valido');
        }

        $mittente = $this->smtp['from'] ?? ($this->smtp['username'] ?? 'no-reply@localhost');
        $nomeMittente = $this->smtp['from_name'] ?? 'Comune di Caltavuturo - Mensa';

        $intestazioni = [
            'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($nomeMittente), $mittente),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($cc !== []) {
            $intestazioni[] = 'Cc: ' . implode(', ', $cc);
        }

        if (empty($this->smtp['host'])) {
            $ok = @mail(implode(', ', $a ?: $cc), $this->codificaOggetto($oggetto), $testo, implode("\r\n", $intestazioni));
            if (!$ok) {
                throw new \RuntimeException('Invio con mail() non riuscito: configurare SMTP');
            }
            return;
        }

        $this->inviaViaSmtp($mittente, $a, $cc, $oggetto, $testo, $intestazioni);
    }

    private function codificaOggetto(string $oggetto): string
    {
        return '=?UTF-8?B?' . base64_encode($oggetto) . '?=';
    }

    private function inviaViaSmtp(string $mittente, array $a, array $cc, string $oggetto, string $testo, array $intestazioni): void
    {
        $host = (string) $this->smtp['host'];
        $porta = (int) ($this->smtp['port'] ?? 587);
        $sicurezza = strtolower((string) ($this->smtp['security'] ?? 'tls')); // tls | ssl | none

        $endpoint = ($sicurezza === 'ssl' ? 'ssl://' : '') . $host . ':' . $porta;
        $contesto = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $socket = @stream_socket_client($endpoint, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contesto);
        if ($socket === false) {
            throw new \RuntimeException("Connessione SMTP fallita ({$errno}): {$errstr}");
        }
        stream_set_timeout($socket, 15);

        $this->attendi($socket, 220);
        $this->comando($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);

        if ($sicurezza === 'tls') {
            $this->comando($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Negoziazione TLS fallita');
            }
            $this->comando($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
        }

        if (!empty($this->smtp['username'])) {
            $this->comando($socket, 'AUTH LOGIN', 334);
            $this->comando($socket, base64_encode((string) $this->smtp['username']), 334);
            $this->comando($socket, base64_encode((string) ($this->smtp['password'] ?? '')), 235);
        }

        $this->comando($socket, 'MAIL FROM:<' . $mittente . '>', 250);
        foreach (array_merge($a, $cc) as $destinatario) {
            $this->comando($socket, 'RCPT TO:<' . $destinatario . '>', 250);
        }
        $this->comando($socket, 'DATA', 354);

        $corpo = 'To: ' . implode(', ', $a ?: $cc) . "\r\n"
            . 'Subject: ' . $this->codificaOggetto($oggetto) . "\r\n"
            . implode("\r\n", $intestazioni) . "\r\n\r\n"
            . str_replace("\n.", "\n..", str_replace("\r\n", "\n", $testo)) . "\r\n.";
        $this->comando($socket, $corpo, 250);
        @fwrite($socket, "QUIT\r\n");
        @fclose($socket);
    }

    private function comando($socket, string $comando, int $atteso): void
    {
        fwrite($socket, $comando . "\r\n");
        $this->attendi($socket, $atteso);
    }

    private function attendi($socket, int $atteso): void
    {
        $risposta = '';
        while (($riga = fgets($socket, 515)) !== false) {
            $risposta .= $riga;
            if (strlen($riga) < 4 || $riga[3] !== '-') {
                break;
            }
        }
        $codice = (int) substr($risposta, 0, 3);
        if ($codice !== $atteso) {
            throw new \RuntimeException("Risposta SMTP inattesa (attesa {$atteso}): " . trim($risposta));
        }
    }
}
