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
    if (vista === 'job') aggiornaMonitoraggioJob();
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
let periodiCalendario = [];

function annoScolasticoCalendario(data) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(data || '')) return 'da definire';
    const anno = Number(data.slice(0, 4));
    const mese = Number(data.slice(5, 7));
    const inizio = mese >= 9 ? anno : anno - 1;
    return inizio + '/' + (inizio + 1);
}
function aggiornaAnnoCalendario() {
    const data = document.getElementById('calendario-inizio').value;
    document.getElementById('calendario-anno-scolastico').textContent =
        annoScolasticoCalendario(data);
}
function messaggioPeriodo(testo = '', errore = false) {
    const messaggio = document.getElementById('eccezione-messaggio');
    messaggio.textContent = testo;
    messaggio.hidden = !testo;
    messaggio.classList.toggle('admin-periodo-messaggio--errore', errore);
}
function azzeraModuloPeriodo() {
    document.getElementById('form-eccezione-calendario').reset();
    document.getElementById('eccezione-id').value = '';
    document.getElementById('eccezione-annulla').hidden = true;
    document.querySelector('[data-testid="eccezione-salva"]').textContent = 'Salva periodo';
    messaggioPeriodo();
}
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
    aggiornaAnnoCalendario();
    giorniCalendario.forEach(g => {
        document.getElementById('calendario-' + g).checked = Number(cfg[g]) === 1;
    });
    periodiCalendario = dati.eccezioni || [];
    const corpo = document.getElementById('eccezioni-calendario-corpo');
    corpo.innerHTML = periodiCalendario.map(e => `
        <tr>
            <td>${dataIt(e.giorno)}</td>
            <td>${e.giorno_fine === e.giorno ? 'Un solo giorno' : dataIt(e.giorno_fine)}</td>
            <td><span class="badge-stato badge-${Number(e.servizio_attivo) === 1 ? 'verde' : 'rosso'}">${Number(e.servizio_attivo) === 1 ? 'Apertura' : 'Chiusura'}</span></td>
            <td>${esc(e.motivazione)}</td>
            <td class="admin-periodo-azioni">
                <button type="button" class="btn-azione" data-modifica-periodo="${Number(e.id)}">Modifica</button>
                <button type="button" class="btn-azione" data-cancella-periodo="${Number(e.id)}">Elimina</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="5" class="vuoto">Nessuna apertura o chiusura straordinaria registrata.</td></tr>';
    azzeraModuloPeriodo();
}
document.getElementById('eccezioni-calendario-corpo').addEventListener('click', async ev => {
    const pulsante = ev.target.closest('button');
    if (!pulsante) return;
    const id = document.getElementById('calendario-plesso').value;
    const modifica = pulsante.dataset.modificaPeriodo;
    const cancella = pulsante.dataset.cancellaPeriodo;
    const p = periodiCalendario.find(e => String(e.id) === String(modifica || cancella));
    if (!p || !id) return;
    if (modifica) {
        document.getElementById('eccezione-id').value = p.id;
        document.getElementById('eccezione-data').value = p.giorno;
        document.getElementById('eccezione-fine').value = p.giorno_fine === p.giorno ? '' : p.giorno_fine;
        document.getElementById('eccezione-attivo').value = String(Number(p.servizio_attivo));
        document.getElementById('eccezione-motivazione').value = p.motivazione;
        document.getElementById('eccezione-annulla').hidden = false;
        document.querySelector('[data-testid="eccezione-salva"]').textContent = 'Salva modifiche';
        messaggioPeriodo('Stai modificando il periodo selezionato.');
        document.getElementById('form-eccezione-calendario').scrollIntoView({behavior: 'smooth', block: 'start'});
        document.getElementById('eccezione-data').focus({preventScroll: true});
    } else if (cancella) {
        const periodo = p.giorno_fine === p.giorno ? dataIt(p.giorno)
            : dataIt(p.giorno) + ' – ' + dataIt(p.giorno_fine);
        if (!confirm('Eliminare ' + periodo + '? Tornerà a valere il calendario ordinario.')) return;
        try {
            await API.del('/api/admin/calendari/' + id + '/periodi/' + p.id);
            avviso('Periodo eliminato. Ora vale il calendario ordinario.');
            await caricaCalendario();
        } catch (err) {
            messaggioPeriodo(err.message, true);
        }
    }
});
document.getElementById('eccezione-annulla').addEventListener('click', azzeraModuloPeriodo);
document.getElementById('calendario-inizio').addEventListener('change', aggiornaAnnoCalendario);
document.getElementById('calendario-plesso').addEventListener('change', () => {
    periodiCalendario = [];
    azzeraModuloPeriodo();
    caricaCalendario().catch(err => avviso(err.message, 'errore'));
});
document.getElementById('form-calendario').addEventListener('submit', async ev => {
    ev.preventDefault();
    const id = document.getElementById('calendario-plesso').value;
    if (!id) { avviso('Seleziona una scuola.', 'errore'); return; }
    const dati = {
        data_inizio: document.getElementById('calendario-inizio').value,
        data_fine: document.getElementById('calendario-fine').value || null
    };
    giorniCalendario.forEach(g => {
        dati[g] = document.getElementById('calendario-' + g).checked ? 1 : 0;
    });
    try {
        await API.put('/api/admin/calendari/' + id, dati);
        avviso('Calendario ordinario salvato per la scuola selezionata.');
        await caricaCalendario();
    } catch (err) { avviso(err.message, 'errore'); }
});
document.getElementById('form-eccezione-calendario').addEventListener('submit', async ev => {
    ev.preventDefault();
    const id = document.getElementById('calendario-plesso').value;
    const periodoId = document.getElementById('eccezione-id').value;
    const dal = document.getElementById('eccezione-data').value;
    const al = document.getElementById('eccezione-fine').value || dal;
    messaggioPeriodo();
    if (!id || !dal || al < dal) {
        messaggioPeriodo('Controlla le date: la fine non può precedere l’inizio.', true);
        return;
    }
    const dati = {
        data_inizio: dal,
        data_fine: al,
        servizio_attivo: Number(document.getElementById('eccezione-attivo').value),
        motivazione: document.getElementById('eccezione-motivazione').value.trim()
    };
    const percorso = '/api/admin/calendari/' + id + '/periodi';
    const pulsante = ev.submitter || document.querySelector('[data-testid="eccezione-salva"]');
    pulsante.disabled = true;
    try {
        if (periodoId) {
            await API.put(percorso + '/' + periodoId, dati);
        } else {
            await API.post(percorso, dati);
        }
        avviso('Periodo straordinario salvato.');
        await caricaCalendario();
    } catch (err) {
        messaggioPeriodo(err.message, true);
    } finally {
        pulsante.disabled = false;
    }
});
/* Verifica generale, distinta dalla scuola selezionata per la modifica. */
let verificaCalendarioDati = null;
let verificaCalendarioPagina = 1;
let verificaCalendarioRichiesta = 0;
const VERIFICA_SCUOLE_PER_PAGINA = 8;
const motiviVerificaCalendario = {
    servizio_non_avviato: 'Servizio non ancora avviato',
    servizio_terminato: 'Servizio terminato',
    fine_settimana: 'Fine settimana',
    festivita_nazionale: 'Festività nazionale',
    chiusura_programmata: 'Chiusura straordinaria',
    giorno_senza_servizio: 'Giorno senza mensa',
    giorno_di_servizio: 'Giorno ordinario di mensa',
    apertura_straordinaria: 'Apertura straordinaria',
    calendario_non_configurato: 'Calendario da configurare'
};
function normalizzaRicercaCalendario(testo) {
    return String(testo || '').toLocaleLowerCase('it')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}
function disegnaRiepilogoCalendario() {
    if (!verificaCalendarioDati) return;
    const {scuole, totali} = verificaCalendarioDati;
    const parola = normalizzaRicercaCalendario(
        document.getElementById('calendario-verifica-cerca').value
    );
    const filtro = document.getElementById('calendario-verifica-filtro').value;
    const risultati = scuole.filter(p => {
        const statoCorretto = filtro === 'tutte' ||
            (filtro === 'aperte' ? p.attivo : !p.attivo);
        const scuolaCorrisponde = normalizzaRicercaCalendario(p.plesso_nome).includes(parola);
        const classiCorrispondono = (p.classi || []).some(c =>
            normalizzaRicercaCalendario(c.nome).includes(parola));
        return statoCorretto && (!parola || scuolaCorrisponde || classiCorrispondono);
    });
    const pagine = Math.max(1, Math.ceil(risultati.length / VERIFICA_SCUOLE_PER_PAGINA));
    verificaCalendarioPagina = Math.min(verificaCalendarioPagina, pagine);
    const inizio = (verificaCalendarioPagina - 1) * VERIFICA_SCUOLE_PER_PAGINA;
    const elementi = risultati.slice(inizio, inizio + VERIFICA_SCUOLE_PER_PAGINA);
    const riepilogo = [
        [totali.scuole_aperte, 'Scuole con mensa'],
        [totali.scuole_chiuse, 'Scuole senza mensa'],
        [totali.classi_aperte, 'Classi con mensa'],
        [totali.classi_chiuse, 'Classi senza mensa']
    ];
    document.getElementById('calendario-verifica-sommario').innerHTML = riepilogo
        .map(([n, etichetta]) =>
            `<div class="admin-verifica-numero"><strong>${Number(n) || 0}</strong>
                <span>${etichetta}</span></div>`).join('');
    document.getElementById('calendario-verifica-contatore').textContent =
        risultati.length ? `Scuole mostrate: ${risultati.length} su ${scuole.length}` :
            'Nessuna scuola corrisponde ai filtri selezionati.';
    document.getElementById('calendario-verifica-risultati').innerHTML =
        elementi.map(p => {
            const scuolaCorrisponde = normalizzaRicercaCalendario(p.plesso_nome).includes(parola);
            const classi = (!parola || scuolaCorrisponde) ? (p.classi || [])
                : (p.classi || []).filter(c =>
                    normalizzaRicercaCalendario(c.nome).includes(parola));
            const stato = p.attivo ? 'Mensa prevista' : 'Mensa non prevista';
            const motivo = motiviVerificaCalendario[p.motivo] || p.motivo;
            const elenco = classi.length
                ? `<ul class="admin-verifica-classi__lista">${classi.map(c =>
                    `<li>${esc(c.nome)}</li>`).join('')}</ul>`
                : '<p class="nota">Nessuna classe registrata per l’anno scolastico indicato.</p>';
            const specifica = parola && !scuolaCorrisponde
                ? ` · ${classi.length} corrispondenti alla ricerca` : '';
            return `<article class="admin-verifica-scuola ${p.attivo ? 'admin-verifica-scuola--aperta' : 'admin-verifica-scuola--chiusa'}"
                         data-testid="verifica-scuola-${Number(p.plesso_id)}">
                <div class="admin-verifica-scuola__testa">
                    <div class="admin-verifica-scuola__titolo">
                        <h4>${esc(p.plesso_nome)}</h4>
                        <p>${esc(motivo)}</p>
                    </div>
                    <span class="badge-stato badge-${p.attivo ? 'verde' : 'rosso'}">${stato}</span>
                </div>
                <details class="admin-verifica-classi">
                    <summary>${Number(p.totale_classi) || 0} classi della scuola${specifica}</summary>
                    <div class="admin-verifica-classi__interno">
                        <p class="nota">Le classi seguono lo stato della scuola:
                           <strong>${stato.toLocaleLowerCase('it')}</strong>.</p>
                        ${elenco}
                    </div>
                </details>
            </article>`;
        }).join('') || '<p class="admin-verifica-vuoto">Nessun risultato. Prova a modificare la ricerca o il filtro.</p>';
    document.getElementById('calendario-verifica-pagina').textContent =
        `Pagina ${verificaCalendarioPagina} di ${pagine}`;
    const nav = document.querySelector('.admin-verifica-pagine');
    nav.hidden = risultati.length <= VERIFICA_SCUOLE_PER_PAGINA;
    document.getElementById('calendario-verifica-precedente').disabled =
        verificaCalendarioPagina === 1;
    document.getElementById('calendario-verifica-successiva').disabled =
        verificaCalendarioPagina >= pagine;
}
function filtraRiepilogoCalendario() {
    verificaCalendarioPagina = 1;
    disegnaRiepilogoCalendario();
}
document.getElementById('calendario-verifica-cerca').addEventListener(
    'input', filtraRiepilogoCalendario
);
document.getElementById('calendario-verifica-filtro').addEventListener(
    'change', filtraRiepilogoCalendario
);
document.getElementById('calendario-verifica-precedente').addEventListener('click', () => {
    if (verificaCalendarioPagina > 1) {
        verificaCalendarioPagina--;
        disegnaRiepilogoCalendario();
    }
});
document.getElementById('calendario-verifica-successiva').addEventListener('click', () => {
    verificaCalendarioPagina++;
    disegnaRiepilogoCalendario();
});
document.getElementById('calendario-verifica-data').addEventListener('change', () => {
    ++verificaCalendarioRichiesta;
    verificaCalendarioDati = null;
    document.getElementById('calendario-verifica-pannello').hidden = true;
    document.getElementById('calendario-verifica-esito').textContent =
        'Data modificata. Premi «Controlla tutte le scuole» per vedere i nuovi risultati.';
});
document.getElementById('calendario-verifica').addEventListener('click', async ev => {
    const data = document.getElementById('calendario-verifica-data').value;
    const stato = document.getElementById('calendario-verifica-esito');
    if (!data) {
        stato.textContent = 'Seleziona una data da verificare.';
        return;
    }
    const richiesta = ++verificaCalendarioRichiesta;
    verificaCalendarioDati = null;
    document.getElementById('calendario-verifica-pannello').hidden = true;
    stato.textContent = 'Verifica di tutte le scuole in corso…';
    const pulsante = ev.currentTarget;
    pulsante.disabled = true;
    try {
        const dati = await API.get('/api/admin/calendari/riepilogo?data=' +
            encodeURIComponent(data));
        if (richiesta !== verificaCalendarioRichiesta) return;
        verificaCalendarioDati = dati;
        verificaCalendarioPagina = 1;
        document.getElementById('calendario-verifica-cerca').value = '';
        document.getElementById('calendario-verifica-filtro').value = 'tutte';
        document.getElementById('calendario-verifica-titolo').textContent =
            'Situazione del ' + dataIt(dati.data);
        document.getElementById('calendario-verifica-anno').textContent =
            'Classi dell’anno scolastico ' + dati.anno_scolastico;
        document.getElementById('calendario-verifica-pannello').hidden = false;
        stato.textContent = '';
        disegnaRiepilogoCalendario();
    } catch (err) {
        if (richiesta === verificaCalendarioRichiesta) {
            stato.textContent = 'Impossibile verificare il servizio: ' + err.message;
        }
    } finally {
        pulsante.disabled = false;
    }
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
        <tr data-classe="${Number(c.id)}"><td>${esc(c.nome)}</td><td>${esc(c.anno_scolastico)}</td>
        <td><button type="button" class="btn-azione" data-cancella-classe="${Number(c.id)}"
            data-testid="classe-cancella-${Number(c.id)}">Disattiva classe</button></td></tr>`).join('')
        || '<tr><td colspan="3" class="vuoto">Non ci sono classi registrate per questa scuola.</td></tr>';
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
        ? 'Anno scolastico: ' + classe.anno_scolastico
        : '';
    document.querySelectorAll('#classi-corpo tr[data-classe]').forEach(riga => {
        riga.classList.toggle('selezionata', riga.dataset.classe === scelta.value);
    });
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

