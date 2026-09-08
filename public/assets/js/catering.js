/* Area catering: report pasti, pubblicazione menu, lettura feedback. */
const utente = proteggi('catering');
let riferimenti = { plessi: [], tipologie: [] };

abilitaSchede((vista) => {
    if (vista === 'menu') caricaMenu();
    if (vista === 'feedback') caricaFeedback();
});

async function caricaReport() {
    const data = document.getElementById('report-data').value || oggi();
    const report = await API.get('/api/catering/report?data=' + data);
    document.getElementById('report').innerHTML = report.plessi.length === 0
        ? '<p class="vuoto">Nessun pasto da preparare per la data indicata.</p>'
        : report.plessi.map((p) => `
            <article class="card-mensa" data-testid="report-plesso-${p.plesso_id}">
                <h3>${esc(p.plesso_nome)}</h3>
                <span class="saldo">${p.totale_pasti}</span>
                <p class="nota">pasti totali · cutoff ${esc(p.cutoff_orario)}</p>
                <span class="badge-stato badge-${p.definitivo ? 'verde' : 'giallo'}">${p.definitivo ? 'definitivo' : 'provvisorio'}</span>
                <dl>${p.tipologie.map((t) => `<dt>${esc(t.tipologia_nome)}</dt><dd data-testid="report-tipologia-${p.plesso_id}-${t.tipologia_id}">${t.pasti}</dd>`).join('')}</dl>
            </article>`).join('');
}

async function caricaMenu() {
    const menu = await API.get('/api/catering/menu?data=' + oggi());
    document.getElementById('menu-lista').innerHTML = menu.length === 0
        ? '<p class="vuoto">Nessuna pubblicazione per oggi.</p>'
        : menu.map((m) => `
            <article class="card-mensa" data-testid="menu-card-${m.id}">
                <h3>${esc(m.plesso_nome)} · ${esc(m.tipologia_nome)}</h3>
                <p>${esc(m.descrizione || '')}</p>
                ${m.foto_path ? `<img src="${BASE}/storage/${esc(m.foto_path)}" alt="Menu">` : ''}
            </article>`).join('');
}

async function caricaFeedback() {
    const parametri = new URLSearchParams();
    const da = document.getElementById('fb-da').value;
    const a = document.getElementById('fb-a').value;
    if (da) parametri.set('data_da', da);
    if (a) parametri.set('data_a', a);
    if (document.getElementById('fb-plesso').value) parametri.set('plesso_id', document.getElementById('fb-plesso').value);
    if (document.getElementById('fb-tipologia').value) parametri.set('tipologia_id', document.getElementById('fb-tipologia').value);
    const elenco = await API.get('/api/catering/feedback?' + parametri.toString());
    document.getElementById('feedback-corpo').innerHTML = elenco.map((f) => `
        <tr><td>${dataIt(f.data)}</td><td>${esc(f.alunno_iniziali)}</td><td>${esc(f.plesso_nome || '-')}</td>
        <td>${esc(f.classe_nome || '-')}</td><td>${esc(f.tipologia_nome)}</td>
        <td>${f.voto ? '★'.repeat(f.voto) : '-'}</td><td>${esc(f.commento || '')}</td></tr>`).join('')
        || '<tr><td colspan="7" class="vuoto">Nessun feedback per i filtri selezionati.</td></tr>';
}

document.getElementById('form-menu').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('plesso_id', document.getElementById('menu-plesso').value);
    fd.append('tipologia_menu_id', document.getElementById('menu-tipologia').value);
    fd.append('data', oggi());
    fd.append('descrizione', document.getElementById('menu-descrizione').value);
    const foto = document.getElementById('menu-foto').files[0];
    if (foto) fd.append('foto', foto);
    try {
        const risposta = await fetch(BASE + '/api/catering/menu', {
            method: 'POST',
            headers: { Authorization: 'Bearer ' + Sessione.token() },
            body: fd
        });
        const dati = await risposta.json();
        if (!risposta.ok || dati.ok === false) throw new Error(dati.errore || 'Errore di pubblicazione');
        avviso('Menu pubblicato.');
        document.getElementById('menu-descrizione').value = '';
        document.getElementById('menu-foto').value = '';
        caricaMenu();
    } catch (err) { avviso(err.message, 'errore'); }
});

document.getElementById('btn-report').addEventListener('click', caricaReport);
document.getElementById('btn-feedback').addEventListener('click', caricaFeedback);

(async () => {
    riferimenti = await API.get('/api/riferimenti');
    const opzPlessi = '<option value="">Tutti</option>' + riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    const opzTipologie = '<option value="">Tutte</option>' + riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
    document.getElementById('fb-plesso').innerHTML = opzPlessi;
    document.getElementById('fb-tipologia').innerHTML = opzTipologie;
    document.getElementById('menu-plesso').innerHTML = riferimenti.plessi.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
    document.getElementById('menu-tipologia').innerHTML = riferimenti.tipologie.map((t) => `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
    document.getElementById('report-data').value = oggi();
    document.getElementById('fb-da').value = new Date(Date.now() - 14 * 86400000).toISOString().slice(0, 10);
    document.getElementById('fb-a').value = oggi();
    await caricaReport();
})().catch((e) => avviso(e.message, 'errore'));
