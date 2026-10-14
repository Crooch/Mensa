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
    <p class="nota">Scegli il giorno per vedere quanti pasti preparare, divisi per scuola, classe e tipo di menu.</p>
    <div class="riga-filtri">
        <label for="report-data">Data</label>
        <input type="date" id="report-data" data-testid="report-data-input">
        <button class="btn btn-primary btn-sm" type="button" id="btn-report" data-testid="report-carica-button">Aggiorna i pasti</button>
    </div>
    <p class="nota" id="report-stato" role="status" aria-live="polite" data-testid="report-stato">Seleziona una data per consultare i pasti.</p>
    <p class="nota">I numeri possono cambiare fino all'orario limite indicato. Dopo il conteggio automatico, il report sarà indicato come «Confermato». Le richieste di pasta in bianco mostrano il nome del bambino e la classe.</p>
    <div id="report" class="griglia-cards" data-testid="report-plessi"></div>
</section>

<section id="vista-menu" class="vista" hidden>
    <h1>Comunica il menu di oggi</h1>
    <form id="form-menu" class="pannello" enctype="multipart/form-data" data-testid="menu-form">
        <div class="riga-filtri">
            <label for="menu-plesso">Plesso</label>
            <select id="menu-plesso" data-testid="menu-plesso-select"></select>
            <label for="menu-tipologia">Tipologia</label>
            <select id="menu-tipologia" data-testid="menu-tipologia-select"></select>
        </div>
        <label for="menu-descrizione">Cosa si mangia oggi?</label>
        <textarea id="menu-descrizione" rows="2" data-testid="menu-descrizione-input"></textarea>
        <label for="menu-foto">Foto del menu (facoltativa)</label>
        <input type="file" id="menu-foto" accept="image/*" data-testid="menu-foto-input">
        <button type="submit" class="btn btn-primary" data-testid="menu-submit-button">Pubblica per oggi</button>
    </form>
    <h2>Menu già pubblicati oggi</h2>
    <div id="menu-lista" class="griglia-cards" data-testid="menu-lista"></div>
</section>

<section id="vista-feedback" class="vista" hidden>
    <h1>Valutazioni delle famiglie</h1>
    <p class="nota">Qui puoi leggere le valutazioni ricevute. Non sono mostrati i nomi completi dei bambini e non puoi rispondere da questa pagina.</p>
    <div class="riga-filtri">
        <label for="fb-da">Dal</label><input type="date" id="fb-da" data-testid="feedback-da-input">
        <label for="fb-a">Al</label><input type="date" id="fb-a" data-testid="feedback-a-input">
        <label for="fb-plesso">Plesso</label><select id="fb-plesso" data-testid="feedback-plesso-select"></select>
        <label for="fb-tipologia">Tipologia</label><select id="fb-tipologia" data-testid="feedback-tipologia-select"></select>
        <button class="btn btn-primary btn-sm" id="btn-feedback" data-testid="feedback-filtra-button">Filtra</button>
    </div>
    <div class="tabella-scroll">
        <table class="table" data-testid="feedback-tabella">
            <thead><tr><th>Data</th><th>Alunno</th><th>Plesso</th><th>Classe</th><th>Tipologia</th><th>Voto</th><th>Commento</th></tr></thead>
            <tbody id="feedback-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