/* ---------------- chiusura giornaliera dei pasti ----------------
 * Il monitoraggio consulta solo GET: non chiama scheduler né addebita buoni.
 * L'esecuzione manuale è riservata al pannello richiudibile e confermata.
 */
let richiestaMonitoraggioJob = 0;
const statoChiusura = {
    completata: ['Completata', 'verde'],
    in_attesa: ['In attesa dell’orario limite', 'giallo'],
    da_eseguire: ['Da completare', 'giallo'],
    non_prevista: ['Mensa non prevista', 'neutro'],
    in_corso: ['Elaborazione in corso', 'giallo'],
    errore: ['Da verificare', 'rosso']
};

function leggiDettaglioEsecuzione(valore) {
    if (valore && typeof valore === 'object') return valore;
    try {
        const dati = JSON.parse(String(valore || ''));
        return dati && typeof dati === 'object' && !Array.isArray(dati) ? dati : {};
    } catch (_) { return {}; }
}
function numeroJob(n) {
    const numero = Number(n);
    return Number.isFinite(numero) && numero >= 0 ? numero : 0;
}
function oraEsecuzione(valore) {
    if (!valore) return '';
    const testo = String(valore);
    return testo.length >= 16 ? testo.slice(11, 16) : '';
}
function riepilogoComunicazioni(dettaglio) {
    const solleciti = dettaglio.solleciti || {};
    const errori = numeroJob(solleciti.errori);
    if (errori) return '<span class="admin-job-errore">' + errori + ' errori di invio</span>';
    return numeroJob(solleciti.inviati).toString();
}

