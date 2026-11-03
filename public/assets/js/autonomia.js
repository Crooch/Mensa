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
 let personale=null;
 async function caricaPersonale(v) {
  personale=null;id('aut-personale-corpo').innerHTML='';
  const p=await API.get(`/api/personale/plessi/${v.plesso_id}?data=${encodeURIComponent(v.data)}`);personale=p;
  const puo=p.modificabile;
  stato('aut-personale-stato',`${nomePlesso(p.plesso_id)} · ${p.data} · ${p.attivo?'Servizio previsto':'Fuori servizio'} · ${p.fonte}${!puo?' · Sola lettura':''}`);
  const righe=[];for(const [categoria,label] of Object.entries(categorie))for(const t of tipi)righe.push({categoria,label,tid:Number(t.id),nome:t.nome,q:p.righe.find(r=>r.categoria===categoria&&Number(r.tipologia_menu_id)===Number(t.id))?.quantita||0});
  id('aut-personale-corpo').innerHTML=`<form id="aut-giorno"><div class="tabella-scroll"><table class="table"><thead><tr><th>Categoria</th><th>Menù</th><th>Quantità</th></tr></thead><tbody>${righe.map(r=>`<tr><td>${e(r.label)}</td><td>${e(r.nome)}</td><td>${puo?`<input aria-label="${e(r.label+' '+r.nome)}" type="number" min="0" max="1000" name="${r.categoria}:${r.tid}" value="${r.q}" required>`:r.q}</td></tr>`).join('')}</tbody></table></div><p>Totale: ${p.disponibile?p.righe.reduce((s,r)=>s+Number(r.quantita),0):'Non disponibile: impostare le regole o registrare le quantità effettive.'}</p>${puo?'<label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva pasti del giorno</button>':''}${p.ripristinabile?'<button type="button" id="aut-ripristina" class="btn btn-outline-primary">Ripristina programmazione</button>':''}</form>`;
  etichetteTabella(id('aut-personale-corpo'));
  if(ufficio) {
   const attive={};p.regole.filter(r=>r.dal<=p.data).forEach(r=>{const key=r.categoria+':'+r.tipologia_menu_id;attive[key]??={categoria:r.categoria,tipo:r.tipologia_menu_id,q:{}};attive[key].q[r.giorno]=r.quantita;});
   id('aut-regole').innerHTML=`<h3>Settimana abituale alla data ${e(p.data)}</h3><p>Quantità ripetute ogni settimana nei soli giorni di servizio. Le variazioni di una singola data non compaiono in questa tabella.</p><div class="tabella-scroll"><table class="table"><thead><tr><th>Categoria / menù</th>${giorni.slice(1).map(g=>`<th>${g}</th>`).join('')}</tr></thead><tbody>${Object.values(attive).map(r=>`<tr><td>${e(categorie[r.categoria])} · ${e(nomeTipo(r.tipo))}</td>${giorni.slice(1).map((_,i)=>`<td>${r.q[i+1]??'—'}</td>`).join('')}</tr>`).join('')}</tbody></table></div><details><summary>Storico e modifiche programmate</summary>${p.regole.map(r=>`<p>Dal ${e(r.dal)} · ${giorni[r.giorno]} · ${e(categorie[r.categoria])} · ${e(nomeTipo(r.tipologia_menu_id))}: ${r.quantita}</p>`).join('')||'<p>Nessuna regola.</p>'}</details>`;
   etichetteTabella(id('aut-regole'));
  }
  if(p.ripristinabile)id('aut-ripristina').addEventListener('click',async()=>{
   const form=id('aut-giorno');if(!form.reportValidity())return;
   if(!conferma(`${nomePlesso(p.plesso_id)} · ${p.data}\nRimuovere la variazione di questa data e usare le quantità programmate?`))return;
   try {await API.put(`/api/personale/plessi/${p.plesso_id}/giorno`,{data:p.data,ripristina:true,precedente:p.righe,motivazione:form.elements.motivazione.value,conferma:true});await caricaPersonale({plesso_id:p.plesso_id,data:p.data});stato('aut-personale-stato','Programmazione ripristinata per la data scelta.');}catch(err){stato('aut-personale-stato',err.message,true);}
  });
  if(puo)id('aut-giorno').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-personale-stato',async v=>{
   const righe=Object.entries(v).filter(([k])=>k.includes(':')).map(([k,q])=>({categoria:k.split(':')[0],tipologia_menu_id:Number(k.split(':')[1]),quantita:Number(q)})).filter(r=>r.quantita>0);
   if(!conferma(`${nomePlesso(p.plesso_id)} · ${p.data}\nTotale personale: ${righe.reduce((s,r)=>s+r.quantita,0)}\nMotivo: ${v.motivazione}`))return;
   await API.put(`/api/personale/plessi/${p.plesso_id}/giorno`,{data:p.data,righe,precedente:p.righe,motivazione:v.motivazione,conferma:true});
   await caricaPersonale({plesso_id:p.plesso_id,data:p.data});stato('aut-personale-stato','Pasti del personale salvati. Nessun buono delle famiglie modificato.');
  });});
 }
 id('aut-personale-filtro').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-personale-stato',caricaPersonale);});
 if(ufficio) {
  id('aut-regola').elements.dal.min=oggi();id('aut-regola').elements.dal.value=oggi();
  id('aut-regola').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-regola-stato',async v=>{
   const selezionati=new FormData(ev.target).getAll('giorni');if(!selezionati.length)throw new Error('Seleziona almeno un giorno.');
   if(!conferma(`${nomePlesso(v.plesso_id)}\nDal ${v.dal}, ogni ${selezionati.map(g=>giorni[g]).join(', ')}: ${v.quantita} ${categorie[v.categoria]}, menù ${nomeTipo(v.tipologia_menu_id)}\nLa regola si ripete tutte le settimane.`))return;
   await API.post(`/api/personale/plessi/${v.plesso_id}/regole`,{...v,giorni:selezionati,conferma:true});stato('aut-regola-stato','Ricorrenza salvata. Premi Mostra pasti personale per verificare una data successiva alla decorrenza.');
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
