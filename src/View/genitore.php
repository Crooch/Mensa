<?php
$titoloPagina = 'Area famiglia · ' . $titoloApp;
$ruoloPagina = 'genitore';
$scriptPagina = 'genitore';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="riepilogo" data-testid="tab-riepilogo">Riepilogo</button>
    <button class="scheda" data-vista="calendario" data-testid="tab-calendario">Calendario presenze</button>
    <button class="scheda" data-vista="pagamenti" data-testid="tab-pagamenti">Pagamenti</button>
    <button class="scheda" data-vista="feedback" data-testid="tab-feedback">Feedback</button>
</nav>

<section id="vista-riepilogo" class="vista" data-testid="genitore-dashboard">
    <h1>Situazione di oggi</h1>
    <div id="crediti-cards" class="griglia-cards" data-testid="crediti-cards"></div>
    <h2>I miei figli</h2>
    <div id="figli-lista" class="griglia-cards" data-testid="figli-lista"></div>
    <h2>Menu pubblicato oggi</h2>
    <div id="menu-oggi" class="griglia-cards" data-testid="menu-oggi"></div>
</section>

<section id="vista-calendario" class="vista" hidden>
    <h1>Calendario presenze</h1>
    <div class="riga-filtri">
        <label for="cal-figlio">Figlio</label>
        <select id="cal-figlio" data-testid="calendario-figlio-select"></select>
        <label for="cal-mese">Mese</label>
        <input type="month" id="cal-mese" data-testid="calendario-mese-input">
        <button class="btn btn-primary btn-sm" id="btn-carica-calendario" data-testid="calendario-carica-button">Mostra</button>
    </div>
    <div id="calendario" class="calendario" data-testid="calendario-griglia"></div>
    <div id="calendario-dettaglio" class="pannello" data-testid="calendario-dettaglio"></div>
</section>

<section id="vista-pagamenti" class="vista" hidden>
    <h1>Ricariche e movimenti</h1>
    <div class="tabella-scroll">
        <table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tipo</th><th>Buoni</th><th>Importo</th><th>Alunno</th><th>Note</th></tr></thead>
            <tbody id="movimenti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-feedback" class="vista" hidden>
    <h1>Feedback sul pasto</h1>
    <form id="form-feedback" class="pannello" data-testid="feedback-form">
        <div class="riga-filtri">
            <label for="fb-figlio">Figlio</label>
            <select id="fb-figlio" data-testid="feedback-figlio-select"></select>
            <label for="fb-data">Data</label>
            <input type="date" id="fb-data" data-testid="feedback-data-input">
        </div>
        <div class="stelle" id="fb-stelle" data-testid="feedback-stelle"></div>
        <label for="fb-commento">Commento (facoltativo)</label>
        <textarea id="fb-commento" rows="2" maxlength="500" data-testid="feedback-commento-input"></textarea>
        <button type="submit" class="btn btn-primary" data-testid="feedback-submit-button">Invia feedback</button>
    </form>
    <h2>Feedback inviati</h2>
    <div class="tabella-scroll">
        <table class="table" data-testid="feedback-tabella">
            <thead><tr><th>Data</th><th>Alunno</th><th>Tipologia</th><th>Voto</th><th>Commento</th></tr></thead>
            <tbody id="feedback-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
