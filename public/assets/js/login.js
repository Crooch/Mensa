document.getElementById('form-accesso').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const errore = document.getElementById('errore-accesso');
    errore.hidden = true;
    const pulsante = document.querySelector('#form-accesso [type="submit"]');
    try {
        await UI.esegui(pulsante, async () => {
            const esito = await API.post('/api/auth/login', {
                username: document.getElementById('username').value.trim(),
                password: document.getElementById('password').value
            });
            Sessione.salva(esito.token, esito.utente);
            window.location.href = BASE + (PAGINE_RUOLO[esito.utente.ruolo] || '/login');
        }, 'Accesso in corso…');
    } catch (e) {
        errore.textContent = e.message === 'Errore 401'
            ? 'Nome utente o password non corretti. Controlla e riprova.'
            : e.message;
        errore.hidden = false;
        errore.focus();
    }
});

// Se esiste gia' una sessione valida, porta l'utente alla propria area.
if (Sessione.token() && Sessione.utente()) {
    window.location.href = BASE + (PAGINE_RUOLO[Sessione.utente().ruolo] || '/login');
}
