/* Read-only export QA, using synthetic localhost accounts. */
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ExcelJS=require('../public/assets/js/vendor/exceljs-4.4.0.min.js');
const {rows}=require('../public/assets/js/report_export.js');
const base=process.env.MENSA_TICKET2_BASE||'http://127.0.0.1:18085';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const out='staging/ticket2-artifacts';fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.MENSA_BROWSER_CHANNEL?{channel:process.env.MENSA_BROWSER_CHANNEL}:{})});
 try {
  for(const width of [375,768,1366]) for(const role of ['operatore','admin','scuola','catering']) {
   const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto(base+'/login');await page.locator('[name=username]').fill(role==='scuola'?'scuola.infanzia.staging':role+'.staging');
   await page.locator('[name=password]').fill('Collaudo2026!');await page.locator('button[type=submit]').click();await page.waitForURL('**/'+role);
   await page.locator('[data-vista=riepilogo-pasti]').first().click();
   assert(await page.locator('#aut-export-excel').isDisabled());
   await page.locator('#aut-report [name=plesso_id]').selectOption('1');
   const response=page.waitForResponse(r=>r.url().includes('/api/report/pasti?'));
   await page.locator('#aut-report button').click();const body=await (await response).json();const report=body.dati;
   await page.locator('#aut-export-excel:not([disabled])').waitFor();
   // Changing unsent inputs must not relabel the result that was actually loaded.
   const originalDate=await page.locator('#aut-report [name=dal]').inputValue();
   await page.locator('#aut-report [name=dal]').fill('2020-01-01');
   const download=page.waitForEvent('download');await page.locator('#aut-export-excel').click();const file=await download;
   assert(file.suggestedFilename().includes(originalDate));const dest=path.join(out,`${role}-${width}.xlsx`);await file.saveAs(dest);
   const book=new ExcelJS.Workbook();await book.xlsx.load(fs.readFileSync(dest));const sheet=book.getWorksheet(1);
   assert(sheet.getCell('A2').value.includes(originalDate));assert(!sheet.getCell('A2').value.includes('2020-01-01'));
   assert.equal(sheet.rowCount,6+report.righe.length);
   const types=await page.evaluate(async()=> (await API.get('/api/riferimenti')).tipologie);
   const expected=rows(report,n=>types.find(t=>Number(t.id)===Number(n))?.nome||'Tipologia '+n);
   expected.forEach((row,i)=>row.forEach((v,j)=>assert.deepEqual(sheet.getCell(i+7,j+1).value,j===0?new Date(v+'T00:00:00Z'):v===''?null:v??'Non disponibile')));
   assert(report.righe.every(r=>r.plesso_id===1),'Scope del plesso');
   const csv=page.waitForEvent('download');await page.locator('#aut-export').click();const csvFile=await csv;await csvFile.saveAs(path.join(out,`${role}-${width}.csv`));
   assert(fs.readFileSync(path.join(out,`${role}-${width}.csv`),'utf8').includes('Dettaglio tipologie'));
   assert.deepEqual(errors,[]);assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth));
   await page.screenshot({path:path.join(out,`${role}-excel-${width}.png`),fullPage:true});
   console.log(`PASS Excel/CSV ${role} ${width}px: valori uguali all’API, filtri caricati, download e layout`);
   await page.close();
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
