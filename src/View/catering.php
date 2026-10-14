<?php
$titoloPagina = 'Area ristorazione · ' . $titoloApp;
$ruoloPagina = 'catering';
$scriptPagina = 'catering';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="report" data-testid="tab-report">Pasti da preparare</button>
    <button class="scheda" data-vista="menu" data-testid="tab-menu">Menu del giorno</button>
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
    <p class="nota">I conteggi sono provvisori fino alla conferma. Le richieste di pasta in bianco indicano nome e classe.</p>
    <div id="report" class="griglia-cards" data-testid="report-plessi"></div>
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
            <label for="menu-foto">Foto del menu (facoltativa)</label>
            <input type="file" id="menu-foto" accept="image/*" data-testid="menu-foto-input">
            <p id="menu-foto-stato" class="nota catering-foto-nota" data-testid="menu-foto-stato">Puoi aggiungere una foto al menu.</p>
        </div>
        <button type="submit" id="menu-submit" class="btn btn-primary catering-menu-submit" disabled data-testid="menu-submit-button">Pubblica questo menu</button>
    </form>
    <h2>Menu già pubblicati oggi</h2>
    <p id="menu-stato" class="stato-operazione" role="status" hidden></p>
    <div id="menu-lista" class="griglia-cards" data-testid="menu-lista"></div>
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
