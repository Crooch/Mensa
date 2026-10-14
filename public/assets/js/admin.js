/* Area admin: configurazioni, utenze, email, job giornaliero. */
const utente = proteggi('admin');
let plessi = [];

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
    document.getElementById('plessi-corpo').innerHTML = plessi.map((p) => `
        <tr data-testid="plesso-riga-${p.id}">
            <td>${esc(p.nome)}</td><td>${euro(p.tariffa_base_pasto)}</td><td>${p.fido_buoni}</td>
            <td>${esc(String(p.cutoff_orario).slice(0, 5))}</td><td>${esc(p.dirigente_email || '-')}</td>
            <td><button class="btn-azione" data-modifica="${p.id}" data-testid="plesso-modifica-${p.id}">Modifica</button>
                <button class="btn-azione" data-cancella="${p.id}" data-testid="plesso-cancella-${p.id}">Cancella</button></td>
        </tr>`).join('');

    document.querySelectorAll('[data-modifica]').forEach((b) => b.addEventListener('click', () => {
        const p = plessi.find((x) => String(x.id) === b.dataset.modifica);
        document.getElementById('plesso-id').value = p.id;
        document.getElementById('plesso-nome').value = p.nome;
        document.getElementById('plesso-tariffa').value = p.tariffa_base_pasto;
        document.getElementById('plesso-fido').value = p.fido_buoni;
        document.getElementById('plesso-cutoff').value = String(p.cutoff_orario).slice(0, 5);
        document.getElementById('plesso-dirigente').value = p.dirigente_email || '';
        document.getElementById('plesso-protocollo').value = p.email_protocollo || '';
    }));
    document.querySelectorAll('[data-cancella]').forEach((b) => b.addEventListener('click', async () => {
        if (!confirm('Cancellazione logica del plesso: gli storici restano consultabili. Confermi?')) return;
        await API.del('/api/admin/plessi/' + b.dataset.cancella);
        avviso('Plesso cancellato logicamente.');
        caricaPlessi();
    }));

    const opzioni = plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    ['classe-plesso', 'modello-plesso', 'job-plesso', 'calendario-plesso'].forEach((id) => { document.getElementById(id).innerHTML = opzioni; });
    document.getElementById('ut-plesso').innerHTML = '<option value="">—</option>' + opzioni;
}

