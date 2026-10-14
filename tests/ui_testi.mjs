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
