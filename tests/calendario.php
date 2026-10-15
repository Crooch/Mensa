<?php

declare(strict_types=1);

// Test delle regole del calendario: non usa il database e non modifica dati.
// Esecuzione: php tests/calendario.php

require dirname(__DIR__) . '/src/Service/CalendarioService.php';

use App\Service\CalendarioService;

function controlla(array $config, string $data, bool $atteso, string $motivo, ?array $eccezione = null): void
{
    $esito = CalendarioService::valutaRegole($config, $eccezione, $data);
    if ($esito['attivo'] !== $atteso || $esito['motivo'] !== $motivo) {
        throw new RuntimeException(sprintf(
            '%s: atteso %s (%s), ottenuto %s (%s)',
            $data, $atteso ? 'attivo' : 'chiuso', $motivo,
            $esito['attivo'] ? 'attivo' : 'chiuso', $esito['motivo']
        ));
    }
    echo "OK {$data} - {$motivo}\n";
}

$infanzia = [
    'data_inizio' => '2026-10-19', 'data_fine' => null,
    'lunedi' => 1, 'martedi' => 1, 'mercoledi' => 1,
    'giovedi' => 1, 'venerdi' => 1, 'sabato' => 0, 'domenica' => 0,
];
$secondaria = array_merge($infanzia, ['martedi' => 0, 'mercoledi' => 0, 'venerdi' => 0]);

controlla($infanzia, '2026-10-14', false, 'servizio_non_avviato');
controlla($infanzia, '2026-10-19', true, 'giorno_di_servizio');
controlla($secondaria, '2026-10-19', true, 'giorno_di_servizio');
controlla($infanzia, '2026-10-20', true, 'giorno_di_servizio');
controlla($secondaria, '2026-10-20', false, 'giorno_senza_servizio');
controlla($secondaria, '2026-10-22', true, 'giorno_di_servizio');
controlla($secondaria, '2026-10-23', false, 'giorno_senza_servizio');
controlla($infanzia, '2026-10-24', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla($infanzia, '2026-10-25', false, 'fine_settimana');
controlla($infanzia, '2026-12-08', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla($infanzia, '2027-03-29', false, 'festivita_nazionale');
controlla($infanzia, '2026-11-03', false, 'chiusura_programmata', ['servizio_attivo' => 0]);
controlla($secondaria, '2026-10-21', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla($infanzia, '2026-10-32', false, 'data_non_valida');
controlla(array_merge($infanzia, ['data_fine' => '2026-10-23']), '2026-10-26', false, 'servizio_terminato');

// L'eccezione è la regola più forte anche prima dell'avvio e dopo la fine.
controlla($infanzia, '2026-10-15', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla($infanzia, '2026-12-25', false, 'chiusura_programmata', ['servizio_attivo' => 0]);
controlla($infanzia, '2026-10-25', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla(array_merge($infanzia, ['data_fine' => '2026-10-23']),
    '2026-10-26', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
// Controllo dei conflitti: soltanto date di confine con stato identico.
$confini = [
    // dal1, al1, dal2, al2, aperto1, aperto2, conflitto
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-16',false,false,false],
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-16',true,true,false],
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-16',false,true,true],
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-16',true,false,true],
    ['2026-11-12','2026-11-14','2026-11-13','2026-11-16',false,false,true],
    ['2026-11-12','2026-11-14','2026-11-10','2026-11-13',false,false,true],
    ['2026-11-12','2026-11-14','2026-11-17','2026-11-19',false,true,false],
    ['2026-11-12','2026-11-14','2026-11-12','2026-11-14',false,false,true],
    ['2026-11-14','2026-11-14','2026-11-14','2026-11-14',false,false,true],
    ['2026-11-12','2026-11-16','2026-11-14','2026-11-14',false,false,true],
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-14',false,false,false],
    ['2026-11-12','2026-11-14','2026-11-14','2026-11-14',false,true,true],
];
foreach ($confini as [$i1,$f1,$i2,$f2,$ap1,$ap2,$conflitto]) {
    foreach ([[ $i1,$f1,$i2,$f2,$ap1,$ap2 ], [ $i2,$f2,$i1,$f1,$ap2,$ap1 ]] as $args) {
        if (CalendarioService::intervalliInConflitto(...$args) !== $conflitto) {
            throw new RuntimeException('Conflitto intervalli non coerente con gli estremi e il tipo.');
        }
    }
}
echo "OK: eccezioni prioritarie e sovrapposizioni con stati concordi al solo confine.\n";
echo "Tutti i test del calendario superati.\n";
