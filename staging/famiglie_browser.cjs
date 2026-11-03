/* Run famiglie_smoke.py first, exclusively against disposable localhost staging. */
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18087';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const out=process.env.MENSA_TICKET2_SCREENSHOTS||'staging/ticket2-artifacts';fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try {
 for(const width of [320,375,768,1366]) {
  const page=await browser.newPage({viewport:{width,height:850}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/login');await page.locator('[name=username]').fill('operatore.staging');await page.locator('[name=password]').fill('Collaudo2026!');await page.locator('button[type=submit]').click();await page.waitForURL('**/operatore');
  async function search(q,total){await page.locator('#ricerca-famiglie').fill(q);await page.locator('#btn-cerca').click();await page.waitForFunction(t=>document.querySelector('#ricerca-stato').textContent.startsWith(t+' famiglie trovate'),total);}
  async function layout(){assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),`Overflow ${width}`);}
  await search('Collaudo Elenco',215);assert.equal(await page.locator('#famiglie-corpo [data-apri]').count(),10);
  await page.locator('#famiglie-successiva').click();await page.locator('#famiglie-pagina').filter({hasText:'Pagina 2 di 22'}).waitFor();
  const first=page.locator('#famiglie-corpo [data-apri]').first();const id=await first.getAttribute('data-apri');
  await layout();await page.screenshot({path:`${out}/famiglie-elenco-${width}.png`,fullPage:true});
  await first.click();await page.locator('#btn-acquisto').waitFor();
  if(width<=980){
   assert(await page.locator('.operatore-risultati').isHidden());assert(await page.locator('#btn-modifica-genitore').isHidden());
   await page.locator('#btn-altre-operazioni').click();assert(await page.locator('#btn-modifica-genitore').isVisible());
  }
  await page.locator('#btn-modifica-genitore').click();await page.locator('#modale form').waitFor();await layout();await page.locator('#modale-chiudi').click();
  await layout();await page.screenshot({path:`${out}/famiglie-scheda-${width}.png`,fullPage:true});
  if(width<=980){
   await page.locator('#famiglie-indietro').click();assert.equal(await page.locator('#famiglie-pagina').textContent(),'Pagina 2 di 22');
   assert.equal(await page.locator('#ricerca-famiglie').inputValue(),'Collaudo Elenco');
   await page.waitForFunction(id=>document.activeElement?.dataset.apri===id,id);
  }
  await page.locator('#famiglie-per-pagina').selectOption('25');await page.locator('#famiglie-pagina').filter({hasText:'Pagina 1 di 9'}).waitFor();assert.equal(await page.locator('#famiglie-corpo [data-apri]').count(),25);
  await page.locator('#famiglie-stato').selectOption('disattivate');await page.locator('#ricerca-stato').filter({hasText:'5 famiglie trovate'}).waitFor();assert.equal(await page.locator('.famiglia-stato-badge').count(),5);
  await page.locator('#famiglie-stato').selectOption('attive');await search('nessuna-famiglia-sintetica',0);assert(await page.locator('#famiglie-successiva').isDisabled());assert(await page.locator('#famiglie-precedente').isDisabled());
  // A real family with children/credits and the main purchase modal must remain usable.
  await search('Fittizio',2);await page.locator('[data-apri="1"]').click();await page.locator('#btn-acquisto').click();await page.locator('#modale form').waitFor();await layout();await page.locator('#modale-chiudi').click();
  await page.screenshot({path:`${out}/famiglie-figli-${width}.png`,fullPage:true});await layout();
  assert.deepEqual(errors,[]);console.log(`PASS famiglie ${width}px: ricerca 215, pagine, filtri, dettaglio/ritorno, azioni, figli/buoni, nessun errore JS o overflow`);await page.close();
 }
 // Simulate delayed requests: old searches and detail responses cannot overwrite current state.
 const page=await browser.newPage({viewport:{width:375,height:850}});
 await page.goto(base+'/login');await page.locator('[name=username]').fill('operatore.staging');await page.locator('[name=password]').fill('Collaudo2026!');await page.locator('button[type=submit]').click();await page.waitForURL('**/operatore');
 await page.route('**/api/famiglie?*',async route=>{if(new URL(route.request().url()).searchParams.get('q')==='Collaudo Elenco') await new Promise(r=>setTimeout(r,900));await route.continue();});
 await page.locator('#ricerca-famiglie').fill('Collaudo Elenco');await page.locator('#btn-cerca').click();await page.locator('#ricerca-famiglie').fill('nessuna-famiglia-sintetica');await page.locator('#btn-cerca').click();await page.waitForTimeout(1400);assert.equal(await page.locator('#famiglie-corpo [data-apri]').count(),0);
 await page.locator('#ricerca-famiglie').fill('Fittizio');await page.locator('#btn-cerca').click();await page.locator('[data-apri="1"]').waitFor();
 await page.route('**/api/famiglie/1',async route=>{await new Promise(r=>setTimeout(r,800));await route.continue();});
 await page.locator('[data-apri="1"]').click();await page.locator('#famiglie-indietro').click();await page.waitForTimeout(1200);assert(await page.locator('.famiglia-dettaglio').isHidden());assert(await page.locator('.operatore-risultati').isVisible());
 console.log('PASS risposte ritardate: ricerca recente e ritorno elenco preservati');await page.close();
 const admin=await browser.newPage({viewport:{width:375,height:850}});
 await admin.goto(base+'/login');await admin.locator('[name=username]').fill('admin.staging');await admin.locator('[name=password]').fill('Collaudo2026!');await admin.locator('button[type=submit]').click();await admin.waitForURL('**/admin');await admin.goto(base+'/operatore');
 await admin.locator('#ricerca-famiglie').fill('Collaudo Elenco');await admin.locator('#btn-cerca').click();await admin.locator('#ricerca-stato').filter({hasText:'215 famiglie trovate'}).waitFor();await admin.locator('#famiglie-corpo [data-apri]').first().click();await admin.locator('#btn-acquisto').waitFor();await admin.locator('#famiglie-indietro').click();assert(await admin.locator('.operatore-risultati').isVisible());await admin.close();console.log('PASS Admin: stessa ricerca e navigazione mobile nell’area Ufficio comunale');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
