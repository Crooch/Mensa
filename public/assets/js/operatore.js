/* Area operatore comunale: anagrafiche, acquisti buoni, override tariffari. */
const utente = proteggi('operatore');
let riferimenti = { plessi: [], tipologie: [], classi: [] };
let famigliaCorrente = null;

abilitaSchede((vista) => {
    if (vista === 'crediti') caricaSituazione();
    if (vista === 'solleciti') caricaSolleciti();
});

const modale = document.getElementById('modale');
document.getElementById('modale-chiudi').addEventListener('click', () => { modale.hidden = true; });

function apriModale(titolo, html, alSalva) {
    document.getElementById('modale-titolo').textContent = titolo;
    document.getElementById('modale-corpo').innerHTML = html;
    modale.hidden = false;
    const form = modale.querySelector('form');
    if (form) form.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await alSalva(new FormData(form));
            modale.hidden = true;
        } catch (err) { avviso(err.message, 'errore'); }
    });
}

const opzioniPlessi = () => riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
const opzioniTipologie = () => riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
const opzioniClassi = () => riferimenti.classi.map((c) => `<option value="${c.id}">${esc(c.plesso_nome)} · ${esc(c.nome)} (${esc(c.anno_scolastico)})</option>`).join('');

async function cerca() {
    const q = document.getElementById('ricerca-famiglie').value.trim();
    const elenco = await API.get('/api/famiglie?q=' + encodeURIComponent(q));
    document.getElementById('famiglie-corpo').innerHTML = elenco.map((g) => `
        <tr data-genitore="${g.id}">
            <td>${esc(g.cognome)} ${esc(g.nome)}</td><td>${esc(g.telefono)}</td><td>${esc(g.email || '-')}</td>
            <td><button class="btn-azione" data-apri="${g.id}" data-testid="apri-famiglia-${g.id}">Apri</button></td>
        </tr>`).join('') || '<tr><td colspan="4" class="vuoto">Nessun risultato.</td></tr>';
    document.querySelectorAll('[data-apri]').forEach((b) => b.addEventListener('click', () => apriFamiglia(Number(b.dataset.apri))));
}

