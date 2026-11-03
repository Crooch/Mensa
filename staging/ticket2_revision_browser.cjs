/* Revised workflows on disposable localhost staging only. */
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18086';
assert(['localhost','127.0.0.1'].includes(new URL(base).hostname));
const date=n=>{const d=new Date();d.setDate(d.getDate()+n);return d.toLocaleDateString('en-CA',{timeZone:'Europe/Rome'});};
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try{
 for(const width of [375,1366]){
  const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
  const login=async user=>{if(page.url().startsWith(base))await page.evaluate(()=>localStorage.clear());await page.context().clearCookies();await page.goto(base+'/login');await page.locator('[name=username]').fill(user);await page.locator('[name=password]').fill('Collaudo2026!');await page.locator('button[type=submit]').click();await page.waitForURL('**/'+(user.startsWith('scuola')?'scuola':'operatore'));};
  await login('operatore.staging');
  await page.locator('[data-vista=riepilogo-pasti]').first().click();const month=date(0).slice(0,7);await page.locator('#aut-mese').fill(month);await page.locator('#aut-mese').dispatchEvent('change');
  assert.equal(await page.locator('#aut-report [name=dal]').inputValue(),month+'-01');
  const [year,num]=month.split('-').map(Number);assert.equal(await page.locator('#aut-report [name=al]').inputValue(),month+'-'+new Date(year,num,0).getDate());
  await page.locator('#aut-report button').click();await page.locator('#aut-report-mensile tr').first().waitFor();

  await page.locator('[data-vista=personale]').first().click();await page.locator('#vista-personale details summary').first().click();
  await page.locator('#aut-regola [name=plesso_id]').selectOption('1');await page.locator('#aut-regola [name=dal]').fill(date(0));
  for(const cb of await page.locator('#aut-regola [name=giorni]').all())await cb.check();
  await page.locator('#aut-regola [name=categoria]').selectOption('assistenti');await page.locator('#aut-regola [name=tipologia_menu_id]').selectOption('1');await page.locator('#aut-regola [name=quantita]').fill('2');await page.locator('#aut-regola [name=motivazione]').fill('Regola settimanale UI');await page.locator('#aut-regola button').click();await page.locator('#aut-regola-stato').filter({hasText:'Ricorrenza salvata'}).waitFor();
  await page.locator('#aut-personale-filtro [name=plesso_id]').selectOption('1');await page.locator('#aut-personale-filtro [name=data]').fill(date(1));await page.locator('#aut-personale-filtro button').click();await page.locator('#aut-regole').filter({hasText:'Settimana abituale'}).waitFor();
  await page.screenshot({path:`staging/ticket2-artifacts/regole-${width}.png`,fullPage:true});
  // Future closures can be edited and removed, using explicit reason and confirmation.
  await page.locator('[data-vista=servizio]').first().click();await page.locator('#aut-servizio-filtro [name=plesso_id]').selectOption('1');await page.locator('#aut-servizio-filtro button').click();await page.locator('#aut-chiusura').waitFor();
  await page.locator('#aut-chiusura [name=data_inizio]').fill(date(52));await page.locator('#aut-chiusura [name=data_fine]').fill(date(53));await page.locator('#aut-chiusura [name=motivazione]').fill('Chiusura revisione UI');await page.locator('#aut-chiusura button').click();await page.locator('#aut-servizio-stato').filter({hasText:'Chiusura registrata'}).waitFor();
  const closure=page.locator('#aut-servizio-corpo p').filter({hasText:'Chiusura revisione UI'});await closure.locator('[data-chiusura]').click();
  await page.locator('#aut-modifica-chiusura [name=data_fine]').fill(date(54));await page.locator('#aut-modifica-chiusura [name=motivazione]').fill('Periodo corretto UI');await page.locator('#aut-modifica-chiusura button').filter({hasText:'Salva chiusura'}).click();await page.locator('#aut-servizio-stato').filter({hasText:'Chiusura modificata'}).waitFor();
  const modified=page.locator('#aut-servizio-corpo p').filter({hasText:'Periodo corretto UI'});assert((await modified.innerText()).includes(date(54)));await modified.locator('[data-chiusura]').click();await page.locator('#aut-modifica-chiusura [name=motivazione]').fill('Eliminazione periodo di prova UI');await page.locator('#aut-elimina-chiusura').click();await page.locator('#aut-servizio-stato').filter({hasText:'Chiusura eliminata'}).waitFor();
  assert.equal(await page.locator('#aut-servizio-corpo p').filter({hasText:'Periodo corretto UI'}).count(),0);
  await login('scuola.infanzia.staging');await page.locator('[data-vista=personale]').first().click();
  await page.locator('#aut-personale-filtro [name=data]').fill(date(1));await page.locator('#aut-personale-filtro button').click();await page.locator('#aut-giorno [name="assistenti:1"]').waitFor();
  assert.equal(await page.locator('#aut-giorno [name="assistenti:1"]').inputValue(),'2');
  assert(!/cutoff|non consolidato/i.test(await page.locator('#aut-personale-stato').innerText()));
  await page.locator('#aut-giorno [name="assistenti:1"]').fill('4');await page.locator('#aut-giorno [name=motivazione]').fill('Due assistenti aggiuntivi domani');await page.locator('#aut-giorno button').filter({hasText:'Salva pasti'}).click();await page.locator('#aut-personale-stato').filter({hasText:'Pasti del personale salvati'}).waitFor();
  assert.equal(await page.locator('#aut-giorno [name="assistenti:1"]').inputValue(),'4');await page.locator('#aut-giorno [name=motivazione]').fill('Ritorno ai valori abituali');await page.locator('#aut-ripristina').click();await page.locator('#aut-personale-stato').filter({hasText:'Programmazione ripristinata'}).waitFor();assert.equal(await page.locator('#aut-giorno [name="assistenti:1"]').inputValue(),'2');
  await page.screenshot({path:`staging/ticket2-artifacts/scuola-futuro-${width}.png`,fullPage:true});
  await page.locator('#aut-personale-filtro [name=data]').fill(date(-1));await page.locator('#aut-personale-filtro button').click();await page.locator('#aut-personale-stato').filter({hasText:'Sola lettura'}).waitFor();assert.equal(await page.locator('#aut-giorno button').count(),0);
  assert.deepEqual(errors,[]);assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));
  console.log(`PASS revisione UI ${width}px: regole più giorni, variazione futura, ripristino, passato protetto, chiusura modificata/eliminata`);await page.close();
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
