import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Le scritte devono spiegare il motivo reale senza gergo o dettagli superflui.
const sorgente = readFileSync('public/assets/js/genitore.js', 'utf8');
const inizio = sorgente.indexOf('const motivoChiusura = ');
const fine = sorgente.indexOf(';', inizio);
assert.ok(inizio >= 0 && fine > inizio, 'Mappatura cause non trovata');
const motivoChiusura = vm.runInNewContext(sorgente.slice(inizio, fine + 1) + '\nmotivoChiusura');
const previsti = {
    fine_settimana: 'Fine settimana',
    festivita_nazionale: 'Festività',
    chiusura_programmata: 'Mensa chiusa',
    giorno_senza_servizio: 'Mensa non prevista',
    servizio_non_avviato: 'Servizio non iniziato',
    servizio_terminato: 'Servizio terminato',
    calendario_non_configurato: 'Calendario da definire',
    nessuna_classe: 'Scuola non assegnata'
};
for (const [causa, etichetta] of Object.entries(previsti)) {
    assert.equal(motivoChiusura(causa), etichetta, 'Motivo ' + causa);
}
assert.ok(!sorgente.includes("'Niente mensa'"), 'Etichetta ambigua rimasta nel JS');
const pagina = readFileSync('src/View/genitore.php', 'utf8');
assert.ok(!pagina.includes('I giorni senza indicazioni non sono necessariamente giorni di chiusura'),
    'Dicitura superflua rimasta nella pagina');
