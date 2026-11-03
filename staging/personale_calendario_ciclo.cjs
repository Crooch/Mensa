/* Full lifecycle through real forms; every checkpoint cross-checks API, SQL and report.
 * Fixture creation only uses APIs. Domain mutations under test use the browser UI.
 */
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),cp=require('node:child_process'),crypto=require('node:crypto');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18091';
const db=process.env.MENSA_TICKET2_DB_CONTAINER||'mensa-ticket2-ciclo-database-1';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));assert(/^mensa-ticket2-(?!final-)[a-z0-9-]*database-1$/.test(db));
const sql=q=>cp.execFileSync('docker',['exec','-i',db,'sh','-c','test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'],{input:q,encoding:'utf8'}).trim();
const hash=q=>crypto.createHash('sha256').update(sql(q)).digest('hex');
const financial=()=>Object.fromEntries(['pool_crediti','movimenti_crediti','presenze_giornaliere','assegnazioni_classe','log_solleciti_inviati'].map(t=>[t,hash('SELECT * FROM '+t+' ORDER BY 1')]));
// Four weeks in February avoid national holidays without opening fixture exceptions.
const today=new Date().toLocaleDateString('en-CA',{timeZone:'Europe/Rome'}),start=new Date(Date.UTC(Number(today.slice(0,4))+1,1,1,12));start.setUTCDate(start.getUTCDate()+(8-start.getUTCDay())%7);
const D=n=>{const d=new Date(start);d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10);};
const canonical=rows=>rows.filter(r=>Number(r.quantita)>0).map(r=>[r.categoria,Number(r.tipologia_menu_id),Number(r.quantita)]).sort((a,b)=>JSON.stringify(a).localeCompare(JSON.stringify(b)));
const log=[];function pass(s){console.log('PASS '+s);log.push(s);}
(async()=>{
 const b=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 const before=financial();
 try{
 for(const width of [1366,375]){
  const p=await b.newPage({viewport:{width,height:950}}),errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',d=>d.accept());
  await p.goto(base+'/login');await p.locator('[name=username]').fill('admin.staging');await p.locator('[name=password]').fill('Collaudo2026!');await p.locator('button[type=submit]').click();await p.waitForURL('**/admin');
  const pid=Number(await p.evaluate(async width=>(await API.post('/api/admin/plessi',{nome:'ZZ Ciclo '+width+' '+Date.now(),cutoff_orario:'00:00:00'})).id,width));assert(Number.isInteger(pid)&&pid>0);
  const root='/api/personale/plessi/'+pid;
  const catering=await b.newPage({viewport:{width,height:950}});catering.on('pageerror',e=>errors.push(e.message));
  await catering.goto(base+'/login');await catering.locator('[name=username]').fill('catering.staging');await catering.locator('[name=password]').fill('Collaudo2026!');await catering.locator('button[type=submit]').click();await catering.waitForURL('**/catering');

  await p.reload();await p.locator('#calendario-plesso option[value="'+pid+'"]').waitFor({state:'attached'});
  async function get(path){return p.evaluate(path=>API.get(path),path);}
  async function submit(selector,url,method='POST'){
   const response=p.waitForResponse(r=>r.url().endsWith(url)&&r.request().method()===method);response.catch(()=>{});
   await p.locator(selector).click();const r=await response,body=await r.json();assert.equal(r.status(),200,JSON.stringify(body));return body.dati;
  }
  async function selectStaff(n){
   await p.locator('[data-vista=personale]').click();await p.locator('#aut-personale-filtro [name=plesso_id]').selectOption(String(pid));await p.locator('#aut-personale-filtro [name=data]').fill(D(n));
   const read=p.waitForResponse(r=>r.url().endsWith(root+'?data='+D(n)));await p.locator('#aut-personale-filtro button').click();await read;
   await p.waitForFunction(({d,id})=>document.querySelector('#aut-regola [name=plesso_id]')?.value===String(id)&&document.querySelector('#aut-programmazione-data').textContent.includes(new Date(d+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'})),{d:D(n),id:pid});
   await p.locator('#aut-vista-giorno').click();await p.locator('#aut-giorno').waitFor();
  }
  async function check(n,qty,baseQty,active,label,refresh=true){
   if(refresh)await selectStaff(n);else{await p.locator('[data-vista=personale]').click();await p.locator('#aut-vista-giorno').click();}
   await p.waitForFunction(q=>Number(document.querySelector('.personale-confronto strong')?.textContent.match(/\d+/)?.[0])===q,qty,{timeout:8000});
   const v=await get(root+'?data='+D(n));assert.equal(v.attivo,active,label);assert.equal(v.righe.reduce((s,r)=>s+Number(r.quantita),0),qty,label);assert.equal(v.programmate.reduce((s,r)=>s+Number(r.quantita),0),baseQty,label);
   assert.equal(await p.locator('#aut-editor-giorno').count(),active?1:0,label);
   const calendar=await get(`/api/admin/calendari/${pid}/verifica?data=${D(n)}`);assert.equal(calendar.attivo,active,label);assert.equal(calendar.motivo,v.motivo_servizio,label);
   const all=await get(`/api/admin/calendari/riepilogo?data=${D(n)}`);const school=all.scuole.find(s=>Number(s.plesso_id)===pid);assert.equal(school.attivo,active,label);assert.equal(school.motivo,v.motivo_servizio,label);
   const report=await get(`/api/report/pasti?dal=${D(n)}&al=${D(n)}&plesso_id=${pid}`);assert.equal(report.righe.length,1);assert.equal(report.righe[0].personale,qty,label);assert.deepEqual(canonical(report.righe[0].tipologie_personale),canonical(v.righe),label);
   // Same mutations must reach the catering's operational screen, not only reports.
   const cr=await catering.evaluate(path=>API.get(path),`/api/catering/report?data=${D(n)}&plesso_id=${pid}`);
   assert.equal(cr.totale_personale,qty,label+' catering staff');assert.equal(cr.totale_alunni,0,label+' no synthetic pupils');assert.equal(cr.totale_pasti,qty,label+' no double counting');
   assert.equal(report.righe[0].alunni,0,label+' summary must not mistake staff for pupils');
   if(active){assert.equal(cr.plessi.length,1);assert.deepEqual(canonical(cr.plessi[0].personale.map(r=>({categoria:r.categoria,tipologia_menu_id:r.tipologia_id,quantita:r.pasti}))),canonical(v.righe));assert.equal(cr.plessi[0].tipologie.reduce((s,t)=>s+Number(t.pasti),0),qty);}
   else assert.equal(cr.plessi.length,0);
   await catering.locator('#report-data').fill(D(n));await catering.locator('#btn-report:not([disabled])').waitFor();await catering.locator('#btn-report').click();await catering.locator('#btn-report:not([disabled])').waitFor();
   const card=catering.locator(`[data-testid="report-plesso-${pid}"]`);assert.equal(await card.count(),active?1:0,label+' catering UI');
   if(active){assert.equal(Number(await card.locator('.saldo').innerText()),qty);assert.equal(await card.locator(`[data-testid="report-personale-${pid}"] > h4`).innerText(),'Pasti del personale · '+qty);assert((await card.innerText()).includes('senza cutoff'));}
   const stored=sql(`SELECT righe FROM pasti_personale_giorni WHERE plesso_id=${pid} AND data='${D(n)}'`);
   if(stored&&active)assert.deepEqual(canonical(JSON.parse(stored)),canonical(v.righe),label);
   const rows=sql(`SELECT JSON_OBJECT('dal',dal,'giorno',giorno,'categoria',categoria,'tipologia_menu_id',tipologia_menu_id,'quantita',quantita) FROM pasti_personale_regole WHERE plesso_id=${pid} ORDER BY dal,giorno,categoria,tipologia_menu_id`).split('\n').filter(Boolean).map(JSON.parse);
   assert.deepEqual(rows,v.programmazione,label+' SQL recurrence/API');
   pass(`${width}px ${label}: UI/API/DB/calendario/report/catering concordi (${D(n)}, ${qty} effettivi, ${baseQty} abituali)`);
  }
  async function calendar(startOffset=0,endOffset=25,week=[1,2,3,4,5]){
   await p.locator('[data-vista=calendario]').click();const read=p.waitForResponse(r=>r.url().endsWith('/api/admin/calendari/'+pid));await p.locator('#calendario-plesso').selectOption(String(pid));await read;
   await p.locator('#calendario-inizio').fill(D(startOffset));await p.locator('#calendario-fine').fill(D(endOffset));
   for(const [i,g]of ['lunedi','martedi','mercoledi','giovedi','venerdi'].entries())await p.locator('#calendario-'+g).setChecked(week.includes(i+1));
   await submit('#form-calendario button',`/api/admin/calendari/${pid}`,'PUT');
   await p.waitForFunction(d=>document.getElementById('calendario-inizio').value===d,D(startOffset));
   assert.equal(sql(`SELECT CONCAT(data_inizio,'|',data_fine,'|',martedi) FROM calendari_servizio WHERE plesso_id=${pid}`),`${D(startOffset)}|${D(endOffset)}|${week.includes(2)?1:0}`);
  }
  async function rule(q,category='insegnanti',type=1,from=0,days=[1,2,3,4,5,6,7]){
   await selectStaff(from);await p.locator('#aut-vista-programma').click();await p.locator('#aut-editor-regola > summary').click();
   for(const [name,value]of [['dal',D(from)],['quantita',String(q)],['motivazione',`Ciclo ${category} ${type} ${q} dal ${D(from)}`]])await p.locator('#aut-regola [name='+name+']').fill(value);
   await p.locator('#aut-regola [name=categoria]').selectOption(category);await p.locator('#aut-regola [name=tipologia_menu_id]').selectOption(String(type));
   for(let g=1;g<=7;g++)await p.locator(`#aut-regola [name=giorni][value="${g}"]`).setChecked(days.includes(g));
   await submit('#aut-regola button',root+'/regole');await p.locator('#aut-personale-stato').filter({hasText:/Ricorrenza (salvata|invariata)/}).waitFor();
   assert.equal(Number(sql(`SELECT COUNT(*) FROM pasti_personale_regole WHERE plesso_id=${pid} AND dal='${D(from)}' AND categoria='${category}' AND tipologia_menu_id=${type} AND quantita=${q} AND giorno IN (${days.join(',')})`)),days.length);
  }
  async function editDay(n,changes,reason='Variazione ciclo'){
   await selectStaff(n);await p.locator('#aut-editor-giorno > summary').click();
   for(const [key,qty]of Object.entries(changes))await p.locator(`#aut-giorno [name="${key}"]`).fill(String(qty));
   await p.locator('#aut-giorno [name=motivazione]').fill(reason);const result=await submit('#aut-giorno button:not([type=button])',root+'/giorno','PUT');
   await p.locator('#aut-personale-stato').filter({hasText:result.modificato?'Pasti del personale salvati':'Quantità invariate'}).waitFor();
   if(result.modificato){assert.equal(sql(`SELECT motivazione FROM audit_amministrativo WHERE riferimento='${pid}:${D(n)}' AND operazione='personale_giorno' ORDER BY id DESC LIMIT 1`),reason);assert((await p.locator('#aut-personale-corpo .personale-storico').innerText()).includes(reason));}
   return result;
  }
  async function reset(n){await selectStaff(n);await p.locator('#aut-editor-giorno > summary').click();await p.locator('#aut-giorno [name=motivazione]').fill('Ripristino ciclo');await submit('#aut-ripristina',root+'/giorno','PUT');await p.locator('#aut-personale-stato').filter({hasText:'Programmazione ripristinata'}).waitFor();assert.equal(sql(`SELECT COUNT(*) FROM pasti_personale_giorni WHERE plesso_id=${pid} AND data='${D(n)}'`),'0');}
  async function officeCalendar(){await p.locator('[data-vista=servizio]').click();await p.locator('#aut-servizio-filtro [name=plesso_id]').selectOption(String(pid));await p.locator('#aut-servizio-filtro button').click();await p.locator('#aut-servizio-stato').filter({hasText:'ID '+pid}).waitFor();await p.locator('#aut-config').waitFor();}
  async function adminPeriod(from,to,open,id){
   await p.locator('[data-vista=calendario]').click();const read=p.waitForResponse(r=>r.url().endsWith('/api/admin/calendari/'+pid));await p.locator('#calendario-plesso').selectOption(String(pid));await read;
   if(id)await p.locator(`[data-modifica-periodo="${id}"]`).click();
   await p.locator('#eccezione-data').fill(D(from));await p.locator('#eccezione-fine').fill(D(to));await p.locator('#eccezione-attivo').selectOption(open?'1':'0');await p.locator('#eccezione-motivazione').fill('Periodo ciclo');
   const result=await submit('[data-testid=eccezione-salva]',`/api/admin/calendari/${pid}/periodi${id?'/'+id:''}`,id?'PUT':'POST');
   await p.locator('#eccezioni-calendario-corpo').filter({hasText:'Periodo ciclo'}).waitFor();
   const periodId=id||Number(result.id);assert.equal(sql(`SELECT CONCAT(giorno,'|',COALESCE(giorno_fine,giorno),'|',servizio_attivo) FROM eccezioni_calendario WHERE id=${periodId} AND plesso_id=${pid}`),`${D(from)}|${D(to)}|${open?1:0}`);return periodId;
  }
  await calendar();await rule(9);await rule(3,'ata');await rule(2,'insegnanti',2);
  await check(0,14,14,true,'aggiunta tre regole');await check(-3,0,0,false,'prima dell’avvio');await check(5,0,14,false,'sabato escluso dal calendario');
  await rule(11,'insegnanti',1,7);await check(0,14,14,true,'decorrenza: settimana precedente invariata');await check(7,16,16,true,'modifica quantità dalla decorrenza');
  await rule(0,'ata',1,14);await check(7,16,16,true,'cessazione futura senza alterare la settimana precedente');await check(14,13,13,true,'rimozione ATA tramite quantità zero');
  await rule(8,'insegnanti',1,7,[2]);await check(8,13,13,true,'modifica solo martedì');await check(7,16,16,true,'lunedì non coinvolto');
  await editDay(7,{'insegnanti:1':10,'ata:1':1},'Assenze ciclo');await check(7,13,16,true,'variazione di una data');await check(8,13,13,true,'data vicina invariata');
  await editDay(7,{'assistenti:1':4},'Aggiunta assistenti ciclo');await check(7,17,16,true,'aggiunta voce giornaliera');
  await editDay(7,{'insegnanti:2':0},'Rimozione menù ciclo');await check(7,15,16,true,'rimozione voce giornaliera');
  await rule(12,'insegnanti',1,7,[1]);await check(7,15,17,true,'variazione prioritaria dopo nuova regola');await reset(7);await check(7,17,17,true,'ripristino ultime regole');
  await editDay(7,{'insegnanti:1':0,'insegnanti:2':0,'ata:1':0},'Nessun pasto necessario');await check(7,0,17,true,'zero esplicito in giorno aperto');await reset(7);
  assert.equal((await editDay(7,{},'Nessun cambiamento')).modificato,false);assert.equal(sql(`SELECT COUNT(*) FROM pasti_personale_giorni WHERE plesso_id=${pid}`),'0');
  await editDay(7,{'insegnanti:1':13},'Variazione da conservare in chiusura');const dailyHash=hash(`SELECT * FROM pasti_personale_giorni WHERE plesso_id=${pid} ORDER BY data`);
  await p.locator('[data-vista=calendario]').click();await p.locator('#calendario-verifica-data').fill(D(7));await p.locator('#calendario-verifica').click();await p.locator('#calendario-verifica-pannello').waitFor();
  await officeCalendar();await p.locator('#aut-chiusura [name=data_inizio]').fill(D(7));await p.locator('#aut-chiusura [name=data_fine]').fill(D(9));await p.locator('#aut-chiusura [name=motivazione]').fill('Chiusura ciclo');
  const closure=await submit('#aut-chiusura button',`/api/servizio/plessi/${pid}/chiusure`);await p.locator('#aut-servizio-stato').filter({hasText:'Chiusura registrata'}).waitFor();
  assert(await p.locator('#calendario-verifica-pannello').isHidden(),'Comune closure invalidates Admin calendar verification');
  assert.equal(sql(`SELECT CONCAT(giorno,'|',COALESCE(giorno_fine,giorno),'|',servizio_attivo) FROM eccezioni_calendario WHERE id=${closure.id}`),`${D(7)}|${D(9)}|0`);
  await check(7,0,17,false,'chiusura: aggiornamento automatico entrando nei pasti',false);await check(9,0,16,false,'ultimo giorno della chiusura incluso');await check(10,16,16,true,'giorno successivo alla chiusura');
  await officeCalendar();await p.locator(`[data-chiusura="${closure.id}"]`).click();await p.locator('#aut-modifica-chiusura [name=data_inizio]').fill(D(8));await p.locator('#aut-modifica-chiusura [name=data_fine]').fill(D(10));await p.locator('#aut-modifica-chiusura [name=motivazione]').fill('Chiusura spostata');
  await submit('#aut-modifica-chiusura button:not([type=button])',`/api/servizio/plessi/${pid}/chiusure/${closure.id}`,'PUT');await p.locator('#aut-servizio-stato').filter({hasText:'Chiusura modificata'}).waitFor();
  await check(7,18,17,true,'spostamento chiusura: riemerge la variazione');await check(10,0,16,false,'nuova fine inclusa');
  await officeCalendar();await p.locator(`[data-chiusura="${closure.id}"]`).click();await p.locator('#aut-modifica-chiusura [name=motivazione]').fill('Eliminazione chiusura ciclo');await submit('#aut-elimina-chiusura',`/api/servizio/plessi/${pid}/chiusure/${closure.id}`,'DELETE');await p.locator('#aut-servizio-stato').filter({hasText:'Chiusura eliminata'}).waitFor();
  assert.equal(sql(`SELECT COUNT(*) FROM eccezioni_calendario WHERE id=${closure.id}`),'0');await check(10,16,16,true,'eliminazione chiusura');assert.equal(hash(`SELECT * FROM pasti_personale_giorni WHERE plesso_id=${pid} ORDER BY data`),dailyHash,'chiusure non cancellano la variazione');
  // Load a report before the Admin calendar mutation, to test invalidation as well.
  await p.locator('[data-vista=riepilogo-pasti]').click();await p.locator('#aut-report [name=plesso_id]').selectOption(String(pid));for(const name of ['dal','al'])await p.locator(`#aut-report [name=${name}]`).fill(D(5));
  await p.locator('#aut-report button').click();await p.locator('#aut-export:not([disabled])').waitFor();assert.equal(await p.locator('#aut-report-corpo tr td').nth(4).innerText(),'0');
  await selectStaff(5);const opening=await adminPeriod(5,6,true);
  await check(5,14,14,true,'apertura Admin: aggiornamento automatico entrando nei pasti',false);await check(6,14,14,true,'domenica aperta straordinariamente');
  assert(await p.locator('#aut-export').isDisabled(),'report invalidated after Admin calendar write');await p.locator('[data-vista=riepilogo-pasti]').click();await p.locator('#aut-export:not([disabled])').waitFor();assert.equal(await p.locator('#aut-report-corpo tr td').nth(4).innerText(),'14');pass(`${width}px riepilogo già aperto aggiornato dopo apertura Admin`);
  await p.locator('[data-vista=servizio]').click();await p.locator('#aut-servizio-corpo p').filter({hasText:'Apertura · Periodo ciclo'}).waitFor();pass(`${width}px calendario Comune riletto dopo apertura Admin`);
  await adminPeriod(6,6,true,opening);await check(5,0,14,false,'spostamento apertura');await check(6,14,14,true,'apertura singola inclusiva');
  await p.locator('[data-vista=calendario]').click();await submit(`[data-cancella-periodo="${opening}"]`,`/api/admin/calendari/${pid}/periodi/${opening}`,'DELETE');await p.locator(`[data-cancella-periodo="${opening}"]`).waitFor({state:'detached'});
  await check(6,0,14,false,'eliminazione apertura: ritorno al calendario',false);
  await selectStaff(8);await calendar(0,25,[1,3,4,5]);await check(8,0,13,false,'rimozione martedì dal calendario ordinario',false);await calendar();await check(8,13,13,true,'ripristino martedì ordinario',false);
  await selectStaff(0);await calendar(1);await check(0,0,14,false,'spostamento avvio del servizio',false);await calendar();await check(0,14,14,true,'ripristino avvio',false);
  await check(25,13,13,true,'ultimo giorno ordinario incluso');await check(28,0,14,false,'dopo fine ordinaria');
  await adminPeriod(28,28,true);await check(28,14,14,true,'apertura esplicita oltre la fine ordinaria');
  await officeCalendar();await p.locator('#aut-config [name=ordine_scolastico]').selectOption('infanzia');await p.locator('#aut-config [name=primo_giorno_sospensione]').fill(D(28));await p.locator('#aut-config [name=motivazione]').fill('Sospensione ciclo');await submit('#aut-config button',`/api/servizio/plessi/${pid}`,'PUT');await p.locator('#aut-servizio-stato').filter({hasText:'Impostazioni salvate'}).waitFor();
  assert.equal(sql(`SELECT primo_giorno_sospensione FROM impostazioni_servizio_plesso WHERE plesso_id=${pid}`),D(28));await check(28,0,14,false,'sospensione prevale sull’apertura',false);
  await officeCalendar();await p.locator('#aut-config [name=primo_giorno_sospensione]').fill('');await p.locator('#aut-config [name=motivazione]').fill('Annullamento sospensione futura');await submit('#aut-config button',`/api/servizio/plessi/${pid}`,'PUT');await p.locator('#aut-servizio-stato').filter({hasText:'Impostazioni salvate'}).waitFor();await check(28,14,14,true,'annullamento sospensione futura',false);
  await p.reload();await check(7,18,17,true,'persistenza dopo ricaricamento completo');assert.equal(hash(`SELECT * FROM pasti_personale_giorni WHERE plesso_id=${pid} ORDER BY data`),dailyHash);
  assert.deepEqual(errors,[]);assert(!await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth));fs.mkdirSync('staging/ticket2-artifacts',{recursive:true});await p.screenshot({path:`staging/ticket2-artifacts/ciclo-personale-calendario-${width}.png`,fullPage:true});await p.close();
  assert(!await catering.evaluate(()=>document.documentElement.scrollWidth>innerWidth));await catering.screenshot({path:`staging/ticket2-artifacts/catering-personale-${width}.png`,fullPage:true});await catering.close();
 }
 assert.deepEqual(financial(),before);pass('intero ciclo senza modifiche a saldi, consumi, presenze alunni, assegnazioni o invii email');
 }finally{fs.mkdirSync('staging/ticket2-artifacts',{recursive:true});fs.writeFileSync('staging/ticket2-artifacts/ciclo-personale-calendario.json',JSON.stringify(log,null,2));await b.close();}
})().then(()=>process.exit(0)).catch(e=>{console.error(e);process.exit(1)});
