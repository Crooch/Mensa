<?php
$titoloPagina = 'Ufficio comunale · ' . $titoloApp;
$ruoloPagina = 'operatore';
$scriptPagina = 'operatore';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="famiglie" data-testid="tab-famiglie">Famiglie</button>
    <button class="scheda" data-vista="crediti" data-testid="tab-crediti">Situazione crediti</button>
    <button class="scheda" data-vista="solleciti" data-testid="tab-solleciti">Solleciti inviati</button>
</nav>

<section id="vista-famiglie" class="vista">
    <h1>Anagrafica famiglie</h1>
    <div class="riga-filtri">
        <input type="search" id="ricerca-famiglie" placeholder="Cognome, telefono o email" data-testid="famiglie-ricerca-input">
        <button class="btn btn-primary btn-sm" id="btn-cerca" data-testid="famiglie-cerca-button">Cerca</button>
        <button class="btn btn-outline-primary btn-sm" id="btn-nuovo-genitore" data-testid="nuovo-genitore-button">Nuovo genitore</button>
    </div>
    <div class="due-colonne">
        <div class="tabella-scroll">
            <table class="table" data-testid="famiglie-tabella">
                <thead><tr><th>Genitore</th><th>Telefono</th><th>Email</th><th></th></tr></thead>
                <tbody id="famiglie-corpo"></tbody>
            </table>
        </div>
        <div id="scheda-famiglia" class="pannello" data-testid="scheda-famiglia">
            <p class="vuoto">Seleziona una famiglia per vedere figli, tariffe e crediti.</p>
        </div>
    </div>
</section>

<section id="vista-crediti" class="vista" hidden>
    <h1>Situazione crediti di tutte le famiglie</h1>
    <p class="nota">I saldi in rosso hanno superato il fido: attivano solo l'escalation informativa, mai il blocco del pasto.</p>
    <div class="tabella-scroll">
        <table class="table" data-testid="situazione-crediti-tabella">
            <thead><tr><th>Famiglia</th><th>Telefono</th><th>Plesso</th><th>Saldo</th><th>Fido</th><th>Stato</th></tr></thead>
            <tbody id="crediti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-solleciti" class="vista" hidden>
    <h1>Log solleciti debito</h1>
    <div class="tabella-scroll">
        <table class="table" data-testid="solleciti-tabella">
            <thead><tr><th>Data</th><th>Famiglia</th><th>Plesso</th><th>Saldo</th><th>Esito</th><th>Destinatari</th></tr></thead>
            <tbody id="solleciti-corpo"></tbody>
        </table>
    </div>
</section>

<div class="modale" id="modale" hidden data-testid="modale">
    <div class="modale__pannello">
        <div class="modale__testa">
            <h2 id="modale-titolo"></h2>
            <button type="button" id="modale-chiudi" class="btn btn-link" data-testid="modale-chiudi">Chiudi</button>
        </div>
        <div id="modale-corpo"></div>
    </div>
</div>
<?php require __DIR__ . '/partials/piede.php'; ?>
