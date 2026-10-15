/* Area catering: report pasti, pubblicazione menu, lettura feedback. */
const utente = proteggi('catering');
let riferimenti = { plessi: [], tipologie: [] };
let menuPubblicati = [];
let menuDisponibili = false;
let numeroRichiestaMenu = 0;
let numeroRichiestaVariazioni = 0;
let variazioniProgrammate = [];

/** Cutoff del plesso, sempre riverificato dal server al salvataggio. */
function menuDataSelezionata() {
    return document.getElementById('menu-data').value || oggi();
}
function menuModificabile(plesso, data) {
    if (!plesso || !/^\d{4}-\d{2}-\d{2}$/.test(data) || data < oggi()) return false;
    if (data > oggi()) return true;
    const limite = String(plesso.cutoff_orario || '').slice(0, 5);
    const adesso = new Date();
    const ora = String(adesso.getHours()).padStart(2, '0') + ':' +
        String(adesso.getMinutes()).padStart(2, '0');
    return !limite || ora < limite;
}

const fotoMenuGiorno = FotoMenu.crea('menu-foto-widget');

/** La chiave di pubblicazione e' data + plesso + tipologia, non l'intero elenco. */
function menuSelezionato() {
    const plessoId = document.getElementById('menu-plesso').value;
    const tipologiaId = document.getElementById('menu-tipologia').value;
    return menuPubblicati.find((m) =>
        String(m.plesso_id) === plessoId && String(m.tipologia_menu_id) === tipologiaId
    ) || null;
}

function sincronizzaMenuSelezionato() {
    const data = menuDataSelezionata();
    const plessoId = document.getElementById('menu-plesso').value;
    const tipologiaId = document.getElementById('menu-tipologia').value;
    const plesso = riferimenti.plessi.find((p) => String(p.id) === plessoId);
    const tipologia = riferimenti.tipologie.find((t) => String(t.id) === tipologiaId);
    const pulsante = document.getElementById('menu-submit');
    const annulla = document.getElementById('menu-ripristina');
    const stato = document.getElementById('menu-selezione-stato');
    const descrizione = document.getElementById('menu-descrizione');

    if (!menuDisponibili || !plesso || !tipologia) {
        pulsante.disabled = true;
        pulsante.textContent = 'Salva menu';
        annulla.hidden = true;
        annulla.disabled = true;
        stato.className = 'stato-operazione catering-menu-stato stato-operazione--info';
        stato.textContent = menuDisponibili
            ? 'Seleziona un plesso e una tipologia.'
            : 'Caricamento dei menu della data selezionata…';
        fotoMenuGiorno.imposta(null);
        return;
    }
    const esistente = menuSelezionato();
    const modificabile = menuModificabile(plesso, data);
    descrizione.value = esistente?.descrizione || '';
    // La foto del modello non viene mai ereditata nella nuova variazione.
    fotoMenuGiorno.imposta(esistente?.origine === 'settimanale' ? null : esistente?.foto_path || null);
    descrizione.disabled = !modificabile;
    pulsante.disabled = !modificabile;
    pulsante.textContent = esistente?.origine === 'giornaliero'
        ? 'Salva modifica' : 'Pubblica menu';
    annulla.hidden = esistente?.origine !== 'giornaliero';
    annulla.disabled = !modificabile;
    stato.className = 'stato-operazione catering-menu-stato stato-operazione--' +
        (!modificabile ? 'non-previsto' : 'info');
    const quando = dataIt(data);
    stato.textContent = !modificabile
        ? 'Menu del ' + quando + ' in sola lettura: cutoff superato o data passata.'
        : esistente?.origine === 'giornaliero'
            ? 'Variazione giornaliera già salvata per il ' + quando +
              '. Puoi modificarla o annullarla senza cambiare gli altri giorni.'
            : esistente?.origine === 'settimanale'
                ? 'Modello settimanale: ' + esistente.modello_nome + '. ' +
                  'Il salvataggio creerà una variazione solo per il ' + quando +
                  ', senza copiare automaticamente la foto del modello.'
                : 'Nessun menu per il ' + quando + ' · ' + plesso.nome + ' · ' + tipologia.nome +
                  '. Salverai solo questo giorno.';
}


