<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Repository\CreditoRepository;
use App\Repository\PlessoRepository;

/**
 * Gestione del pool crediti (buoni pasto) condiviso per coppia (genitore, plesso).
 *
 * PRINCIPIO NON NEGOZIABILE: nessun metodo di questa classe blocca la
 * registrazione di un consumo pasto per saldo insufficiente. Il superamento del
 * fido attiva solo l'escalation informativa (SollecitoService).
 */
final class CreditoService
{
    public function __construct(
        private CreditoRepository $crediti,
        private PlessoRepository $plessi,
        private TariffaService $tariffe
    ) {
    }

    /**
     * Registra l'acquisto di buoni: importo = numero_buoni x tariffa attiva.
     * L'importo non e' modificabile manualmente dall'operatore.
     *
     * @return array{movimento_id:int, importo:float, tariffa:float, origine_tariffa:string, saldo:int}
     */
    public function acquistaBuoni(int $genitoreId, int $plessoId, int $numeroBuoni, int $utenteId,
                                  ?string $note = null, ?string $richiestaId = null): array
    {
        if ($numeroBuoni <= 0) {
            throw HttpException::nonValido('Il numero di buoni deve essere maggiore di zero');
        }
        if ($richiestaId !== null && !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $richiestaId)) {
            throw HttpException::nonValido('Identificativo della richiesta non valido');
        }
        $tariffa = $this->tariffe->tariffaAttiva($genitoreId, $plessoId);
        $importo = round($numeroBuoni * $tariffa['tariffa'], 2);

        // Il medesimo token deve sempre riferirsi allo stesso acquisto.
        // Il controllo preliminare migliora la risposta; il DB UNIQUE evita
        // duplicati anche fra richieste parallele da browser diversi.
        if ($richiestaId !== null) {
            $precedente = $this->crediti->acquistoPerRichiesta($richiestaId);
            if ($precedente !== null) {
                return $this->rispostaAcquistoEsistente($precedente, $genitoreId, $plessoId, $numeroBuoni, $utenteId);
            }
        }

