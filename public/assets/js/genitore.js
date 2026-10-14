/* Area genitore: sola lettura + dichiarazione eccezioni e feedback. */
const utente = proteggi('genitore');
let statoApp = { figli: [], votoSelezionato: null };

abilitaSchede((vista) => {
    if (vista === 'calendario') caricaCalendario();
});

async function caricaDashboard() {
    const dati = await API.get('/api/genitore/dashboard');
    statoApp.figli = dati.figli;

    document.getElementById('crediti-cards').innerHTML = dati.crediti.length === 0
        ? '<p class="vuoto">Nessuna ricarica registrata.</p>'
        : dati.crediti.map((c) => `
            <article class="card-mensa card-mensa--${c.stato}" data-testid="credito-card-${c.plesso_id}">
                <h3>${esc(c.plesso_nome)}</h3>
                <span class="saldo">${c.saldo}</span>
                <p class="nota">buoni disponibili</p>
                <span class="badge-stato badge-${c.stato}">${c.stato === 'verde' ? 'regolare' : c.stato === 'giallo' ? 'attenzione' : 'oltre fido'}</span>
                <dl>
                    <dt>Fido</dt><dd>${c.fido_buoni} buoni</dd>
                    <dt>Tariffa</dt><dd>${euro(c.tariffa_attiva)}${c.origine_tariffa === 'override' ? ' (agevolata)' : ''}</dd>
                </dl>
            </article>`).join('');

    document.getElementById('figli-lista').innerHTML = dati.figli.map((f) => {
        const s = f.stato_oggi;
        const dichiarazione = s ? (s.dichiarazione_genitore || '—') : '—';
        return `
        <article class="card-mensa" data-testid="figlio-card-${f.id}">
            <h3>${esc(f.cognome)} ${esc(f.nome)}</h3>
            <dl>
                <dt>Plesso</dt><dd>${esc(f.plesso_nome || 'non assegnato')}</dd>
                <dt>Classe</dt><dd>${esc(f.classe_nome || '-')} ${esc(f.anno_scolastico || '')}</dd>
                <dt>Dieta</dt><dd>${esc(f.tipologia_nome)}</dd>
                <dt>Oggi</dt><dd>${s ? (s.servizio_mensa && !s.servizio_mensa.attivo ? 'Mensa non prevista' : badgeStato(s.stato_effettivo)) : '-'}</dd>
                <dt>Tua dichiarazione</dt><dd>${esc(dichiarazione)}</dd>
                <dt>Pasta in bianco oggi</dt><dd>${s && s.pasta_in_bianco ? 'richiesta' : 'non richiesta'}</dd>
                <dt>Cutoff</dt><dd>${s ? s.cutoff_orario + (s.oltre_cutoff ? ' (superato)' : '') : '-'}</dd>
            </dl>
            ${s ? `<div class="riga-filtri" style="margin-top:.75rem">
                <label for="figlio-data-${f.id}">Data (oggi o futura)</label>
                <input type="date" id="figlio-data-${f.id}" value="${oggi()}" min="${oggi()}" data-testid="figlio-data-${f.id}">
                <button class="btn-azione" data-azione="presente" data-figlio="${f.id}" data-testid="dichiara-presente-${f.id}">Sarà presente</button>
                <button class="btn-azione" data-azione="assente" data-figlio="${f.id}" data-testid="dichiara-assente-${f.id}">Sarà assente</button>
                <button class="btn-azione" data-pasta="1" data-figlio="${f.id}" data-testid="pasta-richiesta-${f.id}">Richiedi pasta in bianco</button>
                <button class="btn-azione" data-pasta="0" data-figlio="${f.id}" data-testid="pasta-annulla-${f.id}">Annulla pasta in bianco</button>
            </div>` : '<p class="nota">Nessuna classe attiva: contattare l\'ufficio comunale.</p>'}
        </article>`;
    }).join('') || '<p class="vuoto">Nessun figlio associato.</p>';

    document.querySelectorAll('[data-azione]').forEach((b) => b.addEventListener('click', () => {
        const bambinoId = Number(b.dataset.figlio);
        const data = document.getElementById('figlio-data-' + bambinoId).value;
        if (!data || data < oggi()) { avviso('Seleziona oggi o una data futura.', 'errore'); return; }
        dichiara(bambinoId, b.dataset.azione, data);
    }));

    document.querySelectorAll('[data-pasta]').forEach((b) => b.addEventListener('click', async () => {
        const bambinoId = Number(b.dataset.figlio);
        const data = document.getElementById('figlio-data-' + bambinoId).value;
        if (!data || data < oggi()) { avviso('Seleziona oggi o una data futura.', 'errore'); return; }
        try {
            await API.post('/api/genitore/pasta-bianco', {
                bambino_id: bambinoId, data, richiesta: Number(b.dataset.pasta)
            });
            avviso((Number(b.dataset.pasta) === 1 ? 'Pasta in bianco richiesta' : 'Richiesta pasta in bianco annullata')
                + ' per il ' + dataIt(data) + '.');
            await caricaDashboard();
        } catch (err) { avviso(err.message, 'errore'); }
    }));

    document.getElementById('movimenti-corpo').innerHTML = dati.movimenti.map((m) => `
        <tr>
            <td>${dataIt(m.data)}</td><td>${esc(m.plesso_nome)}</td>
            <td>${esc(m.tipo.replace('_', ' '))}</td>
            <td>${m.delta > 0 ? '+' : ''}${m.delta}</td>
            <td>${m.importo_euro ? euro(m.importo_euro) : '-'}</td>
            <td>${m.bambino_nome ? esc(m.bambino_nome + ' ' + m.bambino_cognome) : '-'}</td>
            <td>${esc(m.note || '')}</td>
        </tr>`).join('') || '<tr><td colspan="7" class="vuoto">Nessun movimento.</td></tr>';

    mostraFeedback(dati.feedback);
    riempiSelectFigli();
    caricaMenu();
}