abilitaSchede((vista) => {
    if (vista === 'menu') {
        caricaMenu().catch((e) => UI.stato('menu-stato', 'Non è possibile mostrare il menu: ' + e.message, 'errore'));
        caricaVariazioniProgrammate();
    }
    if (vista === 'settimane') caricaSettimane().catch((e) => UI.stato('settimana-stato', 'Non è possibile caricare i menu settimanali: ' + e.message, 'errore'));
    if (vista === 'feedback') caricaFeedback().catch((e) => UI.stato('feedback-stato', 'Non è possibile mostrare le valutazioni: ' + e.message, 'errore'));
});

let ultimaRichiestaReport = 0;

/** Quantita' effettive per menu: la pasta in bianco e' mostrata a parte. */
function voceDistribuzione(tipo) {
    const nomeMenu = tipo.menu_nome
        ? esc(tipo.menu_nome)
        : 'Menu non disponibile';
    return `<span class="catering-quantita">
        <strong>${Number(tipo.pasti_ordinari ?? tipo.pasti)}</strong>
        <span class="catering-quantita__testi">${esc(tipo.tipologia_nome)}
            <small class="catering-quantita__modello">${nomeMenu}</small>
        </span>
    </span>`;
}

/** I piatti del giorno sono consultabili una volta per scuola,
 * senza ripetere descrizioni lunghe in ogni classe. */
function dettaglioPiattiScuola(plesso) {
    const tipi = (plesso.tipologie || []).filter(t => Number(t.pasti_ordinari ?? t.pasti) > 0);
    if (!tipi.length) return '';
    return `<details class="catering-scuola__piatti">
        <summary>Piatti previsti per questa data</summary>
        <div class="catering-scuola__piatti-elenco">
            ${tipi.map(t => `<div class="catering-scuola__piatto">
                <strong>${esc(t.tipologia_nome)} · ${esc(t.menu_nome || 'Menu non disponibile')}</strong>
                <p>${t.menu_descrizione
                    ? esc(t.menu_descrizione)
                    : 'Nessun piatto descritto per questa data.'}</p>
            </div>`).join('')}
        </div>
    </details>`;
}

/** Mostra subito le tipologie principali, lasciando accessibili le altre. */
function ripartizioneCompatta(tipologie, limite = 3) {
    const tipi = (tipologie || [])
        .filter(t => Number(t.pasti_ordinari ?? t.pasti) > 0)
        .sort((a, b) => Number(b.pasti_ordinari ?? b.pasti) - Number(a.pasti_ordinari ?? a.pasti));
    const principali = tipi.slice(0, limite);
    const altre = tipi.slice(limite);
    const totaleAltre = altre.reduce((somma, t) => somma + Number(t.pasti_ordinari ?? t.pasti), 0);
    return `<div class="catering-ripartizione">
        ${principali.map(voceDistribuzione).join('')}
        ${altre.length ? `<details class="catering-ripartizione__altre">
            <summary>Altri ${altre.length} tipi (${totaleAltre} pasti)</summary>
            <div class="catering-ripartizione__dettagli">
                ${altre.map(voceDistribuzione).join('')}
            </div>
        </details>` : ''}
    </div>`;
}

