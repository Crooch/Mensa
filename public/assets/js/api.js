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

/** Notifiche visibili, con chiusura manuale e senza perdere gli errori. */
function avviso(messaggio, tipo = 'ok') {
    const contenitore = document.getElementById('avvisi');
    if (!contenitore) { alert(messaggio); return; }
    const nodo = document.createElement('div');
    nodo.className = 'avviso avviso--' + tipo;
    nodo.dataset.testid = 'avviso';
    nodo.setAttribute('role', tipo === 'errore' ? 'alert' : 'status');
    const testo = document.createElement('span');
    testo.className = 'avviso__testo';
    testo.textContent = messaggio;
    nodo.appendChild(testo);
    const chiudi = document.createElement('button');
    chiudi.type = 'button';
    chiudi.className = 'avviso__chiudi';
    chiudi.setAttribute('aria-label', 'Chiudi messaggio');
    // La X è disegnata con CSS, non con il carattere tipografico ×:
    // resta centrata indipendentemente dal font e dallo zoom del browser.
    chiudi.addEventListener('click', () => nodo.remove());
    nodo.appendChild(chiudi);
    contenitore.appendChild(nodo);
    if (tipo !== 'errore') setTimeout(() => nodo.remove(), 8500);
}

/** Componenti condivisi fra tutti i ruoli, senza librerie aggiuntive. */
const UI = {
    // Un errore resta a fianco del modulo finche' non si tenta nuovamente.
    stato(id, messaggio, tipo = 'info') {
        const nodo = typeof id === 'string' ? document.getElementById(id) : id;
        if (!nodo) return;
        nodo.textContent = messaggio;
        nodo.className = 'stato-operazione stato-operazione--' + tipo;
        nodo.setAttribute('role', tipo === 'errore' ? 'alert' : 'status');
        nodo.hidden = !messaggio;
    },
    async esegui(pulsante, azione, testo = 'Salvataggio…') {
        if (pulsante?.disabled) return;
        const originale = pulsante?.textContent;
        if (pulsante) {
            pulsante.disabled = true;
            pulsante.setAttribute('aria-busy', 'true');
            pulsante.textContent = testo;
        }
        try {
            return await azione();
        } finally {
            if (pulsante) {
                pulsante.disabled = false;
                pulsante.removeAttribute('aria-busy');
                pulsante.textContent = originale;
            }
        }
    },
    /**
     * Conferma contestuale accessibile. Restituisce true o false.
     * Usare solo in risposta a una interazione esplicita.
     */
    conferma(titolo, messaggio, etichetta = 'Conferma') {
        return new Promise((resolve) => {
            const finestra = document.createElement('dialog');
            finestra.className = 'conferma-dialogo';
            finestra.setAttribute('aria-label', titolo);
            finestra.innerHTML = '<h2></h2><p></p><div class="conferma-dialogo__azioni">' +
                '<button type="button" class="btn btn-outline-primary" data-esito="no">Annulla</button>' +
                '<button type="button" class="btn btn-primary" data-esito="si"></button></div>';
            finestra.querySelector('h2').textContent = titolo;
            finestra.querySelector('p').textContent = messaggio;
            finestra.querySelector('[data-esito="si"]').textContent = etichetta;
            document.body.appendChild(finestra);
            let finito = false;
            const termina = (valore) => {
                if (finito) return;
                finito = true;
                finestra.close();
                finestra.remove();
                resolve(valore);
            };
            finestra.querySelector('[data-esito="no"]').addEventListener('click', () => termina(false));
            finestra.querySelector('[data-esito="si"]').addEventListener('click', () => termina(true));
            finestra.addEventListener('cancel', (e) => { e.preventDefault(); termina(false); });
            finestra.showModal();
            finestra.querySelector('[data-esito="no"]').focus();
        });
    },
    /**
     * Motivazione obbligatoria in un modale accessibile e riutilizzabile.
     * Non altera i dati: restituisce il testo validato o null se annullata.
     */
    chiediMotivazione(titolo, messaggio = 'Indica il motivo della modifica.') {
        return new Promise((resolve) => {
            const finestra = document.createElement('dialog');
            finestra.className = 'motivo-dialogo';
            finestra.setAttribute('aria-label', titolo);
            finestra.innerHTML =
                '<form novalidate>' +
                '<div class="motivo-dialogo__testa"><h2></h2>' +
                '<button type="button" class="motivo-dialogo__chiudi" aria-label="Chiudi finestra">×</button></div>' +
                '<p class="nota motivo-dialogo__testo"></p>' +
                '<label class="motivo-dialogo__etichetta" for="motivo-testo">Motivazione <span aria-hidden="true">*</span></label>' +
                '<textarea id="motivo-testo" rows="3" maxlength="255" required ' +
                'placeholder="Es. Non si è presentato" data-testid="motivo-testo"></textarea>' +
                '<p class="motivo-dialogo__errore" role="alert" hidden>Inserisci una motivazione (massimo 255 caratteri).</p>' +
                '<div class="motivo-dialogo__azioni">' +
                '<button type="button" class="btn btn-outline-primary" data-annulla>Annulla</button>' +
                '<button type="submit" class="btn btn-primary" data-conferma>Conferma modifica</button>' +
                '</div></form>';
            finestra.querySelector('h2').textContent = titolo;
            finestra.querySelector('.motivo-dialogo__testo').textContent = messaggio;
            const campo = finestra.querySelector('textarea');
            const errore = finestra.querySelector('.motivo-dialogo__errore');
            document.body.appendChild(finestra);
            let chiusa = false;
            const termina = (valore) => {
                if (chiusa) return;
                chiusa = true;
                finestra.close();
                finestra.remove();
                resolve(valore);
            };
            finestra.querySelector('[data-annulla]').addEventListener('click', () => termina(null));
            finestra.querySelector('.motivo-dialogo__chiudi').addEventListener('click', () => termina(null));
            finestra.addEventListener('cancel', (e) => { e.preventDefault(); termina(null); });
            finestra.querySelector('form').addEventListener('submit', (e) => {
                e.preventDefault();
                const motivazione = campo.value.trim();
                if (!motivazione || motivazione.length > 255) {
                    errore.hidden = false;
                    campo.setAttribute('aria-invalid', 'true');
                    campo.focus();
                    return;
                }
                termina(motivazione);
            });
            campo.addEventListener('input', () => {
                errore.hidden = true;
                campo.removeAttribute('aria-invalid');
            });
            finestra.showModal();
            campo.focus();
        });
    },
    oraAggiornamento() {
        return new Date().toLocaleTimeString('it-IT', {hour:'2-digit', minute:'2-digit'});
    }
};

