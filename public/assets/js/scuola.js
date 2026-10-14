/* Area scuola: assenze di massa e override motivati delle presenze. */
const utente = proteggi('scuola');
let classi = [];

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
    if (!classeId) return;
    const vista = await API.get(`/api/scuola/classi/${classeId}/giornata?data=${data}`);

    const banner = document.getElementById('stato-cutoff');
    banner.className = 'banner ' + (vista.oltre_cutoff ? 'banner--rosso' : 'banner--verde');
    banner.innerHTML = `Cutoff plesso <strong>${esc(vista.cutoff_orario)}</strong> · ${vista.oltre_cutoff
        ? 'cutoff superato: puoi confermare un pasto effettivamente erogato a un alunno previsto assente. Gli altri cambiamenti sono solo tracciati, senza rimborsi automatici.'
        : 'prima del cutoff: le correzioni modificano il conteggio dei pasti.'}
        ${vista.assenza_massa ? `<br>Assenza di massa registrata: <strong>${esc(vista.assenza_massa.motivo)}</strong>` : ''}`;

    document.getElementById('presenze-corpo').innerHTML = vista.alunni.map((a) => `
        <tr data-testid="riga-alunno-${a.bambino_id}">
            <td>${esc(a.cognome)} ${esc(a.nome)}</td>
            <td>${esc(a.tipologia_nome)}</td>
            <td>${badgeStato(a.default_calcolato)}</td>
            <td>${a.dichiarazione_genitore ? badgeStato(a.dichiarazione_genitore) : '—'}</td>
            <td>${a.override_scuola ? badgeStato(a.override_scuola) + '<br><span class="nota">' + esc(a.override_motivazione || '') + '</span>' : '—'}</td>
            <td>${badgeStato(a.stato_effettivo)} ${Number(a.fuori_cutoff) === 1 ? '<span class="badge-stato badge-giallo">fuori cutoff</span>' : ''}</td>
            <td>
                <button class="btn-azione" data-override="${a.bambino_id}" data-stato="presente" data-testid="override-presente-${a.bambino_id}">Presente</button>
                <button class="btn-azione" data-override="${a.bambino_id}" data-stato="assente" data-testid="override-assente-${a.bambino_id}">Assente</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="vuoto">Nessun alunno assegnato a questa classe nella data indicata.</td></tr>';

    document.querySelectorAll('[data-override]').forEach((b) => b.addEventListener('click', async () => {
        const motivazione = prompt('Motivazione dell\'override (obbligatoria):');
        if (!motivazione) return;
        try {
            const esito = await API.post('/api/scuola/presenze/override', {
                bambino_id: Number(b.dataset.override),
                data: document.getElementById('data').value,
                stato: b.dataset.stato,
                motivazione
            });
            const messaggi = {
                pasto_contabilizzato: 'Pasto confermato e contabilizzato una sola volta.',
                presenza_confermata: 'Presenza confermata. Nessun doppio addebito.',
                registrato_senza_effetto: 'Segnalazione tracciata: nessuna modifica economica.',
                applicato: 'Presenza aggiornata prima del cutoff.'
            };
            avviso(messaggi[esito.effetto] || 'Correzione registrata.', 'ok');
            caricaGiornata();
        } catch (e) { avviso(e.message, 'errore'); }
    }));
}

async function caricaAssenze() {
    const elenco = await API.get('/api/scuola/assenze-massa');
    document.getElementById('assenze-corpo').innerHTML = elenco.map((a) => `
        <tr><td>${dataIt(a.data)}</td><td>${esc(a.plesso_nome)}</td><td>${esc(a.classe_nome)}</td>
        <td>${esc(a.motivo)}</td><td>${esc(a.inserita_da || '-')}</td></tr>`).join('')
        || '<tr><td colspan="5" class="vuoto">Nessuna assenza di massa registrata.</td></tr>';
}

document.getElementById('btn-carica').addEventListener('click', caricaGiornata);
document.getElementById('classe').addEventListener('change', caricaGiornata);

document.getElementById('form-assenza').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        await API.post('/api/scuola/assenze-massa', {
            classe_sezione_id: Number(document.getElementById('am-classe').value),
            data: document.getElementById('am-data').value,
            motivo: document.getElementById('am-motivo').value
        });
        avviso('Assenza di massa registrata: i default della giornata sono aggiornati.');
        document.getElementById('am-motivo').value = '';
        caricaAssenze();
    } catch (err) { avviso(err.message, 'errore'); }
});

inizializza().catch((e) => avviso(e.message, 'errore'));
