<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\HttpException;
use App\Repository\MenuGiornalieroRepository;
use App\Repository\PlessoRepository;
use App\Repository\PresenzaRepository;
use App\Repository\TipologiaMenuRepository;

/**
 * Vista catering: report pasti, pubblicazione menu del giorno.
 * Il catering non riceve mai informazioni economiche o di credito: il report e'
 * calcolato esclusivamente dalle presenze effettive.
 */
final class CateringService
{
    public function __construct(
        private PresenzaRepository $presenze,
        private PlessoRepository $plessi,
        private TipologiaMenuRepository $tipologie,
        private MenuGiornalieroRepository $menu,
        private PresenzaService $presenzaService
    ) {
    }

    /**
     * Report pasti da preparare per una data, raggruppato per plesso e tipologia.
     * Indica per ciascun plesso se il cutoff e' stato superato (dato definitivo)
     * oppure se il conteggio e' ancora provvisorio.
     */
    public function report(string $data, ?int $plessoId = null): array
    {
        $righe = $this->presenze->reportPasti($data, $plessoId);
        $perPlesso = [];
        foreach ($righe as $riga) {
            $pid = (int) $riga['plesso_id'];
            if (!isset($perPlesso[$pid])) {
                $plesso = $this->plessi->trova($pid);
                $perPlesso[$pid] = [
                    'plesso_id'     => $pid,
                    'plesso_nome'   => $riga['plesso_nome'],
                    'cutoff_orario' => substr((string) $plesso['cutoff_orario'], 0, 5),
                    'definitivo'    => $this->presenzaService->oltreCutoff($plesso, $data),
                    'totale_pasti'  => 0,
                    'tipologie'     => [],
                ];
            }
            $perPlesso[$pid]['totale_pasti'] += (int) $riga['pasti'];
            $perPlesso[$pid]['tipologie'][] = [
                'tipologia_id'   => (int) $riga['tipologia_id'],
                'tipologia_nome' => $riga['tipologia_nome'],
                'pasti'          => (int) $riga['pasti'],
            ];
        }

        return [
            'data'         => $data,
            'plessi'       => array_values($perPlesso),
            'totale_pasti' => array_sum(array_column($perPlesso, 'totale_pasti')),
        ];
    }

    public function menuDelGiorno(string $data, ?int $plessoId = null): array
    {
        return $this->menu->elenco($data, $plessoId);
    }

    /**
     * Pubblica/aggiorna il menu per una combinazione plesso+tipologia+data.
     * La foto e' salvata su filesystem in /storage/menu/{plesso}_{tipologia}/{data}.jpg
     *
     * @param array|null $file elemento di $_FILES
     */
    public function pubblicaMenu(int $plessoId, int $tipologiaId, string $data, ?string $descrizione, ?array $file, int $utenteId): int
    {
        if ($this->plessi->trova($plessoId) === null || $this->tipologie->trova($tipologiaId) === null) {
            throw HttpException::nonTrovato('Plesso o tipologia non validi');
        }
        if (($descrizione === null || trim($descrizione) === '') && ($file === null || ($file['error'] ?? 1) !== UPLOAD_ERR_OK)) {
            throw HttpException::nonValido('Indicare almeno una descrizione o una foto');
        }

        $fotoPath = null;
        if ($file !== null && ($file['error'] ?? 1) === UPLOAD_ERR_OK) {
            $fotoPath = $this->salvaFoto($plessoId, $tipologiaId, $data, $file);
        }
        return $this->menu->salva($plessoId, $tipologiaId, $data, $descrizione, $fotoPath, $utenteId);
    }

    /** Salvataggio su filesystem con path prevedibile e portabile (no storage cloud). */
    private function salvaFoto(int $plessoId, int $tipologiaId, string $data, array $file): string
    {
        $max = (int) Config::get('storage.max_upload_bytes', 4194304);
        if (($file['size'] ?? 0) > $max) {
            throw HttpException::nonValido('File troppo grande');
        }
        $estensioniAmmesse = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
        $estensione = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset($estensioniAmmesse[$estensione])) {
            throw HttpException::nonValido('Formato immagine non supportato (ammessi jpg, png, webp)');
        }

        $base = (string) Config::get('storage.menu_dir');
        $cartella = $base . DIRECTORY_SEPARATOR . $plessoId . '_' . $tipologiaId;
        if (!is_dir($cartella) && !mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new \RuntimeException('Impossibile creare la cartella di storage');
        }
        $nomeFile = $data . '.' . $estensioniAmmesse[$estensione];
        $destinazione = $cartella . DIRECTORY_SEPARATOR . $nomeFile;
        if (!move_uploaded_file($file['tmp_name'], $destinazione)) {
            throw new \RuntimeException('Salvataggio del file non riuscito');
        }
        // Path relativo memorizzato con separatore "/" per essere valido anche in URL.
        return 'menu/' . $plessoId . '_' . $tipologiaId . '/' . $nomeFile;
    }
}
