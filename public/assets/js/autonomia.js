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
 const etichetteTabella = root => { const heads=[...root.querySelectorAll('thead th')].map(x=>x.textContent); root.querySelectorAll('tbody tr').forEach(row=>[...row.children].forEach((cell,i)=>cell.dataset.label=heads[i]||'')); };
 const nomePlesso = n => plessi.find(p=>Number(p.id)===Number(n))?.nome || 'Plesso '+n;
 document.querySelectorAll('.aut-plessi').forEach(s=>s.insertAdjacentHTML('beforeend',plessi.map(p=>`<option value="${p.id}">${e(p.nome)} · ID ${p.id}</option>`).join('')));
 document.querySelectorAll('.aut-tipologie').forEach(s=>s.innerHTML=tipi.map(t=>`<option value="${t.id}">${e(t.nome)}</option>`).join(''));
 id('aut-report').elements.dal.value=oggi().slice(0,7)+'-01'; id('aut-report').elements.al.value=oggi();
 id('aut-personale-filtro').elements.data.value=oggi();
 async function operazione(form,target,fn) {
  const b=form.querySelector('button[type=submit],button:not([type])'); if(b?.disabled)return;
  if(b)b.disabled=true;
  try { await fn(dati(form)); } catch(err) { stato(target,err.message,true); } finally { if(b)b.disabled=false; }
 }
 function conferma(testo) { return window.confirm(testo+'\nConfermi il salvataggio?'); }
 let report=null;
 id('aut-report').addEventListener('submit', ev=>{ev.preventDefault();operazione(ev.target,'aut-report-stato',async v=>{
  report=null;id('aut-export').disabled=true;id('aut-report-corpo').innerHTML='';id('aut-report-totali').textContent='';
  stato('aut-report-stato','Caricamento…');
  report=await API.get('/api/report/pasti?'+new URLSearchParams(v));
  const t=report.totali;
  id('aut-report-totali').textContent=`Alunni confermati: ${t.alunni_confermati} · Personale confermato: ${t.personale_confermato} · Totale delle giornate complete: ${t.totale_confermato} · Giornate/plessi incompleti: ${t.giorni_non_consolidati}`;
  id('aut-report-corpo').innerHTML=report.righe.map(r=>`<tr><td>${e(r.data)}</td><td>${e(r.plesso_nome)}<br>${e(r.ordine)}</td><td>${e(r.stato)}${r.stato==='Confermato'&&!r.personale_consolidato?'<br>Personale non consolidato':''}</td><td>${valore(r.alunni)}</td><td>${valore(r.personale)}</td><td>${valore(r.totale)}</td><td><details><summary>Dettaglio</summary>${r.tipologie_alunni.map(t=>`<p>Alunni · ${e(t.tipologia_nome)}: ${Number(t.pasti)}</p>`).join('')}${r.tipologie_personale.map(t=>`<p>${e(categorie[t.categoria])} · ${e(nomeTipo(t.tipologia_menu_id))}: ${Number(t.quantita)}</p>`).join('')}<p>${e(r.nota)}</p></details></td></tr>`).join('');
  etichetteTabella(id('vista-riepilogo-pasti'));
  stato('aut-report-stato',`${report.righe.length} giornate/plessi. I dati non disponibili non sono conteggiati come zero.`);id('aut-export').disabled=false;
 });});
 id('aut-export').addEventListener('click',()=>{
  if(!report)return;
  const csv=[['Data','Plesso','Ordine','Stato','Alunni','Personale','Totale','Dettaglio tipologie','Nota'],...report.righe.map(r=>[r.data,r.plesso_nome,r.ordine,r.stato,r.alunni,r.personale,r.totale,[...r.tipologie_alunni.map(t=>`Alunni ${t.tipologia_nome}: ${t.pasti}`),...r.tipologie_personale.map(t=>`${categorie[t.categoria]} ${nomeTipo(t.tipologia_menu_id)}: ${t.quantita}`)].join(' | '),r.nota])].map(row=>row.map(v=>'"'+String(v??'Non disponibile').replace(/^(?:\s*[=+@\-]|[\t\r\n])/,"'$&").replaceAll('"','""')+'"').join(';')).join('\r\n');
  const url=URL.createObjectURL(new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'}));const a=document.createElement('a');a.href=url;a.download='riepilogo-pasti.csv';a.click();URL.revokeObjectURL(url);
 });
 let personale=null;
 async function caricaPersonale(v) {
  personale=null;id('aut-personale-corpo').innerHTML='';
  const p=await API.get(`/api/personale/plessi/${v.plesso_id}?data=${encodeURIComponent(v.data)}`);personale=p;
  const puo=p.modificabile;
  stato('aut-personale-stato',`${nomePlesso(p.plesso_id)} · ${p.data} · ${p.attivo?'Servizio previsto':'Fuori servizio'} · ${p.consolidato?'Consolidato':'Non consolidato'} · Cutoff ${p.cutoff}${!puo?' · Sola lettura':''}`);
  const righe=[];for(const [categoria,label] of Object.entries(categorie))for(const t of tipi)righe.push({categoria,label,tid:Number(t.id),nome:t.nome,q:p.righe.find(r=>r.categoria===categoria&&Number(r.tipologia_menu_id)===Number(t.id))?.quantita||0});
  id('aut-personale-corpo').innerHTML=`<form id="aut-giorno"><div class="tabella-scroll"><table class="table"><thead><tr><th>Categoria</th><th>Menù</th><th>Quantità</th></tr></thead><tbody>${righe.map(r=>`<tr><td>${e(r.label)}</td><td>${e(r.nome)}</td><td>${puo?`<input aria-label="${e(r.label+' '+r.nome)}" type="number" min="0" max="1000" name="${r.categoria}:${r.tid}" value="${r.q}" required>`:r.q}</td></tr>`).join('')}</tbody></table></div><p>Totale: ${p.righe.reduce((s,r)=>s+Number(r.quantita),0)}</p>${puo?'<label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva pasti del giorno</button>':''}</form>`;
  etichetteTabella(id('aut-personale-corpo'));
  if(ufficio)id('aut-regole').innerHTML='<h3>Ricorrenze registrate</h3>'+p.regole.map(r=>`<p>Dal ${e(r.dal)} · giorno ${r.giorno} · ${e(categorie[r.categoria])} · ${e(nomeTipo(r.tipologia_menu_id))}: ${r.quantita}</p>`).join('');
  if(puo)id('aut-giorno').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-personale-stato',async v=>{
   const righe=Object.entries(v).filter(([k])=>k.includes(':')).map(([k,q])=>({categoria:k.split(':')[0],tipologia_menu_id:Number(k.split(':')[1]),quantita:Number(q)})).filter(r=>r.quantita>0);
   if(!conferma(`${nomePlesso(p.plesso_id)} · ${p.data}\nTotale personale: ${righe.reduce((s,r)=>s+r.quantita,0)}\nMotivo: ${v.motivazione}`))return;
   await API.put(`/api/personale/plessi/${p.plesso_id}/giorno`,{data:p.data,righe,precedente:p.righe,motivazione:v.motivazione,conferma:true});
   await caricaPersonale({plesso_id:p.plesso_id,data:p.data});stato('aut-personale-stato','Pasti del personale salvati. Nessun buono delle famiglie modificato.');
  });});
 }
 id('aut-personale-filtro').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-personale-stato',caricaPersonale);});
 if(ufficio) {
  id('aut-regola').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-regola-stato',async v=>{
   if(!conferma(`${nomePlesso(v.plesso_id)}\nDal ${v.dal}, giorno ${v.giorno}: ${v.quantita} ${categorie[v.categoria]}, menù ${nomeTipo(v.tipologia_menu_id)}`))return;
   await API.post(`/api/personale/plessi/${v.plesso_id}/regole`,{...v,conferma:true});stato('aut-regola-stato','Ricorrenza salvata. Premi Mostra pasti personale per verificare una data successiva alla decorrenza.');
  });});
  id('aut-servizio-filtro').addEventListener('submit',ev=>{ev.preventDefault();operazione(ev.target,'aut-servizio-stato',caricaServizio);});
 }
 async function caricaServizio(v) {
  id('aut-servizio-corpo').innerHTML=''; const s=await API.get(`/api/servizio/plessi/${v.plesso_id}`);const pid=s.plesso.id;
  stato('aut-servizio-stato',`${s.plesso.nome} · ID ${pid}`);
  id('aut-servizio-corpo').innerHTML=`<form id="aut-config"><h2>Impostazioni del servizio</h2><label>Ordine scolastico <select name="ordine_scolastico" required><option value="">Seleziona</option>${['infanzia','primaria','secondaria'].map(o=>`<option value="${o}" ${s.servizio.ordine_scolastico===o?'selected':''}>${o}</option>`).join('')}</select></label><label>Fido concesso (buoni) <input type="number" min="0" max="1000" name="fido_buoni" value="${s.plesso.fido_buoni}" required></label><p>Inserisci 20 per la soglia -20. Il fido non impedisce l’erogazione dei pasti.</p><label>Primo giorno di sospensione <input type="date" name="primo_giorno_sospensione" value="${s.servizio.primo_giorno_sospensione||''}"></label><p>Da questa data non si generano nuovi pasti, anche in presenza di aperture straordinarie. Storico e attività amministrative restano accessibili.</p><label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Salva impostazioni</button></form><hr><h2>Chiusure straordinarie</h2><form id="aut-chiusura"><label>Dal <input name="data_inizio" type="date" required></label><label>Al (compreso) <input name="data_fine" type="date" required></label><label>Motivazione <input name="motivazione" maxlength="255" required></label><button class="btn btn-primary">Inserisci chiusura</button></form><h3>Periodi registrati</h3>${s.calendario.eccezioni.map(x=>`<p>${e(x.giorno)} – ${e(x.giorno_fine||x.giorno)}: ${Number(x.servizio_attivo)?'Apertura':'Chiusura'} · ${e(x.motivazione)}</p>`).join('')||'<p>Nessun periodo.</p>'}`;
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
