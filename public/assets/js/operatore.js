/* Area operatore comunale: anagrafiche, acquisti buoni, override tariffari. */
const utente = proteggi('operatore');
let riferimenti = { plessi: [], tipologie: [], classi: [] };
let famigliaCorrente = null;
let ultimaRicerca = 0;
let ultimaAperturaFamiglia = 0;

abilitaSchede((vista) => {
    if (vista === 'crediti') caricaSituazione();
    if (vista === 'solleciti') caricaSolleciti();
});

const modale = document.getElementById('modale');
document.getElementById('modale-chiudi').addEventListener('click', () => { modale.hidden = true; });

function apriModale(titolo, html, alSalva) {
    document.getElementById('modale-titolo').textContent = titolo;
    document.getElementById('modale-corpo').innerHTML = html;
    raggruppaCampi(document.getElementById('modale-corpo'));
    modale.hidden = false;
    const form = modale.querySelector('form');
    if (form) {
        form.insertAdjacentHTML('beforeend', '<p id="modale-stato" class="stato-operazione" role="status" hidden></p>');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const pulsante = form.querySelector('[type="submit"]');
            try {
                await UI.esegui(pulsante, async () => {
                    UI.stato('modale-stato', 'Controllo dei dati…', 'caricamento');
                    const esito = await alSalva(new FormData(form));
                    if (esito === false) {
                        UI.stato('modale-stato', '', 'info');
                        return;
                    }
                    modale.hidden = true;
                });
            } catch (err) {
                UI.stato('modale-stato', 'Non è stato possibile salvare: ' + err.message, 'errore');
            }
        });
    }
}

const opzioniPlessi = () => riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
const opzioniTipologie = () => riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
const opzioniClassi = () => riferimenti.classi.map((c) => `<option value="${c.id}">${esc(c.plesso_nome)} · ${esc(c.nome)} (${esc(c.anno_scolastico)})</option>`).join('');

async function cerca() {
    const versione = ++ultimaRicerca;
    const q = document.getElementById('ricerca-famiglie').value.trim();
    UI.stato('ricerca-stato', 'Sto cercando le famiglie…', 'caricamento');
    let elenco;
    try {
        elenco = await API.get('/api/famiglie?q=' + encodeURIComponent(q));
    } catch (errore) {
        if (versione === ultimaRicerca) UI.stato('ricerca-stato', 'Ricerca non riuscita: ' + errore.message, 'errore');
        return;
    }
    if (versione !== ultimaRicerca) return;
    UI.stato('ricerca-stato', elenco.length + ' famiglie trovate · aggiornato alle ' + UI.oraAggiornamento(), 'ok');
    document.getElementById('famiglie-corpo').innerHTML = elenco.map((g) => `
        <tr data-genitore="${g.id}">
            <td>${esc(g.cognome)} ${esc(g.nome)}</td><td>${esc(g.telefono)}</td><td>${esc(g.email || '-')}</td>
            <td><button class="btn-azione" data-apri="${g.id}" data-testid="apri-famiglia-${g.id}">Apri</button></td>
        </tr>`).join('') || '<tr><td colspan="4" class="vuoto">Nessun risultato.</td></tr>';
    document.querySelectorAll('[data-apri]').forEach((b) => b.addEventListener('click', () => apriFamiglia(Number(b.dataset.apri))));
}

