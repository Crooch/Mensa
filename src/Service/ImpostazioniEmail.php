<?php
declare(strict_types=1);
namespace App\Service;

use App\Core\Config;
use App\Core\HttpException;

/** La scelta Admin prevale sul vecchio flag, che resta il default delle installazioni esistenti. */
final class ImpostazioniEmail
{
    public static function automatici(array $valori): bool
    {
        return array_key_exists('solleciti_automatici_enabled', $valori)
            ? $valori['solleciti_automatici_enabled'] === '1'
            : Config::get('app.solleciti_automatici_enabled', false) === true;
    }

    public static function validaSmtp(array $v, bool $obbligatoria = true): void
    {
        $host = trim((string) ($v['smtp_host'] ?? ''));
        $from = trim((string) ($v['smtp_from'] ?? ''));
        if (($obbligatoria && ($host === '' || $from === ''))
            || ($host !== '' && !preg_match('/^[a-zA-Z0-9.-]+$/D', $host))) {
            throw HttpException::nonValido('Indicare un server SMTP valido e un indirizzo mittente.');
        }
        foreach (['smtp_from', 'email_protocollo'] as $campo) {
            if (!empty($v[$campo]) && !filter_var($v[$campo], FILTER_VALIDATE_EMAIL)) {
                throw HttpException::nonValido('Indirizzo non valido: '.($campo === 'smtp_from' ? 'mittente' : 'protocollo').'.');
            }
        }
        $porta = filter_var($v['smtp_port'] ?? 587, FILTER_VALIDATE_INT);
        if ($porta === false || $porta < 1 || $porta > 65535) throw HttpException::nonValido('La porta SMTP deve essere compresa tra 1 e 65535.');
        if (!in_array($v['smtp_security'] ?? 'tls', ['tls','ssl','none'], true)) throw HttpException::nonValido('Sicurezza SMTP non valida.');
        if (preg_match('/[\r\n]/', (string) ($v['smtp_from_name'] ?? ''))) throw HttpException::nonValido('Nome mittente non valido.');
    }
}
