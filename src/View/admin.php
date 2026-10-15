<?php
$titoloPagina = 'Amministrazione · ' . $titoloApp;
$ruoloPagina = 'admin';
$scriptPagina = 'admin';
require __DIR__ . '/partials/testa.php';
?>
<nav class="schede" role="tablist">
    <button class="scheda attiva" data-vista="plessi" data-testid="tab-plessi">Plessi</button>
    <button class="scheda" data-vista="calendario" data-testid="tab-calendario">Calendario mensa</button>
    <button class="scheda" data-vista="classi" data-testid="tab-classi">Classi/sezioni</button>
    <button class="scheda" data-vista="tipologie" data-testid="tab-tipologie">Tipologie menu</button>
    <button class="scheda" data-vista="email" data-testid="tab-email">Email e solleciti</button>
    <button class="scheda" data-vista="utenti" data-testid="tab-utenti">Utenze</button>
    <button class="scheda" data-vista="job" data-testid="tab-job">Job giornaliero</button>
</nav>

<section id="vista-plessi" class="vista">
    <header class="area-intro"><h1>Scuole e impostazioni della mensa</h1></header>
    <div id="admin-riepilogo" class="riepilogo-mensa" aria-live="polite"></div>
    <p class="nota">Configura tariffe, orari e soglie di avviso.</p>
    <h2 class="admin-form-titolo">Dati della scuola</h2>
    <form id="form-plesso" class="pannello admin-config" data-testid="plesso-form">
        <input type="hidden" id="plesso-id">
        <div class="admin-plesso-sezioni">
            <fieldset class="admin-sezione-campi">
                <legend>Scuola</legend>
                <div class="riga-filtri">
                    <label for="plesso-nome">Denominazione</label><input type="text" id="plesso-nome" data-testid="plesso-nome-input">
                </div>
            </fieldset>
            <fieldset class="admin-sezione-campi">
                <legend>Tariffa, fido e orario</legend>
                <div class="riga-filtri">
                    <label for="plesso-tariffa">Tariffa base (€)</label><input type="number" step="0.01" min="0" id="plesso-tariffa" data-testid="plesso-tariffa-input">
                    <label for="plesso-fido">Fido concesso (buoni)</label><input type="number" max="0" placeholder="-5" id="plesso-fido" data-testid="plesso-fido-input">
                    <label for="plesso-cutoff">Orario limite per le comunicazioni</label><input type="time" id="plesso-cutoff" data-testid="plesso-cutoff-input">
                </div>
            </fieldset>
            <fieldset class="admin-sezione-campi">
                <legend>Contatti</legend>
                <div class="riga-filtri">
                    <label for="plesso-dirigente">Email dirigente</label><input type="text" id="plesso-dirigente" data-testid="plesso-dirigente-input">
                    <label for="plesso-protocollo">Email protocollo (facoltativa)</label><input type="text" id="plesso-protocollo" data-testid="plesso-protocollo-input">
                </div>
            </fieldset>
        </div>
        <div class="admin-form-azioni">
            <button type="submit" class="btn btn-primary" data-testid="plesso-submit-button">Salva scuola</button>
            <button type="button" class="btn btn-outline-primary" id="plesso-annulla" data-testid="plesso-annulla-button">Aggiungi un'altra scuola</button>
        </div>
    </form>
    <div class="area-sezione-testa"><h2>Scuole configurate</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="plessi-tabella">
            <thead><tr><th>Plesso</th><th>Tariffa</th><th>Fido concesso</th><th>Orario limite</th><th>Dirigente</th><th></th></tr></thead>
            <tbody id="plessi-corpo"></tbody>
        </table>
    </div>
</section>


