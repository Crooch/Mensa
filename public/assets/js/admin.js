/* Area admin: configurazioni, utenze, email, job giornaliero. */
const utente = proteggi('admin');
let plessi = [];
let numeroRichiestaCalendario = 0;

abilitaSchede((vista) => {
    if (vista === 'calendario') caricaCalendario().catch((err) => avviso(err.message, 'errore'));
    if (vista === 'classi') caricaClassi();
    if (vista === 'tipologie') caricaTipologie();
    if (vista === 'email') caricaEmail();
    if (vista === 'utenti') caricaUtenti();
    if (vista === 'job') caricaEsecuzioni();
});

/* ---------------- plessi ---------------- */
async function caricaPlessi() {
    plessi = await API.get('/api/admin/plessi');
    const dato = (numero, etichetta) => '<div class="riepilogo-mensa__dato"><strong>' +
        esc(numero) + '</strong><span>' + esc(etichetta) + '</span></div>';
    try {
        const esecuzioni = await API.get('/api/job/esecuzioni');
        const errori = esecuzioni.filter((x) => x.esito === 'errore').length;
        document.getElementById('admin-riepilogo').innerHTML =
            dato(plessi.length, 'Scuole attive') + dato(esecuzioni.length, 'Ultimi conteggi registrati') +
            dato(errori, 'Conteggi con errore');
    } catch (errore) {
        document.getElementById('admin-riepilogo').innerHTML = dato(plessi.length, 'Scuole attive');
    }
    document.getElementById('plessi-corpo').innerHTML = plessi.map((p) => `
        <tr data-testid="plesso-riga-${p.id}">
            <td>${esc(p.nome)}</td><td>${euro(p.tariffa_base_pasto)}</td><td>${fidoConcesso(p.fido_buoni)}</td>
            <td>${esc(String(p.cutoff_orario).slice(0, 5))}</td><td>${esc(p.dirigente_email || '-')}</td>
            <td><button class="btn-azione" data-modifica="${p.id}" data-testid="plesso-modifica-${p.id}">Modifica</button>
                <button class="btn-azione" data-cancella="${p.id}" data-testid="plesso-cancella-${p.id}">Disattiva scuola</button></td>
        </tr>`).join('');

    document.querySelectorAll('[data-modifica]').forEach((b) => b.addEventListener('click', () => {
        const p = plessi.find((x) => String(x.id) === b.dataset.modifica);
        document.getElementById('plesso-id').value = p.id;
        document.getElementById('plesso-nome').value = p.nome;
        document.getElementById('plesso-tariffa').value = p.tariffa_base_pasto;
        document.getElementById('plesso-fido').value = -Math.abs(Number(p.fido_buoni));
        document.getElementById('plesso-cutoff').value = String(p.cutoff_orario).slice(0, 5);
        document.getElementById('plesso-dirigente').value = p.dirigente_email || '';
        document.getElementById('plesso-protocollo').value = p.email_protocollo || '';
    }));
    document.querySelectorAll('[data-cancella]').forEach((b) => b.addEventListener('click', async () => {
        if (!confirm('Vuoi disattivare questa scuola? Non potrà più essere usata per nuovi pasti. Le informazioni precedenti saranno conservate.')) return;
        await API.del('/api/admin/plessi/' + b.dataset.cancella);
        avviso('Scuola disattivata. Lo storico è conservato.');
        caricaPlessi();
    }));

    const opzioni = plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    ['classe-plesso', 'modello-plesso', 'job-plesso', 'calendario-plesso'].forEach((id) => {
        const campo = document.getElementById(id);
        const precedente = campo.value;
        campo.innerHTML = opzioni;
        if (plessi.some(p => String(p.id) === precedente)) campo.value = precedente;
    });
    const utenza = document.getElementById('ut-plesso');
    const utPlesso = utenza.value;
    utenza.innerHTML = '<option value="">—</option>' + opzioni;
    if (plessi.some(p => String(p.id) === utPlesso)) utenza.value = utPlesso;
    // La scelta della scuola guida immediatamente la lista delle classi.
    aggiornaSceltaClasse();
}

