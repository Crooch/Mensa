/* Client API condiviso: unico punto di accesso al layer REST.
   Il token e' salvato in localStorage (e in un cookie per servire le immagini). */
const BASE = document.body.dataset.base || '';
const CHIAVE_TOKEN = 'mensa_token';
const CHIAVE_UTENTE = 'mensa_utente';

const Sessione = {
    token: () => localStorage.getItem(CHIAVE_TOKEN),
    utente: () => JSON.parse(localStorage.getItem(CHIAVE_UTENTE) || 'null'),
    salva(token, utente) {
        localStorage.setItem(CHIAVE_TOKEN, token);
        localStorage.setItem(CHIAVE_UTENTE, JSON.stringify(utente));
        document.cookie = `mensa_token=${token}; path=/; SameSite=Lax`;
    },
    pulisci() {
        localStorage.removeItem(CHIAVE_TOKEN);
        localStorage.removeItem(CHIAVE_UTENTE);
        document.cookie = 'mensa_token=; path=/; Max-Age=0';
    }
};

const PAGINE_RUOLO = {
    admin: '/admin', operatore: '/operatore', scuola: '/scuola',
    catering: '/catering', genitore: '/genitore'
};

async function chiamata(percorso, opzioni = {}) {
    const intestazioni = { Accept: 'application/json' };
    const token = Sessione.token();
    if (token) intestazioni.Authorization = `Bearer ${token}`;
    if (opzioni.corpo !== undefined && !(opzioni.corpo instanceof FormData)) {
        intestazioni['Content-Type'] = 'application/json';
    }
    let risposta;
    try {
        risposta = await fetch(BASE + percorso, {
            method: opzioni.metodo || 'GET',
            headers: intestazioni,
            body: opzioni.corpo instanceof FormData ? opzioni.corpo
                : (opzioni.corpo !== undefined ? JSON.stringify(opzioni.corpo) : undefined)
        });
    } catch (errore) {
        throw new Error('Impossibile collegarsi al servizio. Controlla la connessione e riprova.');
    }
    let dati = null;
    try { dati = await risposta.json(); } catch (e) { dati = null; }
    if (risposta.status === 401 && percorso !== '/api/auth/login') {
        Sessione.pulisci();
        window.location.href = BASE + '/login';
        throw new Error('Sessione scaduta');
    }
    if (!risposta.ok || (dati && dati.ok === false)) {
        throw new Error((dati && dati.errore) || `Errore ${risposta.status}`);
    }
    return dati ? dati.dati : null;
}

const API = {
    get: (p) => chiamata(p),
    post: (p, corpo) => chiamata(p, { metodo: 'POST', corpo }),
    put: (p, corpo) => chiamata(p, { metodo: 'PUT', corpo }),
    del: (p) => chiamata(p, { metodo: 'DELETE' })
};

function avviso(messaggio, tipo = 'ok') {
    const contenitore = document.getElementById('avvisi');
    if (!contenitore) { alert(messaggio); return; }
    const nodo = document.createElement('div');
    nodo.className = `avviso avviso--${tipo}`;
    nodo.dataset.testid = 'avviso';
    nodo.textContent = messaggio;
    contenitore.appendChild(nodo);
    setTimeout(() => nodo.remove(), 5000);
}

/** Protegge la pagina: verifica token e ruolo, popola la navbar. */
function proteggi(ruoloAtteso) {
    const utente = Sessione.utente();
    if (!Sessione.token() || !utente) {
        window.location.href = BASE + '/login';
        return null;
    }
    if (ruoloAtteso && utente.ruolo !== ruoloAtteso && !(ruoloAtteso === 'operatore' && utente.ruolo === 'admin')) {
        window.location.href = BASE + (PAGINE_RUOLO[utente.ruolo] || '/login');
        return null;
    }
    const etichetta = document.getElementById('utente-corrente');
    if (etichetta) etichetta.textContent = `${utente.nome_completo || utente.username} (${utente.ruolo})`;
    const uscita = document.getElementById('btn-logout');
    if (uscita) uscita.addEventListener('click', async () => {
        try { await API.post('/api/auth/logout', {}); } catch (e) { /* token gia' invalido */ }
        Sessione.pulisci();
        window.location.href = BASE + '/login';
    });
    return utente;
}

/** Gestione delle schede (tab) comuni a tutte le aree. */
function abilitaSchede(alCambio) {
    document.querySelectorAll('.scheda').forEach((bottone) => {
        bottone.addEventListener('click', () => {
            document.querySelectorAll('.scheda').forEach((b) => b.classList.remove('attiva'));
            bottone.classList.add('attiva');
            document.querySelectorAll('.vista').forEach((v) => { v.hidden = true; });
            const vista = document.getElementById('vista-' + bottone.dataset.vista);
            if (vista) vista.hidden = false;
            if (alCambio) alCambio(bottone.dataset.vista);
        });
    });
}

// La data operativa deve seguire il calendario locale (Europe/Rome):
// toISOString() usa UTC e nelle prime ore del mattino puo' restituire ieri.
const oggi = () => {
    const data = new Date();
    const anno = data.getFullYear();
    const mese = String(data.getMonth() + 1).padStart(2, '0');
    const giorno = String(data.getDate()).padStart(2, '0');
    return `${anno}-${mese}-${giorno}`;
};
const euro = (v) => (v === null || v === undefined ? '-' : Number(v).toFixed(2).replace('.', ',') + ' €');

/** Testi per le persone: i codici tecnici delle API non devono apparire nelle schermate. */
const nomeMovimento = (tipo) => ({
    acquisto: 'Acquisto di buoni',
    consumo_pasto: 'Pasto utilizzato',
    rettifica_manuale: 'Correzione dei buoni'
}[tipo] || String(tipo || '').replaceAll('_', ' '));
const nomeRuolo = (ruolo) => ({
    admin: 'Amministratore',
    operatore: 'Operatore comunale',
    scuola: 'Personale della scuola',
    catering: 'Addetto alla mensa',
    genitore: 'Genitore'
}[ruolo] || String(ruolo || ''));
const situazioneBuoni = (stato) => ({
    verde: 'Regolare', giallo: 'Saldo da controllare', rosso: 'Ricarica consigliata'
}[stato] || String(stato || ''));
const esitoOperazione = (stato) => ({
    ok: 'Completato', errore: 'Errore', in_corso: 'In corso', inviato: 'Inviato',
    fallito: 'Non inviato'
}[stato] || String(stato || '').replaceAll('_', ' '));
const dataIt = (v) => (v ? v.split('-').reverse().join('/') : '-');
const esc = (v) => (v === null || v === undefined ? '' : String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])));
const badgeStato = (stato) => `<span class="badge-stato badge-${stato === 'presente' ? 'verde' : stato === 'assente' ? 'rosso' : 'neutro'}">${esc(stato || '-')}</span>`;
