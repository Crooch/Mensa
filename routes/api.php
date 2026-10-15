<?php

declare(strict_types=1);

/**
 * Definizione delle rotte API. Ogni rotta dichiara i ruoli ammessi:
 * l'autorizzazione e' applicata dal front controller, non dai controller.
 *
 * @var App\Core\Container $container
 */

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\CateringController;
use App\Controller\GenitoreController;
use App\Controller\JobController;
use App\Controller\OperatoreController;
use App\Controller\ScuolaController;
use App\Core\Router;

$router = new Router();

$auth = new AuthController($container);
$genitore = new GenitoreController($container);
$operatore = new OperatoreController($container);
$scuola = new ScuolaController($container);
$catering = new CateringController($container);
$admin = new AdminController($container);
$job = new JobController($container);

const RUOLI_UFFICIO = ['admin', 'operatore'];
const RUOLI_TUTTI = ['admin', 'operatore', 'scuola', 'catering', 'genitore'];

// ---------------- autenticazione ----------------
$router->post('/api/auth/login', [$auth, 'login']);                     // pubblica
$router->post('/api/auth/logout', [$auth, 'logout'], RUOLI_TUTTI);
$router->get('/api/auth/profilo', [$auth, 'profilo'], RUOLI_TUTTI);
$router->post('/api/auth/password', [$auth, 'cambiaPassword'], RUOLI_TUTTI);

// ---------------- riferimenti comuni ----------------
$router->get('/api/riferimenti', [$admin, 'riferimenti'], RUOLI_TUTTI);

// ---------------- area genitore ----------------
$router->get('/api/genitore/dashboard', [$genitore, 'dashboard'], ['genitore']);
$router->get('/api/genitore/figli/{id}/calendario', [$genitore, 'calendario'], ['genitore']);
$router->get('/api/genitore/figli/{id}/giornata', [$genitore, 'statoGiornata'], ['genitore']);
$router->post('/api/genitore/presenze/dichiarazione', [$genitore, 'dichiara'], ['genitore']);
$router->post('/api/genitore/pasta-bianco', [$genitore, 'pastaBianco'], ['genitore']);
$router->get('/api/genitore/feedback', [$genitore, 'feedback'], ['genitore']);
$router->post('/api/genitore/feedback', [$genitore, 'inserisciFeedback'], ['genitore']);
$router->get('/api/genitore/menu', [$genitore, 'menu'], ['genitore']);

// ---------------- area operatore comunale (e admin) ----------------
$router->get('/api/famiglie', [$operatore, 'cercaFamiglie'], RUOLI_UFFICIO);
$router->get('/api/famiglie/{id}', [$operatore, 'schedaFamiglia'], RUOLI_UFFICIO);
$router->post('/api/famiglie', [$operatore, 'creaGenitore'], RUOLI_UFFICIO);
$router->put('/api/famiglie/{id}', [$operatore, 'modificaGenitore'], RUOLI_UFFICIO);
$router->delete('/api/famiglie/{id}', [$operatore, 'cancellaGenitore'], RUOLI_UFFICIO);
$router->post('/api/bambini', [$operatore, 'creaBambino'], RUOLI_UFFICIO);
$router->put('/api/bambini/{id}', [$operatore, 'modificaBambino'], RUOLI_UFFICIO);
$router->delete('/api/bambini/{id}', [$operatore, 'cancellaBambino'], RUOLI_UFFICIO);
$router->post('/api/bambini/{id}/assegnazioni', [$operatore, 'assegnaClasse'], RUOLI_UFFICIO);
$router->get('/api/bambini/{id}/assegnazioni', [$operatore, 'storicoClassi'], RUOLI_UFFICIO);
$router->get('/api/tariffe/attiva', [$operatore, 'tariffaAttiva'], RUOLI_UFFICIO);
$router->post('/api/tariffe/override', [$operatore, 'creaOverrideTariffa'], RUOLI_UFFICIO);
$router->post('/api/crediti/acquisti', [$operatore, 'acquistaBuoni'], RUOLI_UFFICIO);
$router->post('/api/crediti/rettifiche', [$operatore, 'rettificaCrediti'], RUOLI_UFFICIO);
$router->get('/api/crediti/situazione', [$operatore, 'situazioneCrediti'], RUOLI_UFFICIO);
$router->get('/api/solleciti', [$operatore, 'logSolleciti'], RUOLI_UFFICIO);

// ---------------- area scuola ----------------
$router->get('/api/scuola/classi', [$scuola, 'classi'], ['scuola', 'admin', 'operatore']);
$router->get('/api/scuola/classi/{id}/giornata', [$scuola, 'vistaGiornaliera'], ['scuola', 'admin', 'operatore']);
$router->post('/api/scuola/assenze-massa', [$scuola, 'assenzaMassa'], ['scuola', 'admin', 'operatore']);
$router->get('/api/scuola/assenze-massa', [$scuola, 'elencoAssenzeMassa'], ['scuola', 'admin', 'operatore']);
$router->post('/api/scuola/presenze/override', [$scuola, 'override'], ['scuola', 'admin', 'operatore']);