/** Una riga per classe, leggibile insieme alle altre a colpo d'occhio. */
function rigaDistribuzioneClasse(classe) {
    const tipi = (classe.tipologie || []).filter(t => Number(t.pasti) > 0);
    const bianchi = tipi.filter(t => Number(t.pasta_in_bianco || 0) > 0);
    const ordinari = Number(classe.pasti_ordinari ?? classe.pasti);
    return `<li class="catering-classe" data-testid="report-classe-${Number(classe.classe_id)}">
        <div class="catering-classe__riga">
            <h4>${esc(classe.classe_nome)}</h4>
            <strong class="catering-classe__totale">${Number(classe.pasti)} <small>pasti</small></strong>
        </div>
        ${Number(classe.pasti) === 0
            ? '<p class="catering-classe__vuoto">Nessun pasto previsto</p>'
            : `${ordinari > 0 ? ripartizioneCompatta(tipi) : ''}
                ${bianchi.map(t => `<div class="catering-classe__bianco">
                    <strong>${Number(t.pasta_in_bianco)} pasta in bianco</strong>
                    <span class="catering-classe__bianco-tipo">${esc(t.tipologia_nome)}</span>
                    <span class="catering-classe__nomi">${(t.richieste_pasta || [])
                        .map(b => esc(b.cognome) + ' ' + esc(b.nome)).join(', ')}</span>
                </div>`).join('')}`}
    </li>`;
}

function schedaDistribuzioneScuola(plesso) {
    const totale = Number(plesso.totale_pasti);
    const bianchi = Number(plesso.totale_pasta_in_bianco || 0);
    const classi = plesso.sezioni || [];
    return `<article class="card-mensa catering-scuola" data-testid="report-plesso-${Number(plesso.plesso_id)}">
        <h3>${esc(plesso.plesso_nome)}</h3>
        <div class="catering-scuola__intestazione">
            <div>
                <strong class="saldo">${totale}</strong>
                <p class="nota">pasti previsti · modifiche fino alle ${esc(plesso.cutoff_orario)}</p>
            </div>
            <span class="badge-stato badge-${plesso.definitivo ? 'verde' : 'giallo'}">
                ${plesso.definitivo ? 'Conteggio confermato' : 'Conteggio provvisorio'}
            </span>
        </div>
        <p class="catering-scuola__sintesi">${totale - bianchi} da menu
            ${bianchi ? ` · <strong>${bianchi} pasta in bianco</strong>` : ''}</p>
        <div class="catering-scuola__tipologie">
            <h4>Tipologie e menu assegnati</h4>
            ${ripartizioneCompatta(plesso.tipologie)}
            ${dettaglioPiattiScuola(plesso)}
        </div>
        <h4 class="catering-scuola__titolo-classi">Pasti per classe o sezione</h4>
        <ul class="catering-scuola__classi">${classi.map(rigaDistribuzioneClasse).join('')}</ul>
    </article>`;
}

async function caricaReport() {
    const data = document.getElementById('report-data').value || oggi();
    const numeroRichiesta = ++ultimaRichiestaReport;
    const stato = document.getElementById('report-stato');
    const pulsante = document.getElementById('btn-report');
    const contenitore = document.getElementById('report');
    const riepilogo = document.getElementById('report-riepilogo');

    stato.textContent = 'Caricamento dei pasti del ' + dataIt(data) + '…';
    contenitore.innerHTML = '';
    riepilogo.innerHTML = '';
    pulsante.disabled = true;
    pulsante.textContent = 'Aggiornamento…';
    try {
        const report = await API.get('/api/catering/report?data=' + encodeURIComponent(data));
        if (numeroRichiesta !== ultimaRichiestaReport) return;
        contenitore.innerHTML = report.plessi.length
            ? report.plessi.map(schedaDistribuzioneScuola).join('')
            : '<p class="vuoto">Non sono previsti pasti per il ' + esc(dataIt(data)) + '.</p>';
        const pasta = report.plessi.reduce((n, p) => n + Number(p.totale_pasta_in_bianco || 0), 0);
        const confermati = report.plessi.filter(p => p.definitivo).length;
        const dato = (numero, etichetta) => '<div class="riepilogo-mensa__dato"><strong>' + numero +
            '</strong><span>' + etichetta + '</span></div>';
        riepilogo.innerHTML = dato(Number(report.totale_pasti || 0), 'Pasti da preparare') +
            dato(report.plessi.length, 'Scuole con mensa') +
            dato(pasta, 'Pasta in bianco') +
            dato(confermati + '/' + report.plessi.length, 'Conteggi confermati');
        stato.textContent = 'Pasti per il ' + dataIt(data) +
            ' · ' + Number(report.totale_pasti || 0) +
            ' pasti previsti · aggiornato alle ' + UI.oraAggiornamento();
    } catch (errore) {
        if (numeroRichiesta !== ultimaRichiestaReport) return;
        contenitore.innerHTML = '';
        riepilogo.innerHTML = '';
        stato.textContent = 'Impossibile caricare il report del ' +
            dataIt(data) + ': ' + errore.message;
        avviso('Errore aggiornamento report: ' + errore.message, 'errore');
    } finally {
        if (numeroRichiesta === ultimaRichiestaReport) {
            pulsante.disabled = false;
            pulsante.textContent = 'Aggiorna i pasti';
        }
    }
}