async function apriFamiglia(id) {
    const versione = ++ultimaAperturaFamiglia;
    const scheda = document.getElementById('scheda-famiglia');
    scheda.innerHTML = '<p class="nota">Sto caricando i dati della famiglia…</p>';
    let s;
    try {
        s = await API.get('/api/famiglie/' + id);
    } catch (errore) {
        if (versione === ultimaAperturaFamiglia) scheda.innerHTML =
            '<p class="stato-operazione stato-operazione--errore">' + esc('Non riesco a mostrare la famiglia: ' + errore.message) + '</p>';
        return;
    }
    if (versione !== ultimaAperturaFamiglia) return;
    famigliaCorrente = s;
    const g = s.genitore;
    document.getElementById('scheda-famiglia').innerHTML = `
        <header class="operatore-famiglia-testa">
            <h2 data-testid="famiglia-nome">${esc(g.cognome)} ${esc(g.nome)}</h2>
            ${Number(g.cancellato_logicamente) === 1 ? '<span class="badge-stato badge-rosso">Disattivata</span>' : ''}
            <dl class="operatore-contatti">
                <div><dt>Telefono</dt><dd>${esc(g.telefono || '-')}</dd></div>
                <div><dt>Email</dt><dd>${esc(g.email || 'Non presente')}</dd></div>
                <div><dt>Utenza</dt><dd>${esc(g.username || '-')}</dd></div>
            </dl>
        </header>
        <div class="operatore-azioni">
            <button class="btn btn-primary btn-sm" id="btn-acquisto" data-testid="acquisto-buoni-button">Registra buoni acquistati</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-override" data-testid="override-tariffa-button">Tariffa agevolata</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-rettifica" data-testid="rettifica-button">Correggi il saldo buoni</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-modifica-genitore" data-testid="modifica-genitore-button">Modifica dati</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-nuovo-figlio" data-testid="nuovo-figlio-button">Aggiungi figlio</button>
        </div>

        <div class="area-sezione-testa"><h3>Buoni disponibili</h3></div>
        <div class="griglia-cards">${s.crediti.map((c) => `
            <article class="card-mensa card-mensa--${c.stato}" data-testid="credito-${c.plesso_id}">
                <h3>${esc(c.plesso_nome)}</h3><span class="saldo">${c.saldo}</span>
                <dl><dt>Fido concesso</dt><dd>${fidoConcesso(c.fido_buoni)}</dd>
                <dt>Costo di un pasto</dt><dd>${euro(c.tariffa_attiva)} (${c.origine_tariffa === 'override' ? 'tariffa agevolata' : 'tariffa ordinaria'})</dd></dl>
            </article>`).join('') || '<p class="vuoto">Nessun saldo buoni registrato per questa famiglia.</p>'}</div>

        <div class="area-sezione-testa"><h3>Figli</h3></div>
        ${s.figli.map((f) => `
            <div class="card-mensa operatore-figlio" data-testid="figlio-${f.id}">
                <h3>${esc(f.cognome)} ${esc(f.nome)} ${Number(f.cancellato_logicamente) === 1 ? '<span class="badge-stato badge-rosso">cancellato</span>' : ''}</h3>
                <dl>
                    <dt>Classe attuale</dt><dd>${esc(f.plesso_nome || '-')} ${esc(f.classe_nome || '')}</dd>
                    <dt>Tipo di menu</dt><dd>${esc(f.tipologia_nome)}</dd>
                </dl>
                <details><summary>Storico classi (${f.storico_classi.length})</summary>
                    <ul class="nota">${f.storico_classi.map((a) => `<li>${esc(a.plesso_nome)} · ${esc(a.classe_nome)} (${esc(a.anno_scolastico)}) dal ${dataIt(a.data_inizio)} ${a.data_fine ? 'al ' + dataIt(a.data_fine) : '(corrente)'}</li>`).join('')}</ul>
                </details>
                <div class="operatore-figlio-azioni">
                    <button class="btn-azione" data-cambio-classe="${f.id}" data-testid="cambia-classe-${f.id}">Cambia classe</button>
                    <button class="btn-azione" data-dieta="${f.id}" data-testid="cambia-dieta-${f.id}">Cambia dieta</button>
                    <button class="btn-azione" data-cancella-figlio="${f.id}" data-testid="cancella-figlio-${f.id}">Disattiva bambino</button>
                </div>
            </div>`).join('') || '<p class="vuoto">Nessun figlio registrato.</p>'}

        <details class="operatore-storico">
            <summary>Storico delle tariffe agevolate (${s.override_tariffe.length})</summary>
            <div class="tabella-scroll"><table class="table" data-testid="override-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tariffa</th><th>Motivazione</th><th>Inserito da</th></tr></thead>
            <tbody>${s.override_tariffe.map((o) => `<tr><td>${dataIt(o.data_inserimento)}</td><td>${esc(o.plesso_nome)}</td>
                <td>${euro(o.tariffa)}</td><td>${esc(o.motivazione)}</td><td>${esc(o.inserito_da || '-')}</td></tr>`).join('')
                || '<tr><td colspan="5" class="vuoto">Nessuna tariffa agevolata registrata.</td></tr>'}</tbody>
        </table></div>
        </details>
        <details class="operatore-storico">
            <summary>Acquisti, pasti e correzioni dei buoni (${s.movimenti.length})</summary>
            <div class="tabella-scroll"><table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tipo</th><th>Buoni</th><th>Importo</th><th>Alunno</th><th>Operatore</th></tr></thead>
            <tbody>${s.movimenti.map((m) => `<tr><td>${dataIt(m.data)}</td><td>${esc(m.plesso_nome)}</td><td>${esc(nomeMovimento(m.tipo))}</td>
                <td>${m.delta > 0 ? '+' : ''}${m.delta}</td><td>${m.importo_euro === null || m.importo_euro === undefined ? '-' : euro(m.importo_euro)}</td>
                <td>${m.bambino_nome ? esc(m.bambino_nome) : '-'}</td><td>${esc(m.operatore || 'sistema')}</td></tr>`).join('')
                || '<tr><td colspan="7" class="vuoto">Nessun movimento.</td></tr>'}</tbody>
        </table></div>
        </details>`;

    collegaAzioniFamiglia(id);
}

