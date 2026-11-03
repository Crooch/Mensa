const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18090';assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{const b=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});try{
for(const width of [375,1366]){
 const p=await b.newPage({viewport:{width,height:950}}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.goto(base+'/login');await p.locator('[name=username]').fill('admin.staging');await p.locator('[name=password]').fill('Collaudo2026!');await p.locator('button[type=submit]').click();await p.waitForURL('**/admin');
 await p.locator('[data-vista=email]').click();await p.locator('#email-ambiente').filter({hasText:'Ambiente demo'}).waitFor();
 await p.locator('#modello-plesso').selectOption('1');await p.locator('#modello-oggetto').fill('Prova browser '+width+' {plesso}');await p.locator('#modello-testo').fill('Gentile {genitore}\n{alunno}: {saldo}, fido {fido}, {data}. <img src=x onerror=alert(1)>');await p.locator('#modello-attivo').check();
 assert(await p.locator('#btn-email-prova').isDisabled());
 const save=p.waitForResponse(r=>r.url().endsWith('/api/admin/modelli-email')&&r.request().method()==='GET');await p.locator('#form-modello button').click();await save;await p.waitForFunction(()=>!document.getElementById('btn-email-prova').disabled);
 await p.locator('#smtp-host').fill('smtp.example.invalid');await p.locator('#smtp-port').fill('587');await p.locator('#smtp-from').fill('mensa@example.invalid');
 await p.locator('#smtp-password').fill('browser-password');assert(await p.locator('#btn-smtp-verifica').isDisabled());
 const config=p.waitForResponse(r=>r.url().endsWith('/api/admin/impostazioni')&&r.request().method()==='GET');await p.locator('#form-smtp button[type=submit]').click();await config;await p.waitForFunction(()=>document.getElementById('smtp-password').value==='********');
 await p.locator('#btn-smtp-verifica').click();await p.locator('#smtp-verifica-stato').filter({hasText:'nessun server contattato'}).waitFor();
 await p.locator('#email-prova-plesso').selectOption('1');await p.locator('#email-prova-indirizzo').fill('test@example.invalid');
 const req=p.waitForRequest(r=>r.url().endsWith('/api/admin/email-prova'));await p.locator('#btn-email-prova').click();assert.deepEqual((await req).postDataJSON(),{email:'test@example.invalid',plesso_id:1});
 await p.locator('#email-prova-stato').filter({hasText:'Prova simulata: nessuna email spedita'}).waitFor();
 assert((await p.locator('#email-prova-testo').innerText()).includes('Genitore di prova'));assert((await p.locator('#email-prova-testo').innerText()).includes('<img'));assert.equal(await p.locator('#email-prova-anteprima img').count(),0);
 // Unsaved SMTP changes survive selecting another model and returning to the tab.
 await p.locator('#smtp-host').fill('draft.example.invalid');await p.locator('#modello-plesso').selectOption('3');assert.equal(await p.locator('#smtp-host').inputValue(),'draft.example.invalid');assert(await p.locator('#btn-email-prova').isDisabled());
 await p.locator('#modello-plesso').selectOption('1');await p.locator('#smtp-host').fill('smtp.example.invalid');await p.waitForFunction(()=>!document.getElementById('btn-email-prova').disabled);
 // Display server errors, never a success toast for a rejected send.
 await p.route('**/api/admin/email-prova',r=>r.fulfill({status:422,contentType:'application/json',body:JSON.stringify({ok:false,errore:'Autenticazione SMTP rifiutata.'})}));
 await p.locator('#btn-email-prova').click();await p.locator('#email-prova-stato').filter({hasText:'Autenticazione SMTP rifiutata'}).waitFor();assert(await p.locator('#email-prova-anteprima').isHidden());await p.unroute('**/api/admin/email-prova');
 await p.route('**/api/admin/impostazioni',r=>r.request().method()==='POST'?r.fulfill({status:422,contentType:'application/json',body:JSON.stringify({ok:false,errore:'Configurazione incompleta'})}):r.continue());
 await p.locator('#email-automatici').check();await p.locator('#form-email-automatici button').click();await p.locator('#email-automatici-stato').filter({hasText:'Configurazione incompleta'}).waitFor();await p.waitForFunction(()=>!document.getElementById('email-automatici').checked);await p.unroute('**/api/admin/impostazioni');
 await p.locator('#email-automatici').check();await p.locator('#form-email-automatici button').click();await p.locator('#email-automatici-stato').filter({hasText:'Solleciti automatici abilitati.'}).waitFor();
 await p.reload();await p.locator('[data-vista=email]').click();await p.locator('#email-automatici-stato').filter({hasText:'Solleciti automatici abilitati.'}).waitFor();assert(await p.locator('#email-automatici').isChecked());
 await p.locator('#email-automatici').uncheck();await p.locator('#form-email-automatici button').click();await p.locator('#email-automatici-stato').filter({hasText:'Solleciti automatici disabilitati.'}).waitFor();
 assert(!await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth));assert.deepEqual(errors,[]);fs.mkdirSync('staging/ticket2-artifacts',{recursive:true});await p.screenshot({path:`staging/ticket2-artifacts/email-admin-${width}.png`,fullPage:true});console.log(`PASS email Admin ${width}px: save/mask, model selection, draft guard, truthful SMTP/send simulation, safe preview, failure, activation persisted and disabled`);await p.close();
}
}finally{await b.close();}})().then(()=>process.exit(0)).catch(e=>{console.error(e);process.exit(1)});
