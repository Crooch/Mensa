/* Area genitore: sola lettura + dichiarazione eccezioni e feedback. */
const utente = proteggi('genitore');
let statoApp = { figli: [], votoSelezionato: null, messaggi: {} };

// Etichette comprensibili senza modificare i valori usati dalle API.
const testoPasto = (stato) => stato === 'presente' ? 'Pasto previsto' : stato === 'assente' ? 'Pasto non previsto' : 'Da verificare';
const testoScelta = (stato) => stato === 'presente' ? 'Mangerà a mensa' : stato === 'assente' ? 'Non mangerà a mensa' : 'Nessuna comunicazione';
const badgePasto = (stato) => `<span class="badge-stato badge-${stato === 'presente' ? 'verde' : 'rosso'}">${testoPasto(stato)}</span>`;

abilitaSchede((vista) => {
    if (vista === 'calendario') caricaCalendario();
});

async function caricaDashboard() {
    // Conserva il giorno scelto dal genitore anche dopo aver registrato
    // un'assenza o una richiesta di pasta in bianco. Tornare in silenzio a
    // "oggi" potrebbe far inviare per errore la richiesta per la data sbagliata.
    const giorniSelezionati = {};
    for (const figlio of statoApp.figli) {
        const campo = document.getElementById('figlio-data-' + figlio.id);
        if (campo && campo.value >= oggi()) giorniSelezionati[figlio.id] = campo.value;
    }
    const dati = await API.get('/api/genitore/dashboard');
    statoApp.figli = dati.figli;

    document.getElementById('crediti-cards').innerHTML = dati.crediti.length === 0
        ? '<p class="vuoto">Nessuna ricarica registrata.</p>'
        : dati.crediti.map((c) => `
            <article class="card-mensa card-mensa--${c.stato}" data-testid="credito-card-${c.plesso_id}">
                <h3>${esc(c.plesso_nome)}</h3>
                <span class="saldo">${c.saldo}</span>
                <p class="nota">buoni mensa disponibili</p>
                <span class="badge-stato badge-${c.stato}">${situazioneBuoni(c.stato)}</span>
                <dl>
                    <dt>Fido concesso</dt><dd>${fidoConcesso(c.fido_buoni)}</dd>
                    <dt>Costo di un pasto</dt><dd>${euro(c.tariffa_attiva)}${c.origine_tariffa === 'override' ? ' (tariffa agevolata)' : ''}</dd>
                </dl>
            </article>`).join('');

    document.getElementById('figli-lista').innerHTML = dati.figli.map((f) => {
        const s = f.stato_oggi;
        const dichiarazione = s ? testoScelta(s.dichiarazione_genitore) : 'Nessuna comunicazione';
        return `
        <article class="card-mensa card-figlio" data-testid="figlio-card-${f.id}">
            <h3>${esc(f.cognome)} ${esc(f.nome)}</h3>
            <dl>
                <dt>Scuola</dt><dd>${esc(f.plesso_nome || 'non assegnato')}</dd>
                <dt>Classe</dt><dd>${esc(f.classe_nome || '-')} ${esc(f.anno_scolastico || '')}</dd>
                <dt>Tipo di menu</dt><dd>${esc(f.tipologia_nome)}</dd>
                <dt>Mensa di oggi</dt><dd>${s ? (s.servizio_mensa && !s.servizio_mensa.attivo ? 'Mensa non prevista oggi' : badgePasto(s.stato_effettivo)) : '-'}</dd>
                <dt>Hai comunicato</dt><dd>${esc(dichiarazione)}</dd>
                <dt>Pasta in bianco oggi</dt><dd>${s && s.pasta_in_bianco ? 'richiesta' : 'non richiesta'}</dd>
                <dt>Modifiche fino alle</dt><dd>${s ? 'Ore ' + esc(s.cutoff_orario) + (s.oltre_cutoff ? ' (oggi non puoi più modificare)' : '') : '-'}</dd>
            </dl>
            ${statoApp.messaggi[f.id] ? '<p class="stato-operazione stato-operazione--ok" role="status">' + esc(statoApp.messaggi[f.id]) + '</p>' : ''}
            ${s ? `<div class="figlio-comandi">
                <div class="figlio-selezione-data">
                    <label for="figlio-data-${f.id}">Per quale giorno?</label>
                    <input type="date" id="figlio-data-${f.id}" value="${giorniSelezionati[f.id] || oggi()}" min="${oggi()}" data-testid="figlio-data-${f.id}">
                </div>
                <p id="anteprima-figlio-${f.id}" class="stato-operazione figlio-stato" role="status">Caricamento della situazione del giorno…</p>
                <div class="figlio-azioni" hidden>
                    <button class="btn btn-primary" data-azione="presente" data-figlio="${f.id}" data-testid="dichiara-presente-${f.id}" hidden disabled>Segnala presenza</button>
                    <button class="btn btn-primary" data-azione="assente" data-figlio="${f.id}" data-testid="dichiara-assente-${f.id}" hidden disabled>Segnala assenza</button>
                    <button class="btn btn-outline-primary" data-pasta="1" data-figlio="${f.id}" data-testid="pasta-richiesta-${f.id}" hidden disabled>Chiedi pasta in bianco</button>
                    <button class="btn btn-outline-primary" data-pasta="0" data-figlio="${f.id}" data-testid="pasta-annulla-${f.id}" hidden disabled>Annulla pasta in bianco</button>
                </div>
                <button class="btn btn-outline-primary btn-sm figlio-calendario" data-apri-calendario="${f.id}">Vedi tutti i giorni nel calendario</button>
            </div>` : '<p class="nota">Nessuna classe attiva: contattare l\'ufficio comunale.</p>'}
        </article>`;
    }).join('') || '<p class="vuoto">Nessun figlio associato.</p>';

    document.querySelectorAll('[id^="figlio-data-"]').forEach((input) => input.addEventListener('change', () => {
        anteprimaGiorno(Number(input.id.replace('figlio-data-', '')));
    }));

    document.querySelectorAll('[data-azione]').forEach((b) => b.addEventListener('click', () => {
        const bambinoId = Number(b.dataset.figlio);
        const data = document.getElementById('figlio-data-' + bambinoId).value;
        if (!data || data < oggi()) { avviso('Scegli oggi o un giorno futuro.', 'errore'); return; }
        const figlio = statoApp.figli.find((f) => Number(f.id) === bambinoId);
        if (data === oggi() && figlio?.stato_oggi?.oltre_cutoff) {
            avviso('L’orario limite di oggi è passato. Scegli un altro giorno.', 'errore');
            return;
        }
        UI.esegui(b, () => dichiara(bambinoId, b.dataset.azione, data), 'Salvataggio…');
    }));

    document.querySelectorAll('[data-apri-calendario]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('cal-figlio').value = b.dataset.apriCalendario;
        giornoSelezionato = null;
        document.querySelector('.scheda[data-vista="calendario"]').click();
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
            await UI.esegui(b, async () => {
            await API.post('/api/genitore/pasta-bianco', {
                bambino_id: bambinoId, data, richiesta: Number(b.dataset.pasta)
            });
            const messaggio = (Number(b.dataset.pasta) === 1 ? 'Pasta in bianco richiesta' : 'Richiesta di pasta in bianco annullata') + ' per il ' + dataIt(data) + '.';
            statoApp.messaggi[bambinoId] = messaggio;
            avviso(messaggio);
            await caricaDashboard();
            }, 'Salvataggio…');
        } catch (err) { avviso(err.message, 'errore'); }
    }));

    statoApp.figli.forEach((f) => { if (document.getElementById('figlio-data-' + f.id)) anteprimaGiorno(f.id); });

    document.getElementById('movimenti-corpo').innerHTML = dati.movimenti.map((m) => `
        <tr>
            <td>${dataIt(m.data)}</td><td>${esc(m.plesso_nome)}</td>
            <td>${esc(nomeMovimento(m.tipo))}</td>
            <td>${m.delta > 0 ? '+' : ''}${m.delta}</td>
            <td>${m.importo_euro === null || m.importo_euro === undefined ? '-' : euro(m.importo_euro)}</td>
            <td>${m.bambino_nome ? esc(m.bambino_nome + ' ' + m.bambino_cognome) : '-'}</td>
            <td>${esc(m.note || '')}</td>
        </tr>`).join('') || '<tr><td colspan="7" class="vuoto">Nessun movimento.</td></tr>';

    mostraFeedback(dati.feedback);
    riempiSelectFigli();
    caricaMenu();
}