document.getElementById('form-plesso').addEventListener('submit', async (e) => {
    e.preventDefault();
    const corpo = {
        nome: document.getElementById('plesso-nome').value,
        tariffa_base_pasto: document.getElementById('plesso-tariffa').value,
        fido_buoni: Math.abs(Number(document.getElementById('plesso-fido').value)),
        cutoff_orario: document.getElementById('plesso-cutoff').value,
        dirigente_email: document.getElementById('plesso-dirigente').value,
        email_protocollo: document.getElementById('plesso-protocollo').value
    };
    const id = document.getElementById('plesso-id').value;
    try {
        if (id) await API.put('/api/admin/plessi/' + id, corpo);
        else await API.post('/api/admin/plessi', corpo);
        avviso('Impostazioni della scuola salvate.');
        document.getElementById('form-plesso').reset();
        document.getElementById('plesso-id').value = '';
        caricaPlessi();
    } catch (err) { avviso(err.message, 'errore'); }
});
document.getElementById('plesso-annulla').addEventListener('click', () => {
    document.getElementById('form-plesso').reset();
    document.getElementById('plesso-id').value = '';
});

/* ---------------- calendario mensa ---------------- */
const giorniCalendario = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi'];
document.getElementById('classe-anno').value = annoScolasticoCorrente();

async function caricaCalendario() {
    const id = document.getElementById('calendario-plesso').value;
    const richiesta = ++numeroRichiestaCalendario;
    if (!id) return;
    const dati = await API.get('/api/admin/calendari/' + id);
    if (richiesta !== numeroRichiestaCalendario
        || document.getElementById('calendario-plesso').value !== id) return;
    const cfg = dati.configurazione || {};
    document.getElementById('calendario-inizio').value = cfg.data_inizio || '';
    document.getElementById('calendario-fine').value = cfg.data_fine || '';
    giorniCalendario.forEach((g) => {
        document.getElementById('calendario-' + g).checked = Number(cfg[g]) === 1;
    });
    const corpo = document.getElementById('eccezioni-calendario-corpo');
    corpo.innerHTML = (dati.eccezioni || []).map((e) => `
        <tr><td>${dataIt(e.giorno)}</td>
            <td>${Number(e.servizio_attivo) === 1 ? 'Apertura' : 'Chiusura'}</td>
            <td>${esc(e.motivazione)}</td>
            <td><button class="btn-azione" data-cancella-eccezione="${esc(e.giorno)}">Elimina</button></td>
        </tr>`).join('') || '<tr><td colspan="4" class="vuoto">Nessun giorno modificato rispetto al calendario normale.</td></tr>';
    corpo.querySelectorAll('[data-cancella-eccezione]').forEach((btn) => btn.addEventListener('click', async () => {
        if (!confirm('Vuoi togliere questa modifica? Per quel giorno tornerà a valere il calendario normale.')) return;
        try {
            await API.del('/api/admin/calendari/' + id + '/eccezioni/' + btn.dataset.cancellaEccezione);
            avviso('Modifica cancellata. Ora vale il calendario normale.');
            await caricaCalendario();
        } catch (err) { avviso(err.message, 'errore'); }
    }));
}

document.getElementById('calendario-plesso').addEventListener('change', () => {
    caricaCalendario().catch((err) => avviso(err.message, 'errore'));
});

