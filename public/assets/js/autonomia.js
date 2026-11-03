/* Funzioni operative nelle aree esistenti. Ogni API verifica autonomamente ruolo e ambito. */
(async () => {
 const ruolo = utente.ruolo;
 const ufficio = ['admin','operatore'].includes(ruolo);
 const e = esc;
 const id = x => document.getElementById(x);
 const valore = n => n === null ? 'Non disponibile' : Number(n);
 const categorie = {insegnanti:'Insegnanti',ata:'ATA',assistenti:'Assistenti mensa',asacom:'ASACOM'};
 const stato = (x,msg,errore=false) => { id(x).textContent=msg; id(x).className=errore?'stato-operazione stato-operazione--errore':'stato-operazione'; };
 const dati = f => Object.fromEntries(new FormData(f));
 const rifer = await API.get('/api/riferimenti');
 const plessi = rifer.plessi.filter(p => ruolo!=='scuola' || Number(p.id)===Number(utente.plesso_id));
 const tipi = rifer.tipologie;
 const nomeTipo = n => tipi.find(t=>Number(t.id)===Number(n))?.nome || 'Tipologia '+n;
 const etichetteTabella = root => root.querySelectorAll('table').forEach(table=>{const heads=[...table.querySelectorAll('thead th')].map(x=>x.textContent);table.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,i)=>cell.dataset.label=heads[i]||''));});
 const giorni=['','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato','Domenica'];
 const nomePlesso = n => plessi.find(p=>Number(p.id)===Number(n))?.nome || 'Plesso '+n;
 document.querySelectorAll('.aut-plessi').forEach(s=>s.insertAdjacentHTML('beforeend',plessi.map(p=>`<option value="${p.id}">${e(p.nome)} · ID ${p.id}</option>`).join('')));
 document.querySelectorAll('.aut-tipologie').forEach(s=>s.innerHTML=tipi.map(t=>`<option value="${t.id}">${e(t.nome)}</option>`).join(''));
 id('aut-report').elements.dal.value=oggi().slice(0,7)+'-01'; id('aut-report').elements.al.value=oggi();
 id('aut-personale-filtro').elements.data.value=oggi();
 for(const option of id('aut-personale-filtro').elements.plesso_id.options) option.textContent=nomePlesso(Number(option.value));
 id('aut-mese').addEventListener('change',ev=>{const mese=ev.target.value;if(!mese)return;const [y,m]=mese.split('-').map(Number);id('aut-report').elements.dal.value=mese+'-01';id('aut-report').elements.al.value=mese+'-'+new Date(y,m,0).getDate();});
 async function operazione(form,target,fn) {
  const b=form.querySelector('button[type=submit],button:not([type])'); if(b?.disabled)return;
  if(b)b.disabled=true;
  try { await fn(dati(form)); } catch(err) { stato(target,err.message,true); } finally { if(b)b.disabled=false; }
 }
 function conferma(testo) { return window.confirm(testo+'\nConfermi il salvataggio?'); }
 let report=null, reportFiltri=null;
 id('aut-report').addEventListener('submit', ev=>{ev.preventDefault();operazione(ev.target,'aut-report-stato',async v=>{
  report=null;reportFiltri=null;id('aut-export').disabled=true;id('aut-export-mensile').disabled=true;id('aut-report-mensile').innerHTML='';id('aut-export-excel').disabled=true;id('aut-report-corpo').innerHTML='';id('aut-report-totali').textContent='';
  stato('aut-report-stato','Caricamento…');
  report=await API.get('/api/report/pasti?'+new URLSearchParams(v));
  reportFiltri={dal:v.dal,al:v.al,plesso:v.plesso_id?nomePlesso(v.plesso_id):(ruolo==='scuola'?nomePlesso(utente.plesso_id):'Tutti'),ordine:v.ordine||'Tutti'};
  const t=report.totali;
  id('aut-report-totali').textContent=`Alunni elaborati: ${t.alunni_confermati} · Personale nelle giornate elaborate: ${t.personale_confermato} · Totale delle giornate complete: ${t.totale_confermato} · Giornate/plessi incompleti: ${t.giorni_non_consolidati}`;
  id('aut-report-corpo').innerHTML=report.righe.map(r=>`<tr><td>${e(r.data)}</td><td>${e(r.plesso_nome)}<br>${e(r.ordine)}</td><td>${e(r.stato)}${r.personale===null?'<br>Personale da configurare':''}</td><td>${valore(r.alunni)}</td><td>${valore(r.personale)}</td><td>${valore(r.totale)}</td><td><details><summary>Dettaglio</summary><p>Personale: ${e(r.fonte_personale)}</p>${r.tipologie_alunni.map(t=>`<p>Alunni · ${e(t.tipologia_nome)}: ${Number(t.pasti)}</p>`).join('')}${r.tipologie_personale.map(t=>`<p>${e(categorie[t.categoria])} · ${e(nomeTipo(t.tipologia_menu_id))}: ${Number(t.quantita)}</p>`).join('')}<p>${e(r.nota)}</p></details></td></tr>`).join('');
  id('aut-report-mensile').innerHTML=report.mensili.map(m=>`<tr><td>${e(m.mese)}<br>${e(m.dal)} – ${e(m.al)}</td><td>${e(m.ordine)}</td><td>${m.alunni}</td><td>${m.personale}</td><td>${m.totale}${m.giornate_incomplete||m.giornate_previste?' (parziale)':''}</td><td>${m.previsti}</td><td>${m.giornate_incomplete} da verificare · ${m.giornate_previste} previste</td></tr>`).join('');
  etichetteTabella(id('vista-riepilogo-pasti'));
  stato('aut-report-stato',`${report.righe.length} giornate/plessi. I dati non disponibili non sono conteggiati come zero.`);id('aut-export').disabled=false;id('aut-export-mensile').disabled=false;id('aut-export-excel').disabled=false;
 });});
 function csvDownload(headers,rows,name) {
  const csv=[headers,...rows].map(row=>row.map(v=>'"'+String(v??'Non disponibile').replace(/^(?:\s*[=+@\-]|[\t\r\n])/,"'$&").replaceAll('"','""')+'"').join(';')).join('\r\n');
  MensaReportExport.download('\ufeff'+csv,'text/csv;charset=utf-8',name);
 }
 id('aut-export').addEventListener('click',()=>{if(report)csvDownload(MensaReportExport.headers,MensaReportExport.rows(report,nomeTipo),'riepilogo-pasti.csv');});
 id('aut-export-mensile').addEventListener('click',()=>{if(report)csvDownload(MensaReportExport.monthlyHeaders,MensaReportExport.monthlyRows(report),'riepilogo-mensile.csv');});
 id('aut-export-excel').addEventListener('click',async()=>{
  if(!report || id('aut-export-excel').disabled)return;
  const current=report, filters=reportFiltri;
  id('aut-export-excel').disabled=true;id('aut-export-excel').textContent='Preparazione Excel…';
  try {await MensaReportExport.excel(current,filters,nomeTipo);}
  catch(err) {avviso(err.message||'Esportazione Excel non riuscita. Riprova.','errore');}
  finally {id('aut-export-excel').disabled=!report;id('aut-export-excel').textContent='Esporta Excel';}
 });
 let personale=null, versionePersonale=0, vistaPersonale='programma';
 function scegliVistaPersonale(vista) {
  vistaPersonale=vista;
  for(const v of ['programma','giorno']){id('aut-vista-'+v).classList.toggle('attiva',v===vista);id('aut-vista-'+v).setAttribute('aria-pressed',String(v===vista));}
  id('aut-programmazione').hidden=!personale||vista!=='programma';id('aut-personale-corpo').hidden=vista!=='giorno';
 }
 id('aut-vista-programma').onclick=()=>scegliVistaPersonale('programma');
 id('aut-vista-giorno').onclick=()=>scegliVistaPersonale('giorno');
 const dataLeggibile = value => new Date(value+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'});
 const somma = righe => righe.reduce((s,r)=>s+Number(r.quantita),0);
 function vociPersonale(righe) {
  const positive=righe.filter(r=>Number(r.quantita)>0);
  return positive.map(r=>`<li><span>${e(categorie[r.categoria])}<small>${e(nomeTipo(r.tipologia_menu_id))}</small></span><strong>${Number(r.quantita)}</strong></li>`).join('');
 }
 function mostraProgrammazione(p) {
  id('aut-programmazione').hidden=false;
  id('aut-programmazione-scuola').textContent=nomePlesso(p.plesso_id);
  id('aut-programmazione-data').textContent=`Regole in vigore al ${dataLeggibile(p.data)}. Le quantità si applicano nei soli giorni di servizio; chiusure e variazioni si verificano nel dettaglio della data.`;
  const attive={};
  for(const r of p.programmazione) if(r.dal<=p.data) attive[`${r.giorno}:${r.categoria}:${r.tipologia_menu_id}`]=r;
  const valori=Object.values(attive);
  id('aut-regole').innerHTML=`<div class="personale-settimana">${giorni.slice(1).map((g,i)=>{
   const righe=valori.filter(r=>Number(r.giorno)===i+1),totale=somma(righe);
   return `<details class="personale-giorno ${righe.length?'':'personale-da-impostare'}"><summary><h3>${g}</h3><p class="personale-numero">${righe.length?totale:'—'} <span>${righe.length?'pasti':'non impostato'}</span></p><span class="personale-sintesi">${Object.entries(categorie).map(([k,label])=>{const q=somma(righe.filter(r=>r.categoria===k));return q?`<span>${e(label)} <b>${q}</b></span>`:'';}).join('')}</span></summary><ul class="personale-voci">${vociPersonale(righe)}</ul>${righe.length&&!totale?'<p class="nota">Zero pasti impostati</p>':''}${!righe.length?'<p class="nota">Nessuna quantità abituale configurata per questo giorno.</p>':''}</details>`;
  }).join('')}</div>${!valori.length?'<p class="personale-avviso">Nessuna programmazione abituale in vigore a questa data. I giorni non impostati non equivalgono a zero pasti.</p>':''}`;
  const future={};for(const r of p.programmazione.filter(r=>r.dal>p.data)) (future[r.dal]??=[]).push(r);
  id('aut-programmazione-futura').innerHTML=Object.keys(future).length?`<h3>Modifiche successive alla data scelta</h3>${Object.entries(future).map(([dal,righe])=>{
   const gruppi={};for(const r of righe){const k=`${r.categoria}:${r.tipologia_menu_id}:${r.quantita}`;(gruppi[k]??={...r,giorni:[]}).giorni.push(giorni[Number(r.giorno)]);}
   return `<div class="personale-futura"><h4>Dal ${dataLeggibile(dal)}</h4><ul>${Object.values(gruppi).map(r=>`<li><strong>${Number(r.quantita)} ${e(categorie[r.categoria])}</strong> · ${e(nomeTipo(r.tipologia_menu_id))}<br><span>${r.giorni.join(', ')}</span></li>`).join('')}</ul></div>`;
  }).join('')}`:'<p class="nota personale-futuro-vuoto">Nessuna modifica successiva programmata.</p>';
  if(ufficio){id('aut-regola').elements.plesso_id.value=p.plesso_id;id('aut-regola').elements.dal.min=oggi();id('aut-regola').elements.dal.value=p.data<oggi()?oggi():p.data;}
 }
 async function caricaPersonale(v) {
  const versione=++versionePersonale;
  personale=null;id('aut-personale-corpo').innerHTML='';id('aut-programmazione').hidden=true;
  if(ufficio)id('aut-editor-regola').open=false;
  if(!v.plesso_id||!v.data){stato('aut-personale-stato','Seleziona scuola e data per consultare i pasti.');return false;}
  stato('aut-personale-stato','Caricamento programmazione…');
  let p;
  try{p=await API.get(`/api/personale/plessi/${v.plesso_id}?data=${encodeURIComponent(v.data)}`);}
  catch(err){if(versione===versionePersonale)stato('aut-personale-stato',err.message,true);return false;}
  if(versione!==versionePersonale)return false;
  personale=p;mostraProgrammazione(p);scegliVistaPersonale(vistaPersonale);
  const puo=p.modificabile;
  const motiviServizio={
   giorno_senza_servizio:'Il calendario della scuola non prevede la mensa in questo giorno della settimana.',
   fine_settimana:'Il calendario non prevede la mensa nel fine settimana.',
   festivita_nazionale:'La data coincide con una festività nazionale.',
   chiusura_programmata:'Per questa data è stata registrata una chiusura della mensa.',
   servizio_non_avviato:'La data precede l’inizio del servizio mensa.',
   servizio_terminato:'La data è successiva alla fine del servizio mensa oppure rientra nel periodo di sospensione.',
   calendario_non_configurato:'Il calendario della mensa non è ancora configurato per questa scuola.',
   plesso_non_attivo:'Questa scuola risulta disattivata nel sistema.'
  };
  const mensaNonPrevista=p.fonte==='Fuori servizio';
  const limiteModifica=ruolo==='scuola'&&p.data<oggi()?'Per correggere i pasti di una data passata interviene il Comune.':ruolo==='catering'?'Con il profilo Catering puoi consultare i pasti; le modifiche spettano alla Scuola e al Comune.':'';
  stato('aut-personale-stato',mensaNonPrevista?`Mensa non prevista per il ${dataLeggibile(p.data)}. ${motiviServizio[p.motivo_servizio]||'Il calendario non prevede il servizio mensa in questa data.'} Non si possono inserire nuovi pasti del personale per questa giornata. ${limiteModifica}`.trim():!puo?limiteModifica||'La modifica dei pasti non è disponibile per questa data. Rivolgiti al Comune.':'');
  const etichetteFonte={'Fuori servizio':'Mensa non prevista','Da programmazione':'Quantità abituali','Da configurare':'Quantità da impostare','Variazione giornaliera':'Variazione per questa data','Registrato':'Quantità già registrate'};
  const righe=[];for(const [categoria,label] of Object.entries(categorie))for(const t of tipi)righe.push({categoria,label,tid:Number(t.id),nome:t.nome,q:p.righe.find(r=>r.categoria===categoria&&Number(r.tipologia_menu_id)===Number(t.id))?.quantita||0});
  const spiegazione=p.fonte==='Variazione giornaliera'?'Questa data ha quantità diverse dalla programmazione abituale. Le altre giornate non cambiano.':p.fonte==='Fuori servizio'?'Per questa giornata non sono previsti pasti del personale. La programmazione abituale resta invariata.':!p.disponibile?'Mancano le quantità per questa data. Il Comune può impostare la programmazione o registrare i pasti effettivi.':p.fonte==='Registrato'?'Quantità registrate per questa data.':'Si applicano automaticamente le quantità programmate. Non occorre confermarle ogni giorno.';
  const categoriePresenti=Object.entries(categorie).filter(([k])=>p.righe.some(r=>r.categoria===k&&Number(r.quantita)>0));
  id('aut-personale-corpo').innerHTML=`<section class="personale-sezione personale-data"><div class="personale-sezione-testa"><div><p class="personale-sopratitolo">Una sola giornata</p><h2>Pasti del ${dataLeggibile(p.data)}</h2></div><span class="personale-fonte ${mensaNonPrevista?'personale-fonte-inattiva':p.fonte==='Variazione giornaliera'?'personale-fonte-variazione':''}">${e(etichetteFonte[p.fonte]||p.fonte)}</span></div>
   <p>${spiegazione}</p><form id="aut-giorno">
   <div class="personale-confronto"><div><span>Totale per questa data</span><strong>${p.disponibile?somma(p.righe):'—'} <small>${p.disponibile?'pasti':'da impostare'}</small></strong></div><div><span>Quantità abituale · ${giorni[new Date(p.data+'T12:00:00').getDay()||7]}</span><strong>${p.programmate.length?somma(p.programmate):'—'} <small>${p.programmate.length?'pasti':'non impostata'}</small></strong></div></div>
   <div class="personale-categorie">${categoriePresenti.map(([k,label])=>`<article><h3>${e(label)} <span>${somma(p.righe.filter(r=>r.categoria===k))}</span></h3><ul class="personale-voci">${p.righe.filter(r=>r.categoria===k&&Number(r.quantita)>0).map(r=>`<li><span>${e(nomeTipo(r.tipologia_menu_id))}</span><strong>${Number(r.quantita)}</strong></li>`).join('')}</ul></article>`).join('')}</div>
   ${puo?`<details id="aut-editor-giorno" class="personale-editor"><summary>Modifica solo questa data</summary><p>Indica il numero totale di pasti necessari, non la differenza. Per 5 insegnanti di cui uno vegetariano: 4 standard e 1 vegetariano.</p><div class="personale-campi">${Object.entries(categorie).map(([k,label])=>`<fieldset><legend>${e(label)}</legend>${righe.filter(r=>r.categoria===k).map(r=>`<label>${e(r.nome)}<input aria-label="${e(r.label+' '+r.nome)}" type="number" min="0" max="1000" name="${r.categoria}:${r.tid}" value="${r.q}" required></label>`).join('')}</fieldset>`).join('')}</div>
    <p class="personale-totale-bozza">Totale da salvare: <output id="aut-totale-bozza">${somma(p.righe)}</output> pasti</p><label>Motivazione <input name="motivazione" maxlength="255" required placeholder="Es. un insegnante assente"></label><div class="personale-salvataggio"><button class="btn btn-primary">Salva pasti del giorno</button>${p.ripristinabile?'<button type="button" id="aut-ripristina" class="btn btn-outline-primary">Ripristina programmazione</button>':''}</div><p class="nota">La modifica vale solo per il ${dataLeggibile(p.data)}.</p></details>`:''}
   </form></section>`;
  if(puo){
   id('aut-giorno').addEventListener('input',()=>{id('aut-totale-bozza').textContent=[...id('aut-giorno').querySelectorAll('input[type=number]')].reduce((s,x)=>s+(Number(x.value)||0),0);});
   id('aut-giorno').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-personale-stato',async v=>{
    const righe=Object.entries(v).filter(([k])=>k.includes(':')).map(([k,q])=>({categoria:k.split(':')[0],tipologia_menu_id:Number(k.split(':')[1]),quantita:Number(q)})).filter(r=>r.quantita>0);
    if(!conferma(`${nomePlesso(p.plesso_id)} · ${p.data}\nTotale personale: ${somma(righe)}\nMotivo: ${v.motivazione}`))return;
    await API.put(`/api/personale/plessi/${p.plesso_id}/giorno`,{data:p.data,righe,precedente:p.righe,motivazione:v.motivazione,conferma:true});
    const riletto=await caricaPersonale(dati(id('aut-personale-filtro')));stato('aut-personale-stato','Pasti del personale salvati. La programmazione abituale non cambia.'+(!riletto?' Ricarica la vista per verificare i dati.':''));
   });});
  }
  if(p.ripristinabile)id('aut-ripristina').addEventListener('click',()=>{
   const form=id('aut-giorno');if(!form.reportValidity())return;
   operazione(form,'aut-personale-stato',async v=>{
    if(!conferma(`${nomePlesso(p.plesso_id)} · ${p.data}\nRimuovere la variazione di questa data e usare le quantità programmate?`))return;
    await API.put(`/api/personale/plessi/${p.plesso_id}/giorno`,{data:p.data,ripristina:true,precedente:p.righe,motivazione:v.motivazione,conferma:true});
    const riletto=await caricaPersonale(dati(id('aut-personale-filtro')));stato('aut-personale-stato','Programmazione ripristinata per la data scelta.'+(!riletto?' Ricarica la vista per verificare i dati.':''));
   });
  });
  return true;
 }
 id('aut-personale-filtro').addEventListener('submit',ev=>{ev.preventDefault();caricaPersonale(dati(ev.target));});
 id('aut-personale-filtro').addEventListener('change',()=>caricaPersonale(dati(id('aut-personale-filtro'))));
 document.querySelectorAll('[data-vista="personale"]').forEach(b=>b.addEventListener('click',()=>{if(!personale)caricaPersonale(dati(id('aut-personale-filtro')));}));
 if(ufficio) {
  id('aut-regola').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-regola-stato',async v=>{
   const selezionati=new FormData(ev.target).getAll('giorni');if(!selezionati.length)throw new Error('Seleziona almeno un giorno.');
   if(!conferma(`${nomePlesso(v.plesso_id)}\nDal ${v.dal}, ogni ${selezionati.map(g=>giorni[g]).join(', ')}: ${v.quantita} ${categorie[v.categoria]}, menù ${nomeTipo(v.tipologia_menu_id)}\nLa regola si ripete tutte le settimane.`))return;
   await API.post(`/api/personale/plessi/${v.plesso_id}/regole`,{...v,giorni:selezionati,conferma:true});
   const riletto=await caricaPersonale(dati(id('aut-personale-filtro')));
   stato('aut-personale-stato',`Ricorrenza salvata per ${nomePlesso(v.plesso_id)} dal ${dataLeggibile(v.dal)}. ${riletto?'Programmazione aggiornata.':'Ricarica la vista per verificare i dati.'}`);
   stato('aut-regola-stato','Ricorrenza salvata.');
  });});
  id('aut-servizio-filtro').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-servizio-stato',caricaServizio);});
 }
 async function caricaServizio(v) {
  id('aut-servizio-corpo').innerHTML=''; const s=await API.get(`/api/servizio/plessi/${v.plesso_id}`);const pid=s.plesso.id;
  stato('aut-servizio-stato',`${s.plesso.nome} · ID ${pid}`);
  id('aut-servizio-corpo').innerHTML=`<form id="aut-config"><h2>Impostazioni del servizio</h2><label>Ordine scolastico <select name="ordine_scolastico" required><option value="">Seleziona</option>${['infanzia','primaria','secondaria'].map(o=>`<option value="${o}" ${s.servizio.ordine_scolastico===o?'selected':''}>${o}</option>`).join('')}</select></label><label>Fido concesso (buoni) <input type="number" min="0" max="1000" name="fido_buoni" value="${s.plesso.fido_buoni}" required></label><p>Inserisci 20 per la soglia -20. Il fido non impedisce l’erogazione dei pasti.</p><label>Primo giorno di sospensione <input type="date" name="primo_giorno_sospensione" value="${s.servizio.primo_giorno_sospensione||''}"></label><p>Da questa data non si generano nuovi pasti, anche in presenza di aperture straordinarie. Storico e attività amministrative restano accessibili.</p><label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva impostazioni</button></form><hr><h2>Chiusure straordinarie</h2><form id="aut-chiusura"><label>Dal <input name="data_inizio" type="date" required></label><label>Al (compreso) <input name="data_fine" type="date" required></label><label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Inserisci chiusura</button></form><h3>Periodi registrati</h3>${s.calendario.eccezioni.map(x=>`<p>${e(x.giorno)} – ${e(x.giorno_fine||x.giorno)}: ${Number(x.servizio_attivo)?'Apertura':'Chiusura'} · ${e(x.motivazione)} ${!Number(x.servizio_attivo)&&x.giorno>oggi()?`<button type="button" class="btn btn-outline-primary" data-chiusura="${x.id}">Modifica / elimina</button>`:''}</p>`).join('')||'<p>Nessun periodo.</p>'}<div id="aut-chiusura-editor"></div>`;
  id('aut-servizio-corpo').querySelectorAll('[data-chiusura]').forEach(button=>button.addEventListener('click',()=>{
   const x=s.calendario.eccezioni.find(x=>Number(x.id)===Number(button.dataset.chiusura));
   const precedente={data_inizio:x.giorno,data_fine:x.giorno_fine||x.giorno,motivazione:x.motivazione};
   id('aut-chiusura-editor').innerHTML=`<form id="aut-modifica-chiusura"><h2>Modifica chiusura · ${e(s.plesso.nome)} · ID ${x.id}</h2><label>Dal <input type="date" name="data_inizio" value="${x.giorno}" required></label><label>Al (compreso) <input type="date" name="data_fine" value="${precedente.data_fine}" required></label><label>Motivazione della modifica o eliminazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva chiusura</button><button type="button" id="aut-elimina-chiusura" class="btn btn-outline-primary">Elimina chiusura</button><button type="button" id="aut-annulla-chiusura" class="btn btn-outline-primary">Annulla</button></form>`;
   const form=id('aut-modifica-chiusura');form.scrollIntoView({block:'nearest'});
   const salva=async(v,elimina=false)=>{
    if(!conferma(`${s.plesso.nome} · Chiusura ID ${x.id}\n${precedente.data_inizio} – ${precedente.data_fine}\n${elimina?'Eliminare la chiusura? Tornerà valido il calendario ordinario.':`Nuovo periodo: ${v.data_inizio} – ${v.data_fine}`}\n${v.motivazione}`))return;
    await (elimina?API.del:API.put)(`/api/servizio/plessi/${pid}/chiusure/${x.id}`,{...v,precedente,conferma:true});
    await caricaServizio({plesso_id:pid});stato('aut-servizio-stato',elimina?'Chiusura eliminata. Calendario riletto.':'Chiusura modificata e riletta.');
   };
   form.addEventListener('submit',ev=>{ev.preventDefault();operazione(form,'aut-servizio-stato',v=>salva(v));});
   id('aut-elimina-chiusura').addEventListener('click',()=>{if(form.reportValidity())operazione(form,'aut-servizio-stato',v=>salva(v,true));});
   id('aut-annulla-chiusura').addEventListener('click',()=>id('aut-chiusura-editor').innerHTML='');
  }));
  id('aut-config').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-servizio-stato',async v=>{
   if(!conferma(`${s.plesso.nome} · ID ${pid}\nOrdine: ${v.ordine_scolastico}\nFido: ${s.plesso.fido_buoni} → ${v.fido_buoni}\nSospensione: ${s.servizio.primo_giorno_sospensione||'nessuna'} → ${v.primo_giorno_sospensione||'nessuna'}\nMotivo: ${v.motivazione}`))return;
   await API.put(`/api/servizio/plessi/${pid}`,{...v,conferma:true});await caricaServizio({plesso_id:pid});stato('aut-servizio-stato','Impostazioni salvate e rilette.');
  });});
  id('aut-chiusura').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-servizio-stato',async v=>{
   if(!conferma(`${s.plesso.nome} · ID ${pid}\nChiusura dal ${v.data_inizio} al ${v.data_fine}, estremi inclusi.\n${v.motivazione}`))return;
   await API.post(`/api/servizio/plessi/${pid}/chiusure`,{...v,conferma:true});await caricaServizio({plesso_id:pid});stato('aut-servizio-stato','Chiusura registrata e riletta.');
  });});
 }
})().catch(err=>avviso(err.message,'errore'));
