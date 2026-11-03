const assert=require('node:assert/strict');
const ExcelJS=require('../public/assets/js/vendor/exceljs-4.4.0.min.js');
const {workbook}=require('../public/assets/js/report_export.js');
const filters={dal:'2026-11-01',al:'2026-11-03',plesso:'Infanzia',ordine:'infanzia'};
const base={data:'2026-11-03',plesso_nome:'Infanzia à & è',ordine:'infanzia',stato:'Confermato',alunni:0,personale:null,totale:null,tipologie_alunni:[],tipologie_personale:[],nota:'Tipologie non storicizzate'};
const totals={alunni_confermati:0,personale_confermato:0,totale_confermato:0,giorni_non_consolidati:1};
(async()=>{
 const report={righe:[base,...['=1+1','+SUM(A1)','-1+1','@SUM(A1)'].map(plesso_nome=>({...base,plesso_nome}))],totali:totals};
 const bytes=await workbook(ExcelJS,report,filters,()=> 'Ordinario').xlsx.writeBuffer();
 const loaded=new ExcelJS.Workbook();await loaded.xlsx.load(bytes);
 const s=loaded.getWorksheet('Riepilogo pasti');
 assert.equal(s.getCell('A7').value.toISOString(),'2026-11-03T00:00:00.000Z');
 assert.equal(s.getCell('A7').numFmt,'dd/mm/yyyy');
 assert.equal(s.getCell('E7').value,0);assert.equal(s.getCell('F7').value,'Non disponibile');assert.equal(s.getCell('G7').value,'Non disponibile');
 assert.equal(s.getCell('B7').value,base.plesso_nome);assert.equal(s.getCell('I7').value,base.nota);
 for(let i=1;i<report.righe.length;i++) {assert.equal(s.getCell(i+7,2).value,report.righe[i].plesso_nome);assert.equal(s.getCell(i+7,2).type,ExcelJS.ValueType.String);}
 s.eachRow(row=>row.eachCell(cell=>assert.notEqual(cell.type,ExcelJS.ValueType.Formula)));
 assert.equal(s.autoFilter,'A6:I11');assert.equal(s.views[0].ySplit,6);assert.equal(s.getCell('A6').font.bold,true);
 assert.notEqual(s.getCell('B7').fill.fgColor.argb,s.getCell('B8').fill.fgColor.argb);
 const empty=new ExcelJS.Workbook();await empty.xlsx.load(await workbook(ExcelJS,{righe:[],totali:totals},filters,()=> '').xlsx.writeBuffer());
 assert.match(empty.getWorksheet(1).getCell('A7').value,/Nessun dato/);
 console.log('PASS XLSX: file riaperto, date, numeri, zero/null, Unicode, testi non eseguibili, filtri, stili, report vuoto');
})().catch(e=>{console.error(e);process.exit(1)});
