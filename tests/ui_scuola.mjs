import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Simula richieste di caricamento risolte fuori ordine, senza browser né DB.
const elementi = new Map();
const el = (id) => {
    if (!elementi.has(id)) elementi.set(id, {
        value: '', className: '', innerHTML: '', textContent: '',
        handlers: {}, addEventListener(tipo, cb) { this.handlers[tipo] = cb; }
    });
    return elementi.get(id);
};
el('classe').value = '10';
const chiamate = [];
const UI = { stato: (id, msg) => { el(id).textContent = msg; }, oraAggiornamento: () => '09:00' };
const ctx = {
    document: {
        getElementById: el,
        querySelectorAll: () => []
    },
    proteggi: () => ({}),
    abilitaSchede: () => {},
    oggi: () => '2026-10-14',
    dataIt: (x) => x.split('-').reverse().join('/'),
    esc: (x) => String(x ?? ''),
    UI,
    avviso: (m) => { throw new Error(m); },
    API: {
        get: (url) => url === '/api/scuola/classi'
            ? Promise.resolve([{id: 10, nome:'Sezione A', plesso_nome:'Infanzia', anno_scolastico:'2026/2027'}])
            : new Promise((resolve) => { chiamate.push({url, resolve}); })
    }
};
vm.createContext(ctx);
vm.runInContext(readFileSync('public/assets/js/scuola.js', 'utf8'), ctx);
const tick = async () => new Promise((resolve) => setImmediate(resolve));
await tick();
assert.equal(chiamate.length, 1);
el('data').value = '2026-10-19';
el('data').handlers.change();
assert.equal(chiamate.length, 2);
const creaVista = (n) => ({
    cutoff_orario: '09:15', oltre_cutoff: false, assenza_massa: null,
    alunni: Array.from({length:n}, (_,i) => ({
        bambino_id: i + 1, nome: 'Figlio', cognome: 'Prova',
        tipologia_nome: 'Ordinario', default_calcolato: 'presente',
        dichiarazione_genitore: null, override_scuola: null,
        stato_effettivo: 'presente', fuori_cutoff: 0
    }))
});
chiamate[1].resolve(creaVista(2));
await tick();
assert.match(el('scuola-stato').textContent, /19\/10\/2026/);
assert.match(el('scuola-riepilogo').innerHTML, /2/);
chiamate[0].resolve(creaVista(1));
await tick();
assert.match(el('scuola-stato').textContent, /19\/10\/2026/);
assert.match(el('scuola-riepilogo').innerHTML, /2/);
console.log('OK: classe/data aggiornate, risposta obsoleta non sovrascrive la giornata corrente.');

const view = readFileSync('src/View/scuola.php', 'utf8');
const script = readFileSync('public/assets/js/scuola.js', 'utf8');
assert.ok(!view.includes('Previsto inizialmente'), 'La colonna tecnica non deve comparire');
assert.match(view, /<th>Comunicazione famiglia<\/th>/);
assert.match(view, /<th>Correzione scuola<\/th>/);
assert.match(view, /<th>Stato del pasto<\/th>/);
assert.match(script, /colspan="6"/);
assert.ok(!script.includes('a.default_calcolato'), 'La colonna iniziale non è più renderizzata');
assert.match(script, /UI\.chiediMotivazione\(/);
assert.ok(!script.includes('prompt('), 'La modifica presenza non deve aprire prompt browser');
assert.match(script, /UI\.conferma\(/);
assert.match(view, /class="scuola-filtri"/);
assert.match(view, /class="scuola-filtri scuola-filtri--assenza"/);
console.log('OK: tabella scuola di sei colonne, filtri compatti e motivazione in dialog.');
