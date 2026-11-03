/* Esportazione del risultato già autorizzato e caricato, senza nuove richieste dati. */
(() => {
 'use strict';
 const scriptUrl = typeof document !== 'undefined' ? document.currentScript.src : null;
 const headers = ['Data','Plesso','Ordine','Stato','Alunni','Personale','Totale','Dettaglio tipologie','Nota'];
 const categorie = {insegnanti:'Insegnanti',ata:'ATA',assistenti:'Assistenti mensa',asacom:'ASACOM'};
 function rows(report, nomeTipo) {
  return report.righe.map(r => [r.data,r.plesso_nome,r.ordine,r.stato,r.alunni,r.personale,r.totale,
   [...r.tipologie_alunni.map(t=>`Alunni ${t.tipologia_nome}: ${t.pasti}`),
    ...r.tipologie_personale.map(t=>`${categorie[t.categoria]} ${nomeTipo(t.tipologia_menu_id)}: ${t.quantita}`)].join(' | '),r.nota]);
 }
 const monthlyHeaders=['Mese','Dal','Al','Ordine','Alunni elaborati','Personale nelle giornate elaborate','Totale disponibile','Pasti previsti','Giornate complete','Giornate da verificare','Giornate previste'];
 function monthlyRows(report) {return (report.mensili||[]).map(m=>[m.mese,m.dal,m.al,m.ordine,m.alunni,m.personale,m.totale,m.previsti,m.giornate_complete,m.giornate_incomplete,m.giornate_previste]);}
 let loading;
 async function library() {
  if (globalThis.ExcelJS) return globalThis.ExcelJS;
  if (!loading) loading = new Promise((resolve,reject) => {
   const script=document.createElement('script');
   script.src=new URL('vendor/exceljs-4.4.0.min.js',scriptUrl).href;
   script.onload=()=>resolve(globalThis.ExcelJS);
   script.onerror=()=>{script.remove();loading=null;reject(new Error('Impossibile caricare l’esportazione Excel. Riprova.'));};
   document.head.append(script);
  });
  return loading;
 }
 function workbook(ExcelJS,report,filters,nomeTipo) {
  const book=new ExcelJS.Workbook();
  book.creator='Comune di Caltavuturo';
  const sheet=book.addWorksheet('Riepilogo pasti',{
   views:[{state:'frozen',ySplit:6,showGridLines:false}],
   pageSetup:{orientation:'landscape',paperSize:9,fitToPage:true,fitToWidth:1,fitToHeight:0,printTitlesRow:'1:6'}
  });
  const widths=[14,32,18,21,18,18,18,60,60];
  widths.forEach((width,i)=>sheet.getColumn(i+1).width=width);
  const t=report.totali;
  const texts=[
   'Comune di Caltavuturo · Riepilogo pasti',
   `Periodo: ${filters.dal} – ${filters.al} · Plesso: ${filters.plesso} · Ordine: ${filters.ordine}`,
   `Alunni elaborati: ${t.alunni_confermati} · Personale nelle giornate elaborate: ${t.personale_confermato} · Totale giornate complete: ${t.totale_confermato} · Giornate/plessi incompleti: ${t.giorni_non_consolidati}`,
   'I totali disponibili escludono le giornate incomplete e provvisorie; le rettifiche del personale aggiornano i conteggi. “Non disponibile” indica un dato mancante, non zero.'
  ];
  texts.forEach((text,i)=>{
   sheet.mergeCells(i+1,1,i+1,9);
   const cell=sheet.getCell(i+1,1);cell.value=text;
   cell.font={name:'Calibri',size:i===0?16:11,bold:i===0,color:{argb:i===0?'FFFFFFFF':'FF334155'}};
   cell.alignment={vertical:'middle',wrapText:true};
   cell.fill={type:'pattern',pattern:'solid',fgColor:{argb:i===0?'FF164E63':'FFF1F5F9'}};
   sheet.getRow(i+1).height=i===0?32:30;
  });
  const header=sheet.getRow(6);header.values=headers;header.height=30;
  header.eachCell(cell=>{
   cell.font={name:'Calibri',size:11,bold:true,color:{argb:'FFFFFFFF'}};
   cell.fill={type:'pattern',pattern:'solid',fgColor:{argb:'FF164E63'}};
   cell.alignment={vertical:'middle',wrapText:true};
  });
  rows(report,nomeTipo).forEach((values,i)=>{
   // String values remain strings (including =, +, - and @); never create formulas.
   const row=sheet.addRow(values.map((v,j)=>j===0?new Date(v+'T00:00:00Z'):v===''?null:v??'Non disponibile'));
   let lines=1;
   row.eachCell({includeEmpty:true},(cell,j)=>{
    cell.font={name:'Calibri',size:11,color:{argb:'FF1E293B'}};
    cell.alignment={vertical:'top',wrapText:true};
    cell.fill={type:'pattern',pattern:'solid',fgColor:{argb:i%2?'FFF1F5F9':'FFFFFFFF'}};
    if (j===1) cell.numFmt='dd/mm/yyyy';
    if (j>=5 && j<=7 && typeof cell.value==='number') {cell.numFmt='0';cell.alignment.horizontal='right';}
    lines=Math.max(lines,Math.ceil(String(values[j-1]??'Non disponibile').length/Math.max(8,widths[j-1]-4)));
   });
   row.height=Math.min(409,Math.max(30,lines*16+8));
   const colors={'Confermato':'FFDCFCE7','Previsto':'FFDBEAFE','Non elaborato':'FFFEF3C7','Fuori servizio':'FFE2E8F0'};
   row.getCell(4).fill={type:'pattern',pattern:'solid',fgColor:{argb:colors[values[3]]||'FFFFFFFF'}};
  });
  sheet.autoFilter={from:'A6',to:`I${Math.max(6,sheet.rowCount)}`};
  if (!report.righe.length) {sheet.mergeCells('A7:I7');sheet.getCell('A7').value='Nessun dato per i filtri selezionati.';}
  const monthly=book.addWorksheet('Riepilogo mensile',{views:[{state:'frozen',ySplit:5,showGridLines:false}],pageSetup:{orientation:'landscape',paperSize:9,fitToPage:true,fitToWidth:1,fitToHeight:0,printTitlesRow:'1:5'}});
  [13,14,14,22,20,26,20,20,20,22,20].forEach((width,i)=>monthly.getColumn(i+1).width=width);
  ['Comune di Caltavuturo · Riepilogo mensile',texts[1], 'Totali limitati alle date selezionate. Totale disponibile: solo giornate complete. Verificare le colonne delle giornate mancanti e previste.'].forEach((value,i)=>{
   monthly.mergeCells(i+1,1,i+1,11);const cell=monthly.getCell(i+1,1);cell.value=value;cell.style=sheet.getCell(i+1,1).style;monthly.getRow(i+1).height=i===0?32:30;
  });
  const mh=monthly.getRow(5);mh.values=monthlyHeaders;mh.height=44;mh.eachCell(cell=>cell.style=sheet.getCell('A6').style);
  monthlyRows(report).forEach((values,i)=>{
   const row=monthly.addRow(values);row.height=28;
   row.eachCell((cell,j)=>{cell.font={name:'Calibri',size:11};cell.alignment={vertical:'middle',wrapText:true};cell.fill={type:'pattern',pattern:'solid',fgColor:{argb:i%2?'FFF1F5F9':'FFFFFFFF'}};if(j===2||j===3){cell.value=new Date(values[j-1]+'T00:00:00Z');cell.numFmt='dd/mm/yyyy';}if(j>=5)cell.numFmt='0';});
  });
  monthly.autoFilter={from:'A5',to:`K${Math.max(5,monthly.rowCount)}`};
  if(!(report.mensili||[]).length){monthly.mergeCells('A6:K6');monthly.getCell('A6').value='Nessun dato per i filtri selezionati.';}
  return book;
 }
 function download(data,type,name) {
  const url=URL.createObjectURL(new Blob([data],{type}));
  const a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();
  setTimeout(()=>URL.revokeObjectURL(url),1000);
 }
 async function excel(report,filters,nomeTipo) {
  const book=workbook(await library(),report,filters,nomeTipo);
  download(await book.xlsx.writeBuffer(),'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',`riepilogo-pasti_${filters.dal}_${filters.al}.xlsx`);
 }
 const api={headers,rows,monthlyHeaders,monthlyRows,workbook,excel,download};
 if (typeof module!=='undefined' && module.exports) module.exports=api;
 else globalThis.MensaReportExport=api;
})();
