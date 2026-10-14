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
controlla($infanzia, '2026-10-24', false, 'fine_settimana', ['servizio_attivo' => 1]);
controlla($infanzia, '2026-10-25', false, 'fine_settimana');
controlla($infanzia, '2026-12-08', false, 'festivita_nazionale', ['servizio_attivo' => 1]);
controlla($infanzia, '2027-03-29', false, 'festivita_nazionale');
controlla($infanzia, '2026-11-03', false, 'chiusura_programmata', ['servizio_attivo' => 0]);
controlla($secondaria, '2026-10-21', true, 'apertura_straordinaria', ['servizio_attivo' => 1]);
controlla($infanzia, '2026-10-32', false, 'data_non_valida');
controlla(array_merge($infanzia, ['data_fine' => '2026-10-23']), '2026-10-26', false, 'servizio_terminato');

echo "Tutti i test del calendario superati.\n";
