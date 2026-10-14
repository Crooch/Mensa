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
    <h1>Controlla chi mangia a mensa</h1>
    <p class="nota">Controlla le comunicazioni delle famiglie e indica eventuali correzioni. La scuola deve sempre spiegare il motivo di una modifica.</p>
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
                <th>Alunno</th><th>Tipo di menu</th><th>Situazione iniziale</th><th>Comunicazione famiglia</th>
                <th>Correzione scuola</th><th>Pasto previsto</th><th>Modifica</th>
            </tr></thead>
            <tbody id="presenze-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-assenze" class="vista" hidden>
    <h1>Segnala che tutta la classe non mangerà a mensa</h1>
    <p class="nota">Usa questa funzione, per esempio, quando la classe è in gita. I bambini della classe saranno considerati inizialmente assenti dalla mensa per quel giorno, salvo comunicazioni della famiglia o correzioni della scuola.</p>
    <form id="form-assenza" class="pannello" data-testid="assenza-massa-form">
        <div class="riga-filtri">
            <label for="am-classe">Classe/sezione</label>
            <select id="am-classe" data-testid="assenza-classe-select"></select>
            <label for="am-data">Data</label>
            <input type="date" id="am-data" data-testid="assenza-data-input">
        </div>
        <label for="am-motivo">Perché la classe non mangerà a mensa?</label>
        <input type="text" id="am-motivo" maxlength="255" placeholder="Es. Gita a Palermo" data-testid="assenza-motivo-input">
        <button type="submit" class="btn btn-primary" data-testid="assenza-submit-button">Segna tutta la classe assente</button>
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
