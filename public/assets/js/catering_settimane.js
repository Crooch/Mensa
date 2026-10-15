/* Catalogo delle settimane tipo. Nessun modello scrive i menu storici. */
let catalogoSettimane = [];
let assegnazioniSettimane = [];
let settimanaInModifica = null;
const nomiGiorniSettimana = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];
const fotoSettimana = {};
for (let giorno = 1; giorno <= 7; giorno++) {
    fotoSettimana[String(giorno)] = FotoMenu.crea('settimana-foto-widget-' + giorno);
}


function azzeraModelloSettimana() {
    settimanaInModifica = null;
    document.getElementById('form-settimana').reset();
    document.getElementById('settimana-tipo').disabled = false;
    document.getElementById('settimana-form-titolo').textContent = 'Nuovo menu settimanale';
    document.getElementById('settimana-salva').textContent = 'Salva menu settimanale';
    nomiGiorniSettimana.forEach((_, i) => {
        document.getElementById('settimana-giorno-' + (i + 1)).value = '';
        fotoSettimana[String(i + 1)].imposta(null);
    });
}

function compilaSettimana(modello, duplicazione = false) {
    settimanaInModifica = duplicazione ? null : Number(modello.id);
    document.getElementById('settimana-form-titolo').textContent =
        duplicazione ? 'Duplica menu settimanale' : 'Modifica menu settimanale';
    document.getElementById('settimana-nome').value =
        duplicazione ? 'Copia di ' + modello.nome : modello.nome;
    const tipo = document.getElementById('settimana-tipo');
    tipo.value = String(modello.tipologia_menu_id);
    tipo.disabled = !duplicazione;
    nomiGiorniSettimana.forEach((_, i) => {
        const giorno = String(i + 1);
        document.getElementById('settimana-giorno-' + (i + 1)).value =
            modello.giorni?.[giorno] || '';
        fotoSettimana[giorno].imposta(modello.foto_giorni?.[giorno] || null);
    });
    document.getElementById('settimana-salva').textContent =
        duplicazione ? 'Crea copia' : 'Salva modifiche';
    document.getElementById('form-settimana').scrollIntoView?.({
        behavior: 'smooth', block: 'start'
    });
    document.getElementById('settimana-nome').focus();
}

function mostraCatalogoSettimane() {
    const elenco = document.getElementById('settimane-elenco');
    elenco.innerHTML = catalogoSettimane.length
        ? catalogoSettimane.map((m) => `
            <article class="card-mensa catering-settimana-card" data-testid="settimana-card-${m.id}">
                <h3>${esc(m.nome)}</h3>
                <p class="nota">${esc(m.tipologia_nome)}</p>
                <dl>${nomiGiorniSettimana.map((giorno, i) => `
                    <dt>${esc(giorno)}</dt>
                    <dd class="menu-descrizione">
                        ${esc(m.giorni?.[String(i + 1)] || (m.foto_giorni?.[String(i + 1)] ? '' : '—'))}
                        ${m.foto_giorni?.[String(i + 1)] ? `<img class="catering-settimana-foto"
                          src="${BASE}/storage/${esc(m.foto_giorni[String(i + 1)])}"
                          alt="Foto del menu di ${esc(giorno)}">` : ''}
                    </dd>
                `).join('')}</dl>
                <div class="catering-settimane-azioni">
                    <button type="button" class="btn btn-outline-primary" data-settimana-modifica="${m.id}">Modifica</button>
                    <button type="button" class="btn btn-outline-primary" data-settimana-duplica="${m.id}">Duplica</button>
                </div>
            </article>`).join('')
        : '<p class="nota">Nessun menu settimanale: creane uno per iniziare.</p>';
    document.querySelectorAll('[data-settimana-modifica]').forEach(b => {
        b.addEventListener('click', () => {
            const m = catalogoSettimane.find(x => String(x.id) === b.dataset.settimanaModifica);
            if (m) compilaSettimana(m, false);
        });
    });
    document.querySelectorAll('[data-settimana-duplica]').forEach(b => {
        b.addEventListener('click', () => {
            const m = catalogoSettimane.find(x => String(x.id) === b.dataset.settimanaDuplica);
            if (m) compilaSettimana(m, true);
        });
    });
}

function mostraAssegnazioniSettimane() {
    const select = document.getElementById('assegna-modello');
    const valore = select.value;
    select.innerHTML = '<option value="">Seleziona un menu</option>' +
        catalogoSettimane.map(m =>
            `<option value="${m.id}">${esc(m.nome)} · ${esc(m.tipologia_nome)}</option>`).join('');
    if (catalogoSettimane.some(m => String(m.id) === valore)) select.value = valore;

    const plessi = document.getElementById('assegna-plessi');
    plessi.innerHTML = riferimenti.plessi.map(p => `
        <label class="catering-plesso-opzione">
            <input type="checkbox" value="${p.id}" name="assegna-plesso">
            ${esc(p.nome)}
        </label>`).join('');
    aggiornaPlessiSelezionati();
    const attive = assegnazioniSettimane.filter(a => a.menu_id !== null);
    document.getElementById('assegnazioni-elenco').innerHTML = attive.length
        ? attive.map(a => `<div class="catering-assegnazione">
             <span>${esc(a.plesso_nome)} · ${esc(a.tipologia_nome)}</span>
             <strong>${esc(a.modello_nome)}</strong>
           </div>`).join('')
        : '<p class="nota">Non ci sono ancora menu settimanali assegnati.</p>';
}