async function caricaMenu() {
    const richiesta = ++numeroRichiestaMenu;
    const data = menuDataSelezionata();
    menuDisponibili = false;
    sincronizzaMenuSelezionato();
    UI.stato('menu-stato', 'Caricamento dei menu del ' + dataIt(data) + '…', 'caricamento');
    try {
        const menu = await API.get('/api/catering/menu?data=' + encodeURIComponent(data));
        if (richiesta !== numeroRichiestaMenu || menuDataSelezionata() !== data) return;
        menuPubblicati = menu;
        menuDisponibili = true;
        sincronizzaMenuSelezionato();
        UI.stato('menu-stato', menu.length + ' menu previsti per il ' + dataIt(data) +
            ' · aggiornato alle ' + UI.oraAggiornamento(), 'ok');
        document.getElementById('menu-lista').innerHTML = menu.length === 0
            ? '<p class="vuoto">Nessun menu previsto per questa data.</p>'
            : menu.map((m) => {
                const p = riferimenti.plessi.find(p => Number(p.id) === Number(m.plesso_id));
                const attivo = menuModificabile(p, data);
                return `<article class="card-mensa catering-menu-card" data-testid="menu-card-${esc(m.id)}">
                    <h3>${esc(m.plesso_nome)}</h3>
                    <p class="catering-menu-card__tipo">${esc(m.tipologia_nome)}</p>
                    ${m.origine === 'settimanale'
                        ? `<span class="badge-stato badge-neutro">Da menu settimanale: ${esc(m.modello_nome)}</span>`
                        : '<span class="badge-stato badge-neutro">Variazione giornaliera</span>'}
                    <p class="menu-descrizione">${esc(m.descrizione || 'Solo foto pubblicata')}</p>
                    ${m.foto_path ? `<img src="${BASE}/storage/${esc(m.foto_path)}" alt="Foto del menu">` : ''}
                    ${attivo ? `<button type="button" class="btn btn-outline-primary"
                        data-menu-modifica="${esc(m.id)}">Modifica menu</button>` : ''}
                </article>`;
            }).join('');
        document.querySelectorAll('[data-menu-modifica]').forEach(pulsante => {
            pulsante.addEventListener('click', () => {
                const scelto = menuPubblicati.find(m => String(m.id) === pulsante.dataset.menuModifica);
                if (!scelto) return;
                document.getElementById('menu-plesso').value = String(scelto.plesso_id);
                document.getElementById('menu-tipologia').value = String(scelto.tipologia_menu_id);
                sincronizzaMenuSelezionato();
                document.getElementById('form-menu').scrollIntoView?.({
                    behavior: 'smooth', block: 'start'
                });
                document.getElementById('menu-descrizione').focus();
            });
        });
    } catch (errore) {
        if (richiesta !== numeroRichiestaMenu) return;
        menuDisponibili = false;
        sincronizzaMenuSelezionato();
        UI.stato('menu-stato', 'Non è possibile caricare i menu: ' + errore.message, 'errore');
        throw errore;
    }
}