function collegaAzioniFamiglia(genitoreId) {
    document.getElementById('btn-acquisto').addEventListener('click', () => formAcquisto(genitoreId));
    document.getElementById('btn-override').addEventListener('click', () => formOverride(genitoreId));
    document.getElementById('btn-rettifica').addEventListener('click', () => formRettifica(genitoreId));
    document.getElementById('btn-modifica-genitore').addEventListener('click', () => formModificaGenitore(famigliaCorrente.genitore));
    document.getElementById('btn-nuovo-figlio').addEventListener('click', () => formNuovoFiglio(genitoreId));
    document.querySelectorAll('[data-cambio-classe]').forEach((b) => b.addEventListener('click', () => formCambioClasse(Number(b.dataset.cambioClasse))));
    document.querySelectorAll('[data-dieta]').forEach((b) => b.addEventListener('click', () => formDieta(Number(b.dataset.dieta))));
    document.querySelectorAll('[data-cancella-figlio]').forEach((b) => b.addEventListener('click', async () => {
        if (!confirm('Vuoi disattivare questo bambino? Non comparirà più tra quelli attivi, ma le operazioni precedenti saranno conservate.')) return;
        await API.del('/api/bambini/' + b.dataset.cancellaFiglio);
        avviso('Bambino disattivato. Le operazioni precedenti sono conservate.');
        apriFamiglia(genitoreId);
    }));
}

/* ---------------- form ---------------- */
function formAcquisto(genitoreId) {
    // Conservata per tutti i retry di questo modulo, anche se la risposta
    // dell'acquisto precedente non e' arrivata al browser.
    const richiestaId = crypto.randomUUID ? crypto.randomUUID()
        : Array.from(crypto.getRandomValues(new Uint8Array(16)), (n) => n.toString(16).padStart(2, '0')).join('');
    apriModale('Registra un acquisto di buoni mensa', `
        <form data-testid="acquisto-form">
            <label for="acq-plesso">Plesso</label><select name="plesso_id" id="acq-plesso" data-testid="acquisto-plesso-select">${opzioniPlessi()}</select>
            <label for="acq-buoni">Quanti buoni sono stati acquistati?</label><input type="number" min="1" name="numero_buoni" id="acq-buoni" value="10" data-testid="acquisto-buoni-input">
            <label for="op-acquisto-note-input">Note (facoltative)</label><input id="op-acquisto-note-input" type="text" name="note" data-testid="acquisto-note-input">
            <p class="nota">Registra l'acquisto solo dopo avere verificato che il pagamento sia stato effettivamente ricevuto.</p>
            <p class="banner" id="acq-importo" data-testid="acquisto-importo">L'importo viene calcolato automaticamente.</p>
            <button class="btn btn-primary" type="submit" data-testid="acquisto-submit-button">Conferma l'acquisto</button>
        </form>`, async (fd) => {
        const plessoId = Number(fd.get('plesso_id'));
        const numeroBuoni = Number(fd.get('numero_buoni'));
        const tariffa = await API.get('/api/tariffe/attiva?genitore_id=' + genitoreId + '&plesso_id=' + plessoId);
        const confermato = await UI.conferma('Conferma acquisto buoni',
            'Stai per registrare ' + numeroBuoni + ' buoni per un importo totale di ' +
            euro(numeroBuoni * Number(tariffa.tariffa)) + '. Hai verificato il pagamento?', 'Registra acquisto');
        if (!confermato) return false;
        UI.stato('modale-stato', 'Registrazione in corso. Non chiudere la pagina…', 'caricamento');
        const esito = await API.post('/api/crediti/acquisti', {
            genitore_id: genitoreId,
            plesso_id: plessoId,
            numero_buoni: numeroBuoni,
            richiesta_id: richiestaId,
            importo_atteso: Math.round(numeroBuoni * Number(tariffa.tariffa) * 100) / 100,
            note: fd.get('note')
        });
        avviso((esito.gia_registrato ? 'Acquisto già registrato, nessun doppio addebito. ' : 'Acquisto registrato. ') + 'Totale: ' + euro(esito.importo) + '. Saldo attuale: ' + esito.saldo + ' buoni.');
        apriFamiglia(genitoreId);
    });

    const aggiornaImporto = async () => {
        const plessoId = document.getElementById('acq-plesso').value;
        const buoni = Number(document.getElementById('acq-buoni').value || 0);
        const t = await API.get(`/api/tariffe/attiva?genitore_id=${genitoreId}&plesso_id=${plessoId}`);
        document.getElementById('acq-importo').innerHTML =
            `Costo di un pasto: <strong>${euro(t.tariffa)}</strong> (${t.origine === 'override' ? 'tariffa agevolata' : 'tariffa ordinaria'}) ·
             Totale dell'acquisto: <strong>${euro(t.tariffa * buoni)}</strong>`;
    };
    document.getElementById('acq-plesso').addEventListener('change', aggiornaImporto);
    document.getElementById('acq-buoni').addEventListener('input', aggiornaImporto);
    aggiornaImporto();
}

