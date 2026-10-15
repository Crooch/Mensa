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
const ripristini = [];
const callbackSchede = [];
const fotoWidget = {};
const FotoMenuStub = {
    crea(id) {
        const campo = {
            salvata: null, rimossa: false, nuova: null,
            imposta(path) { this.salvata = path || null; this.rimossa = false; this.nuova = null; },
            file() { return this.nuova; },
            rimuovere() { return this.rimossa; },
            haFoto() { return Boolean(this.nuova || (this.salvata && !this.rimossa)); },
            segnaRimozione() { if (this.salvata) this.rimossa = true; },
            annulla() { this.rimossa = false; }
        };
        fotoWidget[id] = campo;
        return campo;
    }
};
const menuPubblicati = [
    { id: 7, plesso_id: 1, tipologia_menu_id: 1, plesso_nome: 'Infanzia',
      tipologia_nome: 'Standard', descrizione: 'Pasta', foto_path: 'menu/1_1/2026-10-14.jpg',
      data: '2026-10-14', origine: 'giornaliero' },
    { id: 8, plesso_id: 1, tipologia_menu_id: 2, plesso_nome: 'Infanzia',
      tipologia_nome: 'Senza glutine', descrizione: 'Riso', foto_path: null,
      data: '2026-10-14', origine: 'giornaliero' },
    { id: 9, plesso_id: 2, tipologia_menu_id: 1, plesso_nome: 'Secondaria',
      tipologia_nome: 'Standard', descrizione: 'Minestra', foto_path: null,
      data: '2026-10-14', origine: 'giornaliero' }
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
    URL,
    FotoMenu: FotoMenuStub,
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
            m.plesso_id === dati.plesso_id && m.tipologia_menu_id === dati.tipologia_menu_id &&
            m.data === dati.data);
        if (riga) {
            riga.descrizione = dati.descrizione;
            if (riga.origine === 'settimanale') {
                // Simula la pubblicazione reale di una nuova eccezione completa.
                riga.foto_path = fd.has('foto') ? 'menu/2_1/2026-10-14.png' : null;
                riga.origine = 'giornaliero';
                riga.ha_modello_settimanale = true;
            } else if (dati.rimuovi_foto === '1') {
                riga.foto_path = null;
            }
        }
        else menuPubblicati.push({
            id: 10, plesso_id: dati.plesso_id, tipologia_menu_id: dati.tipologia_menu_id,
            plesso_nome: 'Secondaria', tipologia_nome: 'Senza glutine',
            descrizione: dati.descrizione, foto_path: null, data: dati.data, origine: 'giornaliero'
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
                const data = new URL('http://localhost' + url).searchParams.get('data');
                return Promise.resolve(menuPubblicati.filter(m => m.data === data).map(m => ({ ...m })));
            }
            if (url === '/api/catering/menu/variazioni') {
                return Promise.resolve(menuPubblicati.filter(m =>
                    m.origine === 'giornaliero' && m.data >= '2026-10-14'
                ).map(m => ({ ...m, modificabile: true })));
            }
            if (url.startsWith('/api/catering/report?data=')) {
                return new Promise((resolve, reject) => richieste.push({ url, resolve, reject }));
            }
            throw new Error('API inattesa: ' + url);
        },
        post: (url, dati) => {
            assert.equal(url, '/api/catering/menu/annulla');
            ripristini.push(dati);
            const i = menuPubblicati.findIndex(m =>
                m.plesso_id === dati.plesso_id && m.tipologia_menu_id === dati.tipologia_menu_id &&
                m.data === dati.data
            );
            assert.ok(i >= 0 && menuPubblicati[i].origine === 'giornaliero');
            if (menuPubblicati[i].ha_modello_settimanale) {
                menuPubblicati[i].origine = 'settimanale';
                menuPubblicati[i].descrizione = 'Pesce';
                menuPubblicati[i].foto_path = 'menu/settimane/pesce.jpg';
                menuPubblicati[i].ha_modello_settimanale = false;
            } else {
                menuPubblicati.splice(i, 1);
            }
            return Promise.resolve({ annullato: true });
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
        totale_pasti: 4, pasti_ordinari: 2, cutoff_orario: '09:15',
        definitivo: false, tipologie: [
            { tipologia_id: 1, tipologia_nome: 'Standard', pasti: 3,
              pasti_ordinari: 1, pasta_in_bianco: 2,
              menu_nome: 'Settimana Autunno', menu_descrizione: 'Primo: pasta\\nSecondo: pesce',
              menu_origine: 'settimanale' },
            { tipologia_id: 2, tipologia_nome: 'Senza glutine', pasti: 1,
              pasti_ordinari: 1, pasta_in_bianco: 0,
              menu_nome: 'Menu del giorno', menu_descrizione: 'Riso',
              menu_origine: 'giornaliero' }
        ], sezioni: [
            { classe_id: 1, classe_nome: 'Sezione A', pasti: 4,
              pasti_ordinari: 2, pasta_in_bianco: 2, tipologie: [
                { tipologia_id: 1, tipologia_nome: 'Standard', pasti: 3,
                  pasti_ordinari: 1, pasta_in_bianco: 2,
                  menu_nome: 'Settimana Autunno', menu_descrizione: 'Primo: pasta\\nSecondo: pesce',
                  richieste_pasta: [
                    { cognome: 'Rossi', nome: 'Mario' }, { cognome: 'Verdi', nome: 'Gianni' }
                  ] },
                { tipologia_id: 2, tipologia_nome: 'Senza glutine', pasti: 1,
                  pasti_ordinari: 1, pasta_in_bianco: 0,
                  menu_nome: 'Menu del giorno', menu_descrizione: 'Riso',
                  richieste_pasta: [] }
              ] },
            { classe_id: 2, classe_nome: 'Sezione D', pasti: 0,
              pasti_ordinari: 0, pasta_in_bianco: 0, tipologie: [] }
        ], totale_pasta_in_bianco: 2, pasta_in_bianco: []
    }]
};
richieste[2].resolve(dati19);
await new Promise((resolve) => setImmediate(resolve));
await new Promise((resolve) => setImmediate(resolve));
assert.match(elemento('report-stato').textContent, /^Pasti per il 19\/10\/2026/,
    'Stato report: ' + elemento('report-stato').textContent + '; errori=' + errori.join(';'));