function aggiornaPlessiSelezionati() {
    const modello = catalogoSettimane.find(m =>
        String(m.id) === document.getElementById('assegna-modello').value);
    document.querySelectorAll('input[name="assegna-plesso"]').forEach(input => {
        input.checked = !!modello && assegnazioniSettimane.some(a =>
            String(a.menu_id) === String(modello.id) &&
            String(a.plesso_id) === input.value);
    });
}

async function caricaSettimane() {
    UI.stato('settimana-stato', 'Caricamento dei modelli…', 'caricamento');
    if (!riferimenti.tipologie.length) riferimenti = await API.get('/api/riferimenti');
    const [modelli, assegnazioni] = await Promise.all([
        API.get('/api/catering/menu-settimanali'),
        API.get('/api/catering/menu-settimanali/assegnazioni')
    ]);
    catalogoSettimane = modelli;
    assegnazioniSettimane = assegnazioni;
    const tipo = document.getElementById('settimana-tipo');
    const selezionato = tipo.value;
    tipo.innerHTML = riferimenti.tipologie.map(t =>
        `<option value="${t.id}">${esc(t.nome)}</option>`).join('');
    if (riferimenti.tipologie.some(t => String(t.id) === selezionato)) {
        tipo.value = selezionato;
    }
    mostraCatalogoSettimane();
    mostraAssegnazioniSettimane();
    UI.stato('settimana-stato', catalogoSettimane.length +
        ' menu settimanali disponibili. Le modifiche valgono da domani.', 'ok');
}

document.getElementById('settimana-nuovo').addEventListener('click', azzeraModelloSettimana);
document.getElementById('assegna-modello').addEventListener('change', aggiornaPlessiSelezionati);

document.getElementById('form-settimana').addEventListener('submit', async event => {
    event.preventDefault();
    const tipo = Number(document.getElementById('settimana-tipo').value);
    const nome = document.getElementById('settimana-nome').value.trim();
    const giorni = {};
    nomiGiorniSettimana.forEach((_, i) => {
        giorni[String(i + 1)] = document.getElementById('settimana-giorno-' + (i + 1)).value;
    });
    const haFoto = Object.values(fotoSettimana).some(campo => campo.haFoto());
    if (!nome || !tipo || (!Object.values(giorni).some(g => g.trim() !== '') && !haFoto)) {
        avviso('Indica nome, tipologia e almeno un giorno con descrizione o foto.', 'errore');
        return;
    }
    const modifica = settimanaInModifica;
    if (modifica && !await UI.conferma('Salva il menu settimanale',
        'Le modifiche al modello saranno applicate a partire da domani ai plessi assegnati. I menu dei giorni già trascorsi rimarranno invariati.',
        'Salva modifiche')) return;
    const pulsante = document.getElementById('settimana-salva');
    try {
        await UI.esegui(pulsante, async () => {
            const dati = new FormData();
            dati.append('nome', nome);
            dati.append('tipologia_menu_id', String(tipo));
            dati.append('giorni', JSON.stringify(giorni));
            const rimozioni = [];
            for (let g = 1; g <= 7; g++) {
                const widget = fotoSettimana[String(g)];
                if (widget.rimuovere()) rimozioni.push(String(g));
                if (widget.file()) dati.append('foto_giorno_' + g, widget.file());
            }
            dati.append('rimuovi_foto', JSON.stringify(rimozioni));
            await API.post(modifica
                ? '/api/catering/menu-settimanali/' + modifica + '/aggiorna'
                : '/api/catering/menu-settimanali', dati);
            await caricaSettimane();
        }, 'Salvataggio…');
        avviso(modifica ? 'Modello aggiornato per i giorni futuri.' : 'Menu settimanale creato.');
        azzeraModelloSettimana();
    } catch(e) {
        avviso('Non è stato possibile salvare: ' + e.message, 'errore');
    }
});

document.getElementById('assegna-salva').addEventListener('click', async () => {
    const modello = catalogoSettimane.find(m =>
        String(m.id) === document.getElementById('assegna-modello').value);
    const ids = [...document.querySelectorAll('input[name="assegna-plesso"]:checked')].map(e => Number(e.value));
    if (!modello || !ids.length) {
        avviso('Seleziona un menu e almeno un plesso.', 'errore');
        return;
    }
    if (!await UI.conferma('Assegna menu settimanale',
        'Il modello “' + modello.nome + '” verrà usato dai plessi selezionati a partire da domani, finché non ne assegnerai un altro. Lo storico rimane invariato.',
        'Assegna menu')) return;
    const pulsante = document.getElementById('assegna-salva');
    try {
        await UI.esegui(pulsante, async () => {
            const result = await API.post('/api/catering/menu-settimanali/assegnazioni', {
                menu_id: Number(modello.id), tipologia_menu_id: Number(modello.tipologia_menu_id),
                plesso_ids: ids
            });
            await caricaSettimane();
            UI.stato('assegna-stato',
                'Menu assegnato a ' + ids.length + ' plessi a partire dal ' + dataIt(result.valido_dal) + '.', 'ok');
        }, 'Assegnazione…');
    } catch(e) {
        avviso('Assegnazione non riuscita: ' + e.message, 'errore');
    }
});
