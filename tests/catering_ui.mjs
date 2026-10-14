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
            checked: false, hidden: false, src: '',
            removeAttribute(k) { if (k === 'src') this.src = ''; },
            files: [], handlers: {}, dataset: {}, focus() {},
            addEventListener(nome, fn) { this.handlers[nome] = fn; }
        });
    }
    return elementi.get(id);
};
const richieste = [];
const errori = [];
const conferme = [];
const salvataggi = [];
const callbackSchede = [];
const menuPubblicati = [
    { id: 7, plesso_id: 1, tipologia_menu_id: 1, plesso_nome: 'Infanzia',
      tipologia_nome: 'Standard', descrizione: 'Pasta', foto_path: 'menu/1_1/2026-10-14.jpg' },
    { id: 8, plesso_id: 1, tipologia_menu_id: 2, plesso_nome: 'Infanzia',
      tipologia_nome: 'Senza glutine', descrizione: 'Riso', foto_path: null },
    { id: 9, plesso_id: 2, tipologia_menu_id: 1, plesso_nome: 'Secondaria',
      tipologia_nome: 'Standard', descrizione: 'Minestra', foto_path: null }
];
const context = {
    document: {
        getElementById: elemento,
        querySelectorAll: () => [],
    },
    proteggi: () => ({ ruolo: 'catering' }),
    abilitaSchede: (cb) => callbackSchede.push(cb),
    oggi: () => '2026-10-14',
    dataIt: (val) => val.split('-').reverse().join('/'),
    esc: (val) => String(val ?? ''),
    avviso: (msg) => { errori.push(msg); },
    UI: {
        oraAggiornamento: () => '09:00',
        stato: (id, messaggio) => { elemento(id).textContent = messaggio; },
        conferma: async (titolo, messaggio) => { conferme.push([titolo, messaggio]); return true; },
        esegui: async (_btn, fn) => fn()
    },
    BASE: '',
    Sessione: { token: () => 'token-fittizio' },
    FormData,
    fetch: async (_url, opzioni) => {
        const fd = opzioni.body;
        const dati = {
            plesso_id: Number(fd.get('plesso_id')),
            tipologia_menu_id: Number(fd.get('tipologia_menu_id')),
            data: fd.get('data'),
            descrizione: fd.get('descrizione'),
            rimuovi_foto: fd.get('rimuovi_foto'),
        };
        salvataggi.push(dati);
        const riga = menuPubblicati.find((m) =>
            m.plesso_id === dati.plesso_id && m.tipologia_menu_id === dati.tipologia_menu_id);
        if (riga) {
            riga.descrizione = dati.descrizione;
            if (dati.rimuovi_foto === '1') riga.foto_path = null;
        }
        else menuPubblicati.push({
            id: 10, plesso_id: dati.plesso_id, tipologia_menu_id: dati.tipologia_menu_id,
            plesso_nome: 'Secondaria', tipologia_nome: 'Senza glutine',
            descrizione: dati.descrizione, foto_path: null
        });
        return { ok: true, json: async () => ({ ok: true, dati: { id: riga?.id ?? 10 } }) };
    },
    API: {
        get: (url) => {
            if (url === '/api/riferimenti') {
                return Promise.resolve({
                    plessi: [{ id: 1, nome: 'Infanzia' }, { id: 2, nome: 'Secondaria' }],
                    tipologie: [{ id: 1, nome: 'Standard' }, { id: 2, nome: 'Senza glutine' }]
                });
            }
            if (url.startsWith('/api/catering/menu?data=')) {
                return Promise.resolve(menuPubblicati.map((m) => ({ ...m })));
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
assert.match(elemento('report-stato').textContent, /^Pasti per il 19\/10\/2026/, 'Stato report: ' + elemento('report-stato').textContent + '; errori=' + errori.join(';'));
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

/* Flusso di pubblicazione: stessa data+plesso+tipologia aggiorna soltanto
   il menu selezionato; ogni altra combinazione crea un menu separato. */
elemento('menu-plesso').value = '1';
elemento('menu-tipologia').value = '1';
callbackSchede[0]('menu');
await new Promise((resolve) => setImmediate(resolve));
assert.equal(elemento('menu-submit').textContent, 'Aggiorna menu');
assert.equal(elemento('menu-submit').disabled, false);
assert.equal(elemento('menu-descrizione').value, 'Pasta');
assert.match(elemento('menu-selezione-stato').textContent, /Aggiornerai solo questo menu/);
assert.match(elemento('menu-foto-stato').textContent, /rimane quella attuale/);
assert.match(elemento('menu-lista').innerHTML, /Modifica menu/);

elemento('menu-tipologia').value = '2';
elemento('menu-tipologia').handlers.change();
assert.equal(elemento('menu-descrizione').value, 'Riso');
assert.equal(elemento('menu-submit').textContent, 'Aggiorna menu');

elemento('menu-plesso').value = '2';
elemento('menu-plesso').handlers.change();
assert.equal(elemento('menu-submit').textContent, 'Pubblica menu');
assert.equal(elemento('menu-descrizione').value, '');
assert.match(elemento('menu-selezione-stato').textContent, /Verrà creato solo questo menu/);

elemento('menu-descrizione').value = 'Pasta nuova';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).plesso_id, 2);
assert.equal(salvataggi.at(-1).tipologia_menu_id, 2);
assert.equal(menuPubblicati.length, 4);
assert.equal(elemento('menu-submit').textContent, 'Aggiorna menu');

elemento('menu-plesso').value = '1';
elemento('menu-tipologia').value = '1';
elemento('menu-plesso').handlers.change();
assert.equal(elemento('menu-descrizione').value, 'Pasta');
elemento('menu-descrizione').value = 'Pasta aggiornata';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(conferme.length, 1, 'Un aggiornamento chiede conferma esplicita');
assert.match(conferme[0][1], /Gli altri menu resteranno invariati/);
assert.equal(menuPubblicati.length, 4, 'Non devono comparire duplicati');
assert.equal(menuPubblicati.find((m) => m.id === 7).descrizione, 'Pasta aggiornata');
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path, 'menu/1_1/2026-10-14.jpg');
assert.equal(menuPubblicati.find((m) => m.id === 8).descrizione, 'Riso');
assert.equal(menuPubblicati.find((m) => m.id === 9).descrizione, 'Minestra');
assert.equal(elemento('menu-descrizione').value, 'Pasta aggiornata');
assert.equal(elemento('menu-submit').textContent, 'Aggiorna menu');
console.log('OK: menu precompilati, creazione, aggiornamento isolato, conferma e foto conservata.');

/* Pubblicare testo multilinea non deve collassare i ritorni a capo.
   Una foto esistente puo' essere rimossa senza sostituirla. */
elemento('menu-descrizione').value = 'Primo: pasta\nSecondo: pollo\n\nFrutta';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).descrizione, 'Primo: pasta\nSecondo: pollo\n\nFrutta');
assert.match(elemento('menu-lista').innerHTML, /Primo: pasta\nSecondo: pollo\n\nFrutta/);
assert.match(elemento('menu-lista').innerHTML, /class="menu-descrizione"/);
assert.equal(elemento('menu-foto-anteprima').hidden, false);
assert.match(elemento('menu-foto-immagine').src, /menu\/1_1/);
elemento('menu-foto-elimina').handlers.click();
assert.equal(elemento('menu-foto-anteprima').hidden, true);
assert.equal(elemento('menu-foto-annulla').hidden, false);
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path,
    'menu/1_1/2026-10-14.jpg', 'La X non deve eliminare subito la foto nel backend');
assert.equal(salvataggi.at(-1).rimuovi_foto, '0', 'Nessuna chiamata al server dopo la X');
elemento('menu-foto-annulla').handlers.click();
assert.equal(elemento('menu-foto-anteprima').hidden, false);
assert.equal(elemento('menu-foto-annulla').hidden, true);
elemento('menu-foto-elimina').handlers.click();
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).rimuovi_foto, '1');
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path, null);
assert.equal(elemento('menu-foto-anteprima').hidden, true);
assert.equal(elemento('menu-foto-annulla').hidden, true);
assert.equal(elemento('menu-foto').disabled, false);
assert.equal(menuPubblicati.find((m) => m.id === 8).descrizione, 'Riso');
assert.equal(menuPubblicati.find((m) => m.id === 9).descrizione, 'Minestra');
assert.match(conferme.at(-1)[1], /La foto verrà rimossa/);
console.log('OK: descrizione multilinea visibile e rimozione immagine isolata e confermata.');