async function apriFamiglia(id) {
    const s = await API.get('/api/famiglie/' + id);
    famigliaCorrente = s;
    const g = s.genitore;
    document.getElementById('scheda-famiglia').innerHTML = `
        <h2 data-testid="famiglia-nome">${esc(g.cognome)} ${esc(g.nome)}</h2>
        <p class="nota">Telefono ${esc(g.telefono)} · Email ${esc(g.email || 'non presente')} · Utenza ${esc(g.username || '-')}
        ${Number(g.cancellato_logicamente) === 1 ? '<span class="badge-stato badge-rosso">cancellata</span>' : ''}</p>
        <div class="riga-filtri">
            <button class="btn btn-primary btn-sm" id="btn-acquisto" data-testid="acquisto-buoni-button">Acquisto buoni</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-override" data-testid="override-tariffa-button">Tariffa agevolata</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-rettifica" data-testid="rettifica-button">Correggi buoni</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-modifica-genitore" data-testid="modifica-genitore-button">Modifica dati</button>
            <button class="btn btn-outline-primary btn-sm" id="btn-nuovo-figlio" data-testid="nuovo-figlio-button">Aggiungi figlio</button>
        </div>

        <h2>Crediti per plesso</h2>
        <div class="griglia-cards">${s.crediti.map((c) => `
            <article class="card-mensa card-mensa--${c.stato}" data-testid="credito-${c.plesso_id}">
                <h3>${esc(c.plesso_nome)}</h3><span class="saldo">${c.saldo}</span>
                <dl><dt>Soglia di avviso</dt><dd>${c.fido_buoni}</dd>
                <dt>Tariffa</dt><dd>${euro(c.tariffa_attiva)} (${esc(c.origine_tariffa)})</dd></dl>
            </article>`).join('') || '<p class="vuoto">Nessun pool crediti.</p>'}</div>

        <h2>Figli</h2>
        ${s.figli.map((f) => `
            <div class="card-mensa" data-testid="figlio-${f.id}" style="margin-bottom:.6rem">
                <h3>${esc(f.cognome)} ${esc(f.nome)} ${Number(f.cancellato_logicamente) === 1 ? '<span class="badge-stato badge-rosso">cancellato</span>' : ''}</h3>
                <dl>
                    <dt>Classe attuale</dt><dd>${esc(f.plesso_nome || '-')} ${esc(f.classe_nome || '')}</dd>
                    <dt>Dieta</dt><dd>${esc(f.tipologia_nome)}</dd>
                </dl>
                <details><summary>Storico classi (${f.storico_classi.length})</summary>
                    <ul class="nota">${f.storico_classi.map((a) => `<li>${esc(a.plesso_nome)} · ${esc(a.classe_nome)} (${esc(a.anno_scolastico)}) dal ${dataIt(a.data_inizio)} ${a.data_fine ? 'al ' + dataIt(a.data_fine) : '(corrente)'}</li>`).join('')}</ul>
                </details>
                <div class="riga-filtri" style="margin-top:.5rem">
                    <button class="btn-azione" data-cambio-classe="${f.id}" data-testid="cambia-classe-${f.id}">Cambia classe</button>
                    <button class="btn-azione" data-dieta="${f.id}" data-testid="cambia-dieta-${f.id}">Cambia dieta</button>
                    <button class="btn-azione" data-cancella-figlio="${f.id}" data-testid="cancella-figlio-${f.id}">Cancella (logica)</button>
                </div>
            </div>`).join('') || '<p class="vuoto">Nessun figlio registrato.</p>'}

        <h2>Storico delle tariffe agevolate</h2>
        <div class="tabella-scroll"><table class="table" data-testid="override-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tariffa</th><th>Motivazione</th><th>Inserito da</th></tr></thead>
            <tbody>${s.override_tariffe.map((o) => `<tr><td>${dataIt(o.data_inserimento)}</td><td>${esc(o.plesso_nome)}</td>
                <td>${euro(o.tariffa)}</td><td>${esc(o.motivazione)}</td><td>${esc(o.inserito_da || '-')}</td></tr>`).join('')
                || '<tr><td colspan="5" class="vuoto">Nessuna tariffa agevolata registrata.</td></tr>'}</tbody>
        </table></div>

        <h2>Movimenti</h2>
        <div class="tabella-scroll"><table class="table" data-testid="movimenti-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Tipo</th><th>Buoni</th><th>Importo</th><th>Alunno</th><th>Operatore</th></tr></thead>
            <tbody>${s.movimenti.map((m) => `<tr><td>${dataIt(m.data)}</td><td>${esc(m.plesso_nome)}</td><td>${esc(m.tipo)}</td>
                <td>${m.delta > 0 ? '+' : ''}${m.delta}</td><td>${m.importo_euro ? euro(m.importo_euro) : '-'}</td>
                <td>${m.bambino_nome ? esc(m.bambino_nome) : '-'}</td><td>${esc(m.operatore || 'sistema')}</td></tr>`).join('')
                || '<tr><td colspan="7" class="vuoto">Nessun movimento.</td></tr>'}</tbody>
        </table></div>`;

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
        if (!confirm('Confermi la cancellazione logica? Lo storico resta consultabile.')) return;
        await API.del('/api/bambini/' + b.dataset.cancellaFiglio);
        avviso('Alunno cancellato logicamente.');
        apriFamiglia(genitoreId);
    }));
}

