<?php
$titoloPagina = 'Area ristorazione · ' . $titoloApp;
$ruoloPagina = 'catering';
$scriptPagina = 'catering';
$scriptExtraPagina = 'catering_settimane';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="report" data-testid="tab-report">Pasti da preparare</button>
    <button class="scheda" data-vista="menu" data-testid="tab-menu">Menu del giorno</button>
    <button class="scheda" data-vista="settimane" data-testid="tab-settimane">Menu settimanali</button>
    <button class="scheda" data-vista="feedback" data-testid="tab-feedback">Feedback</button>
</nav>

<section id="vista-report" class="vista">
    <h1>Pasti da preparare</h1>
    <div class="catering-filtri catering-filtri--report">
        <div class="catering-campo">
            <label for="report-data">Data</label>
            <input type="date" id="report-data" data-testid="report-data-input">
        </div>
        <button class="btn btn-primary catering-filtri__azione" type="button" id="btn-report" data-testid="report-carica-button">Aggiorna i pasti</button>
    </div>
    <p class="nota" id="report-stato" role="status" aria-live="polite" data-testid="report-stato">Seleziona una data per consultare i pasti.</p>
    <div id="report-riepilogo" class="riepilogo-mensa" aria-live="polite"></div>
    <p class="nota">Per ogni classe trovi i pasti da consegnare per tipologia. La pasta in bianco è già compresa nel totale: viene separata dai pasti normali, senza contarla due volte. I numeri sono provvisori fino alla conferma.</p>
    <div class="catering-report-filtri">
        <div class="catering-campo">
            <label for="report-plesso">Scuola da visualizzare</label>
            <select id="report-plesso" data-testid="report-plesso-filtro">
                <option value="">Tutte le scuole</option>
            </select>
        </div>
        <div class="catering-campo">
            <label for="report-classe-cerca">Cerca una classe o sezione</label>
            <input type="search" id="report-classe-cerca" placeholder="Es. Sezione A" autocomplete="off"
                   data-testid="report-classe-ricerca">
        </div>
    </div>
    <p class="nota" id="report-filtri-stato" role="status" aria-live="polite"></p>
    <div id="report" class="catering-distribuzione" data-testid="report-plessi"></div>
</section>

<section id="vista-menu" class="vista" hidden>
    <h1>Menu del giorno</h1>
    <p class="nota catering-introduzione">Seleziona il plesso e la tipologia. Puoi pubblicare un menu nuovo oppure aggiornare quello già presente.</p>
    <form id="form-menu" class="pannello catering-form-menu" enctype="multipart/form-data" data-testid="menu-form">
        <div class="catering-filtri catering-filtri--menu">
            <div class="catering-campo">
                <label for="menu-plesso">Plesso</label>
                <select id="menu-plesso" data-testid="menu-plesso-select"></select>
            </div>
            <div class="catering-campo">
                <label for="menu-tipologia">Tipologia</label>
                <select id="menu-tipologia" data-testid="menu-tipologia-select"></select>
            </div>
        </div>
        <p id="menu-selezione-stato" class="stato-operazione catering-menu-stato" role="status" aria-live="polite" data-testid="menu-selezione-stato">Caricamento dei menu pubblicati…</p>
        <div class="catering-campo">
            <label for="menu-descrizione">Descrizione del menu</label>
            <textarea id="menu-descrizione" rows="3" placeholder="Es. Pasta al pomodoro, pollo e verdure" data-testid="menu-descrizione-input"></textarea>
        </div>
        <div class="catering-campo">
            <label>Foto del menu (facoltativa)</label>
            <div id="menu-foto-widget" class="menu-foto" data-testid="menu-foto-widget"></div>
        </div>
        <div class="catering-menu-azioni">
            <button type="submit" id="menu-submit" class="btn btn-primary catering-menu-submit" disabled data-testid="menu-submit-button">Pubblica menu</button>
            <button type="button" id="menu-ripristina" class="btn btn-outline-primary" hidden disabled
                    data-testid="menu-ripristina-button">Ripristina menu settimanale</button>
        </div>
    </form>
    <h2>Menu già pubblicati oggi</h2>
    <p id="menu-stato" class="stato-operazione" role="status" hidden></p>
    <div id="menu-lista" class="griglia-cards" data-testid="menu-lista"></div>
</section>

