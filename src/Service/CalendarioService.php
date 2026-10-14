<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Repository\CalendarioRepository;
use App\Repository\PlessoRepository;

/**
 * Calendario del servizio per plesso.
 * Regola fail-closed: senza configurazione non si congelano presenze ne' si addebitano buoni.
 * Sabati, domeniche e festivita' nazionali sono sempre esclusi.
 */
final class CalendarioService
{
    private const GIORNI = [
        1 => 'lunedi', 2 => 'martedi', 3 => 'mercoledi', 4 => 'giovedi',
        5 => 'venerdi', 6 => 'sabato', 7 => 'domenica',
    ];

    public function __construct(
        private CalendarioRepository $calendari,
        private PlessoRepository $plessi
    ) {
    }

    /** Il job puo' essere eseguito esclusivamente nella data odierna. */
    public function verifica(int $plessoId, string $data): array
    {
        if (!self::dataValida($data)) {
            return ['attivo' => false, 'motivo' => 'data_non_valida'];
        }
        if ($data !== date('Y-m-d')) {
            return ['attivo' => false, 'motivo' => 'data_non_odierna'];
        }
        return $this->previsto($plessoId, $data);
    }

    /** Consultazione anche futura, senza effetti sui consumi. */
    public function previsto(int $plessoId, string $data): array
    {
        if (!self::dataValida($data)) {
            return ['attivo' => false, 'motivo' => 'data_non_valida'];
        }
        $plesso = $this->plessi->trova($plessoId);
        if ($plesso === null || (int) $plesso['cancellato_logicamente'] === 1) {
            return ['attivo' => false, 'motivo' => 'plesso_non_attivo'];
        }
        $config = $this->calendari->configurazione($plessoId);
        if ($config === null) {
            return ['attivo' => false, 'motivo' => 'calendario_non_configurato'];
        }
        return self::valutaRegole($config, $this->calendari->eccezione($plessoId, $data), $data);
    }

    /** Funzione pura, utilizzabile nei test senza collegarsi al database. */
    public static function valutaRegole(array $config, ?array $eccezione, string $data): array
    {
        if (!self::dataValida($data)) {
            return ['attivo' => false, 'motivo' => 'data_non_valida'];
        }
        $giorno = new \DateTimeImmutable($data);
        if ($data < (string) $config['data_inizio']) {
            return ['attivo' => false, 'motivo' => 'servizio_non_avviato'];
        }
        if (($config['data_fine'] ?? null) !== null && $data > $config['data_fine']) {
            return ['attivo' => false, 'motivo' => 'servizio_terminato'];
        }

        // Vincolo assoluto, anche in presenza di una eventuale apertura straordinaria.
        if ((int) $giorno->format('N') >= 6) {
            return ['attivo' => false, 'motivo' => 'fine_settimana'];
        }
        if (self::festivitaNazionale($giorno)) {
            return ['attivo' => false, 'motivo' => 'festivita_nazionale'];
        }

        if ($eccezione !== null) {
            return [
                'attivo' => (int) $eccezione['servizio_attivo'] === 1,
                'motivo' => (int) $eccezione['servizio_attivo'] === 1
                    ? 'apertura_straordinaria' : 'chiusura_programmata',
            ];
        }
        $colonna = self::GIORNI[(int) $giorno->format('N')];
        return (int) ($config[$colonna] ?? 0) === 1
            ? ['attivo' => true, 'motivo' => 'giorno_di_servizio']
            : ['attivo' => false, 'motivo' => 'giorno_senza_servizio'];
    }

    public function dettaglio(int $plessoId): array
    {
        $this->richiediPlesso($plessoId);
        return [
            'plesso_id' => $plessoId,
            'configurazione' => $this->calendari->configurazione($plessoId),
            'eccezioni' => $this->calendari->eccezioni($plessoId),
        ];
    }