/* ---------------- form ---------------- */
function formAcquisto(genitoreId) {
    apriModale('Acquisto buoni pasto', `
        <form data-testid="acquisto-form">
            <label>Plesso</label><select name="plesso_id" id="acq-plesso" data-testid="acquisto-plesso-select">${opzioniPlessi()}</select>
            <label>Numero buoni</label><input type="number" min="1" name="numero_buoni" id="acq-buoni" value="10" data-testid="acquisto-buoni-input">
            <label>Note (facoltative)</label><input type="text" name="note" data-testid="acquisto-note-input">
            <p class="banner" id="acq-importo" data-testid="acquisto-importo">Importo calcolato automaticamente dal sistema.</p>
            <button class="btn btn-primary" type="submit" data-testid="acquisto-submit-button">Registra incasso</button>
        </form>`, async (fd) => {
        const esito = await API.post('/api/crediti/acquisti', {
            genitore_id: genitoreId,
            plesso_id: Number(fd.get('plesso_id')),
            numero_buoni: Number(fd.get('numero_buoni')),
            note: fd.get('note')
        });
        avviso(`Incasso registrato: ${euro(esito.importo)} · nuovo saldo ${esito.saldo} buoni.`);
        apriFamiglia(genitoreId);
    });

    const aggiornaImporto = async () => {
        const plessoId = document.getElementById('acq-plesso').value;
        const buoni = Number(document.getElementById('acq-buoni').value || 0);
        const t = await API.get(`/api/tariffe/attiva?genitore_id=${genitoreId}&plesso_id=${plessoId}`);
        document.getElementById('acq-importo').innerHTML =
            `Tariffa applicata: <strong>${euro(t.tariffa)}</strong> (${t.origine === 'override' ? 'tariffa agevolata' : 'tariffa base'}) ·
             Importo da incassare: <strong>${euro(t.tariffa * buoni)}</strong>`;
    };
    document.getElementById('acq-plesso').addEventListener('change', aggiornaImporto);
    document.getElementById('acq-buoni').addEventListener('input', aggiornaImporto);
    aggiornaImporto();
}

