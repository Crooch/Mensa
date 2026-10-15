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
use App\Repository\MenuSettimanaleRepository;

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
        private JobRepository $job,
        private MenuSettimanaleRepository $settimanali
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
                    'pasti_ordinari'       => 0,
                    'tipologie'            => [],
                    'sezioni'              => [],
                    'pasta_in_bianco'      => [],
                    'totale_pasta_in_bianco' => 0,
                ];
            }

            $pasti = (int) $riga['pasti'];
            $perPlesso[$pid]['totale_pasti'] += $pasti;
            // Sezione completamente vuota: la riga del DB non ha una tipologia.
            // Va mostrata come sezione a 0 pasti, senza inventare un menu "0".
            if ($riga['tipologia_id'] !== null) {
                $tid = (int) $riga['tipologia_id'];
                if (!isset($perPlesso[$pid]['tipologie'][$tid])) {
                    $perPlesso[$pid]['tipologie'][$tid] = [
                        'tipologia_id' => $tid, 'tipologia_nome' => $riga['tipologia_nome'],
                        'pasti' => 0, 'pasti_ordinari' => 0, 'pasta_in_bianco' => 0,
                    ];
                }
                $perPlesso[$pid]['tipologie'][$tid]['pasti'] += $pasti;
                $perPlesso[$pid]['tipologie'][$tid]['pasti_ordinari'] += $pasti;
            }

            $cid = (int) $riga['classe_id'];
            if (!isset($perPlesso[$pid]['sezioni'][$cid])) {
                $perPlesso[$pid]['sezioni'][$cid] = [
                    'classe_id' => $cid, 'classe_nome' => $riga['classe_nome'],
                    'pasti' => 0, 'pasti_ordinari' => 0, 'pasta_in_bianco' => 0,
                    'tipologie' => [],
                ];
            }
            $perPlesso[$pid]['sezioni'][$cid]['pasti'] += $pasti;
            $perPlesso[$pid]['sezioni'][$cid]['pasti_ordinari'] += $pasti;
            if ($riga['tipologia_id'] !== null) {
                $tid = (int) $riga['tipologia_id'];
                if (!isset($perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid])) {
                    $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid] = [
                        'tipologia_id' => $tid, 'tipologia_nome' => $riga['tipologia_nome'],
                        'pasti' => 0, 'pasti_ordinari' => 0, 'pasta_in_bianco' => 0,
                        'richieste_pasta' => [],
                    ];
                }
                $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['pasti'] += $pasti;
                $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['pasti_ordinari'] += $pasti;
            }
        }

        foreach ($this->presenze->elencoPastaBianco($data, $plessoId) as $richiesta) {
            $pid = (int) $richiesta['plesso_id'];
            $cid = (int) $richiesta['classe_id'];
            $tid = (int) $richiesta['tipologia_id'];
            // La pasta in bianco e' una PARTE dei pasti previsti per
            // quella classe e tipologia, non un pasto aggiuntivo.
            if (!isset($perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]) ||
                $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['pasti_ordinari'] <= 0) {
                continue;
            }
            $nominativo = [
                'bambino_id' => (int) $richiesta['bambino_id'],
                'nome' => $richiesta['nome'],
                'cognome' => $richiesta['cognome'],
                'classe_nome' => $richiesta['classe_nome'],
                'classe_id' => $cid,
                'tipologia_id' => $tid,
            ];
            $perPlesso[$pid]['pasta_in_bianco'][] = $nominativo;
            $perPlesso[$pid]['totale_pasta_in_bianco']++;
            $perPlesso[$pid]['sezioni'][$cid]['pasta_in_bianco']++;
            $perPlesso[$pid]['sezioni'][$cid]['pasti_ordinari']--;
            $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['pasta_in_bianco']++;
            $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['pasti_ordinari']--;
            $perPlesso[$pid]['sezioni'][$cid]['tipologie'][$tid]['richieste_pasta'][] = $nominativo;
            $perPlesso[$pid]['tipologie'][$tid]['pasta_in_bianco']++;
            $perPlesso[$pid]['tipologie'][$tid]['pasti_ordinari']--;
        }
        // Il report di distribuzione deve usare esattamente il menu efficace
        // per data/plesso/tipologia: modello settimanale o override giornaliero.
        // Non modifichiamo mai la tipologia dietetica del bambino, ne' i conteggi.
        $menuEfficaci = [];
        foreach ($this->menuDelGiorno($data, $plessoId) as $menuGiorno) {
            $chiave = (int) $menuGiorno['plesso_id'] . ':' . (int) $menuGiorno['tipologia_menu_id'];
            $menuEfficaci[$chiave] = [
                'menu_nome' => $menuGiorno['origine'] === 'settimanale'
                    ? (string) $menuGiorno['modello_nome'] : 'Menu del giorno',
                'menu_descrizione' => (string) ($menuGiorno['descrizione'] ?? ''),
                'menu_origine' => $menuGiorno['origine'],
            ];
        }
        foreach ($perPlesso as &$plesso) {
            $pid = (int) $plesso['plesso_id'];
            foreach ($plesso['tipologie'] as $tid => &$tipo) {
                $menu = $menuEfficaci[$pid . ':' . $tid] ?? null;
                $tipo['menu_nome'] = $menu['menu_nome'] ?? null;
                $tipo['menu_descrizione'] = $menu['menu_descrizione'] ?? null;
                $tipo['menu_origine'] = $menu['menu_origine'] ?? null;
            }
            unset($tipo);
            foreach ($plesso['sezioni'] as &$sezione) {
                foreach ($sezione['tipologie'] as $tid => &$tipo) {
                    $menu = $menuEfficaci[$pid . ':' . $tid] ?? null;
                    $tipo['menu_nome'] = $menu['menu_nome'] ?? null;
                    $tipo['menu_descrizione'] = $menu['menu_descrizione'] ?? null;
                    $tipo['menu_origine'] = $menu['menu_origine'] ?? null;
                }
                unset($tipo);
            }
            unset($sezione);
            $plesso['pasti_ordinari'] = $plesso['totale_pasti'] - $plesso['totale_pasta_in_bianco'];
            $plesso['tipologie'] = array_values($plesso['tipologie']);
            foreach ($plesso['sezioni'] as &$sezione) {
                $sezione['tipologie'] = array_values($sezione['tipologie']);
            }
            unset($sezione);
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
        $giorno = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$giorno || $giorno->format('Y-m-d') !== $data) {
            throw HttpException::nonValido('Data del menu non valida');
        }
        // Calendario e modelli valgono per una singola combinazione
        // plesso+tipologia+data. Il giornaliero prevale come override completo.
        $modelli = [];
        foreach ($this->settimanali->giorniDiSettimana($data, $plessoId) as $modello) {
            if (!$this->calendario->previsto((int) $modello['plesso_id'], $data)['attivo']) continue;
            $chiave = $modello['plesso_id'] . ':' . $modello['tipologia_menu_id'];
            $modelli[$chiave] = $modello;
        }

        $menuVisibili = [];
        $chiavi = [];
        foreach ($this->menu->elenco($data, $plessoId) as $giornaliero) {
            // Non proporre per i giorni correnti/futuri menu se la mensa e'
            // chiusa. Lascia invece consultabili le pubblicazioni storiche.
            if ($data >= date('Y-m-d') &&
                !$this->calendario->previsto((int) $giornaliero['plesso_id'], $data)['attivo']) {
                continue;
            }
            $chiave = $giornaliero['plesso_id'] . ':' . $giornaliero['tipologia_menu_id'];
            $giornaliero['origine'] = 'giornaliero';
            $giornaliero['ha_modello_settimanale'] = isset($modelli[$chiave]);
            $chiavi[$chiave] = true;
            $menuVisibili[] = $giornaliero;
        }
        foreach ($modelli as $chiave => $modello) {
            if (!isset($chiavi[$chiave])) $menuVisibili[] = $modello;
        }
        return $menuVisibili;
    }

    private function dalGiornoSuccessivo(): string
    {
        return (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
    }

    private function validaGiorni(array $giorni): array
    {
        $puliti = [];
        for ($g = 1; $g <= 7; $g++) {
            $valore = $giorni[(string) $g] ?? $giorni[$g] ?? '';
            if (!is_string($valore) || strlen($valore) > 3000) {
                throw HttpException::nonValido('Descrizione non valida per il giorno ' . $g);
            }
            $puliti[(string) $g] = trim($valore);
        }
        return $puliti;
    }

    public function elencoMenuSettimanali(): array
    {
        return $this->settimanali->elenco($this->dalGiornoSuccessivo());
    }

    /** Le foto sono file immutabili: una nuova revisione non sovrascrive quelle passate. */
    private function salvaFotoSettimanale(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw HttpException::nonValido('Caricamento foto non riuscito');
        }
        if ((int) ($file['size'] ?? 0) > (int) Config::get('storage.max_upload_bytes', 4194304)) {
            throw HttpException::nonValido('La foto supera la dimensione massima consentita');
        }
        $tipi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $percorsoTemporaneo = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($percorsoTemporaneo)) {
            throw HttpException::nonValido('Foto non valida');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($percorsoTemporaneo);
        if (!isset($tipi[$mime]) || @getimagesize($percorsoTemporaneo) === false) {
            throw HttpException::nonValido('Usa una foto JPG, PNG o WebP valida');
        }
        $cartella = (string) Config::get('storage.menu_dir') . DIRECTORY_SEPARATOR . 'settimane';
        if (!is_dir($cartella) && !mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new \RuntimeException('Impossibile predisporre la cartella delle foto');
        }
        $nome = bin2hex(random_bytes(16)) . '.' . $tipi[$mime];
        if (!move_uploaded_file($percorsoTemporaneo, $cartella . DIRECTORY_SEPARATOR . $nome)) {
            throw new \RuntimeException('Salvataggio foto non riuscito');
        }
        return 'menu/settimane/' . $nome;
    }

    /** @param array<string,array> $nuoveFoto $_FILES raggruppati per giorno. */
    public function salvaMenuSettimanale(
        ?int $id, string $nome, int $tipo, array $giorni, int $autore,
        array $nuoveFoto = [], array $fotoDaRimuovere = [], array $fotoDaCopiare = [],
        ?int $duplicaDaId = null
    ): int {
        $nome = trim($nome);
        if ($nome === '' || strlen($nome) > 150) {
            throw HttpException::nonValido('Indica un nome per il menu settimanale (massimo 150 caratteri)');
        }
        if ($this->tipologie->trova($tipo) === null) {
            throw HttpException::nonValido('Tipologia menu inesistente');
        }
        $contenuti = $this->validaGiorni($giorni);
        $dal = $this->dalGiornoSuccessivo();
        $esistente = $id !== null ? $this->settimanali->modello($id, $dal) : null;
        if ($id !== null && !$esistente) {
            throw HttpException::nonTrovato('Menu settimanale inesistente');
        }
        if ($esistente && (int) $esistente['tipologia_menu_id'] !== $tipo) {
            throw HttpException::nonValido('La tipologia di un menu già assegnato non può cambiare: duplicalo');
        }
        // Nella duplicazione da interfaccia si ereditano i riferimenti alle foto
        // immutabili del modello sorgente. Il nuovo modello mantiene poi revisioni autonome.
        if ($duplicaDaId !== null) {
            if ($id !== null || $duplicaDaId <= 0) {
                throw HttpException::nonValido('Origine della duplicazione non valida');
            }
            $origine = $this->settimanali->modello($duplicaDaId, $dal);
            if ($origine === null) {
                throw HttpException::nonTrovato('Menu settimanale da duplicare inesistente');
            }
            $fotoDaCopiare = $origine['foto_giorni'] ?? [];
        }
        $fotoGiorni = $esistente['foto_giorni'] ?? $fotoDaCopiare;
        $daCaricare = [];
        for ($i = 1; $i <= 7; $i++) {
            $giorno = (string) $i;
            $foto = $nuoveFoto[$giorno] ?? $nuoveFoto[$i] ?? null;
            $rimozione = in_array($giorno, array_map('strval', $fotoDaRimuovere), true);
            if ($foto !== null && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($rimozione) {
                    throw HttpException::nonValido('Non puoi sostituire e rimuovere la stessa foto');
                }
                if (($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw HttpException::nonValido('Caricamento foto non riuscito per il giorno ' . $i);
                }
                $daCaricare[$giorno] = $foto;
            } elseif ($rimozione) {
                unset($fotoGiorni[$giorno]);
            }
        }
        if (implode('', $contenuti) === '' && $fotoGiorni === [] && $daCaricare === []) {
            throw HttpException::nonValido('Indica almeno un giorno con una descrizione o una foto');
        }
        foreach ($daCaricare as $giorno => $foto) {
            // Ogni caricamento crea un nuovo file e conserva quello referenziato
            // dalla revisione precedente: niente sovrascritture storiche.
            $fotoGiorni[(string) $giorno] = $this->salvaFotoSettimanale($foto);
        }
        if ($id === null) {
            return $this->settimanali->crea($nome, $tipo, $contenuti, $dal, $autore, $fotoGiorni);
        }
        $this->settimanali->modifica($id, $nome, $contenuti, $dal, $autore, $fotoGiorni);
        return $id;
    }

    public function duplicaMenuSettimanale(int $id, string $nome, int $tipo, int $autore): int
    {
        $modello = $this->settimanali->modello($id, $this->dalGiornoSuccessivo());
        if (!$modello) throw HttpException::nonTrovato('Menu settimanale da duplicare inesistente');
        return $this->salvaMenuSettimanale(
            null, $nome, $tipo, $modello['giorni'], $autore, [], [], $modello['foto_giorni']
        );
    }

    public function assegnazioniMenuSettimanali(): array
    {
        return $this->settimanali->assegnazioni($this->dalGiornoSuccessivo());
    }

    public function assegnaMenuSettimanale(?int $id, int $tipologiaId, array $plessi, int $autore): array
    {
        if ($this->tipologie->trova($tipologiaId) === null) {
            throw HttpException::nonValido('Tipologia menu inesistente');
        }
        if ($id !== null) {
            $modello = $this->settimanali->modello($id, $this->dalGiornoSuccessivo());
            if (!$modello || (int) $modello['tipologia_menu_id'] !== $tipologiaId) {
                throw HttpException::nonValido('Il modello non appartiene alla tipologia selezionata');
            }
        }
        $ids = array_values(array_unique(array_map('intval', $plessi)));
        if ($ids === []) throw HttpException::nonValido('Seleziona almeno un plesso');
        foreach ($ids as $plesso) {
            if ($plesso <= 0 || $this->plessi->trova($plesso) === null) {
                throw HttpException::nonValido('Plesso non valido');
            }
        }
        $dal = $this->dalGiornoSuccessivo();
        $this->settimanali->assegna($ids, $tipologiaId, $id, $dal, $autore);
        return ['valido_dal' => $dal, 'plessi' => $ids, 'menu_id' => $id];
    }

    /** Data e cutoff verificati lato server per ogni modifica a un menu giornaliero. */
    private function verificaMenuModificabile(int $plessoId, int $tipologiaId, string $data): void
    {
        $giorno = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);
        if (!$giorno || $giorno->format('Y-m-d') !== $data) {
            throw HttpException::nonValido('La data del menu non è valida');
        }
        $plesso = $this->plessi->trova($plessoId);
        if ($plesso === null || $this->tipologie->trova($tipologiaId) === null) {
            throw HttpException::nonTrovato('Plesso o tipologia non validi');
        }
        if ($this->presenzaService->oltreCutoff($plesso, $data)) {
            if ($data < date('Y-m-d')) {
                throw HttpException::nonValido('Non puoi modificare un menù di un giorno passato.');
            }
            throw HttpException::nonValido('È passato l’orario limite per modificare il menù di oggi nel plesso ' .
                $plesso['nome'] . '. Puoi programmare i giorni successivi.');
        }
        if (!$this->calendario->previsto($plessoId, $data)['attivo']) {
            throw HttpException::nonValido('Il servizio mensa non è previsto per questa data');
        }
        $esecuzione = $this->job->esecuzione($plessoId, $data);
        if ($esecuzione !== null && in_array($esecuzione['esito'], ['ok', 'in_corso'], true)) {
            throw HttpException::nonValido('Giornata già consolidata o in consolidamento');
        }
    }

    /** Riepilogo paginato delle sole variazioni, senza i modelli ripetuti. */
    public function variazioniProgrammate(int $paginaRichiesta = 1): array
    {
        $dal = date('Y-m-d');
        $perPagina = 10;
        $totale = $this->menu->contaProgrammati($dal);
        $pagine = max(1, (int) ceil($totale / $perPagina));
        // Se si annulla l'ultima variazione dell'ultima pagina, torna
        // automaticamente all'ultima pagina ancora esistente.
        $pagina = max(1, min($paginaRichiesta, $pagine));
        $righe = $this->menu->elencoProgrammati($dal, $pagina, $perPagina);
        foreach ($righe as &$riga) {
            $plessoId = (int) $riga['plesso_id'];
            $data = (string) $riga['data'];
            $plesso = $this->plessi->trova($plessoId);
            $riga['ha_modello_settimanale'] = false;
            foreach ($this->settimanali->giorniDiSettimana($data, $plessoId) as $modello) {
                if ((int) $modello['tipologia_menu_id'] === (int) $riga['tipologia_menu_id']) {
                    $riga['ha_modello_settimanale'] = true;
                    break;
                }
            }
            $riga['modificabile'] = $plesso !== null &&
                !$this->presenzaService->oltreCutoff($plesso, (string) $riga['data']) &&
                $this->calendario->previsto((int) $riga['plesso_id'], (string) $riga['data'])['attivo'] &&
                !in_array($this->job->esecuzione((int) $riga['plesso_id'], (string) $riga['data'])['esito'] ?? '',
                    ['ok', 'in_corso'], true);
        }
        unset($riga);
        return [
            'righe' => $righe,
            'pagina' => $pagina,
            'per_pagina' => $perPagina,
            'totale' => $totale,
            'pagine' => $pagine,
        ];
    }

    /** Cancella solo la variazione scelta; se esiste, riemerge il modello. */
    public function annullaVariazioneGiornaliera(int $plessoId, int $tipologiaId, string $data): void
    {
        $this->verificaMenuModificabile($plessoId, $tipologiaId, $data);
        $precedente = $this->menu->trovaPerChiave($plessoId, $tipologiaId, $data);
        if ($precedente === null) {
            throw HttpException::nonValido('Nessuna variazione giornaliera da annullare');
        }
        if (!$this->menu->rimuoviPerChiave($plessoId, $tipologiaId, $data)) {
            throw new \RuntimeException('Impossibile annullare la variazione giornaliera');
        }
        $this->eliminaFotoGiornalieraSePropria(
            $plessoId, $tipologiaId, $data, $precedente['foto_path'] ?? null
        );
    }

    /**
     * Rimuove un'eccezione non consolidata e torna al modello settimanale
     * gia' assegnato per quel giorno. Non tocca le revisioni storiche.
     */
    public function ripristinaMenuSettimanale(int $plessoId, int $tipologiaId, string $data): void
    {
        $this->verificaMenuModificabile($plessoId, $tipologiaId, $data);
        $presente = false;
        foreach ($this->settimanali->giorniDiSettimana($data, $plessoId) as $modello) {
            if ((int) $modello['tipologia_menu_id'] === $tipologiaId) {
                $presente = true;
                break;
            }
        }
        if (!$presente) {
            throw HttpException::nonValido('Nessun menu settimanale disponibile per questa data');
        }
        $this->annullaVariazioneGiornaliera($plessoId, $tipologiaId, $data);
    }

    /** Il file fisico viene eliminato solo se appartiene a questa data. */
    private function eliminaFotoGiornalieraSePropria(
        int $plessoId, int $tipologiaId, string $data, ?string $fotoPrecedente
    ): void {
        if (!$fotoPrecedente) return;
        $prefisso = 'menu/' . $plessoId . '_' . $tipologiaId . '/' . $data . '.';
        if (!str_starts_with($fotoPrecedente, $prefisso)) return;
        $estensione = substr($fotoPrecedente, strlen($prefisso));
        if (!in_array($estensione, ['jpg', 'png', 'webp'], true)) return;
        $percorso = dirname((string) Config::get('storage.menu_dir')) .
            DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fotoPrecedente);
        if (is_file($percorso)) @unlink($percorso);
    }

    /**
     * Pubblica/aggiorna il menu per una combinazione plesso+tipologia+data.
     * La foto e' salvata su filesystem in /storage/menu/{plesso}_{tipologia}/{data}.jpg
     *
     * @param array|null $file elemento di $_FILES
     */
    public function pubblicaMenu(int $plessoId, int $tipologiaId, string $data, ?string $descrizione, ?array $file, int $utenteId, bool $rimuoviFoto = false): int
    {
        $this->verificaMenuModificabile($plessoId, $tipologiaId, $data);
        $fotoNuova = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $esistente = $this->menu->trovaPerChiave($plessoId, $tipologiaId, $data);
        if ($rimuoviFoto && $fotoNuova) {
            throw HttpException::nonValido('Non puoi rimuovere e sostituire la foto insieme');
        }
        if ($rimuoviFoto && empty($esistente['foto_path'])) {
            throw HttpException::nonValido('Non esiste una foto giornaliera da rimuovere');
        }
        // Il menu giornaliero e' un override completo: la foto del modello
        // settimanale non va mai ereditata automaticamente.
        // In una modifica successiva conserviamo invece la foto del giornaliero.
        $fotoDisponibile = !$rimuoviFoto && !empty($esistente['foto_path']);
        if (($descrizione === null || trim($descrizione) === '') && !$fotoNuova && !$fotoDisponibile) {
            throw HttpException::nonValido('Indicare almeno una descrizione o una foto');
        }

        $fotoPath = null;
        if ($fotoNuova) {
            $fotoPath = $this->salvaFoto($plessoId, $tipologiaId, $data, $file);
        }
        $id = $this->menu->salva($plessoId, $tipologiaId, $data, $descrizione, $fotoPath, $utenteId, $rimuoviFoto);
        if ($rimuoviFoto) {
            $this->eliminaFotoGiornalieraSePropria(
                $plessoId, $tipologiaId, $data, $esistente['foto_path'] ?? null
            );
        }
        return $id;
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
