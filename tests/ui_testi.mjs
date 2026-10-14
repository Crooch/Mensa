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
