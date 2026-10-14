<?php
$titoloPagina = 'Area famiglia · ' . $titoloApp;
$ruoloPagina = 'genitore';
$scriptPagina = 'genitore';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="riepilogo" data-testid="tab-riepilogo">Riepilogo</button>
    <button class="scheda" data-vista="calendario" data-testid="tab-calendario">Calendario</button>
    <button class="scheda" data-vista="pagamenti" data-testid="tab-pagamenti">Pagamenti</button>
    <button class="scheda" data-vista="feedback" data-testid="tab-feedback">Feedback</button>
</nav>

<section id="vista-riepilogo" class="vista" data-testid="genitore-dashboard">
    <h1>La mia mensa</h1>
    <div id="crediti-cards" class="griglia-cards" data-testid="crediti-cards"></div>
    <h2>Presenze e assenze</h2>
    <p class="nota">Scegli un giorno per comunicare un'assenza o chiedere pasta in bianco.</p>
    <div id="figli-lista" class="griglia-cards" data-testid="figli-lista"></div>
    <h2>Il menu di oggi</h2>
    <div id="menu-oggi" class="griglia-cards" data-testid="menu-oggi"></div>
</section>

<section id="vista-calendario" class="vista" hidden>
    <h1>Calendario della mensa</h1>
    <p class="nota">Scegli un giorno per vedere o modificare il pasto.</p>
    <div class="riga-filtri">
        <label for="cal-figlio">Scegli il figlio</label>
        <select id="cal-figlio" data-testid="calendario-figlio-select"></select>
        <label for="cal-mese">Scegli il mese</label>
        <input type="month" id="cal-mese" data-testid="calendario-mese-input">
        <button class="btn btn-primary btn-sm" id="btn-carica-calendario" data-testid="calendario-carica-button">Mostra calendario</button>
    </div>
    <p id="calendario-stato" class="stato-operazione" role="status" aria-live="polite" hidden></p>
    <div id="calendario" class="calendario" data-testid="calendario-griglia"></div>
    <div id="calendario-dettaglio" class="pannello" data-testid="calendario-dettaglio" aria-live="polite"><p class="nota">Tocca un giorno del calendario per vedere i dettagli.</p></div>
</section>

<section id="vista-pagamenti" class="vista" hidden>
    <h1>Movimenti dei buoni</h1>
    <div class="tabella-scroll">
        <table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Giorno</th><th>Scuola</th><th>Operazione</th><th>Buoni</th><th>Importo</th><th>Bambino</th><th>Note</th></tr></thead>
            <tbody id="movimenti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-feedback" class="vista" hidden>
    <h1>Come è andato il pasto?</h1>
    <form id="form-feedback" class="pannello" data-testid="feedback-form">
        <div class="riga-filtri">
            <label for="fb-figlio">Scegli il figlio</label>
            <select id="fb-figlio" data-testid="feedback-figlio-select"></select>
            <label for="fb-data">Giorno del pasto</label>
            <input type="date" id="fb-data" data-testid="feedback-data-input">
        </div>
        <p class="nota">Scegli da 1 a 5 stelle: 1 significa poco soddisfatto, 5 molto soddisfatto.</p>
        <div class="stelle" id="fb-stelle" data-testid="feedback-stelle" aria-label="Valuta il pasto da una a cinque stelle"></div>
        <label for="fb-commento">Commento (facoltativo)</label>
        <textarea id="fb-commento" rows="2" maxlength="500" data-testid="feedback-commento-input"></textarea>
        <button type="submit" class="btn btn-primary" data-testid="feedback-submit-button">Invia valutazione</button>
    </form>
    <h2>Le tue valutazioni</h2>
    <div class="tabella-scroll">
        <table class="table" data-testid="feedback-tabella">
            <thead><tr><th>Data</th><th>Alunno</th><th>Tipologia</th><th>Voto</th><th>Commento</th></tr></thead>
            <tbody id="feedback-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
