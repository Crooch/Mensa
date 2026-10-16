<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\BambinoRepository;
use App\Repository\JobRepository;
use App\Repository\PlessoRepository;
use App\Repository\PresenzaRepository;

/**
 * Job giornaliero, eseguito una volta al giorno PER PLESSO subito dopo il
 * rispettivo cutoff (i cutoff possono essere diversi: lo scheduler valuta ogni
 * plesso separatamente, non esiste un orario unico di sistema).
 *
 * Passi per plesso:
 *  1. congelamento presenze (stato_effettivo fissato)
 *  2. consumo crediti (-1 buono per ogni presente, senza alcun controllo di saldo)
 *  3. report pasti per il catering (aggregato per plesso/tipologia)
 *  4. verifica debiti oltre fido -> escalation email (mai al catering)
 */
final class JobGiornalieroService
{
    public function __construct(
        private PlessoRepository $plessi,
        private BambinoRepository $bambini,
        private PresenzaRepository $presenze,
        private PresenzaService $presenzaService,
        private CreditoService $crediti,
        private SollecitoService $solleciti,
        private JobRepository $job,
        private CalendarioService $calendario
    ) {
    }

    /**
     * Esegue lo scheduler: per ogni plesso il cui cutoff e' passato e che non ha
     * ancora l'esecuzione della giornata, lancia il job. Idempotente.
     *
     * @return array<int, array> esito per plesso
     */
    public function eseguiScheduler(?string $data = null): array
    {
        $data ??= date('Y-m-d');
        $adesso = new \DateTimeImmutable('now');
        $risultati = [];

        foreach ($this->plessi->elenco() as $plesso) {
            $plessoId = (int) $plesso['id'];
            $calendario = $this->calendario->verifica($plessoId, $data);
            if (!$calendario['attivo']) {
                $risultati[] = ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'fuori_calendario', 'motivo' => $calendario['motivo']];
                continue;
            }
            $esecuzione = $this->job->esecuzione($plessoId, $data);
            if ($esecuzione !== null && $esecuzione['esito'] === 'ok') {
                $risultati[] = ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'gia_eseguito'];
                continue;
            }
            // Mai ritentare automaticamente una chiusura fallita: potrebbe aver
            // gia' congelato presenze o registrato alcuni consumi/solleciti.
            // Il recupero richiede riconciliazione e intervento manuale autorizzato.
            if ($esecuzione !== null && $esecuzione['esito'] === 'errore') {
                $risultati[] = [
                    'plesso_id' => $plessoId,
                    'plesso' => $plesso['nome'],
                    'stato' => 'errore_precedente',
                    'motivo' => 'Verificare i movimenti e i log prima del recupero manuale',
                ];
                continue;
            }
            if (!$this->presenzaService->oltreCutoff($plesso, $data, $adesso)) {
                $risultati[] = ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'cutoff_non_raggiunto',
                    'cutoff' => substr((string) $plesso['cutoff_orario'], 0, 5)];
                continue;
            }
            $risultati[] = $this->eseguiPerPlesso($plessoId, $data);
        }

        return $risultati;
    }

    /** Esecuzione forzata per un plesso (pulsante "esegui ora" lato Admin). */
    public function eseguiPerPlesso(int $plessoId, ?string $data = null): array
    {
        $data ??= date('Y-m-d');
        $plesso = $this->plessi->trova($plessoId);
        $calendario = $this->calendario->verifica($plessoId, $data);
        if (!$calendario['attivo']) {
            return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'] ?? '', 'stato' => 'fuori_calendario', 'motivo' => $calendario['motivo']];
        }

        // La funzione manuale non deve permettere addebiti prima del cutoff.
        if (!$this->presenzaService->oltreCutoff($plesso, $data)) {
            return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'cutoff_non_raggiunto'];
        }

        // Lock di connessione: impedisce sovrapposizioni tra cron e click Admin.
        if (!$this->job->acquisisciLock($plessoId, $data)) {
            return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'in_corso'];
        }

        try {
            $esistente = $this->job->esecuzione($plessoId, $data);
            if ($esistente !== null && in_array($esistente['esito'], ['ok', 'in_corso'], true)) {
                return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'],
                    'stato' => $esistente['esito'] === 'ok' ? 'gia_eseguito' : 'in_corso'];
            }
            $esecuzioneId = $this->job->apri($plessoId, $data);

            try {
            $conteggi = $this->presenzaService->congelaPlesso($plessoId, $data);

            $consumi = 0;
            foreach ($this->bambini->elencoPerPlesso($plessoId, $data) as $bambino) {
                $record = $this->presenze->trovaPresenza((int) $bambino['id'], $data);
                if ($record === null || $record['stato_effettivo'] !== 'presente') {
                    continue;
                }
                // Nessun controllo di saldo: il pasto viene sempre erogato e addebitato.
                $movimento = $this->crediti->registraConsumo(
                    (int) $bambino['genitore_id'],
                    $plessoId,
                    (int) $bambino['id'],
                    $data
                );
                if ($movimento !== null) {
                    $this->presenze->modificaPresenza((int) $record['id'], ['consumo_registrato' => 1]);
                    $consumi++;
                }
            }

            $report = $this->presenze->reportPasti($data, $plessoId);
            // Dopo T0 i saldi ripartono da zero: non inviare automaticamente
            // solleciti di debito alla prima giornata. La riattivazione
            // richiede una decisione distinta e configurazione esplicita.
            $solleciti = \App\Core\Config::get('app.solleciti_automatici_enabled', false) === true
                ? $this->solleciti->inviaSollecitiPlesso($plessoId, $data)
                : ['inviati' => 0, 'errori' => 0, 'disabilitati' => true];

            $dettaglio = [
                'presenze'      => $conteggi,
                'consumi'       => $consumi,
                'pasti_report'  => array_sum(array_map('intval', array_column($report, 'pasti'))),
                'solleciti'     => $solleciti,
            ];
            $this->job->chiudi($esecuzioneId, 'ok', $dettaglio);

            return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'], 'stato' => 'eseguito'] + $dettaglio;
        } catch (\Throwable $e) {
            $this->job->chiudi($esecuzioneId, 'errore', ['messaggio' => $e->getMessage()]);
            return ['plesso_id' => $plessoId, 'plesso' => $plesso['nome'] ?? '', 'stato' => 'errore', 'messaggio' => $e->getMessage()];
            }
        } finally {
            $this->job->rilasciaLock($plessoId, $data);
        }
    }

    /**
     * Stato attuale (sola lettura). Non attiva il job, non registra consumi e
     * non pretende di conoscere se il cron esterno sia effettivamente attivo.
     * Ogni scuola viene valutata rispetto al suo calendario e orario limite.
     */
    public function statoGiornata(): array
    {
        $data = date('Y-m-d');
        $scuole = [];
        foreach ($this->plessi->elenco() as $plesso) {
            $plessoId = (int) $plesso['id'];
            $calendario = $this->calendario->verifica($plessoId, $data);
            $esecuzione = $this->job->esecuzione($plessoId, $data);
            $stato = 'da_eseguire';
            if (!$calendario['attivo']) {
                $stato = 'non_prevista';
            } elseif ($esecuzione !== null) {
                $stato = match ($esecuzione['esito']) {
                    'ok' => 'completata',
                    'in_corso' => 'in_corso',
                    'errore' => 'errore',
                    default => 'da_eseguire',
                };
            } elseif (!$this->presenzaService->oltreCutoff($plesso, $data)) {
                $stato = 'in_attesa';
            }
            $dettaglio = [];
            if ($esecuzione !== null) {
                $dati = json_decode((string) ($esecuzione['dettaglio'] ?? ''), true);
                if (is_array($dati)) {
                    $dettaglio = $dati;
                }
            }
            $scuole[] = [
                'plesso_id' => $plessoId,
                'plesso_nome' => $plesso['nome'],
                'orario_limite' => substr((string) $plesso['cutoff_orario'], 0, 5),
                'stato' => $stato,
                'motivo' => $stato === 'non_prevista' ? $calendario['motivo'] : null,
                'pasti_confermati' => $stato === 'completata'
                    ? (int) ($dettaglio['pasti_report'] ?? 0) : null,
                'buoni_utilizzati' => $stato === 'completata'
                    ? (int) ($dettaglio['consumi'] ?? 0) : null,
                'solleciti_inviati' => $stato === 'completata'
                    ? (int) ($dettaglio['solleciti']['inviati'] ?? 0) : null,
                'errori_comunicazioni' => $stato === 'completata'
                    ? (int) ($dettaglio['solleciti']['errori'] ?? 0) : null,
                'concluso_il' => $esecuzione['concluso_il'] ?? null,
            ];
        }
        return [
            'data' => $data,
            'scheduler_abilitato' => \App\Core\\App\Core\Config::schedulerAbilitato(),
            'scuole' => $scuole,
        ];
    }

    public function ultimeEsecuzioni(): array
    {
        return $this->job->ultimeEsecuzioni();
    }
}