function formOverride(genitoreId) {
    apriModale('Imposta una tariffa agevolata', `
        <form data-testid="override-form">
            <label for="op-override-plesso-select">Plesso</label><select id="op-override-plesso-select" name="plesso_id" data-testid="override-plesso-select">${opzioniPlessi()}</select>
            <label for="op-override-tariffa-input">Nuovo prezzo di un pasto (€)</label><input id="op-override-tariffa-input" type="number" step="0.01" min="0" name="tariffa" data-testid="override-tariffa-input" required>
            <label for="op-override-motivazione-input">Perché è prevista questa tariffa? (es. delibera)</label><textarea id="op-override-motivazione-input" name="motivazione" rows="2" data-testid="override-motivazione-input" required></textarea>
            <button class="btn btn-primary" type="submit" data-testid="override-submit-button">Salva tariffa agevolata</button>
        </form>`, async (fd) => {
        await API.post('/api/tariffe/override', {
            genitore_id: genitoreId,
            plesso_id: Number(fd.get('plesso_id')),
            tariffa: Number(fd.get('tariffa')),
            motivazione: fd.get('motivazione')
        });
        avviso('Tariffa agevolata registrata.');
        apriFamiglia(genitoreId);
    });
}

function formRettifica(genitoreId) {
    const richiestaId = crypto.randomUUID ? crypto.randomUUID()
        : Array.from(crypto.getRandomValues(new Uint8Array(16)), (n) => n.toString(16).padStart(2, '0')).join('');
    apriModale('Correggi il numero di buoni', `
        <form data-testid="rettifica-form">
            <label for="op-rettifica-plesso-select">Plesso</label><select id="op-rettifica-plesso-select" name="plesso_id" data-testid="rettifica-plesso-select">${opzioniPlessi()}</select>
            <p class="nota">Scrivi per esempio <strong>2</strong> per aggiungere due buoni oppure <strong>-2</strong> per toglierne due. Questa operazione viene registrata nello storico.</p>
            <label for="op-rettifica-delta-input">Quanti buoni aggiungere o togliere?</label><input id="op-rettifica-delta-input" type="number" name="delta" data-testid="rettifica-delta-input" required>
            <label for="op-rettifica-note-input">Motivazione</label><input id="op-rettifica-note-input" type="text" name="note" data-testid="rettifica-note-input" required>
            <button class="btn btn-primary" type="submit" data-testid="rettifica-submit-button">Salva la correzione</button>
        </form>`, async (fd) => {
        const delta = Number(fd.get('delta'));
        if (!delta) throw new Error('Scrivi un numero di buoni diverso da zero.');
        if (!await UI.conferma('Conferma correzione buoni',
            'La correzione ' + (delta > 0 ? 'aggiungerà ' : 'toglierà ') + Math.abs(delta) +
            ' buoni alla famiglia. Il movimento sarà conservato nello storico.', 'Conferma correzione')) return false;
        await API.post('/api/crediti/rettifiche', {
            genitore_id: genitoreId,
            plesso_id: Number(fd.get('plesso_id')),
            delta,
            richiesta_id: richiestaId,
            note: fd.get('note')
        });
        avviso('Saldo buoni corretto. La modifica è registrata nello storico.');
        apriFamiglia(genitoreId);
    });
}