// ---------------- area catering ----------------
$router->get('/api/catering/report', [$catering, 'report'], ['catering', 'admin', 'operatore']);
$router->get('/api/catering/menu', [$catering, 'menu'], ['catering', 'admin', 'operatore', 'scuola']);
$router->get('/api/catering/menu/variazioni', [$catering, 'variazioniProgrammate'], ['catering', 'admin']);
$router->post('/api/catering/menu/annulla', [$catering, 'annullaMenuGiornaliero'], ['catering', 'admin']);
$router->post('/api/catering/menu', [$catering, 'pubblicaMenu'], ['catering', 'admin']);
$router->post('/api/catering/menu/ripristina', [$catering, 'ripristinaMenuSettimanale'], ['catering', 'admin']);
$router->get('/api/catering/feedback', [$catering, 'feedback'], ['catering', 'admin', 'operatore']);
// Modelli ricorrenti: chi può leggere i menu può vedere il catalogo;
// scrittura riservata a Catering e Admin.
$router->get('/api/catering/menu-settimanali', [$catering, 'menuSettimanali'], ['catering', 'admin']);
$router->post('/api/catering/menu-settimanali', [$catering, 'salvaMenuSettimanale'], ['catering', 'admin']);
$router->put('/api/catering/menu-settimanali/{id}', [$catering, 'modificaMenuSettimanale'], ['catering', 'admin']);
$router->post('/api/catering/menu-settimanali/{id}/aggiorna', [$catering, 'modificaMenuSettimanale'], ['catering', 'admin']);
$router->post('/api/catering/menu-settimanali/{id}/duplica', [$catering, 'duplicaMenuSettimanale'], ['catering', 'admin']);
$router->get('/api/catering/menu-settimanali/assegnazioni', [$catering, 'assegnazioniMenuSettimanali'], ['catering', 'admin']);
$router->post('/api/catering/menu-settimanali/assegnazioni', [$catering, 'assegnaMenuSettimanale'], ['catering', 'admin']);


// ---------------- area admin ----------------
$router->get('/api/admin/calendari/{id}', [$admin, 'dettaglioCalendario'], ['admin']);
$router->put('/api/admin/calendari/{id}', [$admin, 'salvaCalendario'], ['admin']);
$router->get('/api/admin/calendari/{id}/verifica', [$admin, 'verificaCalendario'], ['admin']);
$router->post('/api/admin/calendari/{id}/periodi', [$admin, 'creaPeriodoCalendario'], ['admin']);
$router->put('/api/admin/calendari/{id}/periodi/{periodo_id}', [$admin, 'modificaPeriodoCalendario'], ['admin']);
$router->delete('/api/admin/calendari/{id}/periodi/{periodo_id}', [$admin, 'cancellaPeriodoCalendario'], ['admin']);
$router->put('/api/admin/calendari/{id}/eccezioni/{data}', [$admin, 'salvaEccezioneCalendario'], ['admin']);
$router->delete('/api/admin/calendari/{id}/eccezioni/{data}', [$admin, 'cancellaEccezioneCalendario'], ['admin']);
$router->get('/api/admin/plessi', [$admin, 'plessi'], ['admin']);
$router->post('/api/admin/plessi', [$admin, 'creaPlesso'], ['admin']);
$router->put('/api/admin/plessi/{id}', [$admin, 'modificaPlesso'], ['admin']);
$router->delete('/api/admin/plessi/{id}', [$admin, 'cancellaPlesso'], ['admin']);
$router->get('/api/admin/classi', [$admin, 'classi'], ['admin']);
$router->post('/api/admin/classi', [$admin, 'creaClasse'], ['admin']);
$router->put('/api/admin/classi/{id}', [$admin, 'modificaClasse'], ['admin']);
$router->delete('/api/admin/classi/{id}', [$admin, 'cancellaClasse'], ['admin']);
$router->get('/api/admin/tipologie', [$admin, 'tipologie'], ['admin']);
$router->post('/api/admin/tipologie', [$admin, 'creaTipologia'], ['admin']);
$router->put('/api/admin/tipologie/{id}', [$admin, 'modificaTipologia'], ['admin']);
$router->delete('/api/admin/tipologie/{id}', [$admin, 'cancellaTipologia'], ['admin']);
$router->get('/api/admin/utenti', [$admin, 'utenti'], ['admin']);
$router->post('/api/admin/utenti', [$admin, 'creaUtente'], ['admin']);
$router->put('/api/admin/utenti/{id}', [$admin, 'modificaUtente'], ['admin']);
$router->get('/api/admin/modelli-email', [$admin, 'modelli'], ['admin']);
$router->post('/api/admin/modelli-email', [$admin, 'salvaModello'], ['admin']);
$router->get('/api/admin/destinatari', [$admin, 'destinatari'], ['admin']);
$router->post('/api/admin/destinatari', [$admin, 'creaDestinatario'], ['admin']);
$router->put('/api/admin/destinatari/{id}', [$admin, 'modificaDestinatario'], ['admin']);
$router->get('/api/admin/impostazioni', [$admin, 'impostazioni'], ['admin']);
$router->post('/api/admin/impostazioni', [$admin, 'salvaImpostazioni'], ['admin']);
$router->post('/api/admin/email-prova', [$admin, 'emailDiProva'], ['admin']);

// ---------------- job giornaliero ----------------
$router->post('/api/job/scheduler', [$job, 'scheduler'], ['admin']);
$router->post('/api/job/plessi/{id}', [$job, 'eseguiPlesso'], ['admin']);
$router->get('/api/job/stato', [$job, 'stato'], ['admin']);
$router->get('/api/job/esecuzioni', [$job, 'esecuzioni'], ['admin', 'operatore']);

return $router;
