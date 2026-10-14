<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Core\Database;
use App\Repository\AssegnazioneClasseRepository;
use App\Repository\BambinoRepository;
use App\Repository\ClasseSezioneRepository;
use App\Repository\JobRepository;
use App\Repository\PlessoRepository;
use App\Repository\PresenzaRepository;

/**
 * Gestione delle presenze giornaliere e calcolo dello stato effettivo.
 *
 * Tre fonti indipendenti, conservate tutte per tracciabilita':
 *  1. default_calcolato      -> derivato dalle assenze di massa della classe attiva
 *  2. dichiarazione_genitore -> eccezione dichiarata dalla famiglia
 *  3. override_scuola        -> verifica/correzione della scuola (motivata)
 *
 * Priorita' (solo per i contributi arrivati ENTRO il cutoff del plesso):
 *  override_scuola > dichiarazione_genitore > default_calcolato
 *
 * Dopo il cutoff il record viene congelato dal job giornaliero: eventuali
 * dichiarazioni/override tardivi vengono salvati e marcati fuori_cutoff = 1,
 * ma non modificano stato_effettivo ne' il conteggio inviato al catering.
 */
final class PresenzaService
{
    public function __construct(
        private PresenzaRepository $presenze,
        private BambinoRepository $bambini,
        private ClasseSezioneRepository $classi,
        private PlessoRepository $plessi,
        private AssegnazioneClasseRepository $assegnazioni,
        private CalendarioService $calendario,
        private JobRepository $job,
        private CreditoService $crediti
    ) {
    }

    /**
     * Vero se, nel momento in cui viene valutata, la data indicata ha gia'
     * superato il cutoff del plesso (date passate: sempre oltre cutoff).
     */
    public function oltreCutoff(array $plesso, string $data, ?\DateTimeImmutable $adesso = null): bool
    {
        $adesso ??= new \DateTimeImmutable('now');
        $oggi = $adesso->format('Y-m-d');
        if ($data < $oggi) {
            return true;
        }
        if ($data > $oggi) {
            return false;
        }
        return $adesso->format('H:i:s') >= substr((string) $plesso['cutoff_orario'], 0, 8);
    }

    /**
     * Default della giornata: assente se la classe attiva del bambino in quella
     * data ha un'assenza di massa registrata, altrimenti presente.
     */
    public function defaultCalcolato(int $bambinoId, string $data): string
    {
        $assegnazione = $this->assegnazioni->attivaAllaData($bambinoId, $data);
        if ($assegnazione === null) {
            return 'assente'; // nessuna classe attiva: nessun pasto da preparare
        }
        $assenza = $this->presenze->assenzaMassa((int) $assegnazione['classe_sezione_id'], $data);
        return $assenza !== null ? 'assente' : 'presente';
    }

    /** Crea (se assente) il record di presenza della giornata e lo restituisce. */
    public function assicuraRecord(int $bambinoId, string $data): array
    {
        $record = $this->presenze->trovaPresenza($bambinoId, $data);
        if ($record !== null) {
            return $record;
        }
        $default = $this->defaultCalcolato($bambinoId, $data);
        $this->presenze->creaPresenza([
            'bambino_id'        => $bambinoId,
            'data'             => $data,
            'default_calcolato' => $default,
            'stato_effettivo'  => $default,
        ]);
        return $this->presenze->trovaPresenza($bambinoId, $data);
    }

    /**
     * Applica la regola di priorita': override scuola > dichiarazione genitore > default.
     * Va invocata solo su record non congelati: i contributi tardivi non arrivano
     * mai qui, perche' vengono salvati con fuori_cutoff = 1 e il ricalcolo e' saltato.
     *
     * @param array $record record di presenze_giornaliere
     * @return string 'presente' | 'assente'
     */
    public function calcolaStatoEffettivo(array $record): string
    {
        if ($record['override_scuola'] !== null) {
            return $record['override_scuola'];
        }
        if ($record['dichiarazione_genitore'] !== null) {
            return $record['dichiarazione_genitore'];
        }
        return $record['default_calcolato'];
    }

