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
