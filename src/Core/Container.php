<?php

declare(strict_types=1);

namespace App\Core;

use App\Repository as R;
use App\Service as S;

/**
 * Container minimale: istanzia repository e servizi una sola volta per richiesta.
 * Nessuna libreria esterna, cosi' il progetto gira su hosting condiviso senza Composer.
 */
final class Container
{
    private array $istanze = [];

    private function ottieni(string $chiave, callable $fabbrica)
    {
        return $this->istanze[$chiave] ??= $fabbrica();
    }

    // ---------------- repository ----------------

    public function utenti(): R\UtenteRepository
    {
        return $this->ottieni('r.utenti', fn () => new R\UtenteRepository());
    }

    public function genitori(): R\GenitoreRepository
    {
        return $this->ottieni('r.genitori', fn () => new R\GenitoreRepository());
    }

    public function bambini(): R\BambinoRepository
    {
        return $this->ottieni('r.bambini', fn () => new R\BambinoRepository());
    }

    public function plessi(): R\PlessoRepository
    {
        return $this->ottieni('r.plessi', fn () => new R\PlessoRepository());
    }

    public function classi(): R\ClasseSezioneRepository
    {
        return $this->ottieni('r.classi', fn () => new R\ClasseSezioneRepository());
    }

    public function assegnazioni(): R\AssegnazioneClasseRepository
    {
        return $this->ottieni('r.assegnazioni', fn () => new R\AssegnazioneClasseRepository());
    }

    public function tipologie(): R\TipologiaMenuRepository
    {
        return $this->ottieni('r.tipologie', fn () => new R\TipologiaMenuRepository());
    }

    public function overrideTariffe(): R\OverrideTariffaRepository
    {
        return $this->ottieni('r.override', fn () => new R\OverrideTariffaRepository());
    }

    public function crediti(): R\CreditoRepository
    {
        return $this->ottieni('r.crediti', fn () => new R\CreditoRepository());
    }

    public function presenze(): R\PresenzaRepository
    {
        return $this->ottieni('r.presenze', fn () => new R\PresenzaRepository());
    }

    public function menu(): R\MenuGiornalieroRepository
    {
        return $this->ottieni('r.menu', fn () => new R\MenuGiornalieroRepository());
    }

    public function feedbackRepo(): R\FeedbackRepository
    {
        return $this->ottieni('r.feedback', fn () => new R\FeedbackRepository());
    }

    public function comunicazioni(): R\ComunicazioneRepository
    {
        return $this->ottieni('r.comunicazioni', fn () => new R\ComunicazioneRepository());
    }

    public function calendarioRepo(): R\CalendarioRepository
    {
        return $this->ottieni('r.calendario', fn () => new R\CalendarioRepository());
    }

    public function calendario(): S\CalendarioService
    {
        return $this->ottieni('s.calendario', fn () => new S\CalendarioService($this->calendarioRepo(), $this->plessi()));
    }

    public function jobRepo(): R\JobRepository
    {
        return $this->ottieni('r.job', fn () => new R\JobRepository());
    }

    // ---------------- servizi ----------------

    public function auth(): S\AuthService
    {
        return $this->ottieni('s.auth', fn () => new S\AuthService(
            $this->utenti(),
            $this->genitori(),
            (int) Config::get('auth.token_durata_ore', 12)
        ));
    }

    public function tariffe(): S\TariffaService
    {
        return $this->ottieni('s.tariffe', fn () => new S\TariffaService($this->plessi(), $this->overrideTariffe()));
    }

    public function creditoService(): S\CreditoService
    {
        return $this->ottieni('s.crediti', fn () => new S\CreditoService($this->crediti(), $this->plessi(), $this->tariffe()));
    }

    public function presenzaService(): S\PresenzaService
    {
        return $this->ottieni('s.presenze', fn () => new S\PresenzaService(
            $this->presenze(),
            $this->bambini(),
            $this->classi(),
            $this->plessi(),
            $this->assegnazioni(),
            $this->calendario()
        ));
    }

    public function anagrafica(): S\AnagraficaService
    {
        return $this->ottieni('s.anagrafica', fn () => new S\AnagraficaService(
            $this->genitori(),
            $this->bambini(),
            $this->assegnazioni(),
            $this->classi(),
            $this->auth(),
            $this->creditoService(),
            $this->tariffe()
        ));
    }

    public function catering(): S\CateringService
    {
        return $this->ottieni('s.catering', fn () => new S\CateringService(
            $this->presenze(),
            $this->plessi(),
            $this->tipologie(),
            $this->menu(),
            $this->presenzaService()
        ));
    }

    public function feedback(): S\FeedbackService
    {
        return $this->ottieni('s.feedback', fn () => new S\FeedbackService(
            $this->feedbackRepo(),
            $this->anagrafica(),
            $this->assegnazioni()
        ));
    }

    public function solleciti(): S\SollecitoService
    {
        return $this->ottieni('s.solleciti', fn () => new S\SollecitoService(
            $this->crediti(),
            $this->comunicazioni(),
            $this->plessi()
        ));
    }

    public function job(): S\JobGiornalieroService
    {
        return $this->ottieni('s.job', fn () => new S\JobGiornalieroService(
            $this->plessi(),
            $this->bambini(),
            $this->presenze(),
            $this->presenzaService(),
            $this->creditoService(),
            $this->solleciti(),
            $this->jobRepo(),
            $this->calendario()
        ));
    }

    public function configurazione(): S\ConfigurazioneService
    {
        return $this->ottieni('s.config', fn () => new S\ConfigurazioneService(
            $this->plessi(),
            $this->classi(),
            $this->tipologie(),
            $this->comunicazioni()
        ));
    }

    public function autenticazione(): Auth
    {
        return $this->ottieni('core.auth', fn () => new Auth($this->utenti()));
    }
}