/** Elenco breve e cronologico delle sole eccezioni; un clic apre il giorno. */
async function caricaVariazioniProgrammate() {
    const richiesta = ++numeroRichiestaVariazioni;
    const stato = document.getElementById('menu-variazioni-stato');
    stato.textContent = 'Caricamento delle variazioni…';
    try {
        const righe = await API.get('/api/catering/menu/variazioni');
        if (richiesta !== numeroRichiestaVariazioni) return;
        variazioniProgrammate = righe;
        stato.textContent = righe.length + ' variazioni da oggi in poi.';
        document.getElementById('menu-variazioni-lista').innerHTML = righe.length
            ? righe.map(m => `<article class="catering-variazione" data-testid="variazione-${Number(m.id)}">
                <div class="catering-variazione__info">
                    <strong>${esc(dataIt(m.data))} · ${esc(m.plesso_nome)}</strong>
                    <span>${esc(m.tipologia_nome)} — ${esc(m.descrizione || 'Solo foto')}</span>
                    ${!m.modificabile ? '<small>Non più modificabile</small>' : ''}
                </div>
                ${m.modificabile ? `<div class="catering-variazione__azioni">
                    <button type="button" class="btn btn-outline-primary" data-variazione-apri="${Number(m.id)}">Modifica</button>
                    <button type="button" class="btn btn-link" data-variazione-annulla="${Number(m.id)}">Annulla</button>
                </div>` : ''}
            </article>`).join('')
            : '<p class="vuoto">Nessuna variazione giornaliera programmata.</p>';
        document.querySelectorAll('[data-variazione-apri]').forEach(pulsante =>
            pulsante.addEventListener('click', async () => {
                const m = variazioniProgrammate.find(r => Number(r.id) === Number(pulsante.dataset.variazioneApri));
                if (!m || !m.modificabile) return;
                document.getElementById('menu-data').value = m.data;
                document.getElementById('menu-plesso').value = String(m.plesso_id);
                document.getElementById('menu-tipologia').value = String(m.tipologia_menu_id);
                await caricaMenu();
                document.getElementById('form-menu').scrollIntoView?.({behavior: 'smooth', block: 'start'});
                document.getElementById('menu-descrizione').focus();
            }));
        document.querySelectorAll('[data-variazione-annulla]').forEach(pulsante =>
            pulsante.addEventListener('click', () => {
                const m = variazioniProgrammate.find(r => Number(r.id) === Number(pulsante.dataset.variazioneAnnulla));
                if (m?.modificabile) annullaVariazione(m);
            }));
    } catch (errore) {
        if (richiesta !== numeroRichiestaVariazioni) return;
        stato.textContent = 'Impossibile caricare le variazioni: ' + errore.message;
    }
}

async function annullaVariazione(m) {
    const giorno = m.data;
    if (!await UI.conferma(
        'Annulla variazione giornaliera',
        'Annullare il menu ' + m.tipologia_nome + ' del plesso ' + m.plesso_nome +
        ' per il ' + dataIt(giorno) + '? ' +
        (m.ha_modello_settimanale ? 'Tornerà il modello settimanale.' :
            'Non resterà alcun menu per questa combinazione.') +
        ' Gli altri giorni non cambieranno.',
        'Annulla variazione'
    )) return;
    try {
        await API.post('/api/catering/menu/annulla', {
            plesso_id: Number(m.plesso_id), tipologia_menu_id: Number(m.tipologia_menu_id),
            data: giorno
        });
        avviso('Variazione del ' + dataIt(giorno) + ' annullata.', 'ok');
        await Promise.all([caricaVariazioniProgrammate(), caricaMenu()]);
    } catch (errore) {
        avviso('Impossibile annullare la variazione: ' + errore.message, 'errore');
    }
}