function formNuovoGenitore() {
    apriModale('Aggiungi una famiglia', `
        <form data-testid="genitore-form">
            <label for="op-genitore-nome-input">Nome</label><input id="op-genitore-nome-input" type="text" name="nome" data-testid="genitore-nome-input" required>
            <label for="op-genitore-cognome-input">Cognome</label><input id="op-genitore-cognome-input" type="text" name="cognome" data-testid="genitore-cognome-input" required>
            <label for="op-genitore-telefono-input">Telefono (obbligatorio)</label><input id="op-genitore-telefono-input" type="text" name="telefono" data-testid="genitore-telefono-input" required>
            <label for="op-genitore-email-input">Email (facoltativa, usata per i solleciti)</label><input id="op-genitore-email-input" type="text" name="email" data-testid="genitore-email-input">
            <label for="op-genitore-username-input">Nome utente per accedere (deve essere unico)</label><input id="op-genitore-username-input" type="text" name="username" data-testid="genitore-username-input" required>
            <label for="op-genitore-password-input">Password iniziale (almeno 8 caratteri)</label><input id="op-genitore-password-input" type="password" name="password" data-testid="genitore-password-input" required>
            <button class="btn btn-primary" type="submit" data-testid="genitore-submit-button">Salva la famiglia</button>
        </form>`, async (fd) => {
        const esito = await API.post('/api/famiglie', Object.fromEntries(fd.entries()));
        avviso('Famiglia creata.');
        await cerca();
        apriFamiglia(esito.id);
    });
}

function formModificaGenitore(g) {
    apriModale('Modifica dati famiglia', `
        <form data-testid="modifica-genitore-form">
            <label for="op-modifica-nome-input">Nome</label><input id="op-modifica-nome-input" type="text" name="nome" value="${esc(g.nome)}" data-testid="modifica-nome-input">
            <label for="op-modifica-cognome-input">Cognome</label><input id="op-modifica-cognome-input" type="text" name="cognome" value="${esc(g.cognome)}" data-testid="modifica-cognome-input">
            <label for="op-modifica-telefono-input">Telefono</label><input id="op-modifica-telefono-input" type="text" name="telefono" value="${esc(g.telefono)}" data-testid="modifica-telefono-input">
            <label for="op-modifica-email-input">Email</label><input id="op-modifica-email-input" type="text" name="email" value="${esc(g.email || '')}" data-testid="modifica-email-input">
            <button class="btn btn-primary" type="submit" data-testid="modifica-submit-button">Salva</button>
        </form>`, async (fd) => {
        await API.put('/api/famiglie/' + g.id, Object.fromEntries(fd.entries()));
        avviso('Dati aggiornati.');
        apriFamiglia(Number(g.id));
    });
}

function formNuovoFiglio(genitoreId) {
    apriModale('Nuovo alunno', `
        <form data-testid="bambino-form">
            <label for="op-bambino-nome-input">Nome</label><input id="op-bambino-nome-input" type="text" name="nome" data-testid="bambino-nome-input" required>
            <label for="op-bambino-cognome-input">Cognome</label><input id="op-bambino-cognome-input" type="text" name="cognome" data-testid="bambino-cognome-input" required>
            <label for="op-bambino-tipologia-select">Tipologia menu</label><select id="op-bambino-tipologia-select" name="tipologia_menu_id" data-testid="bambino-tipologia-select">${opzioniTipologie()}</select>
            <label for="op-bambino-classe-select">Classe/sezione</label><select id="op-bambino-classe-select" name="classe_sezione_id" data-testid="bambino-classe-select">${opzioniClassi()}</select>
            <button class="btn btn-primary" type="submit" data-testid="bambino-submit-button">Crea alunno</button>
        </form>`, async (fd) => {
        await API.post('/api/bambini', { ...Object.fromEntries(fd.entries()), genitore_id: genitoreId });
        avviso('Alunno registrato.');
        apriFamiglia(genitoreId);
    });
}