assert.equal(elemento('btn-report').disabled, false);
const html = elemento('report').innerHTML;
assert.match(html, /report-plesso-1/);
assert.match(html, /Tipologie e menu assegnati/);
assert.match(html, /Settimana Autunno/);
assert.match(html, /Menu del giorno/);
assert.match(html, /Piatti previsti per questa data/);
assert.match(html, /Primo: pasta/);
assert.match(html, /Secondo: pesce/);
assert.match(html, /Pasti per classe o sezione/);
assert.match(html, /Sezione A/);
assert.match(html, /Sezione D/);
assert.match(html, /Standard/);
assert.match(html, /Senza glutine/);
assert.match(html, /2 pasta in bianco/);
assert.match(html, /Rossi Mario/);
assert.match(html, /Verdi Gianni/);
assert.match(html, /Nessun pasto previsto/);
assert.match(elemento('report-riepilogo').innerHTML, /Pasti da preparare/);
assert.match(elemento('report-riepilogo').innerHTML, /Scuole con mensa/);
assert.match(elemento('report-riepilogo').innerHTML, /Pasta in bianco/);
assert.match(elemento('report-riepilogo').innerHTML, /Conteggi confermati/);
assert.ok(!html.includes('report-plesso-filtro'), 'Non devono comparire filtri per scuola');
assert.ok(!html.includes('report-classe-ricerca'), 'Non deve servire digitare una classe');
const vistaReport = readFileSync('src/View/catering.php', 'utf8');
assert.doesNotMatch(vistaReport, /report-classe-cerca|report-plesso" data-testid="report-plesso-filtro/,
    'Nessun campo di ricerca nella schermata operativa');

