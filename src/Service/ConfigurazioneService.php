<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\HttpException;
use App\Repository\ClasseSezioneRepository;
use App\Repository\ComunicazioneRepository;
use App\Repository\PlessoRepository;
use App\Repository\TipologiaMenuRepository;

/** Configurazioni di competenza Admin: plessi, classi, tipologie, email. */
final class ConfigurazioneService
{
    public function __construct(
        private PlessoRepository $plessi,
        private ClasseSezioneRepository $classi,
        private TipologiaMenuRepository $tipologie,
        private ComunicazioneRepository $comunicazioni
    ) {
    }

    // ---------------- plessi ----------------

    public function plessi(bool $soloAttivi = true): array
    {
        return $this->plessi->elenco($soloAttivi);
    }

    public function creaPlesso(array $dati): int
    {
        return $this->plessi->crea($this->normalizzaPlesso($dati, true));
    }

    public function modificaPlesso(int $id, array $dati): void
    {
        $this->plessi->modifica($id, $this->normalizzaPlesso($dati, false));
    }

    public function cancellaPlesso(int $id): void
    {
        $this->plessi->cancellaLogicamente($id);
    }

    private function normalizzaPlesso(array $dati, bool $creazione): array
    {
        $campi = ['nome', 'tariffa_base_pasto', 'fido_buoni', 'cutoff_orario', 'dirigente_email', 'email_protocollo'];
        $valori = array_intersect_key($dati, array_flip($campi));
        if ($creazione && empty($valori['nome'])) {
            throw HttpException::nonValido('Il nome del plesso è obbligatorio');
        }
        if (isset($valori['cutoff_orario']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $valori['cutoff_orario']) !== 1) {
            throw HttpException::nonValido('Formato cutoff non valido (HH:MM)');
        }
        if (isset($valori['fido_buoni'])) {
            $valori['fido_buoni'] = max(0, (int) $valori['fido_buoni']);
        }
        if (isset($valori['tariffa_base_pasto'])) {
            $valori['tariffa_base_pasto'] = round((float) $valori['tariffa_base_pasto'], 2);
        }
        return $valori;
    }

    // ---------------- classi/sezioni ----------------

    public function classi(?int $plessoId = null, ?string $anno = null): array
    {
        return $this->classi->elenco($plessoId, $anno);
    }

    public function creaClasse(array $dati): int
    {
        foreach (['plesso_id', 'nome', 'anno_scolastico'] as $campo) {
            if (empty($dati[$campo])) {
                throw HttpException::nonValido("Campo obbligatorio mancante: {$campo}");
            }
        }
        return $this->classi->crea([
            'plesso_id'       => (int) $dati['plesso_id'],
            'nome'            => trim((string) $dati['nome']),
            'anno_scolastico' => trim((string) $dati['anno_scolastico']),
        ]);
    }

    public function modificaClasse(int $id, array $dati): void
    {
        $this->classi->modifica($id, array_intersect_key($dati, array_flip(['nome', 'anno_scolastico'])));
    }

    public function cancellaClasse(int $id): void
    {
        $this->classi->cancellaLogicamente($id);
    }

    // ---------------- tipologie menu ----------------

    public function tipologie(bool $soloAttive = true): array
    {
        return $this->tipologie->elenco($soloAttive);
    }

    public function creaTipologia(string $nome): int
    {
        if (trim($nome) === '') {
            throw HttpException::nonValido('Il nome della tipologia è obbligatorio');
        }
        return $this->tipologie->crea(['nome' => trim($nome)]);
    }

    public function modificaTipologia(int $id, string $nome): void
    {
        $this->tipologie->modifica($id, ['nome' => trim($nome)]);
    }

    public function cancellaTipologia(int $id): void
    {
        $this->tipologie->cancellaLogicamente($id);
    }

    // ---------------- email: modelli, destinatari, impostazioni ----------------

    public function modelli(): array
    {
        return $this->comunicazioni->modelli();
    }

    public function salvaModello(int $plessoId, string $oggetto, string $testo, bool $attivo): void
    {
        if (trim($oggetto) === '' || trim($testo) === '') {
            throw HttpException::nonValido('Oggetto e testo del modello sono obbligatori');
        }
        $this->comunicazioni->salvaModello($plessoId, trim($oggetto), trim($testo), $attivo ? 1 : 0);
    }

    public function destinatariFissi(bool $soloAttivi = false): array
    {
        return $this->comunicazioni->destinatariFissi($soloAttivi);
    }

    public function creaDestinatario(string $etichetta, string $email): int
    {
        if (trim($etichetta) === '' || trim($email) === '') {
            throw HttpException::nonValido('Etichetta ed email sono obbligatorie');
        }
        return $this->comunicazioni->creaDestinatario(['etichetta' => trim($etichetta), 'email' => trim($email), 'attivo' => 1]);
    }

    public function modificaDestinatario(int $id, array $dati): void
    {
        $this->comunicazioni->modificaDestinatario($id, array_intersect_key($dati, array_flip(['etichetta', 'email', 'attivo'])));
    }

    /** Impostazioni globali: la password SMTP non viene mai restituita in chiaro. */
    public function impostazioni(): array
    {
        $valori = $this->comunicazioni->impostazioni();
        $valori['smtp_password'] = !empty($valori['smtp_password']) ? '********' : '';
        return $valori;
    }

    public function salvaImpostazioni(array $dati): void
    {
        $chiaviAmmesse = ['email_protocollo', 'smtp_host', 'smtp_port', 'smtp_security', 'smtp_username', 'smtp_password', 'smtp_from', 'smtp_from_name'];
        foreach ($dati as $chiave => $valore) {
            if (!in_array($chiave, $chiaviAmmesse, true)) {
                continue;
            }
            if ($chiave === 'smtp_password' && ($valore === '********' || $valore === null)) {
                continue; // valore mascherato: non sovrascrivere
            }
            $this->comunicazioni->salvaImpostazione($chiave, $valore === null ? null : (string) $valore);
        }
    }
}