function formCambioClasse(bambinoId) {
    apriModale('Cambia la classe del bambino', `
        <form data-testid="cambio-classe-form">
            <label for="op-cambio-classe-select">Nuova classe</label><select id="op-cambio-classe-select" name="classe_sezione_id" data-testid="cambio-classe-select">${opzioniClassi()}</select>
            <label for="op-cambio-classe-data-input">Da quale giorno frequenta la nuova classe?</label><input id="op-cambio-classe-data-input" type="date" name="data_inizio" value="${oggi()}" data-testid="cambio-classe-data-input">
            <p class="nota">La classe precedente rimane nello storico; la nuova classe vale dal giorno indicato.</p>
            <button class="btn btn-primary" type="submit" data-testid="cambio-classe-submit-button">Assegna</button>
        </form>`, async (fd) => {
        await API.post(`/api/bambini/${bambinoId}/assegnazioni`, Object.fromEntries(fd.entries()));
        avviso('Assegnazione aggiornata.');
        apriFamiglia(famigliaCorrente.genitore.id);
    });
}

function formDieta(bambinoId) {
    apriModale('Cambia il menu del bambino', `
        <form data-testid="dieta-form">
            <label for="op-dieta-select">Nuovo tipo di menu</label><select id="op-dieta-select" name="tipologia_menu_id" data-testid="dieta-select">${opzioniTipologie()}</select>
            <button class="btn btn-primary" type="submit" data-testid="dieta-submit-button">Salva</button>
        </form>`, async (fd) => {
        await API.put('/api/bambini/' + bambinoId, { tipologia_menu_id: Number(fd.get('tipologia_menu_id')) });
        avviso('Tipologia aggiornata.');
        apriFamiglia(famigliaCorrente.genitore.id);
    });
}

/* ---------------- viste crediti / solleciti ---------------- */
async function caricaSituazione() {
    const elenco = await API.get('/api/crediti/situazione');
    document.getElementById('crediti-corpo').innerHTML = elenco.map((r) => `
        <tr><td>${esc(r.genitore_cognome)} ${esc(r.genitore_nome)}</td><td>${esc(r.telefono)}</td>
        <td>${esc(r.plesso_nome)}</td><td>${r.saldo}</td><td>${fidoConcesso(r.fido_buoni)}</td>
        <td><span class="badge-stato badge-${r.stato}">${situazioneBuoni(r.stato)}</span></td></tr>`).join('')
        || '<tr><td colspan="6" class="vuoto">Nessun dato.</td></tr>';
}

async function caricaSolleciti() {
    const elenco = await API.get('/api/solleciti');
    document.getElementById('solleciti-corpo').innerHTML = elenco.map((r) => `
        <tr><td>${dataIt(r.data_invio)}</td><td>${esc(r.genitore_cognome)} ${esc(r.genitore_nome)}</td>
        <td>${esc(r.plesso_nome)}</td><td>${r.saldo_al_momento_invio}</td>
        <td><span class="badge-stato badge-${r.esito === 'inviato' ? 'verde' : 'rosso'}">${esc(esitoOperazione(r.esito))}</span>${r.errore ? '<br><span class="nota">' + esc(r.errore) + '</span>' : ''}</td>
        <td class="nota">${esc(r.destinatari || '')}</td></tr>`).join('')
        || '<tr><td colspan="6" class="vuoto">Nessun sollecito inviato.</td></tr>';
}

document.getElementById('btn-cerca').addEventListener('click', cerca);
document.getElementById('ricerca-famiglie').addEventListener('keydown', (e) => { if (e.key === 'Enter') cerca(); });
document.getElementById('btn-nuovo-genitore').addEventListener('click', formNuovoGenitore);

(async () => {
    riferimenti = await API.get('/api/riferimenti');
    await cerca();
})().catch((e) => avviso(e.message, 'errore'));
