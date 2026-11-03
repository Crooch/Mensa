<?php
$titoloPagina = 'Ufficio comunale · ' . $titoloApp;
$ruoloPagina = 'operatore';
$scriptPagina = 'operatore';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="famiglie" data-testid="tab-famiglie">Famiglie</button>
    <button class="scheda" data-vista="crediti" data-testid="tab-crediti">Situazione buoni mensa</button>
    <button class="scheda" data-vista="solleciti" data-testid="tab-solleciti">Solleciti pagamento</button>
<button class="scheda" data-vista="riepilogo-pasti">Riepilogo pasti</button>
<button class="scheda" data-vista="personale">Pasti del personale</button>
<button class="scheda" data-vista="servizio">Calendario e servizio</button>
</nav>

<section id="vista-famiglie" class="vista">
    <header class="area-intro"><h1>Famiglie e buoni mensa</h1><p>Cerca una famiglia per vedere figli, buoni disponibili e operazioni.</p></header>
    <form id="famiglie-filtri" class="operatore-ricerca">
        <div class="famiglie-ricerca-barra">
            <label class="visivamente-nascosto" for="ricerca-famiglie">Cerca per nome, cognome, telefono o email</label>
            <input type="search" id="ricerca-famiglie" placeholder="Nome, cognome, telefono o email" maxlength="120" data-testid="famiglie-ricerca-input">
            <button type="submit" class="btn btn-primary" id="btn-cerca" data-testid="famiglie-cerca-button">Cerca</button>
        </div>
        <label class="famiglie-stato">Mostra <select id="famiglie-stato"><option value="attive">Famiglie attive</option><option value="tutte">Tutte le famiglie</option><option value="disattivate">Famiglie disattivate</option></select></label>
        <button type="button" class="btn btn-outline-primary" id="btn-nuovo-genitore" data-testid="nuovo-genitore-button">Aggiungi famiglia</button>
    </form>
    <p id="ricerca-stato" role="status" aria-live="polite" class="stato-operazione" hidden></p>
    <div class="due-colonne operatore-layout">
        <section class="operatore-risultati" aria-label="Risultati della ricerca">
            <div class="famiglie-elenco-testa"><h2>Famiglie trovate</h2><label>Per pagina <select id="famiglie-per-pagina"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select></label></div>
            <p id="famiglie-intervallo" class="nota" aria-live="polite"></p>
            <div class="tabella-scroll">
            <table class="table" data-testid="famiglie-tabella">
                <thead><tr><th>Genitore</th><th>Telefono</th><th>Email</th><th></th></tr></thead>
                <tbody id="famiglie-corpo"></tbody>
            </table>
            </div>
            <nav class="famiglie-paginazione" aria-label="Pagine delle famiglie">
                <button type="button" id="famiglie-precedente" class="btn btn-outline-primary" disabled>Precedente</button>
                <span id="famiglie-pagina" aria-live="polite">Pagina 1 di 1</span>
                <button type="button" id="famiglie-successiva" class="btn btn-outline-primary" disabled>Successiva</button>
            </nav>
        </section>
        <div class="famiglia-dettaglio">
            <button type="button" id="famiglie-indietro" class="btn btn-outline-primary">← Torna all’elenco</button>
            <div id="scheda-famiglia" class="pannello" data-testid="scheda-famiglia" tabindex="-1" aria-label="Dettaglio famiglia">
                <p class="vuoto">Premi «Apri» accanto a una famiglia per vedere i figli e i buoni mensa.</p>
            </div>
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
    <header class="area-intro"><h1>Comunicazioni di sollecito</h1><p>Consulta posizioni oltre fido e storico. Gli invii restano gestiti dalla configurazione esistente.</p></header>
    <form id="solleciti-filtri" class="riga-filtri"><label>Dal <input type="date" name="dal" required></label><label>Al <input type="date" name="al" required></label><label>Famiglia <input name="q" type="search"></label><label>Plesso <select name="plesso_id" class="aut-plessi"><option value="">Tutti</option></select></label><button class="btn btn-primary">Filtra solleciti</button></form><p id="solleciti-stato" role="status"></p><div id="solleciti-posizioni"></div>
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
<?php require __DIR__ . '/partials/autonomia.php'; ?>
<?php require __DIR__ . '/partials/piede.php'; ?>