<section id="vista-calendario" class="vista" hidden>
    <header class="area-intro"><h1>Calendario del servizio mensa</h1></header>
    <p class="nota">Scegli la scuola e imposta i giorni in cui viene servito il pasto.</p>
    <div class="admin-calendario-scuola">
        <label for="calendario-plesso">Scuola</label>
        <select id="calendario-plesso" data-testid="calendario-plesso-select"></select>
    </div>
    <form id="form-calendario" class="pannello admin-config admin-calendario-form" data-testid="calendario-form">
        <h2>Quando è prevista la mensa?</h2>
        <div class="admin-calendario-date">
            <div class="campo-filtro">
                <label for="calendario-inizio">Primo giorno di mensa</label>
                <input type="date" id="calendario-inizio" required>
            </div>
            <div class="campo-filtro">
                <label for="calendario-fine">Ultimo giorno (facoltativo)</label>
                <input type="date" id="calendario-fine">
            </div>
        </div>
        <fieldset class="admin-calendario-giorni">
            <legend>Giorni della settimana</legend>
            <div class="admin-calendario-giorni__griglia">
                <label class="admin-calendario-giorno" for="calendario-lunedi">
                    <input type="checkbox" id="calendario-lunedi"><span>Lunedì</span>
                </label>
                <label class="admin-calendario-giorno" for="calendario-martedi">
                    <input type="checkbox" id="calendario-martedi"><span>Martedì</span>
                </label>
                <label class="admin-calendario-giorno" for="calendario-mercoledi">
                    <input type="checkbox" id="calendario-mercoledi"><span>Mercoledì</span>
                </label>
                <label class="admin-calendario-giorno" for="calendario-giovedi">
                    <input type="checkbox" id="calendario-giovedi"><span>Giovedì</span>
                </label>
                <label class="admin-calendario-giorno" for="calendario-venerdi">
                    <input type="checkbox" id="calendario-venerdi"><span>Venerdì</span>
                </label>
            </div>
        </fieldset>
        <div class="admin-form-azioni">
            <button class="btn btn-primary" type="submit" data-testid="calendario-salva">Salva calendario</button>
        </div>
    </form>
    <div class="area-sezione-testa"><h2>Un giorno diverso dal solito</h2></div>
    <p class="nota">Registra una chiusura o un'apertura valida soltanto per il giorno scelto.</p>
    <form id="form-eccezione-calendario" class="pannello admin-config admin-calendario-eccezione">
        <div class="riga-filtri">
            <label for="eccezione-data">Data</label><input type="date" id="eccezione-data" required>
            <label for="eccezione-attivo">Servizio mensa</label>
            <select id="eccezione-attivo">
                <option value="0">La mensa sarà chiusa</option>
                <option value="1">La mensa sarà aperta</option>
            </select>
            <label for="eccezione-motivazione">Motivazione</label>
            <input type="text" maxlength="255" id="eccezione-motivazione" required placeholder="Es. sospensione attività didattiche">
        </div>
        <div class="admin-form-azioni">
            <button class="btn btn-primary" type="submit" data-testid="eccezione-salva">Salva modifica</button>
        </div>
    </form>
    <div class="tabella-scroll admin-tabella">
        <table class="table">
            <thead><tr><th>Data</th><th>Stato</th><th>Motivazione</th><th>Azioni</th></tr></thead>
            <tbody id="eccezioni-calendario-corpo"></tbody>
        </table>
    </div>
    <div class="area-sezione-testa"><h2>Verifica una data</h2></div>
    <div class="admin-calendario-verifica">
        <div class="campo-filtro">
            <label for="calendario-verifica-data">Giorno da verificare</label>
            <input type="date" id="calendario-verifica-data">
        </div>
        <button type="button" class="btn btn-outline-primary" id="calendario-verifica">Controlla</button>
        <span id="calendario-verifica-esito" role="status" aria-live="polite"></span>
    </div>
</section>

