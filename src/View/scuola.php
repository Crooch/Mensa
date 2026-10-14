<?php
$titoloPagina = 'Area scuola · ' . $titoloApp;
$ruoloPagina = 'scuola';
$scriptPagina = 'scuola';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="giornata" data-testid="tab-giornata">Pasti del giorno</button>
    <button class="scheda" data-vista="assenze" data-testid="tab-assenze">Assenza di tutta la classe</button>
</nav>

<section id="vista-giornata" class="vista">
    <h1>Chi mangerà a mensa?</h1>
    <p class="nota">Controlla i pasti. Per correggere una presenza, indica il motivo.</p>
    <div class="scuola-filtri">
        <div class="scuola-campo">
            <label for="classe">Classe/sezione</label>
            <select id="classe" data-testid="classe-select"></select>
        </div>
        <div class="scuola-campo">
            <label for="data">Data</label>
            <input type="date" id="data" data-testid="data-input">
        </div>
        <button class="btn btn-primary scuola-aggiorna" id="btn-carica" data-testid="carica-giornata-button">Aggiorna elenco</button>
    </div>
    <p id="scuola-stato" class="stato-operazione" role="status" aria-live="polite" hidden></p>
    <div id="scuola-riepilogo" class="riepilogo-mensa" aria-live="polite"></div>
    <div id="stato-cutoff" class="banner" data-testid="stato-cutoff" role="status"></div>
    <div class="tabella-scroll scuola-tabella">
        <table class="table" data-testid="presenze-tabella">
            <thead><tr>
                <th>Alunno</th><th>Menu</th><th>Comunicazione famiglia</th>
                <th>Correzione scuola</th><th>Stato del pasto</th><th>Azioni</th>
            </tr></thead>
            <tbody id="presenze-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-assenze" class="vista" hidden>
    <h1>Segnala che tutta la classe non mangerà a mensa</h1>
    <p class="nota">Per gite o giornate in cui tutta la classe non pranza.</p>
    <form id="form-assenza" class="pannello scuola-assenza-form" data-testid="assenza-massa-form">
        <div class="scuola-filtri scuola-filtri--assenza">
            <div class="scuola-campo">
                <label for="am-classe">Classe/sezione</label>
                <select id="am-classe" data-testid="assenza-classe-select"></select>
            </div>
            <div class="scuola-campo">
                <label for="am-data">Data</label>
                <input type="date" id="am-data" data-testid="assenza-data-input">
            </div>
        </div>
        <div class="scuola-campo scuola-campo--motivo">
            <label for="am-motivo">Motivo dell'assenza</label>
            <input type="text" id="am-motivo" maxlength="255" required placeholder="Es. Gita scolastica" data-testid="assenza-motivo-input">
        </div>
        <button type="submit" class="btn btn-primary" data-testid="assenza-submit-button">Segnala assenza della classe</button>
    </form>
    <h2>Giornate senza mensa per tutta la classe</h2>
    <div class="tabella-scroll">
        <table class="table" data-testid="assenze-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Classe</th><th>Motivo</th><th>Inserita da</th></tr></thead>
            <tbody id="assenze-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
