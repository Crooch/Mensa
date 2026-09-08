<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Repository\OverrideTariffaRepository;
use App\Repository\PlessoRepository;

/** Calcolo della tariffa applicabile a una coppia (genitore, plesso). */
final class TariffaService
{
    public function __construct(
        private PlessoRepository $plessi,
        private OverrideTariffaRepository $override
    ) {
    }

    /**
     * Tariffa attiva: override piu' recente <= data, altrimenti tariffa base del plesso.
     *
     * @return array{tariffa:float, origine:string, override:?array}
     */
    public function tariffaAttiva(int $genitoreId, int $plessoId, ?string $data = null): array
    {
        $data ??= date('Y-m-d');
        $plesso = $this->plessi->trova($plessoId);
        if ($plesso === null) {
            throw HttpException::nonTrovato('Plesso non trovato');
        }
        $override = $this->override->attivoAllaData($genitoreId, $plessoId, $data);
        if ($override !== null) {
            return ['tariffa' => (float) $override['tariffa'], 'origine' => 'override', 'override' => $override];
        }
        return ['tariffa' => (float) $plesso['tariffa_base_pasto'], 'origine' => 'base', 'override' => null];
    }

    /** Registra un nuovo override tariffario (storicizzato: mai aggiornato in place). */
    public function registraOverride(int $genitoreId, int $plessoId, float $tariffa, string $motivazione, int $utenteId, ?string $data = null): int
    {
        if ($tariffa < 0) {
            throw HttpException::nonValido('La tariffa non può essere negativa');
        }
        if (trim($motivazione) === '') {
            throw HttpException::nonValido('La motivazione è obbligatoria (es. riferimento a delibera)');
        }
        return $this->override->crea([
            'genitore_id'      => $genitoreId,
            'plesso_id'        => $plessoId,
            'tariffa'          => $tariffa,
            'data_inserimento' => $data ?? date('Y-m-d'),
            'motivazione'      => trim($motivazione),
            'utente_id'        => $utenteId,
        ]);
    }

    public function storico(int $genitoreId): array
    {
        return $this->override->storicoGenitore($genitoreId);
    }
}
