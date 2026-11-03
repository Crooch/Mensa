/* Read-first staff planning UI, writes confined to synthetic localhost staging. */
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18088';assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
fs.mkdirSync('staging/ticket2-artifacts',{recursive:true});
const date=n=>{const d=new Date();d.setDate(d.getDate()+n);return d.toLocaleDateString('en-CA',{timeZone:'Europe/Rome'});};
(async()=>{
 const b=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try{
  const setup=await b.newPage();await setup.goto(base+'/login');
  const login=async(p,user)=>{await p.goto(base+'/login');await p.locator('[name=username]').fill(user+'.staging');await p.locator('[name=password]').fill('Collaudo2026!');await p.locator('button[type=submit]').click();await p.waitForURL('**/'+(user.startsWith('scuola')?'scuola':user));};
  await login(setup,'admin');
  const n=(8-new Date().getDay())%7||7, day=date(n), future=date(n+7);
  await setup.evaluate(async({day,future,today})=>{
   const reason={motivazione:'Collaudo grafico sintetico',conferma:true};
   await API.put('/api/admin/calendari/3/eccezioni/'+day,{servizio_attivo:1,motivazione:reason.motivazione});
   for(const [tipo,q] of [[1,6],[2,1]])await API.post('/api/personale/plessi/3/regole',{...reason,dal:today,giorni:[1,2,3,4,5],categoria:'insegnanti',tipologia_menu_id:tipo,quantita:q});
   await API.post('/api/personale/plessi/3/regole',{...reason,dal:today,giorni:[6],categoria:'insegnanti',tipologia_menu_id:1,quantita:0});
   await API.post('/api/personale/plessi/3/regole',{...reason,dal:future,giorni:[1],categoria:'insegnanti',tipologia_menu_id:1,quantita:8});
  },{day,future,today:date(0)});await setup.close();
  for(const width of [320,375,768,1366]){
   const p=await b.newPage({viewport:{width,height:900}}),errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',d=>d.accept());
   await login(p,'operatore');await p.locator('[data-vista=personale]').click();await p.locator('#aut-programmazione').waitFor();
   await p.locator('#aut-personale-filtro [name=plesso_id]').selectOption('3');await p.locator('#aut-personale-filtro [name=data]').fill(day);await p.locator('#aut-personale-filtro button').click();
   await p.waitForFunction(day=>document.querySelector('#aut-programmazione-data').textContent.includes(new Date(day+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'}))&&document.querySelector('#aut-programmazione-scuola').textContent.includes('Secondaria'),day);
   assert.equal(await p.locator('.personale-giorno').count(),7);assert.equal((await p.locator('.personale-giorno').nth(0).locator('.personale-numero').innerText()).replace(/\s+/g,' ').trim(),'7 pasti');
   assert((await p.locator('.personale-giorno').nth(5).innerText()).includes('0'));assert((await p.locator('.personale-giorno').nth(6).innerText()).includes('non impostato'));
   assert(await p.locator('#aut-personale-corpo').isHidden());assert(await p.locator('#aut-regola').isHidden());assert((await p.locator('#aut-programmazione-futura').innerText()).includes('8 Insegnanti'));
   await p.locator('.personale-giorno summary').first().click();assert((await p.locator('.personale-giorno').first().innerText()).includes('Senza glutine'));
   await p.screenshot({path:`staging/ticket2-artifacts/personale-settimana-${width}.png`,fullPage:true});
   await p.locator('#aut-vista-giorno').click();assert(await p.locator('#aut-programmazione').isHidden());assert(await p.locator('#aut-editor-giorno').isVisible());assert.equal(await p.locator('#aut-editor-giorno').getAttribute('open'),null);
   assert((await p.locator('.personale-confronto').innerText()).includes('7'));
   await p.locator('#aut-editor-giorno > summary').click();await p.locator('#aut-giorno [name="insegnanti:1"]').fill('5');assert.equal(await p.locator('#aut-totale-bozza').textContent(),'6');
   for(const input of await p.locator('#aut-giorno input').all()){const r=await input.boundingBox();assert(r&&r.x>=0&&r.x+r.width<=width,`Campo fuori schermo ${width}`);}
   await p.locator('#aut-giorno [name=motivazione]').fill('Un insegnante assente');await p.locator('#aut-giorno button').filter({hasText:'Salva pasti del giorno'}).click();await p.locator('#aut-personale-stato').filter({hasText:'Pasti del personale salvati'}).waitFor();
   assert.equal(await p.locator('.personale-fonte').textContent(),'Variazione per questa data');assert((await p.locator('.personale-confronto').innerText()).includes('6'));
   await p.screenshot({path:`staging/ticket2-artifacts/personale-data-${width}.png`,fullPage:true});
   await p.locator('#aut-editor-giorno > summary').click();await p.locator('#aut-giorno [name=motivazione]').fill('Ripristino dopo prova');await p.locator('#aut-ripristina').click();await p.locator('#aut-personale-stato').filter({hasText:'Programmazione ripristinata'}).waitFor();assert.equal(await p.locator('.personale-fonte').textContent(),'Quantità abituali');
   await p.locator('#aut-vista-programma').click();await p.locator('#aut-editor-regola > summary').click();assert.equal(await p.locator('#aut-regola [name=plesso_id]').inputValue(),'3');
   await p.locator('#aut-regola [name=dal]').fill(future);await p.locator('#aut-regola [name=categoria]').selectOption('asacom');await p.locator('#aut-regola [name=quantita]').fill('2');await p.locator('#aut-regola [name=motivazione]').fill('Programmazione futura sintetica');await p.locator('#aut-regola button').click();await p.locator('#aut-personale-stato').filter({hasText:/Ricorrenza (salvata|invariata)/}).waitFor();assert((await p.locator('#aut-programmazione-futura').innerText()).includes('2 ASACOM'));
   assert(!await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth));assert.deepEqual(errors,[]);console.log(`PASS personale ${width}px: settimana leggibile, zero distinto da mancante, decorrenze future, variazione e confronto, ripristino, scuola unica, aggiornamento dopo salvataggio`);await p.close();
  }
  const race=await b.newPage({viewport:{width:375,height:900}});await login(race,'operatore');
  await race.route('**/api/personale/plessi/1?*',async route=>{await new Promise(r=>setTimeout(r,800));await route.continue();});
  await race.locator('[data-vista=personale]').click();await race.locator('#aut-personale-filtro [name=plesso_id]').selectOption('3');await race.locator('#aut-programmazione-scuola').filter({hasText:'Secondaria'}).waitFor();await race.waitForTimeout(1200);assert((await race.locator('#aut-programmazione-scuola').innerText()).includes('Secondaria'));
  const friday=date(n+4);await race.locator('#aut-personale-filtro [name=data]').fill(friday);await race.locator('#aut-personale-filtro button').click();
  await race.waitForFunction(d=>document.querySelector('#aut-personale-stato').textContent.includes(new Date(d+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'})),friday);
  assert((await race.locator('#aut-personale-stato').innerText()).includes('non prevede la mensa in questo giorno della settimana'));
  await race.locator('#aut-vista-giorno').click();assert.equal(await race.locator('.personale-fonte').innerText(),'Mensa non prevista');assert(!/Fuori servizio|Sola lettura/.test(await race.locator('#vista-personale').innerText()));assert.equal(await race.locator('#aut-editor-giorno').count(),0);
  await race.locator('#aut-personale-filtro [name=data]').fill('');await race.locator('#aut-personale-filtro [name=data]').dispatchEvent('change');assert(await race.locator('#aut-programmazione').isHidden());assert.equal(await race.locator('#aut-giorno').count(),0);
  await race.close();console.log('PASS contesto: risposta tardiva ignorata e nessun dato modificabile con data vuota');
  for(const role of ['scuola.infanzia','catering']){
   const p=await b.newPage({viewport:{width:375,height:900}});await login(p,role);await p.locator('[data-vista=personale]').click();await p.locator('#aut-programmazione').waitFor();assert.equal(await p.locator('.personale-giorno').count(),7);assert.equal(await p.locator('#aut-regola').count(),0);
   const data=await p.evaluate(()=>API.get('/api/personale/plessi/1'));
   assert(data.programmazione.length>0);assert.deepEqual(data.regole,[]);assert(data.programmazione.every(r=>Object.keys(r).sort().join(',')==='categoria,dal,giorno,quantita,tipologia_menu_id'));
   if(role.startsWith('scuola')){const forbidden=await p.evaluate(async()=>{try{await API.get('/api/personale/plessi/3');return false;}catch{return true;}});assert(forbidden);await p.locator('#aut-personale-filtro [name=data]').fill(date(-1));await p.locator('#aut-personale-filtro button').click();await p.locator('#aut-personale-stato').filter({hasText:'Per correggere i pasti di una data passata interviene il Comune.'}).waitFor();}
   await p.locator('#aut-vista-giorno').click();await p.locator('#aut-giorno').waitFor();assert.equal(await p.locator('#aut-editor-giorno').count(),0);console.log(`PASS ${role}: programmazione consultabile, permessi di modifica invariati`);await p.close();
  }
 }finally{await b.close();}
})().then(()=>process.exit(0)).catch(e=>{console.error(e);process.exit(1)});