function riempiSelectFigli() {
    const opzioni = statoApp.figli.map((f) => `<option value="${f.id}">${esc(f.cognome)} ${esc(f.nome)}</option>`).join('');
    document.getElementById('cal-figlio').innerHTML = opzioni;
    document.getElementById('fb-figlio').innerHTML = opzioni;
}

async function dichiara(bambinoId, stato, data = oggi()) {
    try {
        const esito = await API.post('/api/genitore/presenze/dichiarazione', { bambino_id: bambinoId, data, stato });
        avviso(esito.fuori_cutoff
            ? 'Dichiarazione registrata dopo il cutoff: non modifica il pasto di oggi, ma resta tracciata.'
            : `Dichiarazione per il ${dataIt(data)} registrata: ${esito.stato_effettivo}.`, esito.fuori_cutoff ? 'errore' : 'ok');
        caricaDashboard();
    } catch (e) { avviso(e.message, 'errore'); }
}

async function caricaMenu() {
    const menu = await API.get('/api/genitore/menu?data=' + oggi());
    document.getElementById('menu-oggi').innerHTML = menu.length === 0
        ? '<p class="vuoto">Nessun menu pubblicato per oggi.</p>'
        : menu.map((m) => `
            <article class="card-mensa">
                <h3>${esc(m.plesso_nome)} · ${esc(m.tipologia_nome)}</h3>
                <p>${esc(m.descrizione || '')}</p>
                ${m.foto_path ? `<img src="${BASE}/storage/${esc(m.foto_path)}" alt="Menu del giorno">` : ''}
            </article>`).join('');
}

/* ---------------- calendario ---------------- */
async function caricaCalendario() {
    const select = document.getElementById('cal-figlio');
    if (!select.value) return;
    const mese = document.getElementById('cal-mese').value || oggi().slice(0, 7);
    document.getElementById('cal-mese').value = mese;
    const [anno, m] = mese.split('-');
    const dati = await API.get(`/api/genitore/figli/${select.value}/calendario?anno=${anno}&mese=${Number(m)}`);
    disegnaCalendario(dati, Number(anno), Number(m));
}