function formOverride(genitoreId) {
    apriModale('Imposta una tariffa agevolata', `
        <form data-testid="override-form">
            <label>Plesso</label><select name="plesso_id" data-testid="override-plesso-select">${opzioniPlessi()}</select>
            <label>Tariffa concordata (€)</label><input type="number" step="0.01" min="0" name="tariffa" data-testid="override-tariffa-input" required>
            <label>Motivazione (es. delibera n. .../2026)</label><textarea name="motivazione" rows="2" data-testid="override-motivazione-input" required></textarea>
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
    apriModale('Correggi il numero di buoni', `
        <form data-testid="rettifica-form">
            <label>Plesso</label><select name="plesso_id" data-testid="rettifica-plesso-select">${opzioniPlessi()}</select>
            <label>Variazione buoni (positiva o negativa)</label><input type="number" name="delta" data-testid="rettifica-delta-input" required>
            <label>Motivazione</label><input type="text" name="note" data-testid="rettifica-note-input" required>
            <button class="btn btn-primary" type="submit" data-testid="rettifica-submit-button">Applica rettifica</button>
        </form>`, async (fd) => {
        await API.post('/api/crediti/rettifiche', {
            genitore_id: genitoreId,
            plesso_id: Number(fd.get('plesso_id')),
            delta: Number(fd.get('delta')),
            note: fd.get('note')
        });
        avviso('Rettifica registrata.');
        apriFamiglia(genitoreId);
    });
}

function formNuovoGenitore() {
    apriModale('Nuovo genitore e utenza', `
        <form data-testid="genitore-form">
            <label>Nome</label><input type="text" name="nome" data-testid="genitore-nome-input" required>
            <label>Cognome</label><input type="text" name="cognome" data-testid="genitore-cognome-input" required>
            <label>Telefono (obbligatorio)</label><input type="text" name="telefono" data-testid="genitore-telefono-input" required>
            <label>Email (facoltativa, usata per i solleciti)</label><input type="text" name="email" data-testid="genitore-email-input">
            <label>Username (stringa libera univoca)</label><input type="text" name="username" data-testid="genitore-username-input" required>
            <label>Password iniziale (min. 8 caratteri)</label><input type="text" name="password" data-testid="genitore-password-input" required>
            <button class="btn btn-primary" type="submit" data-testid="genitore-submit-button">Crea famiglia</button>
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
            <label>Nome</label><input type="text" name="nome" value="${esc(g.nome)}" data-testid="modifica-nome-input">
            <label>Cognome</label><input type="text" name="cognome" value="${esc(g.cognome)}" data-testid="modifica-cognome-input">
            <label>Telefono</label><input type="text" name="telefono" value="${esc(g.telefono)}" data-testid="modifica-telefono-input">
            <label>Email</label><input type="text" name="email" value="${esc(g.email || '')}" data-testid="modifica-email-input">
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
            <label>Nome</label><input type="text" name="nome" data-testid="bambino-nome-input" required>
            <label>Cognome</label><input type="text" name="cognome" data-testid="bambino-cognome-input" required>
            <label>Tipologia menu</label><select name="tipologia_menu_id" data-testid="bambino-tipologia-select">${opzioniTipologie()}</select>
            <label>Classe/sezione</label><select name="classe_sezione_id" data-testid="bambino-classe-select">${opzioniClassi()}</select>
            <button class="btn btn-primary" type="submit" data-testid="bambino-submit-button">Crea alunno</button>
        </form>`, async (fd) => {
        await API.post('/api/bambini', { ...Object.fromEntries(fd.entries()), genitore_id: genitoreId });
        avviso('Alunno registrato.');
        apriFamiglia(genitoreId);
    });
}

function formCambioClasse(bambinoId) {
    apriModale('Cambio classe (con storicizzazione)', `
        <form data-testid="cambio-classe-form">
            <label>Nuova classe</label><select name="classe_sezione_id" data-testid="cambio-classe-select">${opzioniClassi()}</select>
            <label>Valida dal</label><input type="date" name="data_inizio" value="${oggi()}" data-testid="cambio-classe-data-input">
            <p class="nota">L'assegnazione corrente verrà chiusa il giorno precedente: lo storico presenze resta coerente.</p>
            <button class="btn btn-primary" type="submit" data-testid="cambio-classe-submit-button">Assegna</button>
        </form>`, async (fd) => {
        await API.post(`/api/bambini/${bambinoId}/assegnazioni`, Object.fromEntries(fd.entries()));
        avviso('Assegnazione aggiornata.');
        apriFamiglia(famigliaCorrente.genitore.id);
    });
}

function formDieta(bambinoId) {
    apriModale('Cambio tipologia menu', `
        <form data-testid="dieta-form">
            <label>Tipologia</label><select name="tipologia_menu_id" data-testid="dieta-select">${opzioniTipologie()}</select>
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
        <td>${esc(r.plesso_nome)}</td><td>${r.saldo}</td><td>${r.fido_buoni}</td>
        <td><span class="badge-stato badge-${r.stato}">${r.stato}</span></td></tr>`).join('')
        || '<tr><td colspan="6" class="vuoto">Nessun dato.</td></tr>';
}

async function caricaSolleciti() {
    const elenco = await API.get('/api/solleciti');
    document.getElementById('solleciti-corpo').innerHTML = elenco.map((r) => `
        <tr><td>${dataIt(r.data_invio)}</td><td>${esc(r.genitore_cognome)} ${esc(r.genitore_nome)}</td>
        <td>${esc(r.plesso_nome)}</td><td>${r.saldo_al_momento_invio}</td>
        <td><span class="badge-stato badge-${r.esito === 'inviato' ? 'verde' : 'rosso'}">${esc(r.esito)}</span>${r.errore ? '<br><span class="nota">' + esc(r.errore) + '</span>' : ''}</td>
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