        try {
            return Database::inTransazione(function () use (
                $genitoreId, $plessoId, $numeroBuoni, $utenteId, $note, $tariffa, $importo, $richiestaId
            ) {
                $poolId = $this->crediti->assicuraPool($genitoreId, $plessoId);
                $movimentoId = $this->crediti->registraMovimento([
                    'pool_id'      => $poolId,
                    'tipo'         => 'acquisto',
                    'delta'        => $numeroBuoni,
                    'data'         => date('Y-m-d'),
                    'bambino_id'   => null,
                    'importo_euro' => $importo,
                    'utente_id'    => $utenteId,
                    'note'         => $note,
                    'richiesta_id' => $richiestaId,
                ]);
                $this->crediti->aggiornaSaldo($poolId, $numeroBuoni);
                $pool = $this->crediti->trovaPool($genitoreId, $plessoId);

                return [
                    'movimento_id'    => $movimentoId,
                    'importo'         => $importo,
                    'tariffa'         => $tariffa['tariffa'],
                    'origine_tariffa' => $tariffa['origine'],
                    'saldo'           => (int) $pool['saldo'],
                    'gia_registrato'  => false,
                ];
            });
        } catch (\PDOException $errore) {
            // Un retry parallelo può incontrare la chiave UNIQUE già inserita.
            // La transazione fallita è stata annullata: rilegge il movimento
            // originario senza alterare una seconda volta il saldo.
            if ($richiestaId === null || (string) $errore->getCode() !== '23000') {
                throw $errore;
            }
            $precedente = $this->crediti->acquistoPerRichiesta($richiestaId);
            if ($precedente === null) {
                throw $errore;
            }
            return $this->rispostaAcquistoEsistente($precedente, $genitoreId, $plessoId, $numeroBuoni, $utenteId);
        }
    }

    private function rispostaAcquistoEsistente(array $precedente, int $genitoreId, int $plessoId,
                                               int $numeroBuoni, int $utenteId): array
    {
        if ((int) $precedente['genitore_id'] !== $genitoreId
            || (int) $precedente['plesso_id'] !== $plessoId
            || (int) $precedente['delta'] !== $numeroBuoni
            || (int) $precedente['utente_id'] !== $utenteId) {
            throw HttpException::nonValido('Questa richiesta è già stata usata per un acquisto diverso. Riapri il modulo.');
        }
        $pool = $this->crediti->trovaPool($genitoreId, $plessoId);
        return [
            'movimento_id'    => (int) $precedente['id'],
            'importo'         => (float) $precedente['importo_euro'],
            'tariffa'         => (float) $precedente['importo_euro'] / $numeroBuoni,
            'origine_tariffa' => 'acquisto_precedente',
            'saldo'           => (int) $pool['saldo'],
            'gia_registrato'  => true,
        ];
    }

    /** Rettifica manuale del saldo (con nota obbligatoria per tracciabilita'). */
    public function rettifica(int $genitoreId, int $plessoId, int $delta, string $note,
                             int $utenteId, ?string $richiestaId = null): int
    {
        if ($delta === 0) {
            throw HttpException::nonValido('La correzione deve aggiungere o togliere almeno un buono');
        }
        $note = trim($note);
        if ($note === '') {
            throw HttpException::nonValido('Indica il motivo della correzione');
        }
        if ($richiestaId !== null && !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $richiestaId)) {
            throw HttpException::nonValido('Identificativo della richiesta non valido');
        }
        if ($richiestaId !== null) {
            $precedente = $this->crediti->rettificaPerRichiesta($richiestaId);
            if ($precedente !== null) {
                return $this->verificaRettificaEsistente($precedente, $genitoreId, $plessoId, $delta, $note, $utenteId);
            }
        }
        try {
            return Database::inTransazione(function () use ($genitoreId, $plessoId, $delta, $note, $utenteId, $richiestaId) {
                $poolId = $this->crediti->assicuraPool($genitoreId, $plessoId);
                $id = $this->crediti->registraMovimento([
                    'pool_id' => $poolId,
                    'tipo' => 'rettifica_manuale',
                    'delta' => $delta,
                    'data' => date('Y-m-d'),
                    'utente_id' => $utenteId,
                    'note' => $note,
                    'richiesta_id' => $richiestaId,
                ]);
                $this->crediti->aggiornaSaldo($poolId, $delta);
                return $id;
            });
        } catch (\PDOException $errore) {
            if ($richiestaId === null || (string) $errore->getCode() !== '23000') {
                throw $errore;
            }
            $precedente = $this->crediti->rettificaPerRichiesta($richiestaId);
            if ($precedente === null) {
                throw $errore;
            }
            return $this->verificaRettificaEsistente($precedente, $genitoreId, $plessoId, $delta, $note, $utenteId);
        }
    }

    private function verificaRettificaEsistente(array $precedente, int $genitoreId, int $plessoId,
                                               int $delta, string $note, int $utenteId): int
    {
        if ((int) $precedente['genitore_id'] !== $genitoreId
            || (int) $precedente['plesso_id'] !== $plessoId
            || (int) $precedente['delta'] !== $delta
            || (string) $precedente['note'] !== $note
            || (int) $precedente['utente_id'] !== $utenteId) {
            throw HttpException::nonValido('Questa richiesta è già stata usata per una correzione diversa. Riapri il modulo.');
        }
        return (int) $precedente['id'];
    }

    /**
     * Consumo pasto (-1 buono). Chiamato dal job giornaliero dopo il cutoff.
     * Non effettua alcun controllo di saldo: il bambino mangia sempre.
     */
    public function registraConsumo(int $genitoreId, int $plessoId, int $bambinoId, string $data): ?int
    {
        return Database::inTransazione(fn () =>
            $this->registraConsumoInTransazione($genitoreId, $plessoId, $bambinoId, $data)
        );
    }

    /**
     * Stesso movimento usato dal job e dalle eccezioni accertate dalla scuola.
     * Il chiamante DEVE avere gia' aperto una transazione SQL e acquisito il
     * lock plesso/data prima di accedere a questo metodo.
     */
    public function registraConsumoInTransazione(int $genitoreId, int $plessoId,
                                                 int $bambinoId, string $data, ?int $utenteId = null): ?int
    {
        if ($this->crediti->esisteConsumo($bambinoId, $data)) {
            return null;
        }
        $poolId = $this->crediti->assicuraPool($genitoreId, $plessoId);
        $id = $this->crediti->registraMovimento([
            'pool_id' => $poolId,
            'tipo' => 'consumo_pasto',
            'delta' => -1,
            'data' => $data,
            'bambino_id' => $bambinoId,
            'utente_id' => $utenteId,
            'note' => $utenteId === null ? 'Consumo automatico post-cutoff' : 'Pasto confermato dalla scuola',
        ]);
        $this->crediti->aggiornaSaldo($poolId, -1);
        return $id;
    }

    /** Stato visivo del pool: verde / giallo / rosso in base al fido del plesso. */
    public function statoPool(int $saldo, int $fido): string
    {
        if ($saldo >= 0) {
            return 'verde';
        }
        return $saldo <= -$fido ? 'rosso' : 'giallo';
    }

    /** Situazione crediti di un genitore, con stato colore per plesso. */
    public function situazioneGenitore(int $genitoreId): array
    {
        $risultato = [];
        foreach ($this->crediti->poolDelGenitore($genitoreId) as $pool) {
            $saldo = (int) $pool['saldo'];
            $fido = (int) $pool['fido_buoni'];
            $tariffa = $this->tariffe->tariffaAttiva($genitoreId, (int) $pool['plesso_id']);
            $risultato[] = [
                'plesso_id'       => (int) $pool['plesso_id'],
                'plesso_nome'     => $pool['plesso_nome'],
                'saldo'           => $saldo,
                'fido_buoni'      => $fido,
                'stato'           => $this->statoPool($saldo, $fido),
                'tariffa_attiva'  => $tariffa['tariffa'],
                'origine_tariffa' => $tariffa['origine'],
            ];
        }
        return $risultato;
    }

    public function movimenti(int $genitoreId, ?string $tipo = null): array
    {
        return $this->crediti->movimenti($genitoreId, $tipo);
    }

    public function situazioneGenerale(): array
    {
        return array_map(function (array $riga) {
            $riga['stato'] = $this->statoPool((int) $riga['saldo'], (int) $riga['fido_buoni']);
            return $riga;
        }, $this->crediti->situazioneGenerale());
    }
}
