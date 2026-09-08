<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\HttpException;
use App\Repository\AssegnazioneClasseRepository;
use App\Repository\BambinoRepository;
use App\Repository\ClasseSezioneRepository;
use App\Repository\GenitoreRepository;

/**
 * Anagrafiche genitori/bambini e assegnazioni classe.
 * Tutte le cancellazioni sono logiche: gli storici restano consultabili.
 */
final class AnagraficaService
{
    public function __construct(
        private GenitoreRepository $genitori,
        private BambinoRepository $bambini,
        private AssegnazioneClasseRepository $assegnazioni,
        private ClasseSezioneRepository $classi,
        private AuthService $auth,
        private CreditoService $crediti,
        private TariffaService $tariffe
    ) {
    }

    public function cercaGenitori(?string $testo, bool $includiCancellati = false): array
    {
        return $this->genitori->cerca($testo, $includiCancellati);
    }

    /**
     * Scheda completa della famiglia: genitore, figli con storico classi,
     * override tariffari e situazione crediti per plesso.
     */
    public function schedaFamiglia(int $genitoreId): array
    {
        $genitore = $this->genitori->trova($genitoreId);
        if ($genitore === null) {
            throw HttpException::nonTrovato('Genitore non trovato');
        }
        $oggi = date('Y-m-d');
        $figli = [];
        foreach ($this->bambini->elencoPerGenitore($genitoreId, $oggi) as $bambino) {
            $bambino['storico_classi'] = $this->assegnazioni->storicoBambino((int) $bambino['id']);
            $figli[] = $bambino;
        }

        return [
            'genitore'         => $genitore,
            'figli'            => $figli,
            'override_tariffe' => $this->tariffe->storico($genitoreId),
            'crediti'          => $this->crediti->situazioneGenitore($genitoreId),
            'movimenti'        => $this->crediti->movimenti($genitoreId),
        ];
    }

    /**
     * Crea genitore + relativa utenza (username/password definiti dall'ufficio).
     * Il telefono e' obbligatorio, l'email facoltativa (serve all'escalation debito).
     */
    public function creaGenitore(array $dati): int
    {
        foreach (['nome', 'cognome', 'telefono', 'username', 'password'] as $campo) {
            if (empty($dati[$campo])) {
                throw HttpException::nonValido("Campo obbligatorio mancante: {$campo}");
            }
        }
        return Database::inTransazione(function () use ($dati) {
            $utenteId = $this->auth->creaUtente(
                'genitore',
                (string) $dati['username'],
                (string) $dati['password'],
                trim($dati['cognome'] . ' ' . $dati['nome'])
            );
            return $this->genitori->crea([
                'utente_id' => $utenteId,
                'nome'      => trim((string) $dati['nome']),
                'cognome'   => trim((string) $dati['cognome']),
                'telefono'  => trim((string) $dati['telefono']),
                'email'     => !empty($dati['email']) ? trim((string) $dati['email']) : null,
                'note'      => $dati['note'] ?? null,
            ]);
        });
    }

    public function modificaGenitore(int $id, array $dati): void
    {
        $ammessi = array_intersect_key($dati, array_flip(['nome', 'cognome', 'telefono', 'email', 'note']));
        if (array_key_exists('telefono', $ammessi) && trim((string) $ammessi['telefono']) === '') {
            throw HttpException::nonValido('Il telefono è obbligatorio');
        }
        if (array_key_exists('email', $ammessi) && $ammessi['email'] === '') {
            $ammessi['email'] = null;
        }
        $this->genitori->modifica($id, $ammessi);
    }

    public function cancellaGenitore(int $id): void
    {
        $this->genitori->cancellaLogicamente($id);
    }

    /** Crea il bambino e, se indicata, la prima assegnazione di classe. */
    public function creaBambino(array $dati): int
    {
        foreach (['nome', 'cognome', 'genitore_id', 'tipologia_menu_id'] as $campo) {
            if (empty($dati[$campo])) {
                throw HttpException::nonValido("Campo obbligatorio mancante: {$campo}");
            }
        }
        return Database::inTransazione(function () use ($dati) {
            $bambinoId = $this->bambini->crea([
                'nome'              => trim((string) $dati['nome']),
                'cognome'           => trim((string) $dati['cognome']),
                'genitore_id'       => (int) $dati['genitore_id'],
                'tipologia_menu_id' => (int) $dati['tipologia_menu_id'],
            ]);
            if (!empty($dati['classe_sezione_id'])) {
                $this->assegnaClasse($bambinoId, (int) $dati['classe_sezione_id'], $dati['data_inizio'] ?? date('Y-m-d'));
            }
            return $bambinoId;
        });
    }

    public function modificaBambino(int $id, array $dati): void
    {
        $ammessi = array_intersect_key($dati, array_flip(['nome', 'cognome', 'tipologia_menu_id']));
        $this->bambini->modifica($id, $ammessi);
    }

    public function cancellaBambino(int $id): void
    {
        $this->bambini->cancellaLogicamente($id);
    }

    /**
     * Cambio classe/plesso con storicizzazione: chiude l'assegnazione corrente
     * al giorno precedente e apre la nuova. Mai un UPDATE sulla vecchia riga,
     * cosi' le presenze storiche restano riferite alla classe corretta.
     */
    public function assegnaClasse(int $bambinoId, int $classeSezioneId, ?string $dataInizio = null): int
    {
        $dataInizio ??= date('Y-m-d');
        if ($this->classi->trova($classeSezioneId) === null) {
            throw HttpException::nonTrovato('Classe non trovata');
        }
        $attuale = $this->assegnazioni->attivaAllaData($bambinoId, $dataInizio);
        if ($attuale !== null && (int) $attuale['classe_sezione_id'] === $classeSezioneId) {
            throw HttpException::nonValido('L\'alunno è già assegnato a questa classe');
        }
        $giornoPrima = (new \DateTimeImmutable($dataInizio))->modify('-1 day')->format('Y-m-d');
        $this->assegnazioni->chiudiCorrente($bambinoId, $giornoPrima);
        return $this->assegnazioni->crea($bambinoId, $classeSezioneId, $dataInizio);
    }

    public function storicoClassi(int $bambinoId): array
    {
        return $this->assegnazioni->storicoBambino($bambinoId);
    }

    /** Figli del genitore con classe/plesso attivi oggi (usato dalla dashboard genitore). */
    public function figli(int $genitoreId, ?string $data = null): array
    {
        return $this->bambini->elencoPerGenitore($genitoreId, $data ?? date('Y-m-d'));
    }

    public function bambinoDelGenitore(int $bambinoId, int $genitoreId, ?string $data = null): array
    {
        $bambino = $this->bambini->trova($bambinoId, $data ?? date('Y-m-d'));
        if ($bambino === null || (int) $bambino['genitore_id'] !== $genitoreId) {
            throw HttpException::nonAutorizzato('L\'alunno non risulta associato all\'utenza');
        }
        return $bambino;
    }
}