/**
 * I filtri storici avevano etichette e campi come elementi separati dello
 * stesso flex: su desktop le etichette lunghe occupavano tutta la riga.
 * Accoppiandoli nella stessa cella ogni campo mantiene la sua etichetta
 * e puo' andare a capo senza spingere fuori gli altri elementi.
 */
function raggruppaCampi(radice = document) {
    radice.querySelectorAll('.riga-filtri').forEach((riga) => {
        Array.from(riga.children).forEach((etichetta) => {
            if (etichetta.tagName !== 'LABEL') return;
            // Le caselle di selezione sono gia' racchiuse nella propria label.
            if (etichetta.querySelector('input, select, textarea')) return;
            const controllo = etichetta.nextElementSibling;
            if (!controllo || !['INPUT', 'SELECT', 'TEXTAREA'].includes(controllo.tagName)) return;
            if (etichetta.htmlFor && controllo.id !== etichetta.htmlFor) return;
            const campo = document.createElement('div');
            campo.className = 'campo-filtro';
            riga.insertBefore(campo, etichetta);
            campo.appendChild(etichetta);
            campo.appendChild(controllo);
        });
    });
}
raggruppaCampi();

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
        bottone.setAttribute('role', 'tab');
        bottone.setAttribute('aria-controls', 'vista-' + bottone.dataset.vista);
        bottone.setAttribute('aria-selected', bottone.classList.contains('attiva') ? 'true' : 'false');
        bottone.tabIndex = bottone.classList.contains('attiva') ? 0 : -1;
        bottone.addEventListener('keydown', (evento) => {
            const schede = Array.from(document.querySelectorAll('.scheda'));
            const indice = schede.indexOf(bottone);
            let prossimo = indice;
            if (evento.key === 'ArrowRight') prossimo = (indice + 1) % schede.length;
            else if (evento.key === 'ArrowLeft') prossimo = (indice + schede.length - 1) % schede.length;
            else if (evento.key === 'Home') prossimo = 0;
            else if (evento.key === 'End') prossimo = schede.length - 1;
            else return;
            evento.preventDefault();
            schede[prossimo].click();
            schede[prossimo].focus();
        });
        bottone.addEventListener('click', () => {
            document.querySelectorAll('.scheda').forEach((b) => {
                b.classList.remove('attiva');
                b.setAttribute('aria-selected', 'false');
                b.tabIndex = -1;
            });
            bottone.classList.add('attiva');
            bottone.setAttribute('aria-selected', 'true');
            bottone.tabIndex = 0;
            document.querySelectorAll('.vista').forEach((v) => { v.hidden = true; });
            const vista = document.getElementById('vista-' + bottone.dataset.vista);
            if (vista) {
                vista.hidden = false;
                vista.setAttribute('role', 'tabpanel');
                vista.setAttribute('aria-label', bottone.textContent.trim());
            }
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
const fidoConcesso = (v) => Number(v) === 0 ? '0 buoni' : '-' + Math.abs(Number(v)) + ' buoni';

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