document.getElementById('form-calendario').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const id = document.getElementById('calendario-plesso').value;
    const dati = {
        data_inizio: document.getElementById('calendario-inizio').value,
        data_fine: document.getElementById('calendario-fine').value || null
    };
    giorniCalendario.forEach((g) => { dati[g] = document.getElementById('calendario-' + g).checked ? 1 : 0; });
    try {
        await API.put('/api/admin/calendari/' + id, dati);
        avviso('Giorni della mensa salvati per la scuola selezionata.');
        await caricaCalendario();
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('form-eccezione-calendario').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const id = document.getElementById('calendario-plesso').value;
    const data = document.getElementById('eccezione-data').value;
    try {
        await API.put('/api/admin/calendari/' + id + '/eccezioni/' + data, {
            servizio_attivo: Number(document.getElementById('eccezione-attivo').value),
            motivazione: document.getElementById('eccezione-motivazione').value.trim()
        });
        avviso('Modifica al calendario salvata per la data selezionata.');
        document.getElementById('form-eccezione-calendario').reset();
        await caricaCalendario();
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('calendario-verifica').addEventListener('click', async () => {
    const id = document.getElementById('calendario-plesso').value;
    const data = document.getElementById('calendario-verifica-data').value;
    if (!data) { avviso('Seleziona una data da verificare.', 'errore'); return; }
    try {
        const esito = await API.get('/api/admin/calendari/' + id + '/verifica?data=' + encodeURIComponent(data));
        const motivi = {
            servizio_non_avviato: 'servizio non ancora avviato',
            servizio_terminato: 'servizio terminato',
            fine_settimana: 'fine settimana',
            festivita_nazionale: 'festività nazionale',
            chiusura_programmata: 'chiusura programmata',
            giorno_senza_servizio: 'giorno senza mensa',
            giorno_di_servizio: 'giorno di mensa',
            apertura_straordinaria: 'apertura straordinaria',
            calendario_non_configurato: 'calendario da configurare'
        };
        document.getElementById('calendario-verifica-esito').textContent =
            (esito.attivo ? 'Sì, la mensa è prevista' : 'No, la mensa non è prevista') +
            ' — ' + (motivi[esito.motivo] || esito.motivo);
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- classi ---------------- */
// Le classi disponibili dipendono dalla scuola selezionata. Si digita il nome
// soltanto se si sta creando una tipologia non presente nell'elenco comune.
let classiDisponibili = [];
let numeroRichiestaClassi = 0;
function annoScolasticoCorrente() {
    const adesso = new Date();
    const inizio = adesso.getMonth() >= 8 ? adesso.getFullYear() : adesso.getFullYear() - 1;
    return inizio + '/' + (inizio + 1);
}
function suggerimentiClassi(plessoId) {
    const lettere = ['A', 'B', 'C', 'D', 'E', 'F'];
    const nomeScuola = String(plessi.find(p => Number(p.id) === plessoId)?.nome || '')
        .toLocaleLowerCase('it');
    const sezioni = lettere.map(lettera => 'Sezione ' + lettera);
    const classi = Array.from({ length: 5 }, (_, anno) =>
        lettere.map(lettera => (anno + 1) + 'ª ' + lettera)).flat();
    // Proposte pertinenti alla scuola: infanzia = sezioni; primaria e
    // secondaria = classi. Un nome particolare resta sempre possibile.
    if (nomeScuola.includes('infanzia')) return sezioni;
    if (nomeScuola.includes('primaria') || nomeScuola.includes('secondaria')) return classi;
    return [...sezioni, ...classi];
}
function aggiornaSceltaClasse() {
    const plessoId = Number(document.getElementById('classe-plesso').value);
    const scuola = classiDisponibili.filter(c => Number(c.plesso_id) === plessoId);
    const scelta = document.getElementById('classe-esistente');
    const precedente = scelta.value;
    scelta.innerHTML = '<option value="">Seleziona una classe già registrata</option>' +
        scuola.map(c => `<option value="${Number(c.id)}">${esc(c.nome)} · ${esc(c.anno_scolastico)}</option>`).join('');
    if (scuola.some(c => String(c.id) === precedente)) scelta.value = precedente;
    scelta.disabled = scuola.length === 0;
    if (!scuola.length) scelta.options[0].textContent = 'Nessuna classe registrata per questa scuola';
    mostraClasseEsistente();

    const nomi = document.getElementById('classe-nome');
    const nomePrecedente = nomi.value;
    nomi.innerHTML = '<option value="">Scegli una classe o sezione</option>' +
        suggerimentiClassi(plessoId).map(nome => `<option value="${esc(nome)}">${esc(nome)}</option>`).join('') +
        '<option value="altro">Un altro nome…</option>';
    if ([...nomi.options].some(o => o.value === nomePrecedente)) nomi.value = nomePrecedente;
    mostraCampoAltraClasse();

    // La tabella serve esclusivamente la scuola scelta, senza ripetere il nome
    // del plesso in ogni riga e senza modificare il suo storico.
    const corpo = document.getElementById('classi-corpo');
    corpo.innerHTML = scuola.map(c => `
        <tr><td>${esc(c.plesso_nome)}</td><td>${esc(c.nome)}</td><td>${esc(c.anno_scolastico)}</td>
        <td><button type="button" class="btn-azione" data-cancella-classe="${Number(c.id)}"
            data-testid="classe-cancella-${Number(c.id)}">Disattiva classe</button></td></tr>`).join('')
        || '<tr><td colspan="4" class="vuoto">Non ci sono classi registrate per questa scuola.</td></tr>';
    corpo.querySelectorAll('[data-cancella-classe]').forEach(b => b.addEventListener('click', async () => {
        if (!confirm('Vuoi disattivare questa classe? Le assegnazioni precedenti rimarranno nello storico.')) return;
        try {
            await API.del('/api/admin/classi/' + b.dataset.cancellaClasse);
            avviso('Classe disattivata. Lo storico resta disponibile.');
            await caricaClassi();
        } catch (err) { avviso(err.message, 'errore'); }
    }));
}
function mostraClasseEsistente() {
    const scelta = document.getElementById('classe-esistente');
    const classe = classiDisponibili.find(c => String(c.id) === scelta.value);
    document.getElementById('classe-esistente-dettaglio').textContent = classe
        ? 'Anno scolastico ' + classe.anno_scolastico + '. Puoi gestirla nell’elenco qui sotto.'
        : '';
}
function mostraCampoAltraClasse() {
    const altro = document.getElementById('classe-nome').value === 'altro';
    document.getElementById('classe-altro-gruppo').hidden = !altro;
    document.getElementById('classe-altro').required = altro;
    if (!altro) document.getElementById('classe-altro').value = '';
}
document.getElementById('classe-plesso').addEventListener('change', aggiornaSceltaClasse);
document.getElementById('classe-esistente').addEventListener('change', mostraClasseEsistente);
document.getElementById('classe-nome').addEventListener('change', mostraCampoAltraClasse);

async function caricaClassi() {
    const richiesta = ++numeroRichiestaClassi;
    const elenco = await API.get('/api/admin/classi');
    if (richiesta !== numeroRichiestaClassi) return;
    classiDisponibili = elenco;
    aggiornaSceltaClasse();
}
document.getElementById('form-classe').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('classe-plesso').value;
    const scelta = document.getElementById('classe-nome').value;
    const nome = (scelta === 'altro'
        ? document.getElementById('classe-altro').value : scelta).trim();
    const anno = document.getElementById('classe-anno').value.trim();
    if (!id || !nome || !anno) {
        avviso('Seleziona scuola e classe, poi indica l’anno scolastico.', 'errore');
        return;
    }
    if (classiDisponibili.some(c => Number(c.plesso_id) === Number(id) &&
        c.nome.toLocaleLowerCase('it') === nome.toLocaleLowerCase('it') &&
        c.anno_scolastico === anno)) {
        avviso('Questa classe è già registrata per la scuola e l’anno scelti.', 'errore');
        return;
    }
    try {
        await API.post('/api/admin/classi', {
            plesso_id: Number(id), nome, anno_scolastico: anno
        });
        avviso('Classe creata.');
        document.getElementById('classe-nome').value = '';
        document.getElementById('classe-altro').value = '';
        mostraCampoAltraClasse();
        await caricaClassi();
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- tipologie ---------------- */
async function caricaTipologie() {
    const elenco = await API.get('/api/admin/tipologie');
    document.getElementById('tipologie-corpo').innerHTML = elenco.map((t) => `
        <tr><td>${esc(t.nome)}</td>
        <td><button class="btn-azione" data-cancella-tipologia="${t.id}" data-testid="tipologia-cancella-${t.id}">Disattiva tipo di menu</button></td></tr>`).join('');
    document.querySelectorAll('[data-cancella-tipologia]').forEach((b) => b.addEventListener('click', async () => {
        if (!confirm('Vuoi disattivare questo tipo di menu? Prima verifica che non sia necessario per altri bambini.')) return;
        try {
            await API.del('/api/admin/tipologie/' + b.dataset.cancellaTipologia);
            avviso('Tipo di menu disattivato.');
            caricaTipologie();
        } catch (err) { avviso(err.message, 'errore'); }
    }));
}

document.getElementById('form-tipologia').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/tipologie', { nome: document.getElementById('tipologia-nome').value });
        avviso('Nuovo tipo di menu aggiunto.');
        document.getElementById('tipologia-nome').value = '';
        caricaTipologie();
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- email ---------------- */
async function caricaEmail() {
    const [modelli, destinatari, impostazioni] = await Promise.all([
        API.get('/api/admin/modelli-email'),
        API.get('/api/admin/destinatari'),
        API.get('/api/admin/impostazioni')
    ]);
    const plessoSelezionato = document.getElementById('modello-plesso').value;
    const modello = modelli.find((m) => String(m.plesso_id) === plessoSelezionato);
    document.getElementById('modello-oggetto').value = modello ? modello.oggetto : 'Sollecito pagamento mensa scolastica - {plesso}';
    document.getElementById('modello-testo').value = modello ? modello.testo : '';

    document.getElementById('destinatari-corpo').innerHTML = destinatari.map((d) => `
        <tr><td>${esc(d.etichetta)}</td><td>${esc(d.email)}</td>
        <td>${Number(d.attivo) === 1 ? 'sì' : 'no'}</td>
        <td><button class="btn-azione" data-toggle-dest="${d.id}" data-attivo="${d.attivo}" data-testid="destinatario-toggle-${d.id}">${Number(d.attivo) === 1 ? 'Disattiva' : 'Attiva'}</button></td></tr>`).join('');
    document.querySelectorAll('[data-toggle-dest]').forEach((b) => b.addEventListener('click', async () => {
        await API.put('/api/admin/destinatari/' + b.dataset.toggleDest, { attivo: Number(b.dataset.attivo) === 1 ? 0 : 1 });
        caricaEmail();
    }));

    document.getElementById('smtp-host').value = impostazioni.smtp_host || '';
    document.getElementById('smtp-port').value = impostazioni.smtp_port || 587;
    document.getElementById('smtp-security').value = impostazioni.smtp_security || 'tls';
    document.getElementById('smtp-username').value = impostazioni.smtp_username || '';
    document.getElementById('smtp-password').value = impostazioni.smtp_password || '';
    document.getElementById('smtp-from').value = impostazioni.smtp_from || '';
    document.getElementById('email-protocollo').value = impostazioni.email_protocollo || '';
}

document.getElementById('modello-plesso').addEventListener('change', caricaEmail);

document.getElementById('form-modello').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/modelli-email', {
            plesso_id: Number(document.getElementById('modello-plesso').value),
            oggetto: document.getElementById('modello-oggetto').value,
            testo: document.getElementById('modello-testo').value,
            attivo: true
        });
        avviso('Modello salvato.');
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('form-destinatario').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/destinatari', {
            etichetta: document.getElementById('dest-etichetta').value,
            email: document.getElementById('dest-email').value
        });
        document.getElementById('dest-etichetta').value = '';
        document.getElementById('dest-email').value = '';
        avviso('Destinatario aggiunto.');
        caricaEmail();
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('form-smtp').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/impostazioni', {
            smtp_host: document.getElementById('smtp-host').value,
            smtp_port: document.getElementById('smtp-port').value,
            smtp_security: document.getElementById('smtp-security').value,
            smtp_username: document.getElementById('smtp-username').value,
            smtp_password: document.getElementById('smtp-password').value,
            smtp_from: document.getElementById('smtp-from').value,
            email_protocollo: document.getElementById('email-protocollo').value
        });
        avviso('Configurazione salvata.');
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('btn-email-prova').addEventListener('click', async () => {
    const email = prompt('Indirizzo per l\'invio di prova:');
    if (!email) return;
    try {
        await API.post('/api/admin/email-prova', { email });
        avviso('Email di prova inviata.');
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- utenti ---------------- */
async function caricaUtenti() {
    const elenco = await API.get('/api/admin/utenti');
    document.getElementById('utenti-corpo').innerHTML = elenco.map((u) => `
        <tr><td>${esc(u.username)}</td><td>${esc(nomeRuolo(u.ruolo))}</td><td>${esc(u.plesso_nome || '-')}</td>
        <td>${Number(u.attivo) === 1 ? 'sì' : 'no'}</td>
        <td><button class="btn-azione" data-toggle-utente="${u.id}" data-attivo="${u.attivo}" data-testid="utente-toggle-${u.id}">${Number(u.attivo) === 1 ? 'Disattiva' : 'Attiva'}</button>
            <button class="btn-azione" data-password="${u.id}" data-testid="utente-password-${u.id}">Cambia password</button></td></tr>`).join('');
    document.querySelectorAll('[data-toggle-utente]').forEach((b) => b.addEventListener('click', async () => {
        await API.put('/api/admin/utenti/' + b.dataset.toggleUtente, { attivo: Number(b.dataset.attivo) === 1 ? 0 : 1 });
        caricaUtenti();
    }));
    document.querySelectorAll('[data-password]').forEach((b) => b.addEventListener('click', async () => {
        const password = prompt('Nuova password (min. 8 caratteri):');
        if (!password) return;
        try {
            await API.put('/api/admin/utenti/' + b.dataset.password, { password });
            avviso('Password aggiornata.');
        } catch (err) { avviso(err.message, 'errore'); }
    }));
}

document.getElementById('form-utente').addEventListener('submit', async (e) => {
    e.preventDefault();
    const plessoId = document.getElementById('ut-plesso').value;
    try {
        await API.post('/api/admin/utenti', {
            ruolo: document.getElementById('ut-ruolo').value,
            username: document.getElementById('ut-username').value,
            password: document.getElementById('ut-password').value,
            plesso_id: plessoId ? Number(plessoId) : null
        });
        avviso('Nuovo accesso creato.');
        document.getElementById('form-utente').reset();
        caricaUtenti();
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- job ---------------- */
async function caricaEsecuzioni() {
    const elenco = await API.get('/api/job/esecuzioni');
    document.getElementById('job-corpo').innerHTML = elenco.map((e) => `
        <tr><td>${dataIt(e.data)}</td><td>${esc(e.plesso_nome)}</td>
        <td><span class="badge-stato badge-${e.esito === 'ok' ? 'verde' : e.esito === 'errore' ? 'rosso' : 'giallo'}">${esc(esitoOperazione(e.esito))}</span></td>
        <td class="nota">${esc(e.dettaglio || '')}</td></tr>`).join('')
        || '<tr><td colspan="4" class="vuoto">Nessuna esecuzione registrata.</td></tr>';
}


function mostraEsitoJob(esito) {
    const righe = Array.isArray(esito) ? esito : [esito];
    const traduci = (stato) => ({
        eseguito: 'Conteggio completato',
        gia_eseguito: 'Conteggio già completato in precedenza',
        fuori_calendario: 'Mensa non prevista oggi',
        cutoff_non_raggiunto: 'Orario limite non ancora raggiunto',
        in_corso: 'Elaborazione già in corso',
        errore: 'Errore nel conteggio'
    }[stato] || esitoOperazione(stato));
    const html = righe.map((r) => {
        const colore = r.stato === 'errore' ? 'rosso' :
            r.stato === 'eseguito' || r.stato === 'gia_eseguito' ? 'verde' : 'giallo';
        return '<article class="card-mensa"><h3>' + esc(r.plesso || 'Scuola') + '</h3>' +
            '<p><span class="badge-stato badge-' + colore + '">' + esc(traduci(r.stato)) + '</span></p>' +
            (r.consumi !== undefined ? '<p>Buoni utilizzati: <strong>' + Number(r.consumi) + '</strong></p>' : '') +
            (r.pasti_report !== undefined ? '<p>Pasti nel report: <strong>' + Number(r.pasti_report) + '</strong></p>' : '') +
            (r.messaggio ? '<p class="nota">' + esc(r.messaggio) + '</p>' : '') +
            '</article>';
    }).join('');
    document.getElementById('job-esito').className = 'griglia-cards';
    document.getElementById('job-esito').innerHTML = html || '<p>Nessun nuovo conteggio.</p>';
}

document.getElementById('btn-scheduler').addEventListener('click', async (e) => {
    if (!await UI.conferma('Conferma conteggio dei pasti',
        'Questa operazione non è una simulazione: dove il servizio è attivo e l’orario limite è passato, può scalare i buoni alle famiglie.',
        'Esegui il conteggio')) return;
    try {
        await UI.esegui(e.currentTarget, async () => {
            const esito = await API.post('/api/job/scheduler', {});
            mostraEsitoJob(esito);
            await caricaEsecuzioni();
            avviso('Controllo concluso. Consulta l’esito per ciascuna scuola.');
        }, 'Elaborazione…');
    } catch (err) { UI.stato('job-esito', 'Conteggio non completato: ' + err.message, 'errore'); }
});

document.getElementById('btn-job-plesso').addEventListener('click', async (e) => {
    const scelta = document.getElementById('job-plesso');
    const nomeScuola = scelta.options[scelta.selectedIndex]?.text || 'la scuola selezionata';
    if (!await UI.conferma('Conferma conteggio di ' + nomeScuola,
        'L’operazione può confermare i pasti e scalare i buoni. Non è una simulazione.',
        'Conferma conteggio')) return;
    try {
        await UI.esegui(e.currentTarget, async () => {
            const esito = await API.post('/api/job/plessi/' + scelta.value, {});
            mostraEsitoJob(esito);
            await caricaEsecuzioni();
            avviso('Controllo terminato. Consulta l’esito qui sotto.');
        }, 'Elaborazione…');
    } catch (err) { UI.stato('job-esito', 'Conteggio non completato: ' + err.message, 'errore'); }
});

caricaPlessi().catch((e) => avviso(e.message, 'errore'));
