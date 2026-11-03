<?php
declare(strict_types=1);
namespace App\Core;

/** SMTP con STARTTLS/SSL e AUTH LOGIN. mail() rimane disponibile ai chiamanti legacy. */
final class Mailer
{
    public function __construct(private array $smtp) {}

    public static function simulato(): bool
    {
        return getenv('MENSA_STAGING_ONLY') === '1' && Config::get('db.database') === 'mensa_staging';
    }

    /** Verifica connessione e autenticazione senza MAIL FROM, RCPT TO o DATA. */
    public function verifica(): void
    {
        if (self::simulato()) return;
        if (empty($this->smtp['host'])) throw new \RuntimeException('Configurare il server SMTP prima della verifica.');
        $socket = $this->connetti();
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }

    public function invia(array $a, array $cc, string $oggetto, string $testo): void
    {
        $a = array_values(array_unique(array_filter($a)));
        $cc = array_values(array_diff(array_unique(array_filter($cc)), $a));
        if ($a === [] && $cc === []) throw new \RuntimeException('Nessun destinatario valido.');
        $mittente = $this->smtp['from'] ?? ($this->smtp['username'] ?? 'no-reply@localhost');
        foreach (array_merge([$mittente], $a, $cc) as $indirizzo) {
            if (!filter_var($indirizzo, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Indirizzo mittente o destinatario non valido.');
        }
        if (self::simulato()) return;
        $nomeMittente = $this->smtp['from_name'] ?? 'Comune di Caltavuturo - Mensa';
        $intestazioni = [
            'From: '.sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($nomeMittente), $mittente),
            'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($cc !== []) $intestazioni[] = 'Cc: '.implode(', ', $cc);
        $oggettoCodificato = '=?UTF-8?B?'.base64_encode($oggetto).'?=';
        $corpoCodificato = chunk_split(base64_encode($testo), 76, "\r\n");
        if (empty($this->smtp['host'])) {
            if (!@mail(implode(', ', $a ?: $cc), $oggettoCodificato, $corpoCodificato, implode("\r\n", $intestazioni))) {
                throw new \RuntimeException('Invio con mail() non riuscito: configurare SMTP.');
            }
            return;
        }
        $socket = $this->connetti();
        try {
            $this->comando($socket, 'MAIL FROM:<'.$mittente.'>', 250);
            foreach (array_merge($a, $cc) as $destinatario) $this->comando($socket, 'RCPT TO:<'.$destinatario.'>', [250,251]);
            $this->comando($socket, 'DATA', 354);
            $corpo = 'To: '.implode(', ', $a ?: $cc)."\r\nSubject: ".$oggettoCodificato."\r\n"
                .implode("\r\n", $intestazioni)."\r\n\r\n".$corpoCodificato.'.';
            $this->comando($socket, $corpo, 250);
            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    private function connetti()
    {
        $host = (string) ($this->smtp['host'] ?? '');
        $porta = (int) ($this->smtp['port'] ?? 587);
        $sicurezza = (string) ($this->smtp['security'] ?? 'tls');
        if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $host) || $porta < 1 || $porta > 65535 || !in_array($sicurezza, ['tls','ssl','none'], true)) {
            throw new \RuntimeException('Configurazione SMTP non valida.');
        }
        $contesto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client(($sicurezza === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$porta, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $contesto);
        if (!$socket) throw new \RuntimeException('Connessione SMTP non riuscita. Verificare server, porta e sicurezza.');
        stream_set_timeout($socket, 15);
        try {
            $this->attendi($socket, 220);
            $this->comando($socket, 'EHLO localhost', 250);
            if ($sicurezza === 'tls') {
                $this->comando($socket, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('Connessione TLS non riuscita: verificare sicurezza e certificato del server SMTP.');
                $this->comando($socket, 'EHLO localhost', 250);
            }
            if (!empty($this->smtp['username'])) {
                $this->comando($socket, 'AUTH LOGIN', 334);
                $this->comando($socket, base64_encode((string) $this->smtp['username']), 334);
                $this->comando($socket, base64_encode((string) ($this->smtp['password'] ?? '')), 235);
            }
            return $socket;
        } catch (\Throwable $e) {
            fclose($socket);
            throw $e;
        }
    }

    private function comando($socket, string $comando, $atteso): void
    {
        $dati = $comando."\r\n";
        while ($dati !== '') {
            $n = @fwrite($socket, $dati);
            if ($n === false || $n === 0) throw new \RuntimeException('Connessione SMTP interrotta durante la trasmissione.');
            $dati = substr($dati, $n);
        }
        $this->attendi($socket, $atteso);
    }

    private function attendi($socket, $atteso): void
    {
        $codice = 0;
        for ($i = 0; $i < 100; $i++) {
            $riga = fgets($socket, 515);
            if ($riga === false) throw new \RuntimeException('Il server SMTP non risponde o ha interrotto la connessione.');
            $codice = (int) substr($riga, 0, 3);
            if (strlen($riga) >= 4 && $riga[3] === ' ') break;
            if ($i === 99) throw new \RuntimeException('Risposta SMTP non valida.');
        }
        if (!in_array($codice, (array) $atteso, true)) {
            // Non riproporre il testo remoto: potrebbe contenere credenziali o contenuti del messaggio.
            throw new \RuntimeException(in_array($codice, [530,534,535], true)
                ? 'Autenticazione SMTP rifiutata. Verificare utente e password.'
                : "Il server SMTP ha rifiutato l’operazione (codice {$codice}).");
        }
    }
}
