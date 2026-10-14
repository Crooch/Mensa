<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CalendarioRepository;

/**
 * Regole di erogazione per singolo plesso. Impostazione prudenziale:
 * in assenza di configurazione non si congelano presenze e non si addebitano buoni.
 */
final class CalendarioService
{
    public function __construct(private CalendarioRepository $calendari)
    {
    }

    public function verifica(int $plessoId, string $data): array
    {
        $giorno = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($giorno === false || $giorno->format('Y-m-d') !== $data) {
            return ['attivo' => false, 'motivo' => 'data_non_valida'];
        }

        // Nessun addebito per date passate o future tramite esecuzione manuale.
        if ($data !== date('Y-m-d')) {
            return ['attivo' => false, 'motivo' => 'data_non_odierna'];
        }

        $config = $this->calendari->configurazione($plessoId);
        if ($config === null) {
            return ['attivo' => false, 'motivo' => 'calendario_non_configurato'];
        }
        if ($data < $config['data_inizio']) {
            return ['attivo' => false, 'motivo' => 'servizio_non_avviato'];
        }
        if ($config['data_fine'] !== null && $data > $config['data_fine']) {
            return ['attivo' => false, 'motivo' => 'servizio_terminato'];
        }

        $eccezione = $this->calendari->eccezione($plessoId, $data);
        if ($eccezione !== null) {
            return [
                'attivo' => (bool) $eccezione['servizio_attivo'],
                'motivo' => (bool) $eccezione['servizio_attivo'] ? 'apertura_straordinaria' : 'chiusura_programmata',
            ];
        }

        if ((bool) $config['escludi_festivita_nazionali'] && self::festivitaNazionale($giorno)) {
            return ['attivo' => false, 'motivo' => 'festivita_nazionale'];
        }

        $giorni = [1 => 'lunedi', 2 => 'martedi', 3 => 'mercoledi', 4 => 'giovedi',
            5 => 'venerdi', 6 => 'sabato', 7 => 'domenica'];
        $colonna = $giorni[(int) $giorno->format('N')];
        return (bool) $config[$colonna]
            ? ['attivo' => true, 'motivo' => 'giorno_di_servizio']
            : ['attivo' => false, 'motivo' => 'giorno_senza_servizio'];
    }

    private static function festivitaNazionale(\DateTimeImmutable $giorno): bool
    {
        $fisse = ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15',
            '11-01', '12-08', '12-25', '12-26'];
        if (in_array($giorno->format('m-d'), $fisse, true)) {
            return true;
        }
        // Pasqua e lunedi' dell'Angelo: calcolo gregoriano, senza estensioni PHP.
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
