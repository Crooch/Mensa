<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Repository\AssegnazioneClasseRepository;
use App\Repository\FeedbackRepository;

/**
 * Feedback sui pasti: sempre storicizzati, mai sovrascritti.
 * Il catering li vede in sola lettura e senza dati anagrafici delle famiglie.
 */
final class FeedbackService
{
    public function __construct(
        private FeedbackRepository $feedback,
        private AnagraficaService $anagrafica,
        private AssegnazioneClasseRepository $assegnazioni
    ) {
    }

    /**
     * Inserisce un feedback per un figlio. Almeno uno tra voto e commento.
     * Ammesso anche se il catering non ha pubblicato il menu di quel giorno.
     */
    public function inserisci(int $genitoreId, int $bambinoId, string $data, ?int $voto, ?string $commento): int
    {
        $bambino = $this->anagrafica->bambinoDelGenitore($bambinoId, $genitoreId, $data);
        if ($voto === null && ($commento === null || trim($commento) === '')) {
            throw HttpException::nonValido('Indicare almeno un voto o un commento');
        }
        if ($voto !== null && ($voto < 1 || $voto > 5)) {
            throw HttpException::nonValido('Il voto deve essere compreso tra 1 e 5');
        }
        if ($data > date('Y-m-d')) {
            throw HttpException::nonValido('Non è possibile inserire feedback per date future');
        }

        return $this->feedback->crea([
            'bambino_id'        => $bambinoId,
            'data'              => $data,
            // Tipologia del bambino alla data del pasto (dato storicizzato nel feedback).
            'tipologia_menu_id' => (int) $bambino['tipologia_menu_id'],
            'voto'              => $voto,
            'commento'          => $commento !== null ? trim($commento) : null,
            'utente_id'         => null,
        ]);
    }

    public function storicoGenitore(int $genitoreId): array
    {
        return $this->feedback->perGenitore($genitoreId);
    }

    public function elencoCatering(array $filtri): array
    {
        return $this->feedback->perCatering($filtri);
    }
}