async function caricaStatoJob() {
    const richiesta = ++richiestaMonitoraggioJob;
    const stato = document.getElementById('job-stato-caricamento');
    stato.textContent = 'Caricamento della situazione…';
    try {
        const risposta = await API.get('/api/job/stato');
        if (richiesta !== richiestaMonitoraggioJob) return;
        document.getElementById('job-data-oggi').textContent = '· ' + dataIt(risposta.data);
        const scuole = Array.isArray(risposta.scuole) ? risposta.scuole : [];
        document.getElementById('job-scuole').innerHTML = scuole.map(p => {
            const statoBase = statoChiusura[p.stato] || ['Da verificare', 'rosso'];
            const erroriEmail = numeroJob(p.errori_comunicazioni);
            const completataConErrori = p.stato === 'completata' && erroriEmail > 0;
            const colore = completataConErrori ? 'giallo' : statoBase[1];
            const etichetta = completataConErrori
                ? 'Pasti confermati, email da verificare' : statoBase[0];
            const dati = p.stato === 'completata'
                ? `<div class="admin-job-scuola__numeri">
                    <span><strong>${numeroJob(p.pasti_confermati)}</strong> pasti confermati</span>
                    <span><strong>${numeroJob(p.buoni_utilizzati)}</strong> buoni addebitati</span>
                    <span><strong>${numeroJob(p.solleciti_inviati)}</strong> solleciti inviati</span>
                    ${erroriEmail ? `<span class="admin-job-errore">${erroriEmail} invii non riusciti</span>` : ''}
                </div>` : '';
            const chiarimento = p.stato === 'da_eseguire'
                ? 'L’orario limite è passato, ma non risulta una chiusura completata.'
                : p.stato === 'errore'
                    ? 'Un tentativo non è riuscito. Controlla lo storico prima di intervenire.'
                    : p.stato === 'in_corso'
                        ? 'Elaborazione avviata. Se rimane così a lungo, contatta l’assistenza.'
                        : p.stato === 'non_prevista'
                            ? 'Nessuna elaborazione richiesta per oggi.'
                            : '';
            const ora = oraEsecuzione(p.concluso_il);
            return `<article class="admin-job-scuola" data-testid="job-scuola-${Number(p.plesso_id)}">
                <div class="admin-job-scuola__testa">
                    <div><h3>${esc(p.plesso_nome)}</h3>
                    <p class="nota">Orario limite: ${esc(p.orario_limite)}${ora ? ' · Chiusura alle ' + esc(ora) : ''}</p></div>
                    <span class="badge-stato badge-${colore}">${esc(etichetta)}</span>
                </div>
                ${dati}
                ${chiarimento ? '<p class="nota admin-job-scuola__nota">' + esc(chiarimento) + '</p>' : ''}
            </article>`;
        }).join('') || '<p class="nota">Non ci sono scuole da controllare.</p>';
        stato.textContent = '';
    } catch (err) {
        if (richiesta !== richiestaMonitoraggioJob) return;
        stato.textContent = 'Non è stato possibile caricare la situazione: ' + err.message;
        document.getElementById('job-scuole').innerHTML = '';
    }
}
async function caricaEsecuzioni() {
    try {
        const elenco = await API.get('/api/job/esecuzioni');
        document.getElementById('job-corpo').innerHTML = elenco.map(e => {
            const dettaglio = leggiDettaglioEsecuzione(e.dettaglio);
            const completata = e.esito === 'ok';
            const inErrore = e.esito === 'errore';
            const erroriEmail = numeroJob(dettaglio.solleciti?.errori);
            const segnale = completata && erroriEmail ? 'giallo' : inErrore ? 'rosso' :
                completata ? 'verde' : 'giallo';
            const esito = inErrore ? 'Non riuscita' : e.esito === 'in_corso'
                ? 'In corso' : completata && erroriEmail
                    ? 'Completata, email da verificare' : completata ? 'Completata' : 'Da verificare';
            const nota = inErrore ? 'Controllare le cause prima di un nuovo tentativo.'
                : e.esito === 'in_corso' ? 'Verificare se l’elaborazione è ancora attiva.'
                : erroriEmail ? erroriEmail + ' invii email non riusciti'
                : '';
            // I dettagli possono contenere identificativi familiari o messaggi
            // tecnici. La tabella espone solo aggregati non personali.
            return `<tr><td>${esc(dataIt(e.data))}</td>
                <td>${esc(e.plesso_nome)}</td>
                <td><span class="badge-stato badge-${segnale}">${esc(esito)}</span></td>
                <td>${completata ? numeroJob(dettaglio.pasti_report) : '—'}</td>
                <td>${completata ? numeroJob(dettaglio.consumi) : '—'}</td>
                <td>${completata ? riepilogoComunicazioni(dettaglio) : '—'}</td>
                <td class="nota">${esc(nota || '—')}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="vuoto">Nessuna elaborazione registrata.</td></tr>';
    } catch (err) {
        document.getElementById('job-corpo').innerHTML =
            '<tr><td colspan="7" class="vuoto">Non è stato possibile caricare lo storico.</td></tr>';
        avviso('Impossibile caricare lo storico delle elaborazioni: ' + err.message, 'errore');
    }
}
async function aggiornaMonitoraggioJob() {
    await Promise.all([caricaStatoJob(), caricaEsecuzioni()]);
}
function mostraEsitoJob(esito) {
    const righe = Array.isArray(esito) ? esito : [esito];
    const traduci = (stato) => ({
        eseguito: 'Chiusura completata',
        gia_eseguito: 'Giornata già completata: nessun nuovo addebito',
        fuori_calendario: 'Mensa non prevista oggi',
        cutoff_non_raggiunto: 'Orario limite non ancora raggiunto',
        in_corso: 'Elaborazione già avviata',
        errore: 'Operazione non riuscita: verificare lo storico'
    }[stato] || 'Da verificare');
    const html = righe.map(r => {
        const colore = r.stato === 'errore' ? 'rosso' :
            r.stato === 'eseguito' || r.stato === 'gia_eseguito' ? 'verde' : 'giallo';
        const email = r.solleciti;
        return `<article class="admin-job-risultato">
            <h3>${esc(r.plesso || 'Scuola')}</h3>
            <span class="badge-stato badge-${colore}">${esc(traduci(r.stato))}</span>
            ${r.pasti_report !== undefined ? `<p>Pasti confermati: <strong>${numeroJob(r.pasti_report)}</strong>
                · Buoni addebitati: <strong>${numeroJob(r.consumi)}</strong></p>` : ''}
            ${email ? `<p>Solleciti inviati: <strong>${numeroJob(email.inviati)}</strong>
                ${numeroJob(email.errori) ? ' · Invii non riusciti: ' + numeroJob(email.errori) : ''}</p>` : ''}
            ${r.stato === 'errore' ? '<p class="nota">Controlla le cause prima di ripetere l’operazione.</p>' : ''}
        </article>`;
    }).join('');
    const contenitore = document.getElementById('job-esito');
    contenitore.hidden = false;
    contenitore.innerHTML = html;
}
document.getElementById('job-aggiorna').addEventListener('click', () => aggiornaMonitoraggioJob());
document.getElementById('btn-scheduler').addEventListener('click', async e => {
    if (!await UI.conferma('Controllo manuale di tutte le scuole',
        'Questa operazione è reale: per le scuole con mensa prevista e orario limite trascorso può confermare i pasti, scalare i buoni e inviare solleciti. Le giornate già completate non vengono addebitate di nuovo.',
        'Avvia controllo')) return;
    try {
        await UI.esegui(e.currentTarget, async () => {
            const esito = await API.post('/api/job/scheduler', {});
            mostraEsitoJob(esito);
            await aggiornaMonitoraggioJob();
        }, 'Elaborazione…');
    } catch (err) { avviso('Operazione non completata: ' + err.message, 'errore'); }
});
document.getElementById('btn-job-plesso').addEventListener('click', async e => {
    const scelta = document.getElementById('job-plesso');
    if (!scelta.value) return;
    const nomeScuola = scelta.options[scelta.selectedIndex]?.text || 'la scuola selezionata';
    if (!await UI.conferma('Chiusura manuale: ' + nomeScuola,
        'Questa operazione è reale: può confermare i pasti, scalare i buoni e inviare solleciti. Se la giornata risulta già conclusa non ripete gli addebiti. In caso di errore precedente, controlla prima lo storico.',
        'Avvia chiusura')) return;
    try {
        await UI.esegui(e.currentTarget, async () => {
            const esito = await API.post('/api/job/plessi/' + scelta.value, {});
            mostraEsitoJob(esito);
            await aggiornaMonitoraggioJob();
        }, 'Elaborazione…');
    } catch (err) { avviso('Operazione non completata: ' + err.message, 'errore'); }
});

caricaPlessi().catch(e => avviso(e.message, 'errore'));
