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

    /**
     * Restituisce true se due intervalli si sovrappongono oltre il solo giorno
     * di confine. Esempio: 12-14 e 14-16 ammessi; 12-14 e 13-16 vietati.
     * Un singolo giorno è sempre consentito se libero, ma non può duplicare
     * una giornata già coperta da un altro intervallo.
     */
    public static function intervalliInConflitto(string $inizio1, string $fine1, string $inizio2, string $fine2): bool
    {
        $dal = max($inizio1, $inizio2);
        $al = min($fine1, $fine2);
        if ($dal > $al) {
            return false;
        }
        if ($dal < $al) {
            return true;
        }
        return !(($fine1 === $inizio2 && $inizio1 !== $fine1 && $inizio2 !== $fine2)
            || ($fine2 === $inizio1 && $inizio2 !== $fine2 && $inizio1 !== $fine1));
    }

    /** Crea oppure modifica un periodo salvato, includendo il caso data singola. */
    public function salvaPeriodo(int $plessoId, array $input, ?int $periodoId = null): int
    {
        $this->richiediPlesso($plessoId);
        $inizio = (string) ($input['data_inizio'] ?? '');
        $fine = (string) (($input['data_fine'] ?? null) ?: $inizio);
        if (!self::dataValida($inizio) || !self::dataValida($fine) || $fine < $inizio) {
            throw HttpException::nonValido('Intervallo non valido: controlla le date di inizio e fine.');
        }
        $giorni = (new \DateTimeImmutable($inizio))->diff(new \DateTimeImmutable($fine))->days;
        if ($giorni > 730) {
            throw HttpException::nonValido('L’intervallo non può superare due anni.');
        }
        $attivo = $input['servizio_attivo'] ?? null;
        if (!in_array($attivo, [0, 1, '0', '1', true, false], true)) {
            throw HttpException::nonValido('Seleziona se la mensa sarà aperta o chiusa.');
        }
        $motivo = trim((string) ($input['motivazione'] ?? ''));
        if ($motivo === '' || strlen($motivo) > 255) {
            throw HttpException::nonValido('Indica una motivazione (massimo 255 caratteri).');
        }
        if ((int) $attivo === 1) {
            for ($d = new \DateTimeImmutable($inizio), $ultimo = new \DateTimeImmutable($fine);
                 $d <= $ultimo; $d = $d->modify('+1 day')) {
                if ((int) $d->format('N') >= 6 || self::festivitaNazionale($d)) {
                    throw HttpException::nonValido(
                        'Un’apertura straordinaria non può comprendere sabati, domeniche o festività nazionali. '
                        . 'Seleziona soltanto giorni consentiti.'
                    );
                }
            }
        }
        if (!$this->calendari->acquisisciLockPeriodi($plessoId)) {
            throw HttpException::nonValido('Il calendario è già in modifica. Riprova tra pochi istanti.');
        }
        try {
            if ($periodoId !== null && $this->calendari->trovaPeriodo($plessoId, $periodoId) === null) {
                throw HttpException::nonTrovato('Periodo non trovato per questa scuola.');
            }
            foreach ($this->calendari->periodiIntersecanti($plessoId, $inizio, $fine, $periodoId) as $p) {
                if (self::intervalliInConflitto(
                    $inizio, $fine, (string) $p['giorno'], (string) $p['giorno_fine']
                )) {
                    throw HttpException::nonValido(
                        'Il periodo si sovrappone a quello dal '
                        . date('d/m/Y', strtotime((string) $p['giorno']))
                        . ' al ' . date('d/m/Y', strtotime((string) $p['giorno_fine']))
                        . '. Modifica le date oppure modifica il periodo già registrato.'
                    );
                }
            }
            try {
                return $this->calendari->salvaPeriodo(
                    $plessoId, $inizio, $fine, (bool) $attivo, $motivo, $periodoId
                );
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw HttpException::nonValido(
                        'Esiste già un periodo con questa data di inizio. Modifica quello esistente.'
                    );
                }
                throw $e;
            }
        } finally {
            $this->calendari->rilasciaLockPeriodi($plessoId);
        }
    }

    public function cancellaPeriodo(int $plessoId, int $periodoId): void
    {
        $this->richiediPlesso($plessoId);
        if (!$this->calendari->acquisisciLockPeriodi($plessoId)) {
            throw HttpException::nonValido('Il calendario è già in modifica. Riprova.');
        }
        try {
            if ($this->calendari->trovaPeriodo($plessoId, $periodoId) === null) {
                throw HttpException::nonTrovato('Periodo non trovato.');
            }
            $this->calendari->cancellaPeriodo($plessoId, $periodoId);
        } finally {
            $this->calendari->rilasciaLockPeriodi($plessoId);
        }
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
        if ($motivo === '' || strlen($motivo) > 255) {
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
