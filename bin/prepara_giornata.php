<?php

declare(strict_types=1);

/**
 * Prepara la giornata corrente: crea i record di presenza (default calcolato)
 * per tutti gli alunni attivi, SENZA congelarli e senza scalare buoni.
 *
 * Serve a rendere immediatamente visibile il conteggio "provvisorio" dei pasti
 * nella vista Catering anche prima del cutoff, e la vista giornaliera della
 * scuola con le tre fonti. Il consumo dei buoni resta compito del job
 * post-cutoff (bin/cron_daily.php).
 *
 * Uso:  php bin/prepara_giornata.php [YYYY-MM-DD]
 */

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

$container = new App\Core\Container();
$data = $argv[1] ?? date('Y-m-d');

$creati = 0;
foreach ($container->plessi()->elenco() as $plesso) {
    foreach ($container->bambini()->elencoPerPlesso((int) $plesso['id'], $data) as $bambino) {
        $esistente = $container->presenze()->trovaPresenza((int) $bambino['id'], $data);
        $container->presenzaService()->assicuraRecord((int) $bambino['id'], $data);
        if ($esistente === null) {
            $creati++;
        }
    }
}

echo "Giornata {$data} preparata: {$creati} record di presenza creati.\n";
