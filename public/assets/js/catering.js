/* Area catering: report pasti, pubblicazione menu, lettura feedback. */
const utente = proteggi('catering');
let riferimenti = { plessi: [], tipologie: [] };
let menuPubblicati = [];
let menuDisponibili = false;
let numeroRichiestaMenu = 0;
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
    const plessoId = document.getElementById('menu-plesso').value;
    const tipologiaId = document.getElementById('menu-tipologia').value;
    const plesso = riferimenti.plessi.find((p) => String(p.id) === plessoId);
    const tipologia = riferimenti.tipologie.find((t) => String(t.id) === tipologiaId);
    const pulsante = document.getElementById('menu-submit');
    const stato = document.getElementById('menu-selezione-stato');
    const descrizione = document.getElementById('menu-descrizione');

    if (!menuDisponibili || !plesso || !tipologia) {
        pulsante.disabled = true;
        pulsante.textContent = 'Pubblica menu';
        stato.className = 'stato-operazione catering-menu-stato stato-operazione--info';
        stato.textContent = menuDisponibili
            ? 'Seleziona un plesso e una tipologia di menu.'
            : 'Caricamento dei menu pubblicati…';
        fotoMenuGiorno.imposta(null);
        return;
    }
    const esistente = menuSelezionato();
    // Cambiando plesso o tipologia, nessun testo del precedente menu
    // deve rimanere nel campo della nuova pubblicazione.
    descrizione.value = esistente?.descrizione || '';
    // Un'eccezione giornaliera non deve proporre come propria la foto del modello.
    // Il file settimanale resta visibile nella scheda del menu, non nella bozza.
    fotoMenuGiorno.imposta(esistente?.origine === 'settimanale' ? null : esistente?.foto_path || null);
    pulsante.disabled = false;
    pulsante.textContent = esistente ? 'Aggiorna menu' : 'Pubblica menu';
    stato.className = 'stato-operazione catering-menu-stato stato-operazione--' +
        (esistente ? 'info' : 'non-previsto');
    stato.textContent = esistente
        ? (esistente.origine === 'settimanale'
            ? 'Menu previsto dal modello settimanale per ' + plesso.nome + ' · ' + tipologia.nome +
              '. Salvando creerai una modifica valida soltanto per oggi. La foto del modello non verrà copiata automaticamente.'
            : 'Menu già pubblicato per ' + plesso.nome + ' · ' + tipologia.nome +
              ' (oggi). Aggiornerai solo questo menu: gli altri non cambiano.')
        : 'Nessun menu ancora pubblicato per ' + plesso.nome + ' · ' + tipologia.nome +
          ' (oggi). Verrà creato solo questo menu.';

}


abilitaSchede((vista) => {
    if (vista === 'menu') caricaMenu().catch((e) => UI.stato('menu-stato', 'Non è possibile mostrare il menu: ' + e.message, 'errore'));
    if (vista === 'settimane') caricaSettimane().catch((e) => UI.stato('settimana-stato', 'Non è possibile caricare i menu settimanali: ' + e.message, 'errore'));
    if (vista === 'feedback') caricaFeedback().catch((e) => UI.stato('feedback-stato', 'Non è possibile mostrare le valutazioni: ' + e.message, 'errore'));
});

let ultimaRichiestaReport = 0;