async function caricaFeedback() {
    UI.stato('feedback-stato', 'Caricamento delle valutazioni…', 'caricamento');
    const parametri = new URLSearchParams();
    const da = document.getElementById('fb-da').value;
    const a = document.getElementById('fb-a').value;
    if (da) parametri.set('data_da', da);
    if (a) parametri.set('data_a', a);
    if (document.getElementById('fb-plesso').value) parametri.set('plesso_id', document.getElementById('fb-plesso').value);
    if (document.getElementById('fb-tipologia').value) parametri.set('tipologia_id', document.getElementById('fb-tipologia').value);
    const elenco = await API.get('/api/catering/feedback?' + parametri.toString());
    UI.stato('feedback-stato', elenco.length + ' valutazioni trovate · aggiornato alle ' + UI.oraAggiornamento(), 'ok');
    document.getElementById('feedback-corpo').innerHTML = elenco.map((f) => `
        <tr><td>${dataIt(f.data)}</td><td>${esc(f.alunno_iniziali)}</td><td>${esc(f.plesso_nome || '-')}</td>
        <td>${esc(f.classe_nome || '-')}</td><td>${esc(f.tipologia_nome)}</td>
        <td>${f.voto ? '★'.repeat(f.voto) : '-'}</td><td>${esc(f.commento || '')}</td></tr>`).join('')
        || '<tr><td colspan="7" class="vuoto">Nessun feedback per i filtri selezionati.</td></tr>';
}

document.getElementById('menu-plesso').addEventListener('change', sincronizzaMenuSelezionato);
document.getElementById('menu-tipologia').addEventListener('change', sincronizzaMenuSelezionato);

document.getElementById('form-menu').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!menuDisponibili) {
        avviso('Attendi il caricamento dei menu prima di procedere.', 'errore');
        return;
    }
    const plessoId = document.getElementById('menu-plesso').value;
    const tipologiaId = document.getElementById('menu-tipologia').value;
    const plesso = riferimenti.plessi.find((p) => String(p.id) === plessoId);
    const tipologia = riferimenti.tipologie.find((t) => String(t.id) === tipologiaId);
    if (!plesso || !tipologia) {
        avviso('Seleziona plesso e tipologia prima di pubblicare.', 'errore');
        return;
    }
    const esistente = menuSelezionato();
    const rimuoviFoto = fotoMenuGiorno.rimuovere();
    const nuovaFoto = fotoMenuGiorno.file();
    const messaggioFoto = rimuoviFoto ? 'La foto verrà rimossa.'
        : nuovaFoto ? 'La nuova foto verrà pubblicata.'
        : esistente?.origine === 'settimanale'
            ? 'La foto del modello settimanale non verrà copiata.'
            : fotoMenuGiorno.haFoto()
                ? 'La foto giornaliera già pubblicata rimarrà invariata.'
                : 'Il menu rimarrà senza foto.';
    if (esistente && !await UI.conferma(
        'Aggiorna solo questo menu',
        'Stai per aggiornare il menu ' + tipologia.nome + ' del plesso ' + plesso.nome +
            ' per oggi. Gli altri menu resteranno invariati. ' + messaggioFoto,
        'Aggiorna menu'
    )) return;
    // Ripristina il blocco in caso di selezione cambiata durante il dialogo.
    if (document.getElementById('menu-plesso').value !== plessoId ||
        document.getElementById('menu-tipologia').value !== tipologiaId ||
        menuSelezionato()?.id !== esistente?.id ||
        fotoMenuGiorno.rimuovere() !== rimuoviFoto) {
        avviso('La selezione è cambiata: controlla il menu prima di salvare.', 'errore');
        return;
    }
    const descrizione = document.getElementById('menu-descrizione').value.trim();
    const foto = fotoMenuGiorno.file();
    if (!descrizione && !fotoMenuGiorno.haFoto()) {
        avviso('Inserisci una descrizione oppure seleziona una foto.', 'errore');
        document.getElementById('menu-descrizione').focus();
        return;
    }
    const fd = new FormData();
    fd.append('plesso_id', plessoId);
    fd.append('tipologia_menu_id', tipologiaId);
    fd.append('data', oggi());
    fd.append('descrizione', descrizione);
    fd.append('rimuovi_foto', rimuoviFoto ? '1' : '0');
    if (foto) fd.append('foto', foto);
    const pulsante = document.getElementById('menu-submit');
    try {
        await UI.esegui(pulsante, async () => {
            const risposta = await fetch(BASE + '/api/catering/menu', {
                method: 'POST',
                headers: { Authorization: 'Bearer ' + Sessione.token() },
                body: fd
            });
            const dati = await risposta.json();
            if (!risposta.ok || dati.ok === false) throw new Error(dati.errore || 'Errore di salvataggio');
        }, 'Salvataggio…');
        avviso(esistente
            ? 'Menu ' + tipologia.nome + ' aggiornato. Gli altri menu sono invariati.'
            : 'Menu ' + tipologia.nome + ' pubblicato.', 'ok');
        await caricaMenu();
    } catch (err) {
        avviso('Non è stato possibile completare il salvataggio: ' + err.message, 'errore');
    }
});