/** Mostra solo le azioni possibili per il bambino e il giorno scelto. */
const richiesteAnteprima = new Map();
async function anteprimaGiorno(bambinoId) {
    const input = document.getElementById('figlio-data-' + bambinoId);
    if (!input) return;
    const data = input.value;
    const versione = (richiesteAnteprima.get(bambinoId) || 0) + 1;
    richiesteAnteprima.set(bambinoId, versione);
    const azioni = Array.from(document.querySelectorAll('[data-figlio="' + bambinoId + '"]'))
        .filter((b) => b.dataset.azione || b.dataset.pasta !== undefined);
    const gruppoAzioni = input.closest('.figlio-comandi')?.querySelector('.figlio-azioni');
    const aggiornaLayoutAzioni = () => {
        if (!gruppoAzioni) return;
        const disponibili = azioni.filter((b) => !b.hidden && !b.disabled).length;
        gruppoAzioni.hidden = disponibili === 0;
        gruppoAzioni.classList.toggle('figlio-azioni--singola', disponibili === 1);
    };
    // Fino al caricamento nessuna azione deve partire usando dati precedenti.
    azioni.forEach((b) => { b.hidden = true; b.disabled = true; });
    aggiornaLayoutAzioni();
    UI.stato('anteprima-figlio-' + bambinoId, 'Controllo del ' + dataIt(data) + '…', 'caricamento');
    try {
        const g = await API.get('/api/genitore/figli/' + bambinoId +
            '/giornata?data=' + encodeURIComponent(data));
        if (richiesteAnteprima.get(bambinoId) !== versione || input.value !== data) return;
        const attivo = g.servizio_mensa?.attivo === true;
        const presente = attivo && g.stato_effettivo === 'presente';
        const assente = attivo && g.stato_effettivo === 'assente';
        const modificabile = attivo && !g.oltre_cutoff && !g.congelato && data >= oggi();
        // Una correzione di assenza effettuata dalla scuola prevale sulla famiglia.
        const presenzaBloccataDallaScuola = assente && g.override_scuola === 'assente';
        for (const b of azioni) {
            const mostra = modificabile &&
                (b.dataset.azione === 'assente' && presente ||
                 b.dataset.azione === 'presente' && assente && !presenzaBloccataDallaScuola ||
                 b.dataset.pasta === '1' && presente && !g.pasta_in_bianco ||
                 b.dataset.pasta === '0' && presente && !!g.pasta_in_bianco);
            b.hidden = !mostra;
            b.disabled = !mostra;
        }
        aggiornaLayoutAzioni();
        let messaggio = !attivo ? motivoChiusura(g.servizio_mensa?.motivo)
            : presente ? 'Pasto previsto' : 'Pasto non previsto';
        if (presente && g.pasta_in_bianco) messaggio += ' · Pasta in bianco richiesta';
        if (presenzaBloccataDallaScuola) messaggio += ' · Per modifiche contatta la scuola';
        else if (attivo && !modificabile) messaggio += ' · Modifiche chiuse';
        UI.stato('anteprima-figlio-' + bambinoId, messaggio, !attivo ? 'info' : 'ok');
    } catch (errore) {
        if (richiesteAnteprima.get(bambinoId) !== versione) return;
        aggiornaLayoutAzioni();
        UI.stato('anteprima-figlio-' + bambinoId,
            'Dati non disponibili: ' + errore.message, 'errore');
    }
}