const renderClasse = vm.runInContext('rigaDistribuzioneClasse', context);
const moltiTipi = Array.from({length: 25}, (_, i) => ({
    tipologia_id: i + 1, tipologia_nome: 'Dieta speciale ' + (i + 1),
    pasti: 1, pasti_ordinari: 1, pasta_in_bianco: 0, richieste_pasta: []
}));
const classeLunga = renderClasse({
    classe_id: 44, classe_nome: 'Sezione con 25 menu',
    pasti: 25, pasti_ordinari: 25, pasta_in_bianco: 0, tipologie: moltiTipi
});
assert.match(classeLunga, /Altri 22 tipi \(22 pasti\)/);
assert.equal((classeLunga.match(/<details /g) || []).length, 1,
    'Le tipologie oltre le prime 3 devono essere accessibili espandendo');
assert.equal((classeLunga.match(/class="catering-quantita"/g) || []).length, 25);
assert.ok(!classeLunga.includes('<table'), 'Il riepilogo non usa tabelle larghe');
const classeSoloBianco = renderClasse({
    classe_id: 45, classe_nome: 'Sezione dieta speciale',
    pasti: 1, pasti_ordinari: 0, pasta_in_bianco: 1,
    tipologie: [{
        tipologia_id: 3, tipologia_nome: 'Senza lattosio',
        pasti: 1, pasti_ordinari: 0, pasta_in_bianco: 1,
        richieste_pasta: [{cognome: 'Blu', nome: 'Sofia'}]
    }]
});
assert.match(classeSoloBianco, /1 pasta in bianco/);
assert.match(classeSoloBianco, /Senza lattosio/);
assert.match(classeSoloBianco, /Blu Sofia/);
assert.ok(!classeSoloBianco.includes('catering-quantita'),
    'La sola pasta in bianco non deve conteggiare anche un pasto ordinario');
const senzaMenu = renderClasse({
    classe_id: 88, classe_nome: 'Classe senza menu',
    pasti: 2, pasti_ordinari: 2, pasta_in_bianco: 0,
    tipologie: [{ tipologia_id: 1, tipologia_nome: 'Standard',
        pasti: 2, pasti_ordinari: 2, pasta_in_bianco: 0,
        menu_nome: null, menu_descrizione: null }]
});
assert.match(senzaMenu, /Menu non disponibile/,
    'Il sistema non deve inventare nomi per le tipologie senza un menu assegnato');
const classificaVariazione = renderClasse({
    classe_id: 89, classe_nome: 'Classe con variazione',
    pasti: 1, pasti_ordinari: 1, pasta_in_bianco: 0,
    tipologie: [{ tipologia_id: 1, tipologia_nome: 'Standard',
        pasti: 1, pasti_ordinari: 1, pasta_in_bianco: 0,
        menu_nome: 'Menu del giorno', menu_descrizione: 'Pollo' }]
});
assert.match(classificaVariazione, /Menu del giorno/);
assert.doesNotMatch(classificaVariazione, /Settimana Autunno/);

console.log('OK: schede scuola compatte, nessuna ricerca, 25 menu espandibili e paste in bianco visibili.');

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
assert.equal(elemento('menu-submit').textContent, 'Salva modifica');
assert.equal(elemento('menu-submit').disabled, false);
assert.equal(elemento('menu-descrizione').value, 'Pasta');
assert.match(elemento('menu-selezione-stato').textContent, /Puoi modificarla o annullarla/);
assert.equal(fotoWidget['menu-foto-widget'].salvata, 'menu/1_1/2026-10-14.jpg');
assert.match(elemento('menu-lista').innerHTML, /Modifica menu/);

elemento('menu-tipologia').value = '2';
elemento('menu-tipologia').handlers.change();
assert.equal(elemento('menu-descrizione').value, 'Riso');
assert.equal(elemento('menu-submit').textContent, 'Salva modifica');

