<?php
$titoloPagina = 'Area scuola · ' . $titoloApp;
$ruoloPagina = 'scuola';
$scriptPagina = 'scuola';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="giornata" data-testid="tab-giornata">Presenze del giorno</button>
    <button class="scheda" data-vista="assenze" data-testid="tab-assenze">Assenze di massa</button>
</nav>

<section id="vista-giornata" class="vista">
    <h1>Verifica presenze</h1>
    <div class="riga-filtri">
        <label for="classe">Classe/sezione</label>
        <select id="classe" data-testid="classe-select"></select>
        <label for="data">Data</label>
        <input type="date" id="data" data-testid="data-input">
        <button class="btn btn-primary btn-sm" id="btn-carica" data-testid="carica-giornata-button">Mostra</button>
    </div>
    <div id="stato-cutoff" class="banner" data-testid="stato-cutoff"></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="presenze-tabella">
            <thead><tr>
                <th>Alunno</th><th>Tipologia</th><th>Default</th><th>Dichiarazione famiglia</th>
                <th>Override scuola</th><th>Stato effettivo</th><th>Azioni</th>
            </tr></thead>
            <tbody id="presenze-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-assenze" class="vista" hidden>
    <h1>Assenza di massa (gita, uscita didattica)</h1>
    <form id="form-assenza" class="pannello" data-testid="assenza-massa-form">
        <div class="riga-filtri">
            <label for="am-classe">Classe/sezione</label>
            <select id="am-classe" data-testid="assenza-classe-select"></select>
            <label for="am-data">Data</label>
            <input type="date" id="am-data" data-testid="assenza-data-input">
        </div>
        <label for="am-motivo">Motivo</label>
        <input type="text" id="am-motivo" maxlength="255" placeholder="Es. Gita a Palermo" data-testid="assenza-motivo-input">
        <button type="submit" class="btn btn-primary" data-testid="assenza-submit-button">Registra assenza di massa</button>
    </form>
    <h2>Assenze registrate</h2>
    <div class="tabella-scroll">
        <table class="table" data-testid="assenze-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Classe</th><th>Motivo</th><th>Inserita da</th></tr></thead>
            <tbody id="assenze-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
