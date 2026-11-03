<?php if (in_array($ruoloPagina, ['admin','operatore','scuola','catering'], true)): ?>
<section id="vista-riepilogo-pasti" class="vista" hidden>
 <header class="area-intro"><h1>Riepilogo pasti</h1><p>Alunni e personale, con evidenza delle giornate ancora da elaborare. I conteggi delle giornate elaborate sono separati dalle previsioni. Le rettifiche del personale aggiornano i riepiloghi.</p></header>
 <label>Seleziona un mese <input type="month" id="aut-mese"></label>
 <form id="aut-report" class="riga-filtri">
  <label>Dal <input type="date" name="dal" required></label><label>Al <input type="date" name="al" required></label>
  <label>Plesso <select name="plesso_id" class="aut-plessi"><option value="">Tutti</option></select></label>
  <label>Ordine scolastico <select name="ordine"><option value="">Tutti</option><option value="infanzia">Infanzia</option><option value="primaria">Primaria</option><option value="secondaria">Secondaria</option></select></label>
  <button class="btn btn-primary">Mostra riepilogo</button>
 </form>
 <p id="aut-report-stato" role="status"></p><div id="aut-report-totali"></div>
 <div class="aut-report-esportazioni" role="group" aria-label="Esporta riepilogo">
 <button id="aut-export" class="btn btn-outline-primary" disabled>Esporta CSV</button>
 <button id="aut-export-mensile" class="btn btn-outline-primary" disabled>Esporta mensile CSV</button>
 <button id="aut-export-excel" class="btn btn-outline-primary" disabled>Esporta Excel</button>
 </div>
 <h2>Riepilogo mensile per ordine scolastico</h2>
 <p>Ogni riga somma i plessi dell’ordine nel periodo selezionato. Il totale disponibile include solo le giornate con alunni elaborati e personale disponibile; le altre sono segnalate separatamente. Le giornate sono conteggiate per plesso.</p>
 <div class="tabella-scroll"><table class="table"><thead><tr><th>Mese / periodo</th><th>Ordine</th><th>Alunni elaborati</th><th>Personale nelle giornate elaborate</th><th>Totale disponibile</th><th>Pasti previsti</th><th>Giornate da verificare / previste</th></tr></thead><tbody id="aut-report-mensile"></tbody></table></div>
 <h2>Dettaglio giornaliero</h2>
 <div class="tabella-scroll"><table class="table"><thead><tr><th>Data</th><th>Plesso / ordine</th><th>Stato</th><th>Alunni</th><th>Personale</th><th>Totale</th><th>Tipologie</th></tr></thead><tbody id="aut-report-corpo"></tbody></table></div>
</section>
<section id="vista-personale" class="vista" hidden>
 <header class="area-intro"><h1>Pasti del personale</h1><p>Consulta le quantità abituali della scuola. Intervieni sulla singola giornata solo quando il personale cambia.</p></header>
 <form id="aut-personale-filtro" class="riga-filtri personale-contesto"><label>Scuola <select name="plesso_id" class="aut-plessi" required></select></label><label>Data di riferimento <input name="data" type="date" required></label><button class="btn btn-primary">Mostra pasti personale</button></form>
 <nav class="personale-viste" aria-label="Vista pasti personale"><button type="button" id="aut-vista-programma" class="attiva" aria-pressed="true">Programmazione abituale</button><button type="button" id="aut-vista-giorno" aria-pressed="false">Pasti della data scelta</button></nav>
 <p id="aut-personale-stato" role="status" aria-live="polite"></p>
 <div id="aut-programmazione" class="personale-sezione" hidden>
  <div class="personale-sezione-testa"><div><p class="personale-sopratitolo">Si ripete ogni settimana</p><h2>Programmazione abituale</h2></div><span id="aut-programmazione-scuola"></span></div>
  <p id="aut-programmazione-data" class="nota"></p>
  <div id="aut-regole"></div>
  <?php if (in_array($ruoloPagina,['operatore','admin'],true)): ?>
  <details id="aut-editor-regola" class="personale-editor"><summary>Modifica programmazione abituale</summary>
   <p>Questa modifica si ripete ogni settimana dalla decorrenza scelta. Per un’assenza occasionale usa invece «Modifica solo questa data» nella sezione sottostante.</p>
   <form id="aut-regola" class="griglia-form">
    <input type="hidden" name="plesso_id"><label>Da quando <input type="date" name="dal" required></label>
    <label>Categoria <select name="categoria"><option value="insegnanti">Insegnanti</option><option value="ata">ATA</option><option value="assistenti">Assistenti mensa</option><option value="asacom">ASACOM</option></select></label>
    <label>Menù <select name="tipologia_menu_id" class="aut-tipologie"></select></label><label>Pasti per ciascun giorno selezionato <input type="number" name="quantita" min="0" max="1000" required></label>
    <fieldset class="aut-giorni"><legend>In quali giorni</legend><?php foreach ([1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'] as $g=>$nome): ?><label><input type="checkbox" name="giorni" value="<?= $g ?>" <?= $g<=5?'checked':'' ?>> <?= $nome ?></label><?php endforeach; ?></fieldset>
    <p class="personale-form-nota">La quantità sostituisce quella della stessa categoria e menù nei giorni selezionati. Inserisci 0 per interrompere quella voce. Gli altri giorni e le variazioni già registrate restano validi.</p>
    <label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva ricorrenza</button>
   </form><p id="aut-regola-stato" role="status"></p>
  </details>
  <?php else: ?><p class="nota">La programmazione abituale è gestita dal Comune.</p><?php endif; ?>
  <div id="aut-programmazione-futura"></div><div id="aut-programmazione-storico"></div>
 </div>
 <div id="aut-personale-corpo"></div>
</section>
<?php endif; ?>
<?php if (in_array($ruoloPagina,['admin','operatore'],true)): ?>
<section id="vista-servizio" class="vista" hidden>
 <header class="area-intro"><h1>Calendario e servizio</h1><p>Chiusure future, fido e conclusione del servizio. Le operazioni non riscrivono presenze e movimenti già registrati.</p></header>
 <form id="aut-servizio-filtro" class="riga-filtri"><label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><button class="btn btn-primary">Apri configurazione</button></form>
 <p id="aut-servizio-stato" role="status"></p><div id="aut-servizio-corpo"></div>
</section>
<?php endif; ?>
