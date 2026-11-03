/* Browser QA on synthetic localhost staging only. NODE_PATH may locate Playwright. */
const {chromium}=require('playwright');
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18082';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const out=process.env.MENSA_TICKET2_SCREENSHOTS||'staging/ticket2-artifacts';fs.mkdirSync(out,{recursive:true});
function dateAfter(n){const d=new Date();d.setDate(d.getDate()+n);return d.toLocaleDateString('en-CA',{timeZone:'Europe/Rome'});}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try {
 for(const width of [375,768,1366]) for(const role of ['operatore','admin','scuola','catering','genitore']) {
  const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  page.on('dialog',d=>d.accept());
  await page.goto(base+'/login');await page.locator('[name=username]').fill(role==='scuola'?'scuola.infanzia.staging':role+'.staging');await page.locator('[name=password]').fill('Collaudo2026!');await page.locator('button[type=submit]').click();await page.waitForURL('**/'+role);
  if(role!=='genitore') {
   await page.locator('[data-vista=riepilogo-pasti]').first().click();await page.locator('#aut-report button').click();await page.locator('#aut-report-stato').filter({hasText:'giornate/plessi.'}).waitFor();
   const download=page.waitForEvent('download');await page.locator('#aut-export').click();const file=await download;await file.saveAs(path.join(out,`${role}-${width}.csv`));assert(fs.readFileSync(path.join(out,`${role}-${width}.csv`),'utf8').includes('Alunni'));
   await page.screenshot({path:path.join(out,`${role}-report-${width}.png`),fullPage:true});
   await page.locator('[data-vista=personale]').first().click();await page.locator('#aut-personale-filtro [name=plesso_id]').selectOption('1');await page.locator('#aut-personale-filtro button').click();await page.locator('#aut-giorno').waitFor();
   if(width===375) { for(const input of await page.locator('#aut-giorno input[type=number]').all()) {const box=await input.boundingBox();assert(box.x>=0 && box.x+box.width<=width,'Quantità personale fuori schermo');} }
   if(role==='catering') assert.equal(await page.locator('#aut-giorno button').count(),0);
   if(role==='scuola') {
    assert.equal(await page.locator('#aut-personale-filtro [name=plesso_id] option').count(),1);
    if(width===1366) {
     await page.locator('#aut-giorno [name="ata:1"]').fill('3');await page.locator('#aut-giorno [name=motivazione]').fill('Collaudo UI Scuola');await page.locator('#aut-giorno button').click();await page.locator('#aut-personale-stato').filter({hasText:'Pasti del personale salvati'}).waitFor();
    }
   }
   await page.screenshot({path:path.join(out,`${role}-personale-${width}.png`),fullPage:true});
   if(['admin','operatore'].includes(role)) {
    await page.locator('[data-vista=servizio]').first().click();await page.locator('#aut-servizio-filtro [name=plesso_id]').selectOption('1');await page.locator('#aut-servizio-filtro button').click();await page.locator('#aut-config').waitFor();
    if(role==='operatore'&&width===1366) {
     await page.locator('#aut-config [name=ordine_scolastico]').selectOption('infanzia');await page.locator('#aut-config [name=fido_buoni]').fill('20');await page.locator('#aut-config [name=primo_giorno_sospensione]').fill(dateAfter(120));await page.locator('#aut-config [name=motivazione]').fill('Collaudo UI configurazione');await page.locator('#aut-config button').click();await page.locator('#aut-servizio-stato').filter({hasText:'Impostazioni salvate'}).waitFor();
     await page.locator('#aut-chiusura [name=data_inizio]').fill(dateAfter(70));await page.locator('#aut-chiusura [name=data_fine]').fill(dateAfter(71));await page.locator('#aut-chiusura [name=motivazione]').fill('Chiusura sintetica UI');await page.locator('#aut-chiusura button').click();await page.locator('#aut-servizio-stato').filter({hasText:'Chiusura registrata'}).waitFor();
     await page.locator('[data-vista=personale]').first().click();await page.locator('#vista-personale details summary').click();await page.locator('#aut-regola [name=plesso_id]').selectOption('1');await page.locator('#aut-regola [name=dal]').fill(dateAfter(1));await page.locator('#aut-regola [name=categoria]').selectOption('ata');await page.locator('#aut-regola [name=quantita]').fill('3');await page.locator('#aut-regola [name=motivazione]').fill('Programmazione sintetica UI');await page.locator('#aut-regola button').click();await page.locator('#aut-regola-stato').filter({hasText:'Ricorrenza salvata'}).waitFor();
     await page.locator('[data-vista=solleciti]').click();await page.locator('#solleciti-filtri [name=q]').fill('Fittizio');await page.locator('#solleciti-filtri button').click();await page.locator('#solleciti-stato').filter({hasText:'nessun invio'}).waitFor();
     await page.locator('[data-vista=famiglie]').click();await page.locator('#ricerca-famiglie').fill('Fittizio');await page.locator('#btn-cerca').click();await page.locator('[data-apri="1"]').click();await page.locator('[data-nome="1"]').click();await page.locator('#modale [name=nome]').fill('Mario UI');await page.locator('#modale [name=motivazione]').fill('Correzione nominativo sintetico');await page.locator('#modale button[type=submit]').click();await page.locator('#modale').waitFor({state:'hidden'});await page.locator('[data-testid="figlio-1"] h3').filter({hasText:'Mario UI'}).waitFor();
     await page.locator('#btn-verifica-famiglia').click();await page.locator('#modale-corpo').filter({hasText:'Disattivazione non disponibile'}).waitFor();await page.locator('#modale-chiudi').click();
     // Safe empty family created through the normal API, then deactivated using the UI.
     const uniq='UI'+Date.now();
     const family=await page.evaluate(async uniq=>{const r=await API.post('/api/famiglie',{nome:'Sintetico',cognome:uniq,telefono:'000',username:uniq,password:'Collaudo2026!'});return r.id;},uniq);
     await page.locator('#ricerca-famiglie').fill(uniq);await page.locator('#btn-cerca').click();await page.locator(`[data-apri="${family}"]`).click();await page.locator('#btn-verifica-famiglia').click();await page.locator('#modale [name=motivazione]').fill('Disattivazione sintetica UI');await page.locator('#modale button[type=submit]').click();await page.locator('#modale').waitFor({state:'hidden'});await page.locator('#scheda-famiglia .badge-stato').filter({hasText:'Disattivata'}).waitFor();
    }
    await page.screenshot({path:path.join(out,`${role}-operazioni-${width}.png`),fullPage:true});
   }
  } else {
   await page.locator('#crediti-storici').waitFor({state:'attached'});
   await page.locator('summary').filter({hasText:'Posizioni buoni storiche'}).click();
   await page.locator('#crediti-storici').filter({hasText:'Secondaria'}).waitFor();
   await page.screenshot({path:path.join(out,`genitore-storico-${width}.png`),fullPage:true});
  }
  assert.deepEqual(errors,[],`${role} JS errors`);assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),`${role} overflow ${width}`);
  console.log(`PASS ${role} ${width}px; UI, CSV (se autorizzato), nessun errore JS/overflow`);await page.close();
 }
 } finally {await browser.close();}
})().then(()=>process.exit(0)).catch(e=>{console.error(e);process.exit(1)});
