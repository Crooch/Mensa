<?php if (in_array($ruoloPagina, ['admin','operatore','scuola','catering'], true)): ?>
<section id="vista-riepilogo-pasti" class="vista" hidden>
 <header class="area-intro"><h1>Riepilogo pasti</h1><p>Alunni e personale, con evidenza delle giornate ancora da elaborare. I totali confermati escludono i dati provvisori.</p></header>
 <form id="aut-report" class="riga-filtri">
  <label>Dal <input type="date" name="dal" required></label><label>Al <input type="date" name="al" required></label>
  <label>Plesso <select name="plesso_id" class="aut-plessi"><option value="">Tutti</option></select></label>
  <label>Ordine scolastico <select name="ordine"><option value="">Tutti</option><option value="infanzia">Infanzia</option><option value="primaria">Primaria</option><option value="secondaria">Secondaria</option></select></label>
  <button class="btn btn-primary">Mostra riepilogo</button>
 </form>
 <p id="aut-report-stato" role="status"></p><div id="aut-report-totali"></div>
 <button id="aut-export" class="btn btn-outline-primary" disabled>Esporta CSV</button>
 <button id="aut-export-excel" class="btn btn-outline-primary" disabled>Esporta Excel</button>
 <div class="tabella-scroll"><table class="table"><thead><tr><th>Data</th><th>Plesso / ordine</th><th>Stato</th><th>Alunni</th><th>Personale</th><th>Totale</th><th>Tipologie</th></tr></thead><tbody id="aut-report-corpo"></tbody></table></div>
</section>
<section id="vista-personale" class="vista" hidden>
 <header class="area-intro"><h1>Pasti del personale</h1><p>Quantità aggregate, senza consumi di buoni delle famiglie. Il vegetariano è compreso nel totale: per 5 insegnanti di cui uno vegetariano, inserire 4 ordinari e 1 vegetariano.</p></header>
 <form id="aut-personale-filtro" class="riga-filtri"><label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><label>Data <input name="data" type="date" required></label><button class="btn btn-primary">Mostra pasti personale</button></form>
 <p id="aut-personale-stato" role="status"></p><div id="aut-personale-corpo"></div>
 <?php if (in_array($ruoloPagina,['operatore','admin'],true)): ?>
 <details class="pannello"><summary>Programmazione ricorrente</summary>
 <p>La nuova quantità vale dalla decorrenza scelta. Per terminare una voce, salvarla con quantità zero. Le ricorrenze si applicano solo nei giorni di servizio.</p>
 <form id="aut-regola" class="griglia-form">
  <label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><label>Decorrenza <input type="date" name="dal" required></label>
  <label>Giorno <select name="giorno"><option value="1">Lunedì</option><option value="2">Martedì</option><option value="3">Mercoledì</option><option value="4">Giovedì</option><option value="5">Venerdì</option><option value="6">Sabato</option><option value="7">Domenica</option></select></label>
  <label>Categoria <select name="categoria"><option value="insegnanti">Insegnanti</option><option value="ata">ATA</option><option value="assistenti">Assistenti mensa</option><option value="asacom">ASACOM</option></select></label>
  <label>Tipologia menù <select name="tipologia_menu_id" class="aut-tipologie"></select></label><label>Quantità <input type="number" name="quantita" min="0" max="1000" required></label>
  <label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva ricorrenza</button>
 </form><p id="aut-regola-stato" role="status"></p><div id="aut-regole"></div></details>
 <?php endif; ?>
</section>
<?php endif; ?>
<?php if (in_array($ruoloPagina,['admin','operatore'],true)): ?>
<section id="vista-servizio" class="vista" hidden>
 <header class="area-intro"><h1>Calendario e servizio</h1><p>Chiusure future, fido e conclusione del servizio. Le operazioni non riscrivono presenze e movimenti già registrati.</p></header>
 <form id="aut-servizio-filtro" class="riga-filtri"><label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><button class="btn btn-primary">Apri configurazione</button></form>
 <p id="aut-servizio-stato" role="status"></p><div id="aut-servizio-corpo"></div>
</section>
<?php endif; ?>