<section id="vista-settimane" class="vista" hidden>
    <h1>Menu settimanali</h1>
    <p class="nota catering-introduzione">Crea una settimana tipo per una tipologia alimentare, duplicala per creare varianti e assegnala a uno o più plessi. Resta in uso finché non ne scegli un'altra. Le modifiche valgono dal giorno successivo; lo storico dei pasti già somministrati non viene riscritto.</p>
    <div class="catering-settimane-layout">
        <form id="form-settimana" class="pannello catering-form-menu">
            <h2 id="settimana-form-titolo">Nuovo menu settimanale</h2>
            <div class="catering-filtri catering-filtri--menu">
                <div class="catering-campo">
                    <label for="settimana-nome">Nome del menu settimanale</label>
                    <input id="settimana-nome" required maxlength="150" placeholder="Es. Settimana base">
                </div>
                <div class="catering-campo">
                    <label for="settimana-tipo">Tipologia alimentare</label>
                    <select id="settimana-tipo" required></select>
                </div>
            </div>
            <div class="catering-giorni">
                <div class="catering-campo"><label for="settimana-giorno-1">Lunedì</label>
                    <textarea id="settimana-giorno-1" rows="3" data-testid="settimana-giorno-1" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-1" class="menu-foto" data-testid="settimana-foto-1"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-2">Martedì</label>
                    <textarea id="settimana-giorno-2" rows="3" data-testid="settimana-giorno-2" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-2" class="menu-foto" data-testid="settimana-foto-2"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-3">Mercoledì</label>
                    <textarea id="settimana-giorno-3" rows="3" data-testid="settimana-giorno-3" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-3" class="menu-foto" data-testid="settimana-foto-3"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-4">Giovedì</label>
                    <textarea id="settimana-giorno-4" rows="3" data-testid="settimana-giorno-4" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-4" class="menu-foto" data-testid="settimana-foto-4"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-5">Venerdì</label>
                    <textarea id="settimana-giorno-5" rows="3" data-testid="settimana-giorno-5" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-5" class="menu-foto" data-testid="settimana-foto-5"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-6">Sabato</label>
                    <textarea id="settimana-giorno-6" rows="3" data-testid="settimana-giorno-6" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-6" class="menu-foto" data-testid="settimana-foto-6"></div></div>
                <div class="catering-campo"><label for="settimana-giorno-7">Domenica</label>
                    <textarea id="settimana-giorno-7" rows="3" data-testid="settimana-giorno-7" placeholder="Descrizione del menu"></textarea>
                    <div id="settimana-foto-widget-7" class="menu-foto" data-testid="settimana-foto-7"></div></div>
            </div>
            <p class="nota">Puoi lasciare vuoti i giorni senza menu.</p>
            <div class="catering-settimane-azioni">
                <button type="submit" class="btn btn-primary" id="settimana-salva">Salva menu settimanale</button>
                <button type="button" class="btn btn-outline-primary" id="settimana-nuovo">Nuovo menu</button>
            </div>
            <p id="settimana-stato" class="stato-operazione" role="status" aria-live="polite" hidden></p>
        </form>
        <div>
            <h2>Menu disponibili</h2>
            <div id="settimane-elenco" class="catering-elenco-settimane"></div>
        </div>
    </div>
    <div class="pannello catering-assegna">
        <h2>Assegna ai plessi</h2>
        <p class="nota">Seleziona un menu e i plessi che dovranno utilizzarlo. Le altre assegnazioni rimangono invariate.</p>
        <div class="catering-campo">
            <label for="assegna-modello">Menu settimanale</label>
            <select id="assegna-modello"></select>
        </div>
        <fieldset class="catering-plessi-scelta">
            <legend>Plessi a cui assegnarlo</legend>
            <div id="assegna-plessi"></div>
        </fieldset>
        <button type="button" class="btn btn-primary" id="assegna-salva">Assegna menu</button>
        <p id="assegna-stato" class="stato-operazione" role="status" aria-live="polite" hidden></p>
        <h3>Assegnazioni attive dal prossimo giorno</h3>
        <div id="assegnazioni-elenco" class="catering-elenco-assegnazioni"></div>
    </div>
</section>

<section id="vista-feedback" class="vista" hidden>
    <h1>Valutazioni delle famiglie</h1>
    <p class="nota">Valutazioni ricevute, senza nomi completi.</p>
    <div class="catering-filtri catering-filtri--feedback">
        <div class="catering-campo">
            <label for="fb-da">Dal</label>
            <input type="date" id="fb-da" data-testid="feedback-da-input">
        </div>
        <div class="catering-campo">
            <label for="fb-a">Al</label>
            <input type="date" id="fb-a" data-testid="feedback-a-input">
        </div>
        <div class="catering-campo">
            <label for="fb-plesso">Plesso</label>
            <select id="fb-plesso" data-testid="feedback-plesso-select"></select>
        </div>
        <div class="catering-campo">
            <label for="fb-tipologia">Tipologia</label>
            <select id="fb-tipologia" data-testid="feedback-tipologia-select"></select>
        </div>
        <button type="button" class="btn btn-primary catering-filtri__azione" id="btn-feedback" data-testid="feedback-filtra-button">Filtra</button>
    </div>
    <p id="feedback-stato" class="stato-operazione" role="status" hidden></p>
    <div class="tabella-scroll">
        <table class="table" data-testid="feedback-tabella">
            <thead><tr><th>Data</th><th>Alunno</th><th>Plesso</th><th>Classe</th><th>Tipologia</th><th>Voto</th><th>Commento</th></tr></thead>
            <tbody id="feedback-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
