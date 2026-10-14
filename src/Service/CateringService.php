<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\HttpException;
use App\Repository\JobRepository;
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
        private PresenzaService $presenzaService,
        private CalendarioService $calendario,
        private JobRepository $job
    ) {
    }

    /**
     * Totali per plesso, tipologia dietetica e classe/sezione.
     * I dati dei bambini sono esposti esclusivamente per le richieste
     * specifiche di pasta in bianco, indispensabili alla preparazione.
     */
    public function report(string $data, ?int $plessoId = null): array
    {
        $righe = $this->presenze->reportPasti($data, $plessoId);
        $perPlesso = [];
        foreach ($righe as $riga) {
            $pid = (int) $riga['plesso_id'];
            $servizio = $this->calendario->previsto($pid, $data);
            if (!$servizio['attivo']) {
                continue;
            }
            if (!isset($perPlesso[$pid])) {
                $plesso = $this->plessi->trova($pid);
                $esecuzione = $this->job->esecuzione($pid, $data);
                $perPlesso[$pid] = [
                    'plesso_id'            => $pid,
                    'plesso_nome'          => $riga['plesso_nome'],
                    'cutoff_orario'        => substr((string) $plesso['cutoff_orario'], 0, 5),
                    'definitivo'           => $esecuzione !== null && $esecuzione['esito'] === 'ok',
                    'totale_pasti'         => 0,
                    'tipologie'            => [],
                    'sezioni'              => [],
                    'pasta_in_bianco'      => [],
                    'totale_pasta_in_bianco' => 0,
                ];
            }

            $pasti = (int) $riga['pasti'];
            $perPlesso[$pid]['totale_pasti'] += $pasti;
            $tid = (int) $riga['tipologia_id'];
            if (!isset($perPlesso[$pid]['tipologie'][$tid])) {
                $perPlesso[$pid]['tipologie'][$tid] = [
                    'tipologia_id' => $tid, 'tipologia_nome' => $riga['tipologia_nome'], 'pasti' => 0,
                ];
            }
            $perPlesso[$pid]['tipologie'][$tid]['pasti'] += $pasti;

            $cid = (int) $riga['classe_id'];
            if (!isset($perPlesso[$pid]['sezioni'][$cid])) {
                $perPlesso[$pid]['sezioni'][$cid] = [
                    'classe_id' => $cid, 'classe_nome' => $riga['classe_nome'], 'pasti' => 0,
                ];
            }
            $perPlesso[$pid]['sezioni'][$cid]['pasti'] += $pasti;
        }

        foreach ($this->presenze->elencoPastaBianco($data, $plessoId) as $richiesta) {
            $pid = (int) $richiesta['plesso_id'];
            if (!isset($perPlesso[$pid])) {
                continue;
            }
            $perPlesso[$pid]['pasta_in_bianco'][] = [
                'bambino_id' => (int) $richiesta['bambino_id'],
                'nome' => $richiesta['nome'],
                'cognome' => $richiesta['cognome'],
                'classe_nome' => $richiesta['classe_nome'],
            ];
            $perPlesso[$pid]['totale_pasta_in_bianco']++;
        }
        foreach ($perPlesso as &$plesso) {
            $plesso['tipologie'] = array_values($plesso['tipologie']);
            $plesso['sezioni'] = array_values($plesso['sezioni']);
        }
        unset($plesso);

        return [
            'data' => $data,
            'plessi' => array_values($perPlesso),
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