function riempiSelectFigli() {
    const campoCalendario = document.getElementById('cal-figlio');
    const campoFeedback = document.getElementById('fb-figlio');
    const selezioneCalendario = campoCalendario.value;
    const selezioneFeedback = campoFeedback.value;
    const opzioni = statoApp.figli.map((f) => `<option value="${f.id}">${esc(f.cognome)} ${esc(f.nome)}</option>`).join('');
    campoCalendario.innerHTML = opzioni;
    campoFeedback.innerHTML = opzioni;
    if (statoApp.figli.some((f) => String(f.id) === selezioneCalendario)) campoCalendario.value = selezioneCalendario;
    if (statoApp.figli.some((f) => String(f.id) === selezioneFeedback)) campoFeedback.value = selezioneFeedback;
}

async function dichiara(bambinoId, stato, data = oggi()) {
    try {
        const esito = await API.post('/api/genitore/presenze/dichiarazione', { bambino_id: bambinoId, data, stato });
        const messaggio = esito.fuori_cutoff
            ? 'L’orario limite è passato: la comunicazione non cambia il pasto previsto oggi.'
            : `Comunicazione salvata per il ${dataIt(data)}: ${testoScelta(stato)}.` +
              (esito.pasta_annullata ? ' Pasta in bianco annullata.' : '');
        statoApp.messaggi[bambinoId] = messaggio;
        avviso(messaggio, esito.fuori_cutoff ? 'errore' : 'ok');
        await caricaDashboard();
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


/* Etichette sintetiche per le cause reali del calendario scolastico. */
const motivoChiusura = (motivo) => ({
    fine_settimana: 'Fine settimana',
    festivita_nazionale: 'Festività',
    chiusura_programmata: 'Mensa chiusa',
    giorno_senza_servizio: 'Mensa non prevista',
    servizio_non_avviato: 'Servizio non iniziato',
    servizio_terminato: 'Servizio terminato',
    calendario_non_configurato: 'Calendario da definire',
    nessuna_classe: 'Scuola non assegnata',
    plesso_non_attivo: 'Scuola non attiva',
    data_non_valida: 'Data non valida'
}[motivo] || 'Servizio non previsto');

/* ---------------- Calendario completo e azioni contestuali ---------------- */
let versioneCalendario = 0;
let giornoSelezionato = null;
let messaggioCalendario = '';

async function caricaCalendario(giornoDaAprire = null) {
    const select = document.getElementById('cal-figlio');
    if (!select.value) return;
    const figlio = Number(select.value);
    const mese = document.getElementById('cal-mese').value || oggi().slice(0, 7);
    document.getElementById('cal-mese').value = mese;
    const numeri = mese.split('-').map(Number);
    const versione = ++versioneCalendario;
    UI.stato('calendario-stato', 'Caricamento del calendario…', 'caricamento');
    try {
        const dati = await API.get('/api/genitore/figli/' + figlio + '/calendario?anno=' + numeri[0] + '&mese=' + numeri[1]);
        if (versione !== versioneCalendario) return;
        disegnaCalendario(dati, numeri[0], numeri[1], giornoDaAprire || giornoSelezionato, figlio);
        UI.stato('calendario-stato', 'Aggiornato alle ' + UI.oraAggiornamento(), 'ok');
    } catch (errore) {
        if (versione !== versioneCalendario) return;
        document.getElementById('calendario').innerHTML = '';
        UI.stato('calendario-stato', 'Non riesco a mostrare il calendario. ' + errore.message, 'errore');
    }
}

function disegnaCalendario(dati, anno, mese, giorno, bambinoId) {
    const perData = {};
    dati.giorni.forEach((r) => { perData[r.data] = r; });
    const intestazioni = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    let html = intestazioni.map((x) => '<div class="intestazione-giorno">' + x + '</div>').join('');
    const offset = (new Date(anno, mese - 1, 1).getDay() + 6) % 7;
    for (let k = 0; k < offset; k++) html += '<div class="giorno vuoto"></div>';
    const ultimo = new Date(anno, mese, 0).getDate();
    for (let n = 1; n <= ultimo; n++) {
        const data = anno + '-' + String(mese).padStart(2, '0') + '-' + String(n).padStart(2, '0');
        const r = perData[data];
        const attivo = r && r.servizio_mensa && r.servizio_mensa.attivo;
        const testo = !attivo ? motivoChiusura(r?.servizio_mensa?.motivo) : r.stato_effettivo === 'presente' ? 'Pasto previsto'
            : r.stato_effettivo === 'assente' ? 'Pasto non previsto' : 'Da verificare';
        const classe = !attivo ? 'senza-mensa' : r.stato_effettivo === 'presente' ? 'presente'
            : r.stato_effettivo === 'assente' ? 'assente' : 'non-disponibile';
        html += '<button type="button" class="giorno ' + classe + (giorno === data ? ' selezionato' : '') +
            '" data-data="' + data + '" data-testid="giorno-' + data + '" aria-label="' +
            esc(dataIt(data) + ': ' + testo) + '"><strong>' + n + '</strong><small>' + testo +
            '</small>' + (r && r.dichiarazione_genitore ? '<small>Hai comunicato</small>' : '') +
            (r && Number(r.pasta_in_bianco) === 1 ? '<small>Pasta in bianco</small>' : '') + '</button>';
    }
    const griglia = document.getElementById('calendario');
    griglia.innerHTML = html;
    griglia.querySelectorAll('button[data-data]').forEach((b) => b.addEventListener('click', () => {
        giornoSelezionato = b.dataset.data;
        griglia.querySelectorAll('button[data-data]').forEach((x) =>
            x.classList.toggle('selezionato', x === b));
        mostraDettaglio(perData[b.dataset.data], bambinoId);
    }));
    if (giorno && perData[giorno]) {
        giornoSelezionato = giorno;
        mostraDettaglio(perData[giorno], bambinoId);
    } else {
        giornoSelezionato = null;
        document.getElementById('calendario-dettaglio').innerHTML =
            '<p class="nota">Seleziona un giorno per vedere i dettagli.</p>';
    }
}

function mostraDettaglio(r, bambinoId) {
    const nodo = document.getElementById('calendario-dettaglio');
    if (!r) { nodo.textContent = 'Nessun dettaglio disponibile.'; return; }
    const attivo = r.servizio_mensa && r.servizio_mensa.attivo;
    const stato = !attivo ? motivoChiusura(r.servizio_mensa?.motivo)
        : r.stato_effettivo === null ? 'Pasto da verificare'
        : testoPasto(r.stato_effettivo);
    const presente = r.stato_effettivo === 'presente';
    const assente = r.stato_effettivo === 'assente';
    const assenzaDellaScuola = assente && r.override_scuola === 'assente';
    const comandi = [];
    if (attivo && r.modificabile) {
        if (presente) {
            comandi.push('<button type="button" class="btn btn-primary" data-cal-stato="assente" data-testid="cal-modifica-pasto">Segnala assenza</button>');
            comandi.push(Number(r.pasta_in_bianco) === 1
                ? '<button type="button" class="btn btn-outline-primary" data-cal-pasta="0" data-testid="cal-modifica-pasta">Annulla pasta in bianco</button>'
                : '<button type="button" class="btn btn-outline-primary" data-cal-pasta="1" data-testid="cal-modifica-pasta">Chiedi pasta in bianco</button>');
        } else if (assente && !assenzaDellaScuola) {
            comandi.push('<button type="button" class="btn btn-primary" data-cal-stato="presente" data-testid="cal-modifica-pasto">Segnala presenza</button>');
        }
    }
    const azioni = comandi.length
        ? '<div class="azioni-giorno">' + comandi.join('') + '</div>'
        : assenzaDellaScuola ? '<p class="nota">Per modifiche contatta la scuola.</p>'
        : attivo && !r.modificabile ? '<p class="nota">Modifiche chiuse.</p>' : '';

    nodo.innerHTML = '<h2>' + dataIt(r.data) + '</h2>' +
        '<p class="stato-operazione stato-operazione--info">' + esc(stato) + '</p>' +
        (attivo ? '<dl class="card-mensa"><dt>Hai comunicato</dt><dd>' + testoScelta(r.dichiarazione_genitore) +
          '</dd><dt>Modifica della scuola</dt><dd>' + (r.override_scuola ? testoScelta(r.override_scuola) : 'Nessuna') +
          (r.override_motivazione ? ' — Motivo: ' + esc(r.override_motivazione) : '') +
          '</dd><dt>Pasta in bianco</dt><dd>' + (Number(r.pasta_in_bianco) === 1 ? 'Richiesta' : 'Non richiesta') +
          '</dd></dl>' : '') + azioni + '<p id="calendario-messaggio" role="status" aria-live="polite"></p>';
    if (messaggioCalendario) {
        UI.stato('calendario-messaggio', messaggioCalendario, 'ok');
        messaggioCalendario = '';
    }
    nodo.querySelectorAll('[data-cal-stato], [data-cal-pasta]').forEach((pulsante) =>
        pulsante.addEventListener('click', async () => {
            const data = r.data;
            try {
                await UI.esegui(pulsante, async () => {
                    if (pulsante.dataset.calStato) {
                        const esito = await API.post('/api/genitore/presenze/dichiarazione', {
                            bambino_id: bambinoId, data, stato: pulsante.dataset.calStato
                        });
                        messaggioCalendario = 'Comunicazione salvata.' +
                            (esito.pasta_annullata ? ' Pasta in bianco annullata.' : '');
                    } else {
                        await API.post('/api/genitore/pasta-bianco', {
                            bambino_id: bambinoId, data, richiesta: Number(pulsante.dataset.calPasta)
                        });
                    }
                    if (!messaggioCalendario) messaggioCalendario = 'Comunicazione salvata.';
                });
                await caricaCalendario(data);
                await caricaDashboard();
            } catch (errore) {
                UI.stato('calendario-messaggio', 'Non è stato possibile salvare: ' + errore.message, 'errore');
            }
        }));
}

document.getElementById('btn-carica-calendario').addEventListener('click', () => caricaCalendario());
document.getElementById('cal-figlio').addEventListener('change', () => {
    giornoSelezionato = null; caricaCalendario();
});
document.getElementById('cal-mese').addEventListener('change', () => {
    giornoSelezionato = null; caricaCalendario();
});

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
    const pulsante = e.submitter || document.querySelector('#form-feedback [type=submit]');
    if (statoApp.votoSelezionato === null) { avviso('Seleziona almeno una stella per valutare il pasto.', 'errore'); return; }
    try {
        await UI.esegui(pulsante, async () => {
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
        }, 'Invio in corso…');
    } catch (err) { avviso(err.message, 'errore'); }
});

function mostraFeedback(elenco) {
    document.getElementById('feedback-corpo').innerHTML = elenco.map((f) => `
        <tr><td>${dataIt(f.data)}</td><td>${esc(f.bambino_cognome)} ${esc(f.bambino_nome)}</td>
        <td>${esc(f.tipologia_nome)}</td><td>${f.voto ? '★'.repeat(f.voto) : '-'}</td><td>${esc(f.commento || '')}</td></tr>`
    ).join('') || '<tr><td colspan="5" class="vuoto">Non hai ancora inviato valutazioni.</td></tr>';
}

caricaDashboard().catch((e) => avviso(e.message, 'errore'));