function disegnaCalendario(dati, anno, mese) {
    const perData = {};
    dati.giorni.forEach((g) => { perData[g.data] = g; });
    const giorniSettimana = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    let html = giorniSettimana.map((g) => `<div class="intestazione-giorno">${g}</div>`).join('');
    const primo = new Date(anno, mese - 1, 1);
    const offset = (primo.getDay() + 6) % 7;
    for (let i = 0; i < offset; i++) html += '<div class="giorno vuoto"></div>';
    const ultimo = new Date(anno, mese, 0).getDate();
    for (let giorno = 1; giorno <= ultimo; giorno++) {
        const data = `${anno}-${String(mese).padStart(2, '0')}-${String(giorno).padStart(2, '0')}`;
        const r = perData[data];
        let classi = 'giorno';
        if (r) {
            classi += ' ' + (r.stato_effettivo === 'presente' ? 'presente' : 'assente');
            if (r.default_calcolato === 'assente' && !r.dichiarazione_genitore) classi += ' gita';
            if (r.override_scuola) classi += ' override';
        }
        html += `<button class="${classi}" data-data="${data}" data-testid="giorno-${data}">
            <strong>${giorno}</strong>${r ? (r.stato_effettivo === 'presente' ? 'presente' : 'assente') : ''}</button>`;
    }
    const griglia = document.getElementById('calendario');
    griglia.innerHTML = html;
    griglia.querySelectorAll('.giorno[data-data]').forEach((b) => b.addEventListener('click', () => {
        const r = perData[b.dataset.data];
        document.getElementById('calendario-dettaglio').innerHTML = !r
            ? `<p class="vuoto">Nessun dato registrato per il ${dataIt(b.dataset.data)}.</p>`
            : `<h2>Dettaglio ${dataIt(r.data)}</h2>
               <dl class="card-mensa">
                 <dt>Default (assenze di massa)</dt><dd>${esc(r.default_calcolato)}</dd>
                 <dt>Dichiarazione famiglia</dt><dd>${esc(r.dichiarazione_genitore || '—')}</dd>
                 <dt>Override scuola</dt><dd>${esc(r.override_scuola || '—')} ${r.override_motivazione ? '· ' + esc(r.override_motivazione) : ''}</dd>
                 <dt>Stato effettivo</dt><dd>${badgeStato(r.stato_effettivo)}</dd>
                 <dt>Pasta in bianco</dt><dd>${Number(r.pasta_in_bianco) === 1 ? 'richiesta' : 'non richiesta'}</dd>
                 <dt>Fuori cutoff</dt><dd>${Number(r.fuori_cutoff) === 1 ? 'sì (senza effetto sul conteggio)' : 'no'}</dd>
               </dl>`;
    }));
}

document.getElementById('btn-carica-calendario').addEventListener('click', caricaCalendario);
document.getElementById('cal-figlio').addEventListener('change', caricaCalendario);

/* ---------------- feedback ---------------- */
const contenitoreStelle = document.getElementById('fb-stelle');
contenitoreStelle.innerHTML = [1, 2, 3, 4, 5].map((v) => `<button type="button" data-voto="${v}" data-testid="stella-${v}">★</button>`).join('');
contenitoreStelle.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => {
    statoApp.votoSelezionato = Number(b.dataset.voto);
    contenitoreStelle.querySelectorAll('button').forEach((x) => x.classList.toggle('attiva', Number(x.dataset.voto) <= statoApp.votoSelezionato));
}));

document.getElementById('fb-data').value = oggi();
document.getElementById('form-feedback').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/genitore/feedback', {
            bambino_id: Number(document.getElementById('fb-figlio').value),
            data: document.getElementById('fb-data').value,
            voto: statoApp.votoSelezionato,
            commento: document.getElementById('fb-commento').value
        });
        avviso('Feedback inviato. Grazie!');
        document.getElementById('fb-commento').value = '';
        statoApp.votoSelezionato = null;
        contenitoreStelle.querySelectorAll('button').forEach((x) => x.classList.remove('attiva'));
        mostraFeedback(await API.get('/api/genitore/feedback'));
    } catch (err) { avviso(err.message, 'errore'); }
});

function mostraFeedback(elenco) {
    document.getElementById('feedback-corpo').innerHTML = elenco.map((f) => `
        <tr><td>${dataIt(f.data)}</td><td>${esc(f.bambino_cognome)} ${esc(f.bambino_nome)}</td>
        <td>${esc(f.tipologia_nome)}</td><td>${f.voto ? '★'.repeat(f.voto) : '-'}</td><td>${esc(f.commento || '')}</td></tr>`
    ).join('') || '<tr><td colspan="5" class="vuoto">Nessun feedback inviato.</td></tr>';
}

caricaDashboard().catch((e) => avviso(e.message, 'errore'));
