/* Area genitore: sola lettura + dichiarazione eccezioni e feedback. */
const utente = proteggi('genitore');
let statoApp = { figli: [], votoSelezionato: null };

// Etichette comprensibili senza modificare i valori usati dalle API.
const testoPasto = (stato) => stato === 'presente' ? 'Pasto previsto' : stato === 'assente' ? 'Pasto non previsto' : 'Da verificare';
const testoScelta = (stato) => stato === 'presente' ? 'Mangerà a mensa' : stato === 'assente' ? 'Non mangerà a mensa' : 'Nessuna comunicazione';
const badgePasto = (stato) => `<span class="badge-stato badge-${stato === 'presente' ? 'verde' : 'rosso'}">${testoPasto(stato)}</span>`;

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
                <p class="nota">buoni mensa disponibili</p>
                <span class="badge-stato badge-${c.stato}">${c.stato === 'verde' ? 'regolare' : c.stato === 'giallo' ? 'attenzione' : 'oltre fido'}</span>
                <dl>
                    <dt>Soglia per il sollecito</dt><dd>Saldo di -${c.fido_buoni} buoni</dd>
                    <dt>Costo di un pasto</dt><dd>${euro(c.tariffa_attiva)}${c.origine_tariffa === 'override' ? ' (tariffa agevolata)' : ''}</dd>
                </dl>
            </article>`).join('');

    document.getElementById('figli-lista').innerHTML = dati.figli.map((f) => {
        const s = f.stato_oggi;
        const dichiarazione = s ? testoScelta(s.dichiarazione_genitore) : 'Nessuna comunicazione';
        return `
        <article class="card-mensa" data-testid="figlio-card-${f.id}">
            <h3>${esc(f.cognome)} ${esc(f.nome)}</h3>
            <dl>
                <dt>Scuola</dt><dd>${esc(f.plesso_nome || 'non assegnato')}</dd>
                <dt>Classe</dt><dd>${esc(f.classe_nome || '-')} ${esc(f.anno_scolastico || '')}</dd>
                <dt>Tipo di menu</dt><dd>${esc(f.tipologia_nome)}</dd>
                <dt>Mensa di oggi</dt><dd>${s ? (s.servizio_mensa && !s.servizio_mensa.attivo ? 'Mensa non prevista oggi' : badgePasto(s.stato_effettivo)) : '-'}</dd>
                <dt>Hai comunicato</dt><dd>${esc(dichiarazione)}</dd>
                <dt>Pasta in bianco oggi</dt><dd>${s && s.pasta_in_bianco ? 'richiesta' : 'non richiesta'}</dd>
                <dt>Orario limite per le modifiche</dt><dd>${s ? 'Ore ' + esc(s.cutoff_orario) + (s.oltre_cutoff ? ' (oggi non puoi più modificare)' : '') : '-'}</dd>
            </dl>
            ${s ? `<div class="riga-filtri" style="margin-top:.75rem">
                ${s.oltre_cutoff ? '<p class="nota">Per oggi è passato l’orario limite. Puoi ancora indicare un giorno futuro.</p>' : ''}
                <label for="figlio-data-${f.id}">Per quale giorno?</label>
                <input type="date" id="figlio-data-${f.id}" value="${oggi()}" min="${oggi()}" data-testid="figlio-data-${f.id}">
                <button class="btn-azione" data-azione="presente" data-figlio="${f.id}" data-testid="dichiara-presente-${f.id}">Mangerà a mensa</button>
                <button class="btn-azione" data-azione="assente" data-figlio="${f.id}" data-testid="dichiara-assente-${f.id}">Non mangerà a mensa</button>
                <button class="btn-azione" data-pasta="1" data-figlio="${f.id}" data-testid="pasta-richiesta-${f.id}">Chiedi pasta in bianco</button>
                <button class="btn-azione" data-pasta="0" data-figlio="${f.id}" data-testid="pasta-annulla-${f.id}">Annulla richiesta pasta in bianco</button>
            </div>` : '<p class="nota">Nessuna classe attiva: contattare l\'ufficio comunale.</p>'}
        </article>`;
    }).join('') || '<p class="vuoto">Nessun figlio associato.</p>';

    document.querySelectorAll('[data-azione]').forEach((b) => b.addEventListener('click', () => {
        const bambinoId = Number(b.dataset.figlio);
        const data = document.getElementById('figlio-data-' + bambinoId).value;
        if (!data || data < oggi()) { avviso('Scegli oggi o un giorno futuro.', 'errore'); return; }
        const figlio = statoApp.figli.find((f) => Number(f.id) === bambinoId);
        if (data === oggi() && figlio?.stato_oggi?.oltre_cutoff) {
            avviso('L’orario limite di oggi è passato. Scegli un altro giorno.', 'errore');
            return;
        }
        dichiara(bambinoId, b.dataset.azione, data);
    }));

    document.querySelectorAll('[data-pasta]').forEach((b) => b.addEventListener('click', async () => {
        const bambinoId = Number(b.dataset.figlio);
        const data = document.getElementById('figlio-data-' + bambinoId).value;
        if (!data || data < oggi()) { avviso('Scegli oggi o un giorno futuro.', 'errore'); return; }
        const figlio = statoApp.figli.find((f) => Number(f.id) === bambinoId);
        if (data === oggi() && figlio?.stato_oggi?.oltre_cutoff) {
            avviso('L’orario limite di oggi è passato. Scegli un altro giorno.', 'errore');
            return;
        }
        try {
            await API.post('/api/genitore/pasta-bianco', {
                bambino_id: bambinoId, data, richiesta: Number(b.dataset.pasta)
            });
            avviso((Number(b.dataset.pasta) === 1 ? 'Richiesta di pasta in bianco registrata' : 'Richiesta di pasta in bianco annullata')
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
            ? 'L’orario limite è passato: la comunicazione è stata registrata, ma non cambia il pasto previsto per oggi.'
            : `Comunicazione registrata per il ${dataIt(data)}. ${testoScelta(stato)}.`, esito.fuori_cutoff ? 'errore' : 'ok');
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
            <strong>${giorno}</strong>${r ? (r.stato_effettivo === 'presente' ? 'A mensa' : 'Non a mensa') + (r.dichiarazione_genitore ? '<small> · Hai comunicato</small>' : '') : ''}</button>`;
    }
    const griglia = document.getElementById('calendario');
    griglia.innerHTML = html;
    griglia.querySelectorAll('.giorno[data-data]').forEach((b) => b.addEventListener('click', () => {
        const r = perData[b.dataset.data];
        document.getElementById('calendario-dettaglio').innerHTML = !r
            ? `<p class="vuoto">Non ci sono dettagli salvati per il ${dataIt(b.dataset.data)}. Per comunicare una presenza o un'assenza, usa la schermata iniziale.</p>`
            : `<h2>La mensa del ${dataIt(r.data)}</h2>
               <p class="nota">Normalmente il pasto è previsto. Se tutta la classe non pranza, ad esempio per una gita, la scuola può indicare un'assenza per tutti. Le tue comunicazioni e le correzioni della scuola possono cambiare questa situazione.</p>
               <dl class="card-mensa">
                 <dt>Situazione prevista inizialmente</dt><dd>${testoPasto(r.default_calcolato)}</dd>
                 <dt>Hai comunicato</dt><dd>${testoScelta(r.dichiarazione_genitore)}</dd>
                 <dt>Correzione della scuola</dt><dd>${r.override_scuola ? testoScelta(r.override_scuola) : 'Nessuna correzione'} ${r.override_motivazione ? '· Motivo: ' + esc(r.override_motivazione) : ''}</dd>
                 <dt>Situazione attuale</dt><dd>${badgePasto(r.stato_effettivo)}</dd>
                 <dt>Pasta in bianco</dt><dd>${Number(r.pasta_in_bianco) === 1 ? 'richiesta' : 'non richiesta'}</dd>
                 <dt>Modifica dopo l'orario limite</dt><dd>${Number(r.fuori_cutoff) === 1 ? 'Sì' : 'No'}</dd>
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
        avviso('Valutazione inviata. Grazie!');
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
    ).join('') || '<tr><td colspan="5" class="vuoto">Non hai ancora inviato valutazioni.</td></tr>';
}

caricaDashboard().catch((e) => avviso(e.message, 'errore'));