elemento('menu-plesso').value = '2';
elemento('menu-plesso').handlers.change();
assert.equal(elemento('menu-submit').textContent, 'Pubblica menu');
assert.equal(elemento('menu-descrizione').value, '');
assert.match(elemento('menu-selezione-stato').textContent, /Salverai solo questo giorno/);

elemento('menu-descrizione').value = 'Pasta nuova';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).plesso_id, 2);
assert.equal(salvataggi.at(-1).tipologia_menu_id, 2);
assert.equal(menuPubblicati.length, 4);
assert.equal(elemento('menu-submit').textContent, 'Salva modifica');

elemento('menu-plesso').value = '1';
elemento('menu-tipologia').value = '1';
elemento('menu-plesso').handlers.change();
assert.equal(elemento('menu-descrizione').value, 'Pasta');
elemento('menu-descrizione').value = 'Pasta aggiornata';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(conferme.length, 1, 'Un aggiornamento chiede conferma esplicita');
assert.match(conferme[0][1], /Gli altri giorni resteranno invariati/);
assert.equal(menuPubblicati.length, 4, 'Non devono comparire duplicati');
assert.equal(menuPubblicati.find((m) => m.id === 7).descrizione, 'Pasta aggiornata');
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path, 'menu/1_1/2026-10-14.jpg');
assert.equal(menuPubblicati.find((m) => m.id === 8).descrizione, 'Riso');
assert.equal(menuPubblicati.find((m) => m.id === 9).descrizione, 'Minestra');
assert.equal(elemento('menu-descrizione').value, 'Pasta aggiornata');
assert.equal(elemento('menu-submit').textContent, 'Salva modifica');
console.log('OK: menu precompilati, creazione, aggiornamento isolato, conferma e foto conservata.');

/* Pubblicare testo multilinea non deve collassare i ritorni a capo.
   Una foto esistente puo' essere rimossa senza sostituirla. */
elemento('menu-descrizione').value = 'Primo: pasta\nSecondo: pollo\n\nFrutta';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).descrizione, 'Primo: pasta\nSecondo: pollo\n\nFrutta');
assert.match(elemento('menu-lista').innerHTML, /Primo: pasta\nSecondo: pollo\n\nFrutta/);
assert.match(elemento('menu-lista').innerHTML, /class="menu-descrizione"/);
assert.equal(fotoWidget['menu-foto-widget'].salvata, 'menu/1_1/2026-10-14.jpg');
fotoWidget['menu-foto-widget'].segnaRimozione();
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path,
    'menu/1_1/2026-10-14.jpg', 'La X non deve cancellare la foto già salvata');
fotoWidget['menu-foto-widget'].annulla();
assert.equal(fotoWidget['menu-foto-widget'].haFoto(), true);
fotoWidget['menu-foto-widget'].segnaRimozione();
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).rimuovi_foto, '1');
assert.equal(menuPubblicati.find((m) => m.id === 7).foto_path, null);
assert.equal(fotoWidget['menu-foto-widget'].haFoto(), false);
assert.equal(menuPubblicati.find((m) => m.id === 8).descrizione, 'Riso');
assert.equal(menuPubblicati.find((m) => m.id === 9).descrizione, 'Minestra');
assert.match(conferme.at(-1)[1], /La foto verrà rimossa/);
console.log('OK: descrizione multilinea visibile e rimozione immagine isolata e confermata.');

/* Una foto sul modello settimanale non viene preselezionata nell'override.
   Pesce con foto sul modello -> Pollo senza foto sul giornaliero. */
Object.assign(menuPubblicati.find(m => m.id === 9), {
    id: 'modello-11', descrizione: 'Pesce',
    foto_path: 'menu/settimane/pesce.jpg', origine: 'settimanale', modello_nome: 'Settimana base'
});
elemento('menu-plesso').value = '2';
elemento('menu-tipologia').value = '1';
callbackSchede[0]('menu');
await new Promise((resolve) => setImmediate(resolve));
assert.equal(elemento('menu-descrizione').value, 'Pesce');
assert.equal(fotoWidget['menu-foto-widget'].salvata, null,
    'La foto del pesce non deve essere preselezionata per il giornaliero');
