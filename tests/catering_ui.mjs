/* Test regressione browser: una risposta AJAX vecchia non puo' sostituire
   il report relativo alla data piu' recente. Esecuzione: node tests/catering_ui.mjs */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const elementi = new Map();
const elemento = (id) => {
    if (!elementi.has(id)) {
        elementi.set(id, {
            value: '', innerHTML: '', textContent: '', disabled: false,
            files: [], handlers: {},
            addEventListener(nome, fn) { this.handlers[nome] = fn; }
        });
    }
    return elementi.get(id);
};
const richieste = [];
const errori = [];
const context = {
    document: {
        getElementById: elemento,
        querySelectorAll: () => [],
    },
    proteggi: () => ({ ruolo: 'catering' }),
    abilitaSchede: () => {},
    oggi: () => '2026-10-14',
    dataIt: (val) => val.split('-').reverse().join('/'),
    esc: (val) => String(val ?? ''),
    avviso: (msg) => { errori.push(msg); },
    API: {
        get: (url) => {
            if (url === '/api/riferimenti') {
                return Promise.resolve({ plessi: [], tipologie: [] });
            }
            if (url.startsWith('/api/catering/report?data=')) {
                return new Promise((resolve, reject) => richieste.push({ url, resolve, reject }));
            }
            throw new Error('API inattesa: ' + url);
        }
    },
};
vm.createContext(context);
const codice = readFileSync('public/assets/js/catering.js', 'utf8');
vm.runInContext(codice, context);

const campo = elemento('report-data');
assert.equal(campo.value, '2026-10-14');
assert.equal(richieste.length, 1);

campo.value = '2026-10-20';
campo.handlers.change();
campo.value = '2026-10-19';
campo.handlers.change();
assert.equal(richieste.length, 3);

const dati19 = {
    data: '2026-10-19', totale_pasti: 4,
    plessi: [{
        plesso_id: 1, plesso_nome: 'Infanzia',
        totale_pasti: 4, cutoff_orario: '09:15',
        definitivo: false, tipologie: [], sezioni: [
            { classe_id: 1, classe_nome: 'Sezione A', pasti: 2 },
            { classe_id: 2, classe_nome: 'Sezione D', pasti: 0 }
        ], totale_pasta_in_bianco: 0, pasta_in_bianco: []
    }]
};
richieste[2].resolve(dati19);
await new Promise((resolve) => setImmediate(resolve));
await new Promise((resolve) => setImmediate(resolve));
assert.match(elemento('report-stato').textContent, /^Report visualizzato: 19\/10\/2026/, 'Stato report: ' + elemento('report-stato').textContent + '; errori=' + errori.join(';'));
assert.match(elemento('report').innerHTML, /Sezione D/, 'Report mancante: ' + elemento('report-stato').textContent + '; errori=' + errori.join(';'));
assert.equal(elemento('btn-report').disabled, false);

// Vecchio 20/10 completato DOPO 19/10: non deve aggiornare la UI.
richieste[1].resolve({ data: '2026-10-20', totale_pasti: 0, plessi: [] });
await new Promise((resolve) => setImmediate(resolve));
assert.match(elemento('report-stato').textContent, /19\/10\/2026/);
assert.match(elemento('report').innerHTML, /Sezione D/);

// Caricamento iniziale ancora piu' vecchio: non deve azzerare la data.
richieste[0].resolve({ data: '2026-10-14', totale_pasti: 0, plessi: [] });
await new Promise((resolve) => setImmediate(resolve));
await new Promise((resolve) => setImmediate(resolve));
await new Promise((resolve) => setImmediate(resolve));
assert.equal(campo.value, '2026-10-19');
assert.match(elemento('report-stato').textContent, /19\/10\/2026/);

// Pulsante Aggiorna: la richiesta piu' recente deve partire.
elemento('btn-report').handlers.click();
assert.equal(richieste.length, 4);
richieste[3].resolve(dati19);
await new Promise((resolve) => setImmediate(resolve));
assert.equal(elemento('btn-report').disabled, false);
console.log('OK: report catering coerente con ultima data, anche con risposte fuori ordine.');