document.getElementById('menu-ripristina').addEventListener('click', async () => {
    if (!menuDisponibili) return;
    const esistente = menuSelezionato();
    if (!esistente || esistente.origine !== 'giornaliero' || !esistente.ha_modello_settimanale) return;
    const plessoId = String(esistente.plesso_id);
    const tipologiaId = String(esistente.tipologia_menu_id);
    const confermato = await UI.conferma(
        'Ripristina menu settimanale',
        'Vuoi eliminare la variazione giornaliera per ' + esistente.plesso_nome +
            ' · ' + esistente.tipologia_nome + '? Torneranno la descrizione e la foto del menu settimanale. ' +
            'Le altre giornate e gli altri plessi non cambieranno.',
        'Ripristina menu'
    );
    if (!confermato) return;
    if (document.getElementById('menu-plesso').value !== plessoId ||
        document.getElementById('menu-tipologia').value !== tipologiaId ||
        menuSelezionato()?.id !== esistente.id) {
        avviso('La selezione è cambiata: ricontrolla il menu prima di ripristinarlo.', 'errore');
        return;
    }
    const pulsante = document.getElementById('menu-ripristina');
    try {
        await UI.esegui(pulsante, () => API.post('/api/catering/menu/ripristina', {
            plesso_id: Number(plessoId), tipologia_menu_id: Number(tipologiaId), data: oggi()
        }), 'Ripristino…');
        avviso('Variazione giornaliera rimossa. È di nuovo attivo il menu settimanale.', 'ok');
        await caricaMenu();
    } catch (errore) {
        avviso('Ripristino non riuscito: ' + errore.message, 'errore');
    }
});

document.getElementById('btn-report').addEventListener('click', caricaReport);
// Aggiorna anche scegliendo una data diversa dal calendario.
document.getElementById('report-data').addEventListener('change', caricaReport);
document.getElementById('btn-feedback').addEventListener('click', () => caricaFeedback().catch((e) => UI.stato('feedback-stato', e.message, 'errore')));

(async () => {
    // Imposta subito la data, prima della richiesta iniziale dei riferimenti:
    // una scelta manuale dell'utente non deve essere sovrascritta all'avvio.
    const campoDataReport = document.getElementById('report-data');
    if (!campoDataReport.value) campoDataReport.value = oggi();
    await caricaReport();
    riferimenti = await API.get('/api/riferimenti');
    const opzPlessi = '<option value="">Tutti</option>' + riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    const opzTipologie = '<option value="">Tutte</option>' + riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
    document.getElementById('fb-plesso').innerHTML = opzPlessi;
    document.getElementById('fb-tipologia').innerHTML = opzTipologie;
    document.getElementById('menu-plesso').innerHTML = riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    document.getElementById('menu-tipologia').innerHTML = riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
    sincronizzaMenuSelezionato();
    document.getElementById('fb-da').value = new Date(Date.now() - 14 * 86400000).toISOString().slice(0, 10);
    document.getElementById('fb-a').value = oggi();
})().catch((e) => avviso(e.message, 'errore'));
