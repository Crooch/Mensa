<?php
$titoloPagina = 'Ufficio comunale · ' . $titoloApp;
$ruoloPagina = 'operatore';
$scriptPagina = 'operatore';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="famiglie" data-testid="tab-famiglie">Famiglie</button>
    <button class="scheda" data-vista="crediti" data-testid="tab-crediti">Situazione buoni mensa</button>
    <button class="scheda" data-vista="solleciti" data-testid="tab-solleciti">Solleciti inviati</button>
</nav>

<section id="vista-famiglie" class="vista">
    <header class="area-intro"><h1>Famiglie e buoni mensa</h1><p>Cerca una famiglia per vedere figli, buoni disponibili e operazioni.</p></header>
    <div class="riga-filtri operatore-ricerca">
        <label class="visivamente-nascosto" for="ricerca-famiglie">Cerca per cognome, telefono o email</label>
        <input type="search" id="ricerca-famiglie" placeholder="Scrivi un cognome, un telefono o un'email" data-testid="famiglie-ricerca-input">
        <button class="btn btn-primary btn-sm" id="btn-cerca" data-testid="famiglie-cerca-button">Cerca</button>
        <button class="btn btn-outline-primary btn-sm" id="btn-nuovo-genitore" data-testid="nuovo-genitore-button">Aggiungi famiglia</button>
    </div>
    <p id="ricerca-stato" role="status" aria-live="polite" class="stato-operazione" hidden></p>
    <div class="due-colonne operatore-layout">
        <section class="operatore-risultati" aria-label="Risultati della ricerca">
            <h2>Famiglie trovate</h2>
            <div class="tabella-scroll">
            <table class="table" data-testid="famiglie-tabella">
                <thead><tr><th>Genitore</th><th>Telefono</th><th>Email</th><th></th></tr></thead>
                <tbody id="famiglie-corpo"></tbody>
            </table>
            </div>
        </section>
        <div id="scheda-famiglia" class="pannello" data-testid="scheda-famiglia">
            <p class="vuoto">Premi «Apri» accanto a una famiglia per vedere i figli e i buoni mensa.</p>
        </div>
    </div>
</section>

<section id="vista-crediti" class="vista" hidden>
    <header class="area-intro"><h1>Situazione buoni mensa</h1><p>Saldi disponibili e limiti di credito delle famiglie. Un saldo negativo non blocca il pasto.</p></header>
    <div class="tabella-scroll">
        <table class="table" data-testid="situazione-crediti-tabella">
            <thead><tr><th>Famiglia</th><th>Telefono</th><th>Plesso</th><th>Saldo buoni</th><th>Fido concesso</th><th>Situazione</th></tr></thead>
            <tbody id="crediti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-solleciti" class="vista" hidden>
    <header class="area-intro"><h1>Comunicazioni di sollecito</h1><p>Elenco delle comunicazioni già registrate.</p></header>
    <div class="tabella-scroll">
        <table class="table" data-testid="solleciti-tabella">
            <thead><tr><th>Data</th><th>Famiglia</th><th>Plesso</th><th>Saldo</th><th>Esito</th><th>Destinatari</th></tr></thead>
            <tbody id="solleciti-corpo"></tbody>
        </table>
    </div>
</section>

<div class="modale" id="modale" hidden data-testid="modale">
    <div class="modale__pannello operatore-modale" role="dialog" aria-modal="true" aria-labelledby="modale-titolo">
        <div class="modale__testa">
            <h2 id="modale-titolo"></h2>
            <button type="button" id="modale-chiudi" class="btn btn-link" data-testid="modale-chiudi">Chiudi</button>
        </div>
        <div id="modale-corpo"></div>
    </div>
</div>
<?php require __DIR__ . '/partials/piede.php'; ?>