<section id="vista-classi" class="vista" hidden>
    <header class="area-intro"><h1>Classi e sezioni delle scuole</h1></header>
    <div class="admin-calendario-scuola">
        <label for="classe-plesso">Scuola</label>
        <select id="classe-plesso" data-testid="classe-plesso-select"></select>
    </div>
    <div class="admin-classi-consulta">
        <div class="campo-filtro">
            <label for="classe-esistente">Classi e sezioni della scuola</label>
            <select id="classe-esistente" data-testid="classe-esistente-select">
                <option value="">Seleziona prima una scuola</option>
            </select>
        </div>
        <p id="classe-esistente-dettaglio" class="nota" role="status" aria-live="polite"></p>
    </div>
    <details class="admin-aggiungi-sezione">
        <summary>Aggiungi una nuova classe o sezione</summary>
        <form id="form-classe" class="admin-config" data-testid="classe-form">
            <div class="admin-classe-campi">
                <div class="campo-filtro">
                    <label for="classe-nome">Classe o sezione</label>
                    <select id="classe-nome" data-testid="classe-nome-input" required>
                        <option value="">Seleziona una classe</option>
                    </select>
                </div>
                <div class="campo-filtro" id="classe-altro-gruppo" hidden>
                    <label for="classe-altro">Nome della nuova classe o sezione</label>
                    <input type="text" id="classe-altro" placeholder="Es. Sezione G" maxlength="100">
                </div>
                <div class="campo-filtro">
                    <label for="classe-anno">Anno scolastico</label>
                    <input type="text" id="classe-anno" placeholder="Es. 2026/2027" data-testid="classe-anno-input" required>
                </div>
            </div>
            <div class="admin-form-azioni">
                <button type="submit" class="btn btn-primary" data-testid="classe-submit-button">Aggiungi classe</button>
            </div>
        </form>
    </details>
    <div class="area-sezione-testa"><h2>Classi registrate nella scuola</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="classi-tabella">
            <thead><tr><th>Classe o sezione</th><th>Anno scolastico</th><th>Azioni</th></tr></thead>
            <tbody id="classi-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-tipologie" class="vista" hidden>
    <header class="area-intro"><h1>Tipi di menu</h1></header>
    <p class="nota">Ogni bambino ha un menu assegnato.</p>
    <h2 class="admin-form-titolo">Aggiungi una tipologia</h2>
    <form id="form-tipologia" class="pannello admin-config admin-modulo-breve" data-testid="tipologia-form">
        <div class="riga-filtri">
            <label for="tipologia-nome">Nome della tipologia</label><input type="text" id="tipologia-nome" data-testid="tipologia-nome-input">
        </div>
        <div class="admin-form-azioni">
            <button type="submit" class="btn btn-primary" data-testid="tipologia-submit-button">Aggiungi tipologia</button>
        </div>
    </form>
    <div class="area-sezione-testa"><h2>Tipologie registrate</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="tipologie-tabella">
            <thead><tr><th>Tipologia</th><th></th></tr></thead>
            <tbody id="tipologie-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-email" class="vista" hidden>
    <header class="area-intro"><h1>Testi e destinatari dei solleciti</h1></header>
    <h2 class="admin-form-titolo">Testo della comunicazione</h2>
    <form id="form-modello" class="pannello admin-config" data-testid="modello-form">
        <div class="riga-filtri">
            <label for="modello-plesso">Plesso</label><select id="modello-plesso" data-testid="modello-plesso-select"></select>
            <label for="modello-oggetto">Oggetto</label><input type="text" id="modello-oggetto" data-testid="modello-oggetto-input">
        </div>
        <label for="modello-testo">Testo della comunicazione (puoi usare: {genitore} {alunno} {plesso} {saldo} {fido} {data})</label>
        <p class="nota">Le parole tra { } sono compilate automaticamente.</p>
        <textarea id="modello-testo" rows="6" data-testid="modello-testo-input"></textarea>
        <div class="admin-form-azioni"><button type="submit" class="btn btn-primary" data-testid="modello-submit-button">Salva modello</button></div>
    </form>

    <h2>Altri indirizzi che ricevono una copia</h2>
    <form id="form-destinatario" class="pannello admin-config" data-testid="destinatario-form">
        <div class="riga-filtri">
            <label for="dest-etichetta">Descrizione del destinatario</label><input type="text" id="dest-etichetta" data-testid="destinatario-etichetta-input">
            <label for="dest-email">Indirizzo email</label><input type="text" id="dest-email" data-testid="destinatario-email-input">
        </div>
        <div class="admin-form-azioni">
            <button type="submit" class="btn btn-primary" data-testid="destinatario-submit-button">Aggiungi destinatario</button>
        </div>
    </form>
    <div class="tabella-scroll">
        <table class="table" data-testid="destinatari-tabella">
            <thead><tr><th>Etichetta</th><th>Email</th><th>Attivo</th><th></th></tr></thead>
            <tbody id="destinatari-corpo"></tbody>
        </table>
    </div>

    <h2>Impostazioni per l'invio delle email (SMTP)</h2>
    <p class="nota">Queste impostazioni sono riservate a chi gestisce la posta elettronica del Comune.</p>
    <form id="form-smtp" class="pannello admin-config" data-testid="smtp-form">
        <div class="riga-filtri">
            <label for="smtp-host">Server</label><input type="text" id="smtp-host" data-testid="smtp-host-input">
            <label for="smtp-port">Porta</label><input type="number" id="smtp-port" data-testid="smtp-port-input">
            <label for="smtp-security">Sicurezza</label>
            <select id="smtp-security" data-testid="smtp-security-select">
                <option value="tls">STARTTLS</option><option value="ssl">SSL</option><option value="none">Nessuna</option>
            </select>
        </div>
        <div class="riga-filtri">
            <label for="smtp-username">Utente</label><input type="text" id="smtp-username" data-testid="smtp-username-input">
            <label for="smtp-password">Password</label><input type="password" id="smtp-password" data-testid="smtp-password-input">
            <label for="smtp-from">Mittente</label><input type="text" id="smtp-from" data-testid="smtp-from-input">
            <label for="email-protocollo">Email protocollo</label><input type="text" id="email-protocollo" data-testid="email-protocollo-input">
        </div>
        <div class="admin-form-azioni">
            <button type="submit" class="btn btn-primary" data-testid="smtp-submit-button">Salva configurazione</button>
            <button type="button" class="btn btn-outline-primary" id="btn-email-prova" data-testid="smtp-prova-button">Invio di prova</button>
        </div>
    </form>
