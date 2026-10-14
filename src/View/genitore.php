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
    <h1>La mia mensa</h1>
    <div id="crediti-cards" class="griglia-cards" data-testid="crediti-cards"></div>
    <h2>Presenze e assenze dei miei figli</h2>
    <p class="nota">Di solito tuo figlio risulta presente nei giorni in cui è prevista la mensa. Se non mangerà a scuola, comunicaci l'assenza scegliendo il giorno. Puoi cambiare la scelta fino all'orario indicato.</p>
    <div id="figli-lista" class="griglia-cards" data-testid="figli-lista"></div>
    <h2>Menu pubblicato oggi</h2>
    <div id="menu-oggi" class="griglia-cards" data-testid="menu-oggi"></div>
</section>

<section id="vista-calendario" class="vista" hidden>
    <h1>Calendario della mensa</h1>
    <p class="nota">Scegli un figlio e il mese. Tocca un giorno per vedere ciò che hai comunicato e le eventuali modifiche della scuola. I giorni senza indicazioni non significano necessariamente che la mensa sia chiusa.</p>
    <div class="riga-filtri">
        <label for="cal-figlio">Scegli il figlio</label>
        <select id="cal-figlio" data-testid="calendario-figlio-select"></select>
        <label for="cal-mese">Scegli il mese</label>
        <input type="month" id="cal-mese" data-testid="calendario-mese-input">
        <button class="btn btn-primary btn-sm" id="btn-carica-calendario" data-testid="calendario-carica-button">Mostra calendario</button>
    </div>
    <p class="nota">Nel calendario: <strong>Presente a mensa</strong> = è previsto il pasto; <strong>Assente dalla mensa</strong> = non è previsto il pasto. Le comunicazioni della famiglia sono indicate con «Hai comunicato».</p>
    <div id="calendario" class="calendario" data-testid="calendario-griglia"></div>
    <div id="calendario-dettaglio" class="pannello" data-testid="calendario-dettaglio"></div>
</section>

<section id="vista-pagamenti" class="vista" hidden>
    <h1>Buoni acquistati e utilizzati</h1>
    <div class="tabella-scroll">
        <table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tipo</th><th>Buoni</th><th>Importo</th><th>Alunno</th><th>Note</th></tr></thead>
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
        <div class="stelle" id="fb-stelle" data-testid="feedback-stelle"></div>
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
