/* End-to-end regression using only the dedicated synthetic fixture from the HTTP test. */
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18089';assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const f=JSON.parse(fs.readFileSync('staging/ticket2-artifacts/coerenza-fixture.json','utf8'));
const root='/api/personale/plessi/'+f.pid;
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try{
  const login=async(p,user,role)=>{await p.goto(base+'/login');await p.locator('[name=username]').fill(user);await p.locator('[name=password]').fill('Collaudo2026!');await p.locator('button[type=submit]').click();await p.waitForURL('**/'+role);};
  const select=async(p,d)=>{
   await p.locator('[data-vista=personale]').click();await p.locator('#aut-personale-filtro [name=plesso_id]').selectOption(String(f.pid));
   await p.locator('#aut-personale-filtro [name=data]').fill(d);
   const response=p.waitForResponse(r=>r.url().endsWith(root+'?data='+d));
   await p.locator('#aut-personale-filtro button').click();await response;
   await p.waitForFunction(({d,pid})=>document.querySelector('#aut-regola [name=plesso_id]')?.value===String(pid)&&document.querySelector('#aut-programmazione-data').textContent.includes(new Date(d+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'})),{d,pid:f.pid});
   await p.locator('#aut-vista-giorno').click();await p.locator('#aut-giorno').waitFor();
  };
  const read=async(p,d=f.date)=>p.evaluate(({root,d})=>API.get(root+'?data='+d),{root,d});
  for(const [i,width] of [375,1366].entries()){
   const p=await browser.newPage({viewport:{width,height:950}}),errors=[];p.on('pageerror',e=>errors.push(e.message));p.on('dialog',d=>d.accept());
   await login(p,'operatore.staging','operatore');
   // Load a report before modifying the day's data.
   await p.locator('[data-vista=riepilogo-pasti]').click();await p.locator('#aut-report [name=plesso_id]').selectOption(String(f.pid));
   for(const name of ['dal','al'])await p.locator(`#aut-report [name=${name}]`).fill(f.date);
   await p.locator('#aut-report button').click();await p.locator('#aut-export-excel:not([disabled])').waitFor();
   // The UI sorts menu names, unlike the database. Saving unchanged must remain recurring.
   const next=new Date(f.date+'T12:00:00');next.setDate(next.getDate()+1);const d=next.toLocaleDateString('en-CA');
   await select(p,d);const before=await read(p,d);assert.equal(before.fonte,'Da programmazione');
   await p.locator('#aut-editor-giorno > summary').click();await p.locator('#aut-giorno [name=motivazione]').fill('Conferma senza cambiamenti');
   await p.locator('#aut-giorno button').filter({hasText:'Salva pasti'}).click();await p.locator('#aut-personale-stato').filter({hasText:'Quantità invariate'}).waitFor();
   assert.equal((await read(p,d)).fonte,'Da programmazione');
   // Real day variation preserves the retired menu, totals and entered reason after reload.
   await select(p,f.date);await p.locator('#aut-editor-giorno > summary').click();
   assert.equal(await p.locator(`#aut-giorno [name="insegnanti:${f.retired}"]`).inputValue(),'2');
   assert.equal(await p.locator(`#aut-giorno [name="ata:${f.retired}"]`).count(),0);
   const qty=14+i,reason='Assenza verificata '+width+' <img src=x onerror=alert(1)>';
   await p.locator('#aut-giorno [name="insegnanti:1"]').fill(String(qty));assert.equal(await p.locator('#aut-totale-bozza').innerText(),String(qty+5));
   await p.locator('#aut-giorno [name=motivazione]').fill(reason);await p.locator('#aut-giorno button').filter({hasText:'Salva pasti'}).click();
   await p.locator('#aut-personale-stato').filter({hasText:'Pasti del personale salvati'}).waitFor();
   assert((await p.locator('#aut-personale-corpo .personale-storico').innerText()).includes(reason));assert.equal(await p.locator('.personale-storico img').count(),0);
   const saved=await read(p);assert.equal(saved.righe.reduce((s,r)=>s+r.quantita,0),qty+5);assert.equal(saved.storico_giorno[0].motivazione,reason);
   assert(await p.locator('#aut-export-excel').isDisabled());
   const refreshed=p.waitForResponse(r=>r.url().includes('/api/report/pasti?'));
   await p.locator('[data-vista=riepilogo-pasti]').click();const report=(await (await refreshed).json()).dati;
   await p.locator('#aut-export-excel:not([disabled])').waitFor();assert.equal(report.righe[0].personale,qty+5);
   assert.equal(await p.locator('#aut-report-corpo tr').first().locator('td').nth(4).innerText(),String(qty+5));
   // A changed filter invalidates exports immediately; late responses cannot repopulate old data.
   let release,ready;const gate=new Promise(r=>release=r),started=new Promise(r=>ready=r);
   await p.route('**/api/report/pasti?*',async route=>{const response=await route.fetch();ready();await gate;await route.fulfill({response});});
   await p.locator('#aut-report button').click();await started;
   await p.locator('#aut-report [name=al]').fill(d);release();await p.waitForTimeout(250);
   assert(await p.locator('#aut-export').isDisabled());assert.equal(await p.locator('#aut-report-corpo tr').count(),0);await p.unroute('**/api/report/pasti?*');
   // Recurring form sends its version, rejects a concurrent rule change, and displays the reason.
   await select(p,d);await p.locator('#aut-vista-programma').click();await p.locator('#aut-editor-regola > summary').click();
   await p.locator('#aut-regola [name=dal]').fill(f.today);await p.locator('#aut-regola [name=tipologia_menu_id]').selectOption('1');
   await p.locator('#aut-regola [name=quantita]').fill('21');await p.locator('#aut-regola [name=motivazione]').fill('Vecchia schermata');
   await p.evaluate(({root,today,i})=>API.post(root+'/regole',{dal:today,giorni:[1,2,3,4,5,6,7],categoria:'insegnanti',tipologia_menu_id:1,quantita:22+i,motivazione:'Aggiornamento concorrente',conferma:true}),{root,today:f.today,i});
   await p.locator('#aut-regola button').click();await p.locator('#aut-regola-stato').filter({hasText:'La programmazione è cambiata'}).waitFor();
   await select(p,d);await p.locator('#aut-vista-programma').click();
   assert((await p.locator('#aut-programmazione-storico').innerText()).includes('Aggiornamento concorrente'));
   await p.locator('#aut-editor-regola > summary').click();await p.locator('#aut-regola [name=quantita]').fill(String(24+i));
   await p.locator('#aut-regola [name=motivazione]').fill('Programmazione confermata '+width);await p.locator('#aut-regola button').click();
   await p.locator('#aut-personale-stato').filter({hasText:'Ricorrenza salvata'}).waitFor();
   assert((await p.locator('#aut-programmazione-storico').innerText()).includes('Programmazione confermata '+width));
   const weekday=new Date(d+'T12:00:00').getDay()||7;
   assert.equal((await read(p,d)).righe.find(r=>r.categoria==='insegnanti'&&r.tipologia_menu_id===1).quantita,weekday<=5?24+i:22+i);
   await p.reload();await select(p,f.date);assert((await p.locator('#aut-personale-corpo .personale-storico').innerText()).includes(reason));
   assert(!await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth));assert.deepEqual(errors,[]);
   await p.screenshot({path:`staging/ticket2-artifacts/coerenza-${width}.png`,fullPage:true});
   await select(p,f.past);assert.equal(await p.locator('#aut-editor-giorno').count(),1);
   console.log(`PASS coerenza browser ${width}px: no-op menu order, retired counts, persisted/escaped reason, report refresh, stale report response, recurring conflict and refresh, Comune past edit`);await p.close();
  }
  const p=await browser.newPage({viewport:{width:375,height:900}});await login(p,f.username,'scuola');
  await p.locator('[data-vista=personale]').click();await p.locator('#aut-programmazione').waitFor();await p.locator('#aut-vista-giorno').click();
  for(const d of [f.past,f.today,f.tomorrow,f.date]){
   await p.locator('#aut-personale-filtro [name=data]').fill(d);const response=p.waitForResponse(r=>r.url().endsWith(root+'?data='+d));await p.locator('#aut-personale-filtro button').click();await response;
   await p.waitForFunction(d=>document.querySelector('.personale-data h2')?.textContent.includes(new Date(d+'T12:00:00').toLocaleDateString('it-IT',{day:'numeric',month:'long',year:'numeric'})),d);
   assert.equal(await p.locator('#aut-editor-giorno').count(),d===f.past?0:1);
  }
  assert((await p.locator('#aut-personale-corpo .personale-storico').innerText()).includes('Assenza verificata 1366'));
  console.log('PASS Scuola browser: own history visible, past read-only, today/future editable despite midnight cutoff');await p.close();
 }finally{await browser.close();}
})().then(()=>process.exit(0)).catch(e=>{console.error(e);process.exit(1)});