</section>

<section id="vista-utenti" class="vista" hidden>
    <header class="area-intro"><h1>Persone che possono accedere</h1></header>
    <p class="nota">Crea gli accessi e assegna il ruolo corretto.</p>
    <h2 class="admin-form-titolo">Nuovo accesso</h2>
    <form id="form-utente" class="pannello admin-config" data-testid="utente-form">
        <div class="riga-filtri">
            <label for="ut-ruolo">Ruolo</label>
            <select id="ut-ruolo" data-testid="utente-ruolo-select">
                <option value="operatore">Operatore comunale</option>
                <option value="scuola">Scuola</option>
                <option value="catering">Catering</option>
                <option value="admin">Admin</option>
            </select>
            <label for="ut-username">Nome utente per accedere</label><input type="text" id="ut-username" data-testid="utente-username-input">
            <label for="ut-password">Password iniziale (minimo 8 caratteri)</label><input type="password" id="ut-password" data-testid="utente-password-input">
            <label for="ut-plesso">Plesso (scuola)</label><select id="ut-plesso" data-testid="utente-plesso-select"></select>
        </div>
        <div class="admin-form-azioni"><button type="submit" class="btn btn-primary" data-testid="utente-submit-button">Crea utenza</button></div>
    </form>
    <div class="area-sezione-testa"><h2>Utenze registrate</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="utenti-tabella">
            <thead><tr><th>Username</th><th>Ruolo</th><th>Plesso</th><th>Attivo</th><th></th></tr></thead>
            <tbody id="utenti-corpo"></tbody>
        </table>
    </div>
</section>

<section id="vista-job" class="vista" hidden>
    <header class="area-intro"><h1>Conferma dei pasti e addebito dei buoni</h1></header>
    <p class="banner banner--rosso"><strong>Attenzione:</strong> questa funzione non è una semplice verifica. Se è il giorno di mensa ed è passato l'orario limite, l'esecuzione può confermare i pasti e scalare i buoni alle famiglie. Usala solo se sai che il conteggio è pronto. L'esecuzione automatica può essere programmata ogni 5 minuti.</p>
    <div class="riga-filtri admin-job-azioni">
        <button class="btn btn-primary btn-sm" id="btn-scheduler" data-testid="job-scheduler-button">Conferma pasti di tutte le scuole</button>
        <div class="campo-filtro">
            <label for="job-plesso">Scuola</label>
            <select id="job-plesso" data-testid="job-plesso-select"></select>
        </div>
        <button class="btn btn-outline-primary btn-sm" id="btn-job-plesso" data-testid="job-plesso-button">Conferma pasti della scuola scelta</button>
    </div>
    <div id="job-esito" class="pannello" aria-live="polite" data-testid="job-esito"><p class="nota">Qui vedrai l’esito dell’ultima operazione eseguita manualmente.</p></div>
    <div class="area-sezione-testa"><h2>Ultime esecuzioni</h2></div>
    <div class="tabella-scroll">
        <table class="table" data-testid="job-esecuzioni-tabella">
            <thead><tr><th>Data</th><th>Plesso</th><th>Esito</th><th>Dettaglio</th></tr></thead>
            <tbody id="job-corpo"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/partials/piede.php'; ?>
