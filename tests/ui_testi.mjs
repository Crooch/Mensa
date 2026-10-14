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