    /**
     * Dichiarazione della famiglia. Ammessa per oggi (entro cutoff) e per date
     * future; oltre il cutoff viene registrata ma marcata fuori_cutoff.
     */
    public function dichiaraGenitore(int $bambinoId, string $data, string $stato, int $utenteId): array
    {
        $this->validaStato($stato);
        $this->validaDataDichiarazione($data);
        $bambino = $this->bambinoConPlesso($bambinoId, $data);
        $plessoId = (int) $bambino['plesso_id'];
        if (!$this->calendario->previsto($plessoId, $data)['attivo']) {
            throw HttpException::nonValido('Non e previsto servizio mensa in questa data');
        }
        $plesso = $this->plessi->trova($plessoId);
        if (!$this->job->acquisisciLock($plessoId, $data)) {
            throw new HttpException(409, 'Elaborazione della giornata in corso. Riprovare.');
        }
        try {
            $this->verificaModificabile($plesso, $plessoId, $data);
            return Database::inTransazione(function () use ($bambinoId, $data, $stato, $utenteId) {
                $record = $this->assicuraRecord($bambinoId, $data);
                if ((int) $record['congelato'] === 1) {
                    throw HttpException::nonValido('Giornata chiusa');
                }
                if ($record['dichiarazione_genitore'] === $stato) {
                    return $record + ['effetto' => 'invariato', 'fonte' => 'dichiarazione'];
                }
                $this->presenze->modificaPresenza((int) $record['id'], [
                    'dichiarazione_genitore' => $stato,
                    'dichiarazione_timestamp' => date('Y-m-d H:i:s'),
                    'dichiarazione_utente_id' => $utenteId,
                    'fuori_cutoff' => 0,
                ]);
                $this->presenze->registraAudit(
                    $bambinoId, $data, 'dichiarazione_genitore',
                    $record['dichiarazione_genitore'], $stato, $utenteId
                );
                return $this->ricalcola($bambinoId, $data, false, 'dichiarazione');
            });
        } finally {
            $this->job->rilasciaLock($plessoId, $data);
        }
    }

    /**
     * Richiesta indipendente di pasta in bianco, per bambino e giornata.
     * Consentita oggi entro cutoff o in un giorno futuro di effettivo servizio.
     */
    public function pastaBiancoGenitore(int $bambinoId, string $data, bool $richiesta, int $utenteId): array
    {
        $this->validaDataDichiarazione($data);
        $bambino = $this->bambinoConPlesso($bambinoId, $data);
        $plessoId = (int) $bambino['plesso_id'];
        if (!$this->calendario->previsto($plessoId, $data)['attivo']) {
            throw HttpException::nonValido('Non e previsto servizio mensa in questa data');
        }
        $plesso = $this->plessi->trova($plessoId);
        if (!$this->job->acquisisciLock($plessoId, $data)) {
            throw new HttpException(409, 'Elaborazione della giornata in corso. Riprovare.');
        }
        try {
            $this->verificaModificabile($plesso, $plessoId, $data);
            return Database::inTransazione(function () use ($bambinoId, $data, $richiesta, $utenteId) {
                $record = $this->assicuraRecord($bambinoId, $data);
                if ((int) $record['congelato'] === 1) {
                    throw HttpException::nonValido('Giornata chiusa');
                }
                $precedente = $this->presenze->richiestaPastaBianco($bambinoId, $data);
                if ($precedente !== null && (int) $precedente['richiesta'] === (int) $richiesta) {
                    return ['bambino_id' => $bambinoId, 'data' => $data, 'richiesta' => $richiesta];
                }
                $this->presenze->salvaPastaBianco($bambinoId, $data, $richiesta, $utenteId);
                $this->presenze->registraAudit(
                    $bambinoId, $data, 'pasta_in_bianco',
                    $precedente === null ? null : (string) $precedente['richiesta'],
                    $richiesta ? '1' : '0', $utenteId
                );
                return ['bambino_id' => $bambinoId, 'data' => $data, 'richiesta' => $richiesta];
            });
        } finally {
            $this->job->rilasciaLock($plessoId, $data);
        }
    }

    /** Override della scuola, sempre motivato. Consentito anche su date passate (correzione tracciata). */
    public function overrideScuola(int $bambinoId, string $data, string $stato, string $motivazione, int $utenteId, ?int $plessoUtente = null): array
    {
        $this->validaStato($stato);
        if (trim($motivazione) === '') {
            throw HttpException::nonValido('La motivazione dell\'override è obbligatoria');
        }
        $bambino = $this->bambinoConPlesso($bambinoId, $data);
        if ($plessoUtente !== null && (int) $bambino['plesso_id'] !== $plessoUtente) {
            throw HttpException::nonAutorizzato('L\'alunno non appartiene al plesso dell\'utenza');
        }
        $plesso = $this->plessi->trova((int) $bambino['plesso_id']);
        $fuoriCutoff = $this->oltreCutoff($plesso, $data);

        $record = $this->assicuraRecord($bambinoId, $data);
        $this->presenze->modificaPresenza((int) $record['id'], [
            'override_scuola'      => $stato,
            'override_timestamp'   => date('Y-m-d H:i:s'),
            'override_utente_id'   => $utenteId,
            'override_motivazione' => trim($motivazione),
            'fuori_cutoff'         => $fuoriCutoff ? 1 : 0,
        ]);

        return $this->ricalcola($bambinoId, $data, $fuoriCutoff, 'override');
    }