async function caricaReport() {
    const data = document.getElementById('report-data').value || oggi();
    const numeroRichiesta = ++ultimaRichiestaReport;
    const stato = document.getElementById('report-stato');
    const pulsante = document.getElementById('btn-report');
    const contenitore = document.getElementById('report');
    const riepilogo = document.getElementById('report-riepilogo');

    // Eventuali richieste precedenti possono arrivare dopo l'ultima selezione:
    // solo la piu' recente puo' modificare la pagina.
    stato.textContent = 'Caricamento dei pasti del ' + dataIt(data) + '…';
    riepilogo.innerHTML = '';
    pulsante.disabled = true;
    pulsante.textContent = 'Aggiornamento…';
    try {
        const report = await API.get('/api/catering/report?data=' + encodeURIComponent(data));
        if (numeroRichiesta !== ultimaRichiestaReport) return;
        contenitore.innerHTML = report.plessi.length === 0
            ? '<p class="vuoto">Non sono previsti pasti per il ' + esc(dataIt(data)) + '. Controlla il giorno scelto.</p>'
            : report.plessi.map((p) => `
                <article class="card-mensa" data-testid="report-plesso-${p.plesso_id}">
                    <h3>${esc(p.plesso_nome)}</h3>
                    <span class="saldo">${p.totale_pasti}</span>
                    <p class="nota">pasti previsti · puoi ricevere modifiche fino alle ${esc(p.cutoff_orario)}</p>
                    <span class="badge-stato badge-${p.definitivo ? 'verde' : 'giallo'}">${p.definitivo ? 'Conteggio confermato' : 'Conteggio provvisorio'}</span>
                    <h4>Pasti per tipo di menu</h4>
                    <dl>${p.tipologie.map((t) => `<dt>${esc(t.tipologia_nome)}</dt><dd data-testid="report-tipologia-${p.plesso_id}-${t.tipologia_id}">${t.pasti}</dd>`).join('')}</dl>
                    <h4>Pasti per classe o sezione</h4>
                    <dl>${(p.sezioni || []).map((c) => `<dt>${esc(c.classe_nome)}</dt><dd data-testid="report-classe-${c.classe_id}">${c.pasti}</dd>`).join('')}</dl>
                    <h4>Richieste di pasta in bianco: ${Number(p.totale_pasta_in_bianco || 0)}</h4>
                    ${(p.pasta_in_bianco || []).length ? `
                        <ul>${p.pasta_in_bianco.map((b) =>
                            `<li>${esc(b.cognome)} ${esc(b.nome)} — ${esc(b.classe_nome)}</li>`
                        ).join('')}</ul>` : '<p class="nota">Nessuna richiesta per questa giornata.</p>'}
                </article>`).join('');
        const pasta = report.plessi.reduce((n, p) => n + Number(p.totale_pasta_in_bianco || 0), 0);
        const definitivi = report.plessi.filter((p) => p.definitivo).length;
        const dato = (numero, etichetta) => '<div class="riepilogo-mensa__dato"><strong>' + numero + '</strong><span>' + etichetta + '</span></div>';
        riepilogo.innerHTML = dato(Number(report.totale_pasti || 0), 'Pasti da preparare') +
            dato(report.plessi.length, 'Scuole con mensa') + dato(pasta, 'Pasta in bianco') +
            dato(definitivi + '/' + report.plessi.length, 'Conteggi confermati');
        stato.textContent = 'Pasti per il ' + dataIt(data) +
            ' · ' + Number(report.totale_pasti || 0) + ' pasti previsti · aggiornato alle ' + UI.oraAggiornamento();
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
    menuDisponibili = false;
    sincronizzaMenuSelezionato();
    UI.stato('menu-stato', 'Caricamento dei menu…', 'caricamento');
    try {
        const menu = await API.get('/api/catering/menu?data=' + oggi());
        if (richiesta !== numeroRichiestaMenu) return;
        menuPubblicati = menu;
        menuDisponibili = true;
        sincronizzaMenuSelezionato();
        UI.stato('menu-stato', menu.length + ' menu pubblicati oggi · aggiornato alle ' +
            UI.oraAggiornamento(), 'ok');
        document.getElementById('menu-lista').innerHTML = menu.length === 0
            ? '<p class="vuoto">Nessuna pubblicazione per oggi.</p>'
            : menu.map((m) => `
                <article class="card-mensa catering-menu-card" data-testid="menu-card-${m.id}">
                    <h3>${esc(m.plesso_nome)}</h3>
                    <p class="catering-menu-card__tipo">${esc(m.tipologia_nome)}</p>
                    ${m.origine === 'settimanale' ? `<span class="badge-stato badge-neutro">Da menu settimanale: ${esc(m.modello_nome)}</span>` : ''}
                    <p class="menu-descrizione">${esc(m.descrizione || 'Solo foto pubblicata')}</p>
                    ${m.foto_path ? `<img src="${BASE}/storage/${esc(m.foto_path)}" alt="Foto del menu">` : ''}
                    <button type="button" class="btn btn-outline-primary" data-menu-modifica="${m.id}" data-testid="menu-modifica-${m.id}">Modifica menu</button>
                </article>`).join('');

        document.querySelectorAll('[data-menu-modifica]').forEach((pulsante) => {
            pulsante.addEventListener('click', () => {
                const scelto = menuPubblicati.find((m) => String(m.id) === pulsante.dataset.menuModifica);
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