document.getElementById('form-plesso').addEventListener('submit', async (e) => {
    e.preventDefault();
    const corpo = {
        nome: document.getElementById('plesso-nome').value,
        tariffa_base_pasto: document.getElementById('plesso-tariffa').value,
        fido_buoni: document.getElementById('plesso-fido').value,
        cutoff_orario: document.getElementById('plesso-cutoff').value,
        dirigente_email: document.getElementById('plesso-dirigente').value,
        email_protocollo: document.getElementById('plesso-protocollo').value
    };
    const id = document.getElementById('plesso-id').value;
    try {
        if (id) await API.put('/api/admin/plessi/' + id, corpo);
        else await API.post('/api/admin/plessi', corpo);
        avviso('Plesso salvato.');
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

async function caricaCalendario() {
    const id = document.getElementById('calendario-plesso').value;
    if (!id) return;
    const dati = await API.get('/api/admin/calendari/' + id);
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
        </tr>`).join('') || '<tr><td colspan="4" class="vuoto">Nessuna eccezione.</td></tr>';
    corpo.querySelectorAll('[data-cancella-eccezione]').forEach((btn) => btn.addEventListener('click', async () => {
        if (!confirm('Eliminare questa eccezione al calendario?')) return;
        try {
            await API.del('/api/admin/calendari/' + id + '/eccezioni/' + btn.dataset.cancellaEccezione);
            avviso('Eccezione eliminata.');
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
        avviso('Calendario salvato.');
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
        avviso('Eccezione salvata.');
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
            (esito.attivo ? 'Servizio attivo' : 'Servizio NON attivo') +
            ' — ' + (motivi[esito.motivo] || esito.motivo);
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- classi ---------------- */
async function caricaClassi() {
    const elenco = await API.get('/api/admin/classi');
    document.getElementById('classi-corpo').innerHTML = elenco.map((c) => `
        <tr><td>${esc(c.plesso_nome)}</td><td>${esc(c.nome)}</td><td>${esc(c.anno_scolastico)}</td>
        <td><button class="btn-azione" data-cancella-classe="${c.id}" data-testid="classe-cancella-${c.id}">Cancella</button></td></tr>`).join('');
    document.querySelectorAll('[data-cancella-classe]').forEach((b) => b.addEventListener('click', async () => {
        await API.del('/api/admin/classi/' + b.dataset.cancellaClasse);
        avviso('Classe cancellata logicamente.');
        caricaClassi();
    }));
}

document.getElementById('form-classe').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/classi', {
            plesso_id: Number(document.getElementById('classe-plesso').value),
            nome: document.getElementById('classe-nome').value,
            anno_scolastico: document.getElementById('classe-anno').value
        });
        avviso('Classe creata.');
        document.getElementById('classe-nome').value = '';
        caricaClassi();
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- tipologie ---------------- */
async function caricaTipologie() {
    const elenco = await API.get('/api/admin/tipologie');
    document.getElementById('tipologie-corpo').innerHTML = elenco.map((t) => `
        <tr><td>${esc(t.nome)}</td>
        <td><button class="btn-azione" data-cancella-tipologia="${t.id}" data-testid="tipologia-cancella-${t.id}">Cancella</button></td></tr>`).join('');
    document.querySelectorAll('[data-cancella-tipologia]').forEach((b) => b.addEventListener('click', async () => {
        await API.del('/api/admin/tipologie/' + b.dataset.cancellaTipologia);
        avviso('Tipologia cancellata logicamente.');
        caricaTipologie();
    }));
}

document.getElementById('form-tipologia').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/admin/tipologie', { nome: document.getElementById('tipologia-nome').value });
        avviso('Tipologia creata.');
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
        <tr><td>${esc(u.username)}</td><td>${esc(u.ruolo)}</td><td>${esc(u.plesso_nome || '-')}</td>
        <td>${Number(u.attivo) === 1 ? 'sì' : 'no'}</td>
        <td><button class="btn-azione" data-toggle-utente="${u.id}" data-attivo="${u.attivo}" data-testid="utente-toggle-${u.id}">${Number(u.attivo) === 1 ? 'Disattiva' : 'Attiva'}</button>
            <button class="btn-azione" data-password="${u.id}" data-testid="utente-password-${u.id}">Password</button></td></tr>`).join('');
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
        avviso('Utenza creata.');
        document.getElementById('form-utente').reset();
        caricaUtenti();
    } catch (err) { avviso(err.message, 'errore'); }
});

/* ---------------- job ---------------- */
async function caricaEsecuzioni() {
    const elenco = await API.get('/api/job/esecuzioni');
    document.getElementById('job-corpo').innerHTML = elenco.map((e) => `
        <tr><td>${dataIt(e.data)}</td><td>${esc(e.plesso_nome)}</td>
        <td><span class="badge-stato badge-${e.esito === 'ok' ? 'verde' : e.esito === 'errore' ? 'rosso' : 'giallo'}">${esc(e.esito)}</span></td>
        <td class="nota">${esc(e.dettaglio || '')}</td></tr>`).join('')
        || '<tr><td colspan="4" class="vuoto">Nessuna esecuzione registrata.</td></tr>';
}

document.getElementById('btn-scheduler').addEventListener('click', async () => {
    try {
        const esito = await API.post('/api/job/scheduler', {});
        document.getElementById('job-esito').textContent = JSON.stringify(esito, null, 2);
        avviso('Scheduler eseguito.');
        caricaEsecuzioni();
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('btn-job-plesso').addEventListener('click', async () => {
    try {
        const esito = await API.post('/api/job/plessi/' + document.getElementById('job-plesso').value, {});
        document.getElementById('job-esito').textContent = JSON.stringify(esito, null, 2);
        avviso('Job eseguito per il plesso selezionato.');
        caricaEsecuzioni();
    } catch (err) { avviso(err.message, 'errore'); }
});

caricaPlessi().catch((e) => avviso(e.message, 'errore'));
