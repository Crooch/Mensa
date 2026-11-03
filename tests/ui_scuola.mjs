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
    servizio_mensa: { attivo: true, motivo: 'giorno_di_servizio' },
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

/* La tabella deve occupare tutto il contenitore su desktop e diventare
   una sequenza di schede etichettate su smartphone (non sei colonne strette). */
const css = readFileSync('public/assets/css/app.css', 'utf8');
const tabellaCss = css.slice(css.indexOf('/* Sostituisce il max-content'));
assert.ok(tabellaCss.startsWith('/* Sostituisce il max-content'),
    'Manca la correzione della tabella a larghezza piena');
assert.match(tabellaCss, /\.scuola-tabella\s*>\s*table\.table\s*\{[^}]*width:\s*100%\s*!important/s);
assert.match(tabellaCss, /\.scuola-tabella\s*>\s*table\.table\s*\{[^}]*min-width:\s*920px/s);
const cssMobile = css.slice(css.indexOf('/* Scuola su smartphone:'));
assert.ok(cssMobile.startsWith('/* Scuola su smartphone:'));
assert.match(cssMobile, /@media\s*\(max-width:\s*760px\)/);
assert.match(cssMobile, /\.scuola-tabella\s*>\s*table\.table\s*\{[^}]*min-width:\s*0/s);
assert.match(cssMobile, /\.scuola-tabella\s+\.table tbody\s*\{[^}]*display:\s*grid/s);
assert.match(cssMobile, /\.scuola-tabella\s+\.table tbody td::before\s*\{[^}]*content:\s*attr\(data-label\)/s);
for (const label of ['Alunno', 'Menu', 'Comunicazione famiglia', 'Correzione scuola', 'Stato del pasto', 'Azioni']) {
    assert.ok(script.includes('data-label="' + label + '"'), 'Etichetta mobile mancante: ' + label);
}
assert.match(script, /data-testid="override-assente-\$\{a\.bambino_id\}"/);
assert.match(script, /data-testid="override-presente-\$\{a\.bambino_id\}"/);
console.log('OK: tabella Scuola piena sul desktop, schede mobili e azioni preservate.');

/* Una giornata prima dell'avvio non puo' mostrare pasti/assenze o azioni. */
el('data').value = '2026-10-15';
el('data').handlers.change();
await tick();
const nonAvviata = creaVista(2);
nonAvviata.servizio_mensa = { attivo: false, motivo: 'servizio_non_avviato' };
nonAvviata.alunni.forEach(a => { a.stato_effettivo = null; a.default_calcolato = null; });
chiamate.at(-1).resolve(nonAvviata);
await tick();
assert.match(el('scuola-stato').textContent, /Servizio non iniziato/);
assert.match(el('scuola-riepilogo').innerHTML, /<strong>0<\/strong><span>Pasti previsti/);
assert.match(el('scuola-riepilogo').innerHTML, /<strong>0<\/strong><span>Pasti non previsti/);
assert.match(el('presenze-corpo').innerHTML, /Mensa non prevista/);
assert.ok(!el('presenze-corpo').innerHTML.includes('data-override='),
    'Nessuna azione deve apparire per giorni senza mensa');
assert.match(el('stato-cutoff').className, /banner--neutro/);
console.log('OK: il 15 ottobre nessun pasto, nessuna assenza e nessuna azione Scuola.');

/* La gita e' un vincolo su tutta la classe, anche con vecchie conferme
   individuali. La schermata non deve mostrare pulsanti per ripristinarle. */
el('data').value = '2026-10-20';
el('data').handlers.change();
await tick();
const vistaGita = creaVista(2);
vistaGita.assenza_massa = { motivo: 'Gita scolastica' };
vistaGita.alunni[0].dichiarazione_genitore = 'presente';
vistaGita.alunni[1].override_scuola = 'presente';
vistaGita.alunni.forEach(a => { a.stato_effettivo = 'assente'; });
chiamate.at(-1).resolve(vistaGita);
await tick();
assert.match(el('scuola-riepilogo').innerHTML, /<strong>0<\/strong><span>Pasti previsti/);
assert.match(el('scuola-riepilogo').innerHTML, /<strong>2<\/strong><span>Pasti non previsti/);
assert.match(el('scuola-stato').textContent, /Assenza dell.intera classe/);
assert.match(el('stato-cutoff').innerHTML, /Gita scolastica/);
assert.match(el('stato-cutoff').className, /banner--neutro/);
assert.ok(!el('presenze-corpo').innerHTML.includes('data-override='),
    'Non proporre conferme di pasti individuali se la classe e in gita');
assert.match(script, /document\.getElementById\('classe'\)\.value = classeSegnalata/);
assert.match(script, /document\.getElementById\('data'\)\.value = dataSegnalata/);
assert.match(script, /document\.querySelector\('\.scheda\[data-vista="giornata"\]'\)\.click\(\)/);
console.log('OK: gita visualizzata subito e azioni individuali nascoste.');

/* Rifinitura: badge e nota devono avere un blocco comune con partenza
   del testo allineata e senza margini ereditati, anche nelle schede mobili. */
assert.match(css, /\.scuola-correzione,\s*\.scuola-stato-pasto\s*\{[^}]*display:\s*flex;[^}]*flex-direction:\s*column;[^}]*align-items:\s*flex-start;/s);
assert.match(css, /\.scuola-correzione > \.badge-stato,\s*\.scuola-stato-pasto > \.badge-stato\s*\{[^}]*align-items:\s*center;/s);
assert.match(css, /\.scuola-correzione > small,\s*\.scuola-stato-pasto > small\s*\{[^}]*margin:\s*0;[^}]*padding:\s*0 0 0 \.55rem;/s);
assert.match(cssMobile, /\.scuola-tabella\s+\.table tbody td\s*\{[^}]*grid-template-columns:\s*minmax\(0, 43%\) minmax\(0, 57%\)/s);
assert.match(script, /Non applicata: gita/);
console.log('OK: badge e note allineati su desktop e smartphone, stati invariati.');
