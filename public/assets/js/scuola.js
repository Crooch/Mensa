/* Area scuola: assenze di massa e override motivati delle presenze. */
const utente = proteggi('scuola');
let classi = [];
let versioneGiornata = 0;
let giornataCaricata = null;
const badgePastoScuola = (stato) => stato === null
    ? '<span class="badge-stato badge-neutro">Mensa non prevista</span>'
    : `<span class="badge-stato badge-${stato === 'presente' ? 'verde' : 'rosso'}">${stato === 'presente' ? 'Pasto previsto' : 'Pasto non previsto'}</span>`;
const motivoServizioScuola = (motivo) => ({
    servizio_non_avviato: 'Servizio non iniziato',
    servizio_terminato: 'Servizio terminato',
    fine_settimana: 'Fine settimana',
    festivita_nazionale: 'Festività',
    chiusura_programmata: 'Mensa chiusa',
    giorno_senza_servizio: 'Mensa non prevista',
    calendario_non_configurato: 'Calendario da definire',
    plesso_non_attivo: 'Plesso non attivo',
    data_non_valida: 'Data non valida'
}[motivo]) || 'Mensa non prevista';

abilitaSchede((vista) => { if (vista === 'assenze') caricaAssenze(); });

async function inizializza() {
    classi = await API.get('/api/scuola/classi');
    const opzioni = classi.map((c) => `<option value="${c.id}">${esc(c.plesso_nome)} · ${esc(c.nome)} (${esc(c.anno_scolastico)})</option>`).join('');
    document.getElementById('classe').innerHTML = opzioni;
    document.getElementById('am-classe').innerHTML = opzioni;
    document.getElementById('data').value = oggi();
    document.getElementById('am-data').value = oggi();
    await caricaGiornata();
}

