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
 <button id="aut-export" class="btn btn-outline-primary" disabled>Esporta CSV</button>
 <button id="aut-export-mensile" class="btn btn-outline-primary" disabled>Esporta mensile CSV</button>
 <button id="aut-export-excel" class="btn btn-outline-primary" disabled>Esporta Excel</button>
 <h2>Riepilogo mensile per ordine scolastico</h2>
 <p>Ogni riga somma i plessi dell’ordine nel periodo selezionato. Il totale disponibile include solo le giornate con alunni elaborati e personale disponibile; le altre sono segnalate separatamente. Le giornate sono conteggiate per plesso.</p>
 <div class="tabella-scroll"><table class="table"><thead><tr><th>Mese / periodo</th><th>Ordine</th><th>Alunni elaborati</th><th>Personale nelle giornate elaborate</th><th>Totale disponibile</th><th>Pasti previsti</th><th>Giornate da verificare / previste</th></tr></thead><tbody id="aut-report-mensile"></tbody></table></div>
 <h2>Dettaglio giornaliero</h2>
 <div class="tabella-scroll"><table class="table"><thead><tr><th>Data</th><th>Plesso / ordine</th><th>Stato</th><th>Alunni</th><th>Personale</th><th>Totale</th><th>Tipologie</th></tr></thead><tbody id="aut-report-corpo"></tbody></table></div>
</section>
<section id="vista-personale" class="vista" hidden>
 <header class="area-intro"><h1>Pasti del personale</h1><p>Le quantità settimanali si ripetono automaticamente nei giorni di servizio: intervieni solo per assenze o personale aggiuntivo. Le variazioni riguardano una sola data. Oggi e le date future sono modificabili dalla Scuola senza limite orario; per il passato interviene il Comune. Nessun consumo di buoni delle famiglie. Il vegetariano è compreso nel totale: per 5 insegnanti di cui uno vegetariano, inserire 4 ordinari e 1 vegetariano.</p></header>
 <form id="aut-personale-filtro" class="riga-filtri"><label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><label>Data <input name="data" type="date" required></label><button class="btn btn-primary">Mostra pasti personale</button></form>
 <p id="aut-personale-stato" role="status"></p><div id="aut-personale-corpo"></div>
 <?php if (in_array($ruoloPagina,['operatore','admin'],true)): ?>
 <details class="pannello"><summary>Programmazione ricorrente</summary>
 <p>Imposta una volta le quantità abituali per scuola e giorni della settimana: restano valide tutte le settimane, fino a una nuova modifica. La decorrenza può essere oggi o futura. Per terminare una voce, salva quantità zero. Una variazione giornaliera ha precedenza e non modifica queste regole.</p>
 <form id="aut-regola" class="griglia-form">
  <label>Plesso <select name="plesso_id" class="aut-plessi" required></select></label><label>Decorrenza <input type="date" name="dal" required></label>
  <fieldset class="aut-giorni"><legend>Giorni della settimana</legend><?php foreach ([1=>'Lunedì',2=>'Martedì',3=>'Mercoledì',4=>'Giovedì',5=>'Venerdì',6=>'Sabato',7=>'Domenica'] as $g=>$nome): ?><label><input type="checkbox" name="giorni" value="<?= $g ?>" <?= $g<=5?'checked':'' ?>> <?= $nome ?></label><?php endforeach; ?></fieldset>
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
