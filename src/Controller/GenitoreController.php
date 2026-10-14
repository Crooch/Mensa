<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\HttpException;
use App\Core\Request;

/** API area Genitore: sola lettura + dichiarazioni di eccezione e feedback. */
final class GenitoreController
{
    public function __construct(private Container $c)
    {
    }

    private function genitoreId(array $utente): int
    {
        $genitore = $this->c->genitori()->trovaPerUtente((int) $utente['id']);
        if ($genitore === null) {
            throw HttpException::nonAutorizzato('Utenza non collegata ad alcuna anagrafica genitore');
        }
        return (int) $genitore['id'];
    }

    /** Dashboard: saldi per plesso, figli con stato di oggi, ultimi movimenti e feedback. */
    public function dashboard(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $oggi = date('Y-m-d');
        $figli = [];
        foreach ($this->c->anagrafica()->figli($genitoreId, $oggi) as $figlio) {
            $stato = $figlio['plesso_id'] !== null
                ? $this->c->presenzaService()->statoGiornata((int) $figlio['id'], $oggi)
                : null;
            $figli[] = [
                'id'              => (int) $figlio['id'],
                'nome'            => $figlio['nome'],
                'cognome'         => $figlio['cognome'],
                'tipologia_nome'  => $figlio['tipologia_nome'],
                'plesso_id'       => $figlio['plesso_id'] !== null ? (int) $figlio['plesso_id'] : null,
                'plesso_nome'     => $figlio['plesso_nome'],
                'classe_nome'     => $figlio['classe_nome'],
                'anno_scolastico' => $figlio['anno_scolastico'],
                'attivo'          => (int) $figlio['cancellato_logicamente'] === 0,
                'stato_oggi'      => $stato,
            ];
        }

        return [
            'data'      => $oggi,
            'crediti'   => $this->c->creditoService()->situazioneGenitore($genitoreId),
            'figli'     => $figli,
            'movimenti' => $this->c->creditoService()->movimenti($genitoreId),
            'feedback'  => $this->c->feedback()->storicoGenitore($genitoreId),
        ];
    }

    public function calendario(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $bambinoId = (int) $r->parametro('id');
        $this->c->anagrafica()->bambinoDelGenitore($bambinoId, $genitoreId);
        $anno = (int) ($r->query('anno') ?? date('Y'));
        $mese = (int) ($r->query('mese') ?? date('n'));
        return $this->c->presenzaService()->calendarioBambino($bambinoId, $anno, $mese);
    }

    public function statoGiornata(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $bambinoId = (int) $r->parametro('id');
        $this->c->anagrafica()->bambinoDelGenitore($bambinoId, $genitoreId);
        return $this->c->presenzaService()->statoGiornata($bambinoId, (string) ($r->query('data') ?? date('Y-m-d')));
    }

    /** Dichiarazione di eccezione presenza/assenza (entro il cutoff del plesso). */
    public function dichiara(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $bambinoId = (int) $r->obbligatorio('bambino_id');
        $this->c->anagrafica()->bambinoDelGenitore($bambinoId, $genitoreId);

        $esito = $this->c->presenzaService()->dichiaraGenitore(
            $bambinoId,
            (string) $r->obbligatorio('data'),
            (string) $r->obbligatorio('stato'),
            (int) $utente['id']
        );

        return [
            'stato_effettivo' => $esito['stato_effettivo'],
            'fuori_cutoff'    => (int) $esito['fuori_cutoff'] === 1,
            'effetto'         => $esito['effetto'],
            'pasta_annullata' => (bool) ($esito['pasta_annullata'] ?? false),
        ];
    }

    /** La richiesta del piatto non cambia lo stato presenza/assenza. */
    public function pastaBianco(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $bambinoId = (int) $r->obbligatorio('bambino_id');
        $this->c->anagrafica()->bambinoDelGenitore($bambinoId, $genitoreId);
        $richiesta = $r->obbligatorio('richiesta');
        if (!in_array($richiesta, [0, 1, '0', '1', false, true], true)) {
            throw HttpException::nonValido('Valore richiesta non valido');
        }
        return $this->c->presenzaService()->pastaBiancoGenitore(
            $bambinoId, (string) $r->obbligatorio('data'), (bool) $richiesta, (int) $utente['id']
        );
    }

    public function inserisciFeedback(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $voto = $r->input('voto');
        $id = $this->c->feedback()->inserisci(
            $genitoreId,
            (int) $r->obbligatorio('bambino_id'),
            (string) $r->obbligatorio('data'),
            $voto !== null ? (int) $voto : null,
            $r->input('commento')
        );
        return ['id' => $id];
    }

    public function feedback(Request $r, array $utente): array
    {
        return $this->c->feedback()->storicoGenitore($this->genitoreId($utente));
    }

    /** Menu pubblicato dal catering per il plesso/tipologia dei propri figli. */
    public function menu(Request $r, array $utente): array
    {
        $genitoreId = $this->genitoreId($utente);
        $data = (string) ($r->query('data') ?? date('Y-m-d'));
        $plessi = [];
        foreach ($this->c->anagrafica()->figli($genitoreId, $data) as $figlio) {
            if ($figlio['plesso_id'] !== null) {
                $plessi[(int) $figlio['plesso_id']] = true;
            }
        }
        $risultato = [];
        foreach (array_keys($plessi) as $plessoId) {
            $risultato = array_merge($risultato, $this->c->catering()->menuDelGiorno($data, $plessoId));
        }
        return $risultato;
    }
}