async function caricaGiornata() {
    const classeId = document.getElementById('classe').value;
    const data = document.getElementById('data').value;
    const versione = ++versioneGiornata;
    giornataCaricata = null;
    const righe = document.getElementById('presenze-corpo');
    righe.innerHTML = '<tr><td colspan="6">Sto caricando i dati della classe…</td></tr>';
    UI.stato('scuola-stato', 'Caricamento dei pasti per il ' + dataIt(data) + '…', 'caricamento');
    document.getElementById('scuola-riepilogo').innerHTML = '';
    if (!classeId || !data) return;
    let vista;
    try {
        vista = await API.get('/api/scuola/classi/' + classeId + '/giornata?data=' + encodeURIComponent(data));
    } catch (errore) {
        if (versione !== versioneGiornata) return;
        righe.innerHTML = '';
        UI.stato('scuola-stato', 'Non riesco a mostrare le presenze: ' + errore.message, 'errore');
        return;
    }
    if (versione !== versioneGiornata || document.getElementById('classe').value !== classeId
        || document.getElementById('data').value !== data) return;
    giornataCaricata = { classeId, data };
    const servizioAttivo = vista.servizio_mensa?.attivo === true;
    const classeInGita = servizioAttivo && !!vista.assenza_massa;
    const presenti = servizioAttivo
        ? vista.alunni.filter((a) => a.stato_effettivo === 'presente').length : 0;
    const assenti = servizioAttivo ? vista.alunni.length - presenti : 0;
    const elementoDato = (valore, etichetta) =>
        '<div class="riepilogo-mensa__dato"><strong>' + valore + '</strong><span>' + etichetta + '</span></div>';
    document.getElementById('scuola-riepilogo').innerHTML =
        elementoDato(presenti, 'Pasti previsti') + elementoDato(assenti, 'Pasti non previsti') +
        elementoDato(vista.alunni.length, 'Bambini nella classe');
    const motivo = motivoServizioScuola(vista.servizio_mensa?.motivo);
    UI.stato('scuola-stato', classeInGita
        ? 'Assenza dell’intera classe il ' + dataIt(data) + ' · Nessun pasto previsto.'
        : servizioAttivo
            ? 'Stai guardando i dati del ' + dataIt(data) + '. Aggiornati alle ' + UI.oraAggiornamento()
            : 'Nessun servizio mensa il ' + dataIt(data) + ': ' + motivo + '.',
        servizioAttivo && !classeInGita ? 'ok' : 'info');

    const banner = document.getElementById('stato-cutoff');
    banner.className = 'banner ' + (!servizioAttivo || classeInGita ? 'banner--neutro'
        : vista.oltre_cutoff ? 'banner--rosso' : 'banner--verde');
    banner.innerHTML = !servizioAttivo
        ? 'Non sono previsti pasti né modifiche delle presenze per questa giornata.'
        : classeInGita
            ? 'Nessun pasto per questa classe. Motivo: <strong>' + esc(vista.assenza_massa.motivo) + '</strong>.'
            : `Orario limite per modificare i pasti: <strong>${esc(vista.cutoff_orario)}</strong> · ${vista.oltre_cutoff
                ? 'l’orario limite è passato. Puoi confermare un pasto realmente consumato da un bambino che risultava assente. Le altre modifiche vengono registrate, ma non restituiscono automaticamente i buoni.'
                : 'puoi ancora modificare il numero dei pasti previsti.'}`;

    document.getElementById('presenze-corpo').innerHTML = vista.alunni.map((a) => `
        <tr data-testid="riga-alunno-${a.bambino_id}">
            <td data-label="Alunno">${esc(a.cognome)} ${esc(a.nome)}</td>
            <td data-label="Menu">${esc(a.tipologia_nome)}</td>
            <td data-label="Comunicazione famiglia">${servizioAttivo && a.dichiarazione_genitore ? badgePastoScuola(a.dichiarazione_genitore) : '<span class="scuola-senza-modifica">Nessuna</span>'}</td>
            <td data-label="Correzione scuola">${servizioAttivo && a.override_scuola ? '<div class="scuola-correzione">' + badgePastoScuola(a.override_scuola) +
                (a.override_motivazione ? '<small>Motivo: ' + esc(a.override_motivazione) + '</small>' : '') +
                '</div>' : '<span class="scuola-senza-modifica">Nessuna</span>'}</td>
            <td data-label="Stato del pasto"><div class="scuola-stato-pasto">${badgePastoScuola(a.stato_effettivo)} ${servizioAttivo && Number(a.fuori_cutoff) === 1 ? '<small>Modifica dopo il limite</small>' : ''}</div></td>
            <td data-label="Azioni">
                <div class="scuola-azioni">
                    ${servizioAttivo && !classeInGita && a.stato_effettivo !== 'presente' ?
                        `<button type="button" class="btn btn-outline-primary btn-sm" data-override="${a.bambino_id}" data-alunno="${esc(a.cognome + ' ' + a.nome)}" data-stato="presente" data-testid="override-presente-${a.bambino_id}">Conferma pasto</button>` : ''}
                    ${servizioAttivo && !classeInGita && a.stato_effettivo !== 'assente' ?
                        `<button type="button" class="btn btn-outline-primary btn-sm" data-override="${a.bambino_id}" data-alunno="${esc(a.cognome + ' ' + a.nome)}" data-stato="assente" data-testid="override-assente-${a.bambino_id}">Segna assente</button>` : ''}
                </div>
            </td>
        </tr>`).join('') || '<tr><td colspan="6" class="vuoto">Nessun alunno assegnato a questa classe nella data indicata.</td></tr>';

    document.querySelectorAll('[data-override]').forEach((b) => b.addEventListener('click', async () => {
        // Evita di correggere il giorno precedente dopo che l'utente ha
        // modificato classe o data, mentre è in corso un nuovo caricamento.
        if (!giornataCaricata || giornataCaricata.classeId !== document.getElementById('classe').value
            || giornataCaricata.data !== document.getElementById('data').value) {
            UI.stato('scuola-stato', 'I dati sono cambiati: aggiorna la classe prima di fare modifiche.', 'errore');
            return;
        }
        if (vista.oltre_cutoff) {
            const avvertimento = b.dataset.stato === 'presente'
                ? 'L’orario limite è passato. Conferma solo un pasto realmente consumato: potrebbe essere addebitato un buono.'
                : 'L’orario limite è passato. La correzione sarà registrata, ma non restituirà automaticamente buoni già utilizzati.';
            if (!await UI.conferma('Modifica dopo l’orario limite', avvertimento, 'Continua')) return;
        }
        const nominativo = b.dataset.alunno || 'l’alunno selezionato';
        const azione = b.dataset.stato === 'presente' ? 'Conferma pasto' : 'Segna assente';
        const motivazione = await UI.chiediMotivazione(
            azione + ' · ' + nominativo,
            'Indica il motivo della modifica per il ' + dataIt(data) + '.'
        );
        if (motivazione === null) return;
        if (!giornataCaricata || document.getElementById('data').value !== data
            || document.getElementById('classe').value !== classeId) {
            avviso('Hai cambiato giorno o classe: carica i dati aggiornati prima di salvare.', 'errore');
            return;
        }
        try {
            await UI.esegui(b, async () => {
            const esito = await API.post('/api/scuola/presenze/override', {
                bambino_id: Number(b.dataset.override),
                data,
                stato: b.dataset.stato,
                motivazione
            });
            const messaggi = {
                pasto_contabilizzato: 'Pasto confermato e contabilizzato una sola volta.',
                presenza_confermata: 'Pasto confermato. Non viene scalato due volte lo stesso buono.',
                registrato_senza_effetto: 'Modifica registrata. Nessun buono aggiunto o scalato.',
                applicato: 'Il numero di pasti previsti è stato aggiornato.'
            };
            avviso(messaggi[esito.effetto] || 'Correzione registrata.', 'ok');
            await caricaGiornata();
            });
        } catch (e) { avviso(e.message, 'errore'); }
    }));
}

