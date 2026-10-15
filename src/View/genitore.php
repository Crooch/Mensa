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
    <header class="area-intro">
        <h1>La mia mensa</h1>
        <p>Controlla i buoni disponibili e gestisci i pasti dei tuoi figli.</p>
    </header>
    <div class="area-sezione-testa"><h2>Buoni disponibili</h2><span class="area-sezione-aiuto">Saldo per scuola</span></div>
    <div id="crediti-cards" class="griglia-cards famiglia-crediti" data-testid="crediti-cards"></div>
    <div class="area-sezione-testa"><h2>Presenze e assenze</h2></div>
    <p class="nota area-sezione-descrizione">Seleziona il giorno per comunicare un'assenza o chiedere pasta in bianco.</p>
    <div id="figli-lista" class="griglia-cards" data-testid="figli-lista"></div>
    <div class="area-sezione-testa"><h2>Menù di oggi</h2></div>
    <div id="menu-oggi" class="griglia-cards" data-testid="menu-oggi"></div>
</section>

<section id="vista-calendario" class="vista" hidden>
    <header class="area-intro">
        <h1>Calendario della mensa</h1>
        <p>Consulta i giorni di mensa e le comunicazioni per ciascun figlio.</p>
    </header>
    <div class="riga-filtri famiglia-filtri">
        <label for="cal-figlio">Scegli il figlio</label>
        <select id="cal-figlio" data-testid="calendario-figlio-select"></select>
        <label for="cal-mese">Scegli il mese</label>
        <input type="month" id="cal-mese" data-testid="calendario-mese-input">
        <button class="btn btn-primary btn-sm" id="btn-carica-calendario" data-testid="calendario-carica-button">Mostra calendario</button>
    </div>
    <p id="calendario-stato" class="stato-operazione" role="status" aria-live="polite" hidden></p>
    <div class="famiglia-calendario-layout">
        <div class="famiglia-calendario-mese">
            <div id="calendario" class="calendario" data-testid="calendario-griglia"></div>
            <p class="famiglia-calendario-legenda">Verde: pasto previsto · Rosso: pasto non previsto · Grigio: mensa chiusa</p>
        </div>
        <div id="calendario-dettaglio" class="pannello famiglia-calendario-dettaglio" data-testid="calendario-dettaglio" aria-live="polite"><p class="nota">Seleziona un giorno per vedere i dettagli.</p></div>
    </div>
</section>

<section id="vista-pagamenti" class="vista" hidden>
    <header class="area-intro"><h1>Movimenti dei buoni</h1><p>Acquisti, pasti e correzioni registrati per la tua famiglia.</p></header>
    <div class="tabella-scroll">
        <table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Giorno</th><th>Scuola</th><th>Operazione</th><th>Buoni</th><th>Importo</th><th>Bambino</th><th>Note</th></tr></thead>
            <tbody id="movimenti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-feedback" class="vista" hidden>
    <header class="area-intro"><h1>Come è andato il pasto?</h1><p>Lascia una valutazione per il giorno e il figlio selezionati.</p></header>
    <form id="form-feedback" class="pannello famiglia-feedback-form" data-testid="feedback-form">
        <div class="riga-filtri famiglia-filtri">
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
    <div class="area-sezione-testa"><h2>Le tue valutazioni</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="feedback-tabella">
            <thead><tr><th>Data</th><th>Alunno</th><th>Tipologia</th><th>Voto</th><th>Commento</th></tr></thead>
            <tbody id="feedback-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