assert.match(elemento('menu-selezione-stato').textContent, /senza copiare automaticamente la foto del modello/i);
assert.match(elemento('menu-lista').innerHTML, /menu\/settimane\/pesce\.jpg/,
    'Il modello settimanale mantiene la sua foto nell\'elenco');
elemento('menu-descrizione').value = 'Pollo';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).descrizione, 'Pollo');
assert.equal(salvataggi.at(-1).rimuovi_foto, '0',
    'Una nuova eccezione senza foto non richiede di cancellare il file settimanale');
assert.equal(menuPubblicati.find(m => m.id === 'modello-11').foto_path, null);
assert.match(conferme.at(-1)[1], /foto del modello settimanale non verrà copiata/);
console.log('OK: pollo senza foto non eredita la foto del pesce settimanale.');

/* Il ripristino e' disponibile soltanto per un giornaliero che copre un
   modello settimanale: dopo la conferma tornano testo e foto del modello. */
assert.equal(elemento('menu-data').value, '2026-10-14');
assert.equal(elemento('menu-ripristina').hidden, false);
assert.equal(elemento('menu-ripristina').disabled, false);
await elemento('menu-ripristina').handlers.click();
assert.equal(ripristini.length, 1);
assert.equal(ripristini[0].plesso_id, 2);
assert.equal(ripristini[0].tipologia_menu_id, 1);
assert.equal(ripristini[0].data, '2026-10-14');
assert.match(conferme.at(-1)[1], /altri giorni non cambieranno/);
assert.equal(menuPubblicati.find(m => m.id === 'modello-11').descrizione, 'Pesce');
assert.equal(menuPubblicati.find(m => m.id === 'modello-11').foto_path, 'menu/settimane/pesce.jpg');
assert.equal(elemento('menu-ripristina').hidden, true);
assert.equal(elemento('menu-descrizione').value, 'Pesce');
elemento('menu-plesso').value = '1';
elemento('menu-plesso').handlers.change();
assert.equal(elemento('menu-ripristina').hidden, false,
    'Anche una variazione senza modello può essere annullata');
console.log('OK: ripristino del solo menu giornaliero con modello settimanale e foto originali.');

/* Programmazione singola per data futura: non deve sovrascrivere il giorno
   odierno, e deve essere recuperabile dalla lista senza ulteriori filtri. */
elemento('menu-data').value = '2026-10-19';
await elemento('menu-data').handlers.change();
await new Promise((resolve) => setImmediate(resolve));
assert.equal(elemento('menu-descrizione').value, '',
    'La data futura non deve ereditare la descrizione del giorno precedente');
elemento('menu-tipologia').value = '2';
elemento('menu-tipologia').handlers.change();
elemento('menu-descrizione').value = 'Lasagne di lunedì';
await elemento('form-menu').handlers.submit({ preventDefault() {} });
assert.equal(salvataggi.at(-1).data, '2026-10-19');
assert.equal(salvataggi.at(-1).descrizione, 'Lasagne di lunedì');
assert.equal(menuPubblicati.find(m => m.data === '2026-10-14' && m.id === 8).descrizione, 'Riso');
assert.match(elemento('menu-variazioni-lista').innerHTML, /Lasagne di lunedì/);
assert.match(elemento('menu-variazioni-lista').innerHTML, /19\/10\/2026/);
assert.equal(elemento('menu-ripristina').hidden, false,
    'La variazione giornaliera futura deve essere annullabile senza modello');

await elemento('menu-ripristina').handlers.click();
assert.equal(ripristini.at(-1).data, '2026-10-19');
assert.equal(menuPubblicati.some(m => m.data === '2026-10-19'), false);
assert.match(elemento('menu-variazioni-lista').innerHTML, /Nessuna variazione|14\/10\/2026/);
assert.equal(elemento('menu-submit').textContent, 'Pubblica menu');
console.log('OK: data futura, elenco programmati e annullamento anche senza modello.');