    public function salvaConfigurazione(int $plessoId, array $input): void
    {
        $this->richiediPlesso($plessoId);
        $inizio = (string) ($input['data_inizio'] ?? '');
        $fine = $input['data_fine'] ?? null;
        if (!self::dataValida($inizio) || ($fine !== null && $fine !== '' && !self::dataValida((string) $fine))) {
            throw HttpException::nonValido('Data inizio/fine non valida (YYYY-MM-DD)');
        }
        $fine = $fine === '' ? null : $fine;
        if ($fine !== null && $fine < $inizio) {
            throw HttpException::nonValido('La data di fine non puo\' precedere quella di inizio');
        }
        $dati = ['data_inizio' => $inizio, 'data_fine' => $fine];
        foreach (['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi'] as $campo) {
            if (!array_key_exists($campo, $input) || !in_array($input[$campo], [0, 1, '0', '1', false, true], true)) {
                throw HttpException::nonValido('Giorno della settimana non valido: ' . $campo);
            }
            $dati[$campo] = (int) $input[$campo];
        }
        $dati['sabato'] = 0;
        $dati['domenica'] = 0;
        $dati['escludi_festivita_nazionali'] = 1;
        $this->calendari->salvaConfigurazione($plessoId, $dati);
    }

    public function salvaEccezione(int $plessoId, string $data, array $input): void
    {
        $this->richiediPlesso($plessoId);
        if (!self::dataValida($data)) {
            throw HttpException::nonValido('Data non valida (YYYY-MM-DD)');
        }
        $attivo = $input['servizio_attivo'] ?? null;
        if (!in_array($attivo, [0, 1, '0', '1', false, true], true)) {
            throw HttpException::nonValido('Stato della giornata non valido');
        }
        $motivo = trim((string) ($input['motivazione'] ?? ''));
        if ($motivo === '' || mb_strlen($motivo) > 255) {
            throw HttpException::nonValido('Indicare una motivazione (massimo 255 caratteri)');
        }
        if ((int) $attivo === 1) {
            $giorno = new \DateTimeImmutable($data);
            if ((int) $giorno->format('N') >= 6 || self::festivitaNazionale($giorno)) {
                throw HttpException::nonValido('Non e\' consentita l\'apertura nei fine settimana o nelle festivita\' nazionali');
            }
        }
        $this->calendari->salvaEccezione($plessoId, $data, (bool) $attivo, $motivo);
    }

    public function cancellaEccezione(int $plessoId, string $data): void
    {
        $this->richiediPlesso($plessoId);
        if (!self::dataValida($data)) {
            throw HttpException::nonValido('Data non valida');
        }
        $this->calendari->cancellaEccezione($plessoId, $data);
    }

    private function richiediPlesso(int $plessoId): void
    {
        $plesso = $this->plessi->trova($plessoId);
        if ($plesso === null || (int) $plesso['cancellato_logicamente'] === 1) {
            throw HttpException::nonTrovato('Plesso inesistente o non attivo');
        }
    }

    private static function dataValida(string $data): bool
    {
        $giorno = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        return $giorno !== false && $giorno->format('Y-m-d') === $data;
    }

    private static function festivitaNazionale(\DateTimeImmutable $giorno): bool
    {
        $fisse = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15',
            '11-01', '12-08', '12-25', '12-26'];
        if (in_array($giorno->format('m-d'), $fisse, true)) {
            return true;
        }

        // Pasqua e Lunedi' dell'Angelo, algoritmo gregoriano.
        $anno = (int) $giorno->format('Y');
        $a = $anno % 19;
        $b = intdiv($anno, 100);
        $c = $anno % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mese = intdiv($h + $l - 7 * $m + 114, 31);
        $giornoMese = (($h + $l - 7 * $m + 114) % 31) + 1;
        $pasqua = $giorno->setDate($anno, $mese, $giornoMese);
        return $giorno->format('Y-m-d') === $pasqua->format('Y-m-d')
            || $giorno->format('Y-m-d') === $pasqua->modify('+1 day')->format('Y-m-d');
    }
}