async function caricaAssenze() {
    const elenco = await API.get('/api/scuola/assenze-massa');
    document.getElementById('assenze-corpo').innerHTML = elenco.map((a) => `
        <tr><td>${dataIt(a.data)}</td><td>${esc(a.plesso_nome)}</td><td>${esc(a.classe_nome)}</td>
        <td>${esc(a.motivo)}</td><td>${esc(a.inserita_da || '-')}</td></tr>`).join('')
        || '<tr><td colspan="5" class="vuoto">Nessuna giornata con assenza dell’intera classe registrata.</td></tr>';
}

document.getElementById('btn-carica').addEventListener('click', caricaGiornata);
document.getElementById('classe').addEventListener('change', caricaGiornata);
document.getElementById('data').addEventListener('change', caricaGiornata);

document.getElementById('form-assenza').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const motivo = document.getElementById('am-motivo').value.trim();
        if (!motivo) {
            avviso('Inserisci il motivo dell’assenza della classe.', 'errore');
            document.getElementById('am-motivo').focus();
            return;
        }
        if (!await UI.conferma('Assenza dell’intera classe',
            'Nella data scelta la classe non mangerà a mensa. Confermi la segnalazione?',
            'Conferma assenza')) return;
        await UI.esegui(e.submitter || document.querySelector('#form-assenza [type=submit]'), () => API.post('/api/scuola/assenze-massa', {
            classe_sezione_id: Number(document.getElementById('am-classe').value),
            data: document.getElementById('am-data').value,
            motivo
        }), 'Salvataggio…');
        const classeSegnalata = document.getElementById('am-classe').value;
        const dataSegnalata = document.getElementById('am-data').value;
        document.getElementById('am-motivo').value = '';
        // Porta il referente direttamente alla classe e al giorno appena
        // registrati. La vista precedente poteva essere su un'altra data.
        document.getElementById('classe').value = classeSegnalata;
        document.getElementById('data').value = dataSegnalata;
        document.querySelector('.scheda[data-vista="giornata"]').click();
        await caricaGiornata();
        avviso('Gita registrata: nessun pasto previsto per questa classe.', 'ok');
    } catch (err) { avviso(err.message, 'errore'); }
});

inizializza().catch((e) => avviso(e.message, 'errore'));
