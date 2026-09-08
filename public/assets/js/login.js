document.getElementById('form-accesso').addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const errore = document.getElementById('errore-accesso');
    errore.hidden = true;
    try {
        const esito = await API.post('/api/auth/login', {
            username: document.getElementById('username').value.trim(),
            password: document.getElementById('password').value
        });
        Sessione.salva(esito.token, esito.utente);
        window.location.href = BASE + (PAGINE_RUOLO[esito.utente.ruolo] || '/login');
    } catch (e) {
        errore.textContent = e.message;
        errore.hidden = false;
    }
});

// Se esiste gia' una sessione valida, porta l'utente alla propria area.
if (Sessione.token() && Sessione.utente()) {
    window.location.href = BASE + (PAGINE_RUOLO[Sessione.utente().ruolo] || '/login');
}