    /**
     * Ricalcola stato_effettivo dopo un nuovo contributo.
     * Se il contributo e' fuori cutoff (o il record e' congelato) lo stato non
     * viene toccato: resta quello congelato al momento del cutoff.
     */
    private function ricalcola(int $bambinoId, string $data, bool $fuoriCutoff, string $fonte): array
    {
        $record = $this->presenze->trovaPresenza($bambinoId, $data);
        if ($fuoriCutoff || (int) $record['congelato'] === 1) {
            return $record + ['effetto' => 'registrato_senza_effetto', 'fonte' => $fonte];
        }
        // Nessun contributo tardivo presente: le fonti salvate sono tutte valide.
        $nuovo = $this->calcolaStatoEffettivo($record);
        if ($nuovo !== $record['stato_effettivo']) {
            $this->presenze->modificaPresenza((int) $record['id'], ['stato_effettivo' => $nuovo]);
            $record['stato_effettivo'] = $nuovo;
        }
        return $record + ['effetto' => 'applicato', 'fonte' => $fonte];
    }

    /** Assenza di massa per una classe (gita/uscita didattica). */
    public function dichiaraAssenzaMassa(int $classeSezioneId, string $data, string $motivo, int $utenteId, ?int $plessoUtente = null): int
    {
        $classe = $this->classi->trova($classeSezioneId);
        if ($classe === null) {
            throw HttpException::nonTrovato('Classe non trovata');
        }
        if ($plessoUtente !== null && (int) $classe['plesso_id'] !== $plessoUtente) {
            throw HttpException::nonAutorizzato('La classe non appartiene al plesso dell\'utenza');
        }
        if ($this->presenze->assenzaMassa($classeSezioneId, $data) !== null) {
            throw HttpException::nonValido('Assenza di massa già registrata per questa classe e data');
        }
        $id = $this->presenze->creaAssenzaMassa([
            'classe_sezione_id' => $classeSezioneId,
            'data'              => $data,
            'motivo'            => trim($motivo),
            'utente_id'         => $utenteId,
        ]);

        // Allinea i record già creati per quella data (il default cambia in "assente").
        foreach ($this->bambini->elencoPerClasse($classeSezioneId, $data) as $bambino) {
            $record = $this->presenze->trovaPresenza((int) $bambino['id'], $data);
            if ($record === null || (int) $record['congelato'] === 1) {
                continue;
            }
            $record['default_calcolato'] = 'assente';
            $this->presenze->modificaPresenza((int) $record['id'], [
                'default_calcolato' => 'assente',
                'stato_effettivo'   => $this->calcolaStatoEffettivo($record),
            ]);
        }
        return $id;
    }

    public function elencoAssenzeMassa(?int $plessoId, string $da, string $a): array
    {
        return $this->presenze->elencoAssenzeMassa($plessoId, $da, $a);
    }

    /** Vista giornaliera per classe (uso scuola): tre fonti + stato effettivo. */
    public function vistaClasse(int $classeSezioneId, string $data, ?int $plessoUtente = null): array
    {
        $classe = $this->classi->trova($classeSezioneId);
        if ($classe === null) {
            throw HttpException::nonTrovato('Classe non trovata');
        }
        if ($plessoUtente !== null && (int) $classe['plesso_id'] !== $plessoUtente) {
            throw HttpException::nonAutorizzato();
        }
        $plesso = $this->plessi->trova((int) $classe['plesso_id']);
        $righe = $this->presenze->dettaglioGiornoClasse($classeSezioneId, $data);

        // Per gli alunni senza record il default e' calcolato "a vista" (nessuna scrittura).
        $assenzaMassa = $this->presenze->assenzaMassa($classeSezioneId, $data);
        foreach ($righe as &$riga) {
            if ($riga['presenza_id'] === null) {
                $riga['default_calcolato'] = $assenzaMassa !== null ? 'assente' : 'presente';
                $riga['stato_effettivo'] = $riga['default_calcolato'];
                $riga['fuori_cutoff'] = 0;
                $riga['congelato'] = 0;
            }
        }
        unset($riga);

        return [
            'classe'        => $classe,
            'data'          => $data,
            'cutoff_orario' => substr((string) $plesso['cutoff_orario'], 0, 5),
            'oltre_cutoff'  => $this->oltreCutoff($plesso, $data),
            'assenza_massa' => $assenzaMassa,
            'alunni'        => $righe,
        ];
    }