const css = readFileSync('public/assets/css/app.css', 'utf8');
assert.match(css, /\.campo-filtro\s*\{/);
assert.match(css, /\.tabella-scroll\s*\{[^}]*overflow-x:\s*auto/s);
assert.match(css, /overflow-wrap:\s*anywhere/);
console.log('OK: motivazioni concise, testi superflui rimossi e regole anti-overflow presenti.');

const common = readFileSync('public/assets/js/api.js', 'utf8');
const funzioneFido = vm.runInNewContext(
    common.slice(common.indexOf('const fidoConcesso ='), common.indexOf(';', common.indexOf('const fidoConcesso =')) + 1) + '\nfidoConcesso'
);
assert.equal(funzioneFido(5), '-5 buoni');
assert.equal(funzioneFido(0), '0 buoni');
assert.equal(funzioneFido(-5), '-5 buoni');
const genitore = readFileSync('public/assets/js/genitore.js', 'utf8');
const operatore = readFileSync('public/assets/js/operatore.js', 'utf8');
const amministratore = readFileSync('public/assets/js/admin.js', 'utf8');
assert.match(genitore, /<dt>Fido concesso<\/dt><dd>\$\{fidoConcesso\(c\.fido_buoni\)\}<\/dd>/);
assert.match(operatore, /<dt>Fido concesso<\/dt><dd>\$\{fidoConcesso\(c\.fido_buoni\)\}<\/dd>/);
assert.match(amministratore, /fido_buoni: Math\.abs\(Number\(document\.getElementById\('plesso-fido'\)\.value\)\)/);
console.log('OK: fido concesso mostrato negativo con unità buoni; salvataggio in formato DB invariato.');

assert.ok(!genitore.includes('Ripristina pasto') && !genitore.includes('Ripristina il pasto'),
    'Ripristina pasto è ambiguo per le famiglie');
assert.ok(!genitore.includes('<details><summary>Pasta in bianco'),
    'Le richieste di pasta non devono richiedere un menu a scomparsa');
assert.match(genitore, /data-testid="dichiara-presente-\$\{f.id\}" hidden disabled>Segnala presenza/);
assert.match(genitore, /b\.dataset\.pasta === '1' && presente && !g\.pasta_in_bianco/);
assert.match(genitore, /b\.dataset\.pasta === '0' && presente && !!g\.pasta_in_bianco/);
assert.match(genitore, /if \(presente\) \{[\s\S]*?data-cal-pasta="1"/);
assert.match(genitore, /else if \(assente && !assenzaDellaScuola\)/);
console.log('OK: azioni genitore contestuali, pasta solo con pasto e senza menu a scomparsa.');

assert.match(genitore, /class="card-mensa card-figlio"/);
assert.match(genitore, /class="figlio-comandi"/);
assert.match(genitore, /class="figlio-selezione-data"/);
assert.match(genitore, /class="stato-operazione figlio-stato"/);
assert.match(genitore, /class="figlio-azioni" hidden/);
assert.match(genitore, /class="btn btn-outline-primary btn-sm figlio-calendario"/);
assert.match(genitore, /gruppoAzioni\.hidden = disponibili === 0/);
assert.match(genitore, /classList\.toggle\('figlio-azioni--singola', disponibili === 1\)/);
assert.match(css, /#figli-lista\s*\{\s*align-items:\s*stretch/);
assert.match(css, /#figli-lista\s+\.figlio-comandi\s*\{[^}]*margin-top:\s*auto/s);
assert.match(css, /#figli-lista\s+\.figlio-azioni\s*\{[^}]*grid-template-columns:\s*repeat\(2,\s*minmax\(0,\s*1fr\)\)/s);
assert.match(css, /#figli-lista\s+\.figlio-calendario\s*\{\s*margin:\s*0/);
assert.match(css, /@media\s*\(max-width:\s*600px\)[\s\S]*#figli-lista\s+\.figlio-azioni\s*\{\s*grid-template-columns:\s*minmax\(0,\s*1fr\)/);
console.log('OK: layout famiglia con stato separato, azioni allineate e calendario a tutta larghezza.');

assert.match(genitore, /const colore = !attivo \? 'non-previsto'[\s\S]*?: presente \? 'ok' : assente \? 'assente' : 'verifica'/);
assert.match(genitore, /const coloreStato = !attivo \? 'non-previsto'[\s\S]*?: presente \? 'ok' : assente \? 'assente' : 'verifica'/);
assert.match(css, /\.stato-operazione--assente\s*\{[^}]*background:\s*#fff0ed/);
assert.match(css, /\.stato-operazione--non-previsto\s*\{[^}]*background:\s*#f0f2f5/);
assert.match(css, /#figli-lista\s+\.figlio-selezione-data\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\) minmax\(0, 210px\)/s);
assert.match(css, /#figli-lista\s+\.figlio-selezione-data input\[type="date"\]\s*\{[^}]*max-width:\s*210px/s);
assert.match(css, /@container \(max-width: 370px\)/);
for (const wf of ['.github/workflows/php-checks.yml', '.github/workflows/staging-smoke.yml']) {
    const workflow = readFileSync(wf, 'utf8');
    assert.match(workflow, /uses:\s*actions\/checkout@v5/);
    assert.match(workflow, /runs-on:\s*ubuntu-latest/);
    assert.ok(!workflow.includes('actions/checkout@v4'));
}
console.log('OK: colori coerenti, selettore data responsive, checkout@v5 e runner invariato.');

/* Notifiche comuni: struttura, chiusura e allineamento indipendenti dal font. */
const estrattoAvviso = common.slice(
    common.indexOf('function avviso('),
    common.indexOf('/** Componenti condivisi', common.indexOf('function avviso('))
);
assert.ok(estrattoAvviso.startsWith('function avviso('));
const messaggi = [];
const creaElemento = (nome) => ({
    tagName: nome.toUpperCase(), dataset: {}, attributes: {}, children: [],
    textContent: '', className: '', removed: false, listeners: {},
    setAttribute(k, v) { this.attributes[k] = v; },
    appendChild(el) { this.children.push(el); },
    addEventListener(k, cb) { this.listeners[k] = cb; },
    remove() { this.removed = true; }
});
const contenitoreAvvisi = creaElemento('div');
const ambienteAvvisi = {
    document: {
        getElementById: (id) => id === 'avvisi' ? contenitoreAvvisi : null,
        createElement: creaElemento
    },
    setTimeout: (f, durata) => { messaggi.push({ f, durata }); },
    alert: () => { throw new Error('Toast container missing'); }
};
const mostraAvviso = vm.runInNewContext(estrattoAvviso + '\navviso', ambienteAvvisi);
mostraAvviso('Comunicazione salvata', 'ok');
assert.equal(contenitoreAvvisi.children.length, 1);
const toast = contenitoreAvvisi.children[0];
assert.equal(toast.children[0].className, 'avviso__testo');
assert.equal(toast.children[0].textContent, 'Comunicazione salvata');
assert.equal(toast.children[1].className, 'avviso__chiudi');
assert.equal(toast.children[1].textContent, '', 'La X non deve dipendere dal font');
assert.equal(toast.children[1].attributes['aria-label'], 'Chiudi messaggio');
assert.equal(messaggi.length, 1);
toast.children[1].listeners.click();
assert.equal(toast.removed, true, 'Pulsante chiusura non funzionante');
mostraAvviso('Operazione non riuscita', 'errore');
assert.equal(contenitoreAvvisi.children[1].attributes.role, 'alert');
assert.equal(messaggi.length, 1, 'Gli errori devono rimanere visibili');
assert.match(css, /#avvisi \.avviso\s*\{[^}]*align-items:\s*center/s);
assert.match(css, /#avvisi \.avviso__testo\s*\{[^}]*flex:\s*1 1 auto/s);
assert.match(css, /#avvisi \.avviso__chiudi\s*\{[^}]*display:\s*inline-flex/s);
assert.match(css, /#avvisi \.avviso__chiudi::before,[\s\S]*#avvisi \.avviso__chiudi::after\s*\{[^}]*top:\s*50%;[^}]*left:\s*50%/s);
console.log('OK: notifiche allineate, X geometrica centrata, chiusura funzionante e messaggi di errore persistenti.');

/* Controllo globale: il selettore data non deve essere curato solo per il genitore.
   La stessa regola deve valere per tutte le schermate e per i modali dinamici. */
const inizioDate = css.indexOf('/* Componente comune data/mese/ora:');
assert.ok(inizioDate >= 0, 'Manca lo stile globale dei selettori data');
const dateCss = css.slice(inizioDate);
for (const tipo of ['date', 'month', 'time', 'datetime-local']) {
    assert.ok(dateCss.includes('input[type="' + tipo + '"]'),
        'Stile condiviso assente per il campo ' + tipo);
}
assert.match(dateCss, /width:\s*min\(100%, 220px\)\s*!important/);
assert.match(dateCss, /border:\s*1px solid #91a9bd\s*!important/);
assert.match(dateCss, /border-bottom:\s*1px solid #91a9bd\s*!important/);
assert.match(dateCss, /border-radius:\s*8px\s*!important/);
assert.match(dateCss, /::-webkit-calendar-picker-indicator\s*\{[^}]*margin-left:\s*auto/s);
assert.match(css, /#figli-lista\s+\.figlio-selezione-data input\[type="date"\]\s*\{\s*max-width:\s*210px/);
for (const vista of ['genitore', 'scuola', 'catering', 'admin']) {
    const html = readFileSync('src/View/' + vista + '.php', 'utf8');
    assert.ok(html.includes('type="date"') || html.includes('type="month"'),
        'Nessun campo data nella schermata ' + vista);
}
assert.match(readFileSync('public/assets/js/operatore.js', 'utf8'), /type="date"/);
console.log('OK: selettori data/mese/ora condivisi tra tutti i profili e modali, responsive.');

/* Modale motivazione: validazione reale, annullamento e testo ripulito. */
const estrattoUI = common.slice(common.indexOf('const UI ='), common.indexOf('\n};', common.indexOf('const UI =')) + 3);
const modaliAperti = [];
const nodoFinto = () => ({
    value: '', hidden: true, attributi: {}, listener: {}, testo: '', focusCount: 0,
    setAttribute(nome, valore) { this.attributi[nome] = valore; },
    removeAttribute(nome) { delete this.attributi[nome]; },
    addEventListener(nome, cb) { this.listener[nome] = cb; },
    focus() { this.focusCount++; },
    remove() { this.rimosso = true; }
});
const creaModale = () => {
    const selettori = Object.fromEntries([
        'h2', '.motivo-dialogo__testo', 'textarea', '.motivo-dialogo__errore',
        '[data-annulla]', '.motivo-dialogo__chiudi', 'form'
    ].map(k => [k, nodoFinto()]));
    const dialogo = {
        ...nodoFinto(), selettori,
        querySelector(selettore) { return selettori[selettore]; },
        showModal() { this.aperto = true; },
        close() { this.aperto = false; }
    };
    modaliAperti.push(dialogo);
    return dialogo;
};
const uiModale = vm.runInNewContext(estrattoUI + '\nUI', {
    document: { createElement: creaModale, body: { appendChild() {} } }
});
const richiestaVuota = uiModale.chiediMotivazione('Segna assente', 'Motivo obbligatorio');
const dialogoVuoto = modaliAperti.at(-1);
assert.equal(dialogoVuoto.aperto, true);
assert.equal(dialogoVuoto.selettori['textarea'].focusCount, 1);
dialogoVuoto.selettori['form'].listener.submit({ preventDefault() {} });
assert.equal(dialogoVuoto.selettori['.motivo-dialogo__errore'].hidden, false);
assert.equal(dialogoVuoto.selettori['textarea'].attributi['aria-invalid'], 'true');
assert.equal(dialogoVuoto.aperto, true, 'Non deve chiudere il modale con campo vuoto');
dialogoVuoto.selettori['textarea'].value = '  Non si è presentato  ';
dialogoVuoto.selettori['textarea'].listener.input();
dialogoVuoto.selettori['form'].listener.submit({ preventDefault() {} });
assert.equal(await richiestaVuota, 'Non si è presentato');
assert.equal(dialogoVuoto.rimosso, true);
const richiestaAnnullata = uiModale.chiediMotivazione('Correzione scuola');
const dialogoAnnullato = modaliAperti.at(-1);
dialogoAnnullato.selettori['[data-annulla]'].listener.click();
assert.equal(await richiestaAnnullata, null);
const richiestaEscape = uiModale.chiediMotivazione('Correzione scuola');
const dialogoEscape = modaliAperti.at(-1);
let prevenuto = false;
dialogoEscape.listener.cancel({ preventDefault() { prevenuto = true; } });
assert.equal(prevenuto, true);
assert.equal(await richiestaEscape, null);
console.log('OK: motivazione obbligatoria, trim, conferma, annullamento ed Escape.');

/* Select condivisi: icona non nativa centrata (Chrome, Safari, mobile)
   e identica tra le diverse aree, senza modificare il valore o il picker. */
const selettoriCss = css.slice(css.indexOf('/* Select condiviso:'));
assert.ok(selettoriCss.startsWith('/* Select condiviso:'),
    'Manca il componente select condiviso');
assert.match(selettoriCss, /select:not\(\[multiple\]\):not\(\[size\]\)\s*\{[^}]*-webkit-appearance:\s*none/s);
assert.match(selettoriCss, /select:not\(\[multiple\]\):not\(\[size\]\)\s*\{[^}]*appearance:\s*none/s);
assert.match(selettoriCss, /background-image:\s*url\("data:image\/svg\+xml,/);
assert.match(selettoriCss, /background-position:\s*right\s+\.85rem\s+center\s*!important/);
assert.match(selettoriCss, /padding:\s*\.55rem\s+2\.65rem\s+\.55rem\s+\.75rem\s*!important/);
assert.match(selettoriCss, /min-height:\s*46px/);
assert.match(selettoriCss, /select:not\(\[multiple\]\):not\(\[size\]\):focus-visible/);
for (const vista of ['scuola', 'catering', 'admin']) {
    assert.match(readFileSync('src/View/' + vista + '.php', 'utf8'), /<select\b/,
        'La regola comune deve coprire l\'area ' + vista);
}
assert.match(readFileSync('public/assets/js/operatore.js', 'utf8'), /<select\b/,
    'I select dinamici operatore devono ereditare la stessa regola');
console.log('OK: freccia select unica, centrata e accessibile in tutti i profili.');

/* Catering: filtri raggruppati e allineati per report, menu e feedback;
   layout responsivo e modalita' di salvataggio visibile. */
const cateringHtml = readFileSync('src/View/catering.php', 'utf8');
const cateringJs = readFileSync('public/assets/js/catering.js', 'utf8');
for (const variante of ['report', 'menu', 'feedback']) {
    assert.ok(cateringHtml.includes('catering-filtri--' + variante),
        'Filtri Catering non raggruppati: ' + variante);
}
for (const id of ['menu-selezione-stato', 'menu-foto-widget', 'menu-submit']) {
    assert.ok(cateringHtml.includes('id="' + id + '"'), 'Campo Catering mancante: ' + id);
}
assert.match(cateringJs, /'Salva modifica'\s*:\s*'Pubblica menu'/);
assert.match(cateringJs, /Puoi modificarla o annullarla senza cambiare gli altri giorni/);
assert.equal((cateringHtml.match(/Se presente, il menù del giorno prevale su quello settimanale\./g) || []).length, 2,
    'La regola di precedenza deve comparire in entrambe le sezioni');
assert.ok(!cateringHtml.includes('lo storico dei pasti già somministrati non viene riscritto'),
    'La spiegazione superflua sullo storico deve essere rimossa');
assert.match(cateringJs, /È passato l’orario limite per modificare il menù di oggi/);
assert.doesNotMatch(cateringJs, /cutoff superato/i);
assert.doesNotMatch(cateringJs, /Menu non disponibile/);
assert.match(readFileSync('public/assets/js/menu_foto.js', 'utf8'), /Sostituisci foto/);
assert.match(cateringJs, /data-menu-modifica="/);
assert.match(cateringJs, /UI\.conferma\(/);
const cssCatering = css.slice(css.indexOf('/* Catering: griglie uniformi'));
assert.ok(cssCatering.startsWith('/* Catering: griglie uniformi'));
assert.match(cssCatering, /\.catering-filtri--report\s*\{[^}]*grid-template-columns:/s);
assert.match(cssCatering, /\.catering-filtri--menu\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*210px\)\s+repeat\(2,/s);
assert.match(cssCatering, /\.catering-filtri--feedback\s*\{[^}]*grid-template-columns:/s);
assert.match(cssCatering, /@media \(max-width: 980px\)/);
assert.match(cssCatering, /@media \(max-width: 600px\)/);
assert.match(cssCatering, /@media \(max-width: 600px\)[\s\S]*\.catering-filtri--report,[\s\S]*\.catering-filtri--menu,[\s\S]*\.catering-filtri--feedback\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\);/);
console.log('OK: Catering allineato desktop/mobile; pubblicazione e modifica distinguibili.');

/* Foto unica per menu giornaliero e ciascun giorno di menu settimanale. */
const menuCss = css.slice(css.indexOf('/* Stesso controllo fotografico'));
assert.ok(menuCss.startsWith('/* Stesso controllo fotografico'));
assert.ok(css.includes('white-space: pre-wrap'));
const photoJs = readFileSync('public/assets/js/menu_foto.js', 'utf8');
assert.ok(photoJs.includes("Aggiungi foto"));
assert.ok(photoJs.includes("Sostituisci foto"));
assert.ok(photoJs.includes("Annulla rimozione"));
assert.ok(photoJs.includes("inRimozione = true"));
assert.ok(cateringHtml.includes('id="menu-foto-widget"'));
assert.ok(cateringJs.includes("FotoMenu.crea('menu-foto-widget')"));
assert.ok(cateringJs.includes("fd.append('rimuovi_foto', rimuoviFoto ? '1' : '0')"));
assert.ok(cateringHtml.includes('Puoi lasciare vuoti i giorni senza menu.'));
assert.ok(!cateringHtml.includes('Gli a capo saranno mantenuti.'));
for (let g = 1; g <= 7; g++) {
    assert.ok(cateringHtml.includes('id="settimana-foto-widget-' + g + '"'));
}
console.log('OK: foto uguali nei due moduli e testo richiesto semplificato.');

/* Settimane tipo: catalogo, duplicazione, assegnazione e modifica responsive. */
const settimanePagina = cateringHtml;
const settimaneJS = readFileSync('public/assets/js/catering_settimane.js', 'utf8');
for (const id of ['vista-settimane', 'form-settimana', 'settimana-nome', 'settimana-tipo',
                 'settimane-elenco', 'assegna-modello', 'assegna-plessi', 'assegna-salva',
                 'assegnazioni-elenco']) {
    assert.ok(settimanePagina.includes('id="' + id + '"'), 'Controllo catalogo mancante: ' + id);
}
for (let i = 1; i <= 7; i++) {
    assert.ok(settimanePagina.includes('id="settimana-giorno-' + i + '"'));
}
assert.ok(settimaneJS.includes("data-settimana-modifica"));
assert.ok(settimaneJS.includes("data-settimana-duplica"));
assert.ok(settimaneJS.includes("/api/catering/menu-settimanali/assegnazioni"));
assert.ok(settimaneJS.includes('Le modifiche al modello saranno applicate a partire da domani'));
const cssSettimane = css.slice(css.indexOf('/* Catalogo di settimane tipo'));
assert.ok(cssSettimane.startsWith('/* Catalogo di settimane tipo'));
assert.ok(cssSettimane.includes('max-width: 940px'));
assert.ok(cssSettimane.includes('max-width: 600px'));
console.log('OK: catalogo settimanale con sette giorni e assegnazioni, desktop/mobile.');

assert.ok(cateringHtml.includes('id="menu-data"'), 'La data del menu deve essere selezionabile');
assert.ok(cateringHtml.includes('id="menu-variazioni-lista"'), 'Le variazioni future devono essere elencate');
assert.ok(!cateringHtml.includes('menu-variazioni-stato'),
    'Non mostrare un contatore o un messaggio sotto il titolo delle variazioni');
assert.ok(!cateringHtml.includes('Dalle date più lontane alle più vicine.'),
    'La spiegazione sull\'ordinamento è superflua');
assert.ok(!cateringHtml.includes('Solo modifiche specifiche, ordinate per data.'),
    'Non ripristinare la vecchia spiegazione');
assert.doesNotMatch(cateringJs, /variazioni da oggi in poi|variazioni programmate · 10 per pagina|Caricamento delle variazioni…/);
assert.doesNotMatch(cateringJs, /stato\.textContent\s*=\s*totale/);
assert.ok(cateringJs.includes("'/api/catering/menu/annulla'"), 'Annullamento disponibile per le variazioni');
console.log('OK: scelta data, modifica e annullamento delle variazioni programmate.');

/* Amministratore: regressioni visive segnalate nel collaudo manuale. */
const adminPaginaRivista = readFileSync('src/View/admin.php', 'utf8');
const adminJsRivisto = readFileSync('public/assets/js/admin.js', 'utf8');
assert.match(adminPaginaRivista, /class="admin-calendario-giorni__griglia"/);
assert.match(adminPaginaRivista, /class="admin-calendario-date"/);
assert.match(adminPaginaRivista, /id="classe-esistente"/);
assert.match(adminPaginaRivista, /<select id="classe-nome"/);
assert.match(adminPaginaRivista, /id="classe-altro-gruppo" hidden/);
assert.match(adminJsRivisto, /function aggiornaSceltaClasse\(/);
assert.match(adminJsRivisto, /classiDisponibili\.filter\(c => Number\(c\.plesso_id\) === plessoId\)/);
assert.match(adminJsRivisto, /function suggerimentiClassi\(plessoId\)/);
assert.match(css, /\.admin-calendario-giorno input\[type="checkbox"\]\s*\{[^}]*width:\s*19px\s*!important/s);
assert.match(css, /\.admin-calendario-giorni__griglia\s*\{[^}]*grid-template-columns:\s*repeat\(5,/s);
assert.match(css, /\.riga-filtri > \.campo-filtro > label,[\s\S]*align-self:\s*stretch\s*!important/);
console.log('OK: Admin calendario, checkbox, allineamento etichette e classi filtrate per scuola.');

/* Chiusura giornaliera: monitoraggio informativo separato dagli addebiti. */
assert.match(adminPaginaRivista, /<h1>Chiusura giornaliera dei pasti<\/h1>/);
assert.match(adminPaginaRivista, /id="job-scuole"/);
assert.match(adminPaginaRivista, /id="job-aggiorna"/);
assert.match(adminPaginaRivista, /<details class="admin-job-manuale"/);
assert.match(adminPaginaRivista, /pianificazione sul server è attiva/);
assert.ok(adminPaginaRivista.indexOf('id="job-scuole"') <
          adminPaginaRivista.indexOf('id="btn-scheduler"'),
    'Lo stato deve precedere le azioni manuali');
assert.match(adminJsRivisto, /API\.get\('\/api\/job\/stato'\)/);
assert.match(adminJsRivisto, /API\.post\('\/api\/job\/scheduler'/);
assert.match(adminJsRivisto, /leggiDettaglioEsecuzione\(e\.dettaglio\)/);
assert.doesNotMatch(adminJsRivisto, /esc\(e\.dettaglio\s*\|\|/);
assert.match(css, /\.admin-job-scuole\s*\{[^}]*display:\s*grid/s);
console.log('OK: job in sola lettura, cron dichiarato esterno, manuale separato e storico comprensibile.');