    /** Calendario mensile di un bambino con dettaglio delle tre fonti. */
    public function calendarioBambino(int $bambinoId, int $anno, int $mese): array
    {
        $inizio = sprintf('%04d-%02d-01', $anno, $mese);
        $fine = date('Y-m-t', strtotime($inizio));
        $giorni = [];
        foreach ($this->presenze->calendarioBambino($bambinoId, $inizio, $fine) as $riga) {
            $giorni[$riga['data']] = $riga;
        }
        return ['data_da' => $inizio, 'data_a' => $fine, 'giorni' => array_values($giorni)];
    }

    /** Stato della giornata per un bambino, con le fonti e l'informazione di cutoff. */
    public function statoGiornata(int $bambinoId, string $data): array
    {
        $bambino = $this->bambinoConPlesso($bambinoId, $data);
        $plesso = $this->plessi->trova((int) $bambino['plesso_id']);
        $record = $this->presenze->trovaPresenza($bambinoId, $data);
        $default = $record['default_calcolato'] ?? $this->defaultCalcolato($bambinoId, $data);

        return [
            'bambino_id'             => $bambinoId,
            'data'                   => $data,
            'default_calcolato'      => $default,
            'dichiarazione_genitore' => $record['dichiarazione_genitore'] ?? null,
            'override_scuola'        => $record['override_scuola'] ?? null,
            'override_motivazione'   => $record['override_motivazione'] ?? null,
            'stato_effettivo'        => $record['stato_effettivo'] ?? $default,
            'pasta_in_bianco'        => (int) ($this->presenze->richiestaPastaBianco($bambinoId, $data)['richiesta'] ?? 0) === 1,
            'fuori_cutoff'           => (int) ($record['fuori_cutoff'] ?? 0),
            'congelato'              => (int) ($record['congelato'] ?? 0),
            'cutoff_orario'          => substr((string) $plesso['cutoff_orario'], 0, 5),
            'oltre_cutoff'           => $this->oltreCutoff($plesso, $data),
            'servizio_mensa'         => $this->calendario->previsto((int) $bambino['plesso_id'], $data),
        ];
    }

    /**
     * Congelamento di fine cutoff per un plesso: fissa stato_effettivo di tutti
     * i bambini attivi in quella data. Invocato dal job giornaliero.
     *
     * @return array<string,int> conteggi per stato
     */
    public function congelaPlesso(int $plessoId, string $data): array
    {
        $conteggi = ['presente' => 0, 'assente' => 0];
        foreach ($this->bambini->elencoPerPlesso($plessoId, $data) as $bambino) {
            $record = $this->assicuraRecord((int) $bambino['id'], $data);
            if ((int) $record['congelato'] !== 1) {
                $stato = (int) $record['fuori_cutoff'] === 1 ? $record['stato_effettivo'] : $this->calcolaStatoEffettivo($record);
                $this->presenze->modificaPresenza((int) $record['id'], [
                    'stato_effettivo' => $stato,
                    'congelato'       => 1,
                ]);
                $record['stato_effettivo'] = $stato;
            }
            $conteggi[$record['stato_effettivo']]++;
        }
        return $conteggi;
    }

    /** Validazione lato server; nessun limite arbitrario alle assenze future. */
    private function validaDataDichiarazione(string $data): void
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if ($d === false || $d->format('Y-m-d') !== $data) {
            throw HttpException::nonValido('Data non valida');
        }
        if ($data < date('Y-m-d')) {
            throw HttpException::nonValido('La giornata e gia trascorsa');
        }
    }

    /** Da chiamare solo dopo aver preso il lock condiviso con lo scheduler. */
    private function verificaModificabile(array $plesso, int $plessoId, string $data): void
    {
        if ($this->oltreCutoff($plesso, $data)) {
            throw HttpException::nonValido('La giornata e chiusa: termine per le modifiche superato');
        }
        $esecuzione = $this->job->esecuzione($plessoId, $data);
        if ($esecuzione !== null && in_array($esecuzione['esito'], ['ok', 'in_corso'], true)) {
            throw HttpException::nonValido('La giornata e gia contabilizzata o in elaborazione');
        }
    }

    private function validaStato(string $stato): void
    {
        if (!in_array($stato, ['presente', 'assente'], true)) {
            throw HttpException::nonValido('Stato non valido: ammessi "presente" o "assente"');
        }
    }

    /** @return array bambino con plesso/classe attivi nella data */
    private function bambinoConPlesso(int $bambinoId, string $data): array
    {
        $bambino = $this->bambini->trova($bambinoId, $data);
        if ($bambino === null) {
            throw HttpException::nonTrovato('Alunno non trovato');
        }
        if ($bambino['plesso_id'] === null) {
            throw HttpException::nonValido('L\'alunno non ha una classe attiva nella data indicata');
        }
        return $bambino;
    }
}
