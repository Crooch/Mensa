
# Sistema di Gestione Mensa Scolastica — Comune di Caltavuturo (PA)

Applicazione web per la gestione del servizio di mensa scolastica di un piccolo Comune, in
sostituzione del sistema cartaceo ("quadernetti e bigliettini"). Backend **PHP 8** a livelli,
database **MySQL 8**, frontend responsive con palette **Bootstrap Italia**, architettura
**API-first** (le stesse API REST potranno alimentare una futura app nativa senza modifiche
lato server).

> **Principio non negoziabile**: il sistema non impedisce **mai** a un bambino di ricevere il
> pasto per credito insufficiente. Il superamento del fido attiva solo un'escalation
> informativa quotidiana verso gli uffici, mai un blocco del servizio né una segnalazione
> alla ditta di ristorazione.

---

## 1. Requisiti

| Componente | Versione minima | Note |
|---|---|---|
| PHP | 8.1 (testato su 8.2) | estensioni `pdo_mysql`, `json`, `openssl` (per SMTP TLS), `fileinfo` |
| MySQL / MariaDB | MySQL 8 / MariaDB 10.4+ | charset `utf8mb4` |
| Web server | Apache con `mod_rewrite` (o IIS/Nginx con rewrite equivalente) | tipico hosting condiviso Linux (Aruba) |

Nessuna dipendenza da Composer, da servizi cloud, da code manager o container: l'autoloader è
incluso nel progetto. Il codice non usa funzioni Unix-only e costruisce i percorsi con
`DIRECTORY_SEPARATOR`, quindi funziona anche su Windows.

---

## 2. Struttura del progetto

```
mensa-caltavuturo/
├── bin/                       script CLI
│   ├── install.php            crea il database (se possibile) ed esegue le migration
│   ├── seed.php               dati di esempio realistici
│   ├── cron_daily.php         job giornaliero (scheduler multi-cutoff)
│   └── router_dev.php         router per il server integrato di PHP (solo sviluppo)
├── bootstrap.php              autoloader PSR-4 + caricamento configurazione
├── config/
│   ├── config.php             configurazione base
│   └── config.local.example.php  → copiare in config.local.php (credenziali hosting)
├── database/migrations/001_schema.sql
├── docs/API.md                documentazione delle API REST
├── public/                    DOCUMENT ROOT
│   ├── index.php              front controller (web + API)
│   ├── .htaccess              rewrite verso index.php
│   └── assets/                CSS e JS del frontend
├── routes/api.php             tabella delle rotte API con ruoli ammessi
├── src/
│   ├── Core/                  infrastruttura (Config, Database, Router, Request, Response,
│   │                          Auth, Mailer, Repository base, Container)
│   ├── Repository/            accesso ai dati (nessun SQL fuori da qui)
│   ├── Service/               logica di business (PHPDoc sui metodi pubblici)
│   ├── Controller/            controller sottili (validazione minima + delega ai servizi)
│   └── View/                  template HTML delle pagine
└── storage/menu/{plesso}_{tipologia}/{data}.jpg   foto dei menu su filesystem
```

**Architettura a livelli**: `Controller` → `Service` → `Repository` → PDO.
I controller non contengono logica di business; i servizi non conoscono HTTP; i repository sono
l'unico punto in cui compare SQL. L'autorizzazione è dichiarata nelle rotte (`routes/api.php`) e
applicata dal front controller.

---

## 3. Installazione su hosting condiviso (PHP + MySQL)

1. **Caricare i file** via FTP nella cartella dell'hosting, ad esempio:
   - contenuto di `public/` in `httpdocs/` (o `public_html/`)
   - le altre cartelle (`src`, `config`, `routes`, `database`, `storage`, `bin`, `bootstrap.php`)
     **al di fuori** della document root, se l'hosting lo consente.
   In alternativa (setup più semplice, tipico Aruba) caricare l'intero progetto in
   `httpdocs/mensa/` e puntare il dominio/sottodominio a `httpdocs/mensa/public`.
2. **Creare il database** MySQL dal pannello di controllo e annotare host, nome, utente, password.
3. **Configurare**: copiare `config/config.local.example.php` in `config/config.local.php` e
   valorizzare la sezione `db` più `app.scheduler_key` (chiave casuale). Se l'app è pubblicata in
   una sottocartella (es. `https://dominio/mensa`), impostare `app.base_path = '/mensa'`.
4. **Creare lo schema**: `php bin/install.php` da shell, oppure importare
   `database/migrations/001_schema.sql` con phpMyAdmin.
5. **Dati di esempio** (facoltativo, solo per test): `php bin/seed.php`.
   ⚠️ svuota e ripopola le tabelle applicative.
6. **Permessi**: la cartella `storage/menu` deve essere scrivibile dal web server (755/775).
7. **Primo accesso**: se non si usa il seed, creare a mano il primo utente admin:
   ```sql
   INSERT INTO utenti (ruolo, username, password_hash, nome_completo, attivo)
   VALUES ('admin', 'admin', '<hash>', 'Amministratore', 1);
   ```
   generando l'hash con `php -r "echo password_hash('LaTuaPassword', PASSWORD_DEFAULT);"`.
8. **Cron del job giornaliero** (vedi §5).

### Server di sviluppo locale

```bash
php bin/install.php && php bin/seed.php
php -S 0.0.0.0:8080 -t public bin/router_dev.php
# http://localhost:8080/login
```

---

## 4. Ruoli e credenziali di esempio (dopo `bin/seed.php`)

| Ruolo | Username | Password |
|---|---|---|
| Admin | `admin` | `Admin2026!` |
| Operatore comunale | `operatore` | `Operatore2026!` |
| Scuola (per plesso) | `scuola.infanzia`, `scuola.primaria`, `scuola.secondaria` | `Scuola2026!` |
| Catering | `catering` | `Catering2026!` |
| Genitori | `lobianco.g`, `curcio.m`, `ferrara.s`, `militello.r`, `gervasi.a`, `panzarella.c`, `dimarco.v`, `sciortino.a`, `rinaldi.f`, `vaccaro.c` | `Genitore2026!` |

Nessuna auto-registrazione: le utenze sono create dall'ufficio comunale (area Admin → Utenze,
oppure creando la famiglia dall'area Operatore, che genera anche l'utenza del genitore).
Lo `username` è una stringa libera univoca (di norma un'email, ma non è validata come tale).

Permessi sintetici:

- **Admin** — tutto l'Operatore + plessi, classi/sezioni, tipologie menu, parametri di plesso
  (tariffa base, fido, cutoff, email dirigente), modelli email di sollecito (uno per plesso),
  lista unica di destinatari in CC, utenze, configurazione SMTP, esecuzione manuale del job.
- **Operatore comunale** — anagrafiche genitori/bambini, assegnazioni classe storicizzate,
  tipologia menu del bambino, acquisto buoni (incasso + carico crediti), rettifiche, override
  tariffari motivati, situazione crediti di tutte le famiglie, log solleciti.
- **Scuola** (vincolata al proprio plesso) — assenze di massa per classe, verifica e override
  motivato delle presenze giornaliere.
- **Catering** — report pasti per plesso/tipologia (sola lettura), pubblicazione facoltativa del
  menu del giorno, lettura feedback. Non vede mai anagrafiche, importi o crediti.
- **Genitore** — sola lettura di saldi/pagamenti/calendario/feedback; in scrittura dichiarazione
  di eccezione presenza-assenza (entro il cutoff) e feedback giornaliero (stelle 1–5 + commento).

---

## 5. Job giornaliero (scheduler multi-cutoff)

Ogni plesso ha un proprio `cutoff_orario`: non esiste un orario unico di sistema. Lo scheduler va
quindi invocato **frequentemente** e decide da sé quali plessi hanno superato il cutoff e non sono
ancora stati elaborati per la giornata (idempotenza garantita dalla tabella `esecuzioni_job`).

Passi eseguiti per plesso:

1. **Congelamento presenze** — fissa `stato_effettivo` per tutti gli alunni attivi in quella data.
2. **Consumo crediti** — `-1` buono per ogni presenza effettiva, **senza alcun controllo di saldo**.
3. **Report catering** — aggregazione per plesso e tipologia dietetica.
4. **Verifica debiti oltre fido** — invio email di sollecito (mai al catering) e log in
   `log_solleciti_inviati`; l'invio si ripete ogni giorno finché la condizione persiste.

Cron (Linux, ogni 5 minuti):

```
*/5 * * * * /usr/bin/php /home/utente/mensa/bin/cron_daily.php >> /home/utente/mensa/storage/cron.log 2>&1
```

Se l'hosting non offre cron su CLI, usare il cron HTTP del pannello:

```
https://dominio/scheduler?key=CHIAVE_CONFIGURATA_IN_config.local.php
```

Dall'area Admin → *Job giornaliero* sono inoltre disponibili **“Esegui scheduler adesso”** e
**“Forza esecuzione plesso”** (utili per test e recuperi manuali).

---

## 6. Configurazione email (SMTP)

Area Admin → *Email e solleciti* → **Configurazione invio email**: server, porta, sicurezza
(STARTTLS/SSL/nessuna), utente, password, mittente, indirizzo del protocollo comunale.
Il client SMTP è incluso (`src/Core/Mailer.php`, socket + AUTH LOGIN): nessuna libreria esterna.
Se la configurazione SMTP è vuota il sistema tenta `mail()` di PHP.

Destinatari del sollecito:

- **A**: email del genitore (se presente) + email del protocollo comunale
  (per plesso, altrimenti quella globale)
- **CC**: email del dirigente scolastico del plesso + tutti i destinatari fissi attivi

L'assenza dell'email del genitore **non** blocca la segnalazione agli uffici.
Placeholder disponibili nel modello: `{genitore}`, `{alunno}`, `{plesso}`, `{saldo}`, `{fido}`, `{data}`.

---

## 7. Regole di business chiave

**Stato effettivo di presenza** (`src/Service/PresenzaService.php`)

```
override_scuola (entro cutoff)  >  dichiarazione_genitore (entro cutoff)  >  default_calcolato
```

- `default_calcolato` = *assente* se esiste un'assenza di massa per la classe attiva del bambino
  in quella data, altrimenti *presente*.
- I contributi arrivati **dopo** il cutoff vengono salvati per tracciabilità con
  `fuori_cutoff = 1` e non modificano `stato_effettivo` né il conteggio già inviato al catering.
- Al cutoff il record viene **congelato** (`congelato = 1`).

**Tariffa applicata** = override attivo per la coppia (genitore, plesso) — record con
`data_inserimento` massima ≤ data di riferimento — altrimenti `plessi.tariffa_base_pasto`.
L'importo dell'acquisto (`numero_buoni × tariffa`) è calcolato dal sistema e non è modificabile
manualmente dall'operatore.

**Pool crediti** condiviso per coppia (genitore, plesso), in buoni interi, può essere negativo.
Stato visivo: verde `saldo ≥ 0`, giallo `-fido < saldo < 0`, rosso `saldo ≤ -fido`.

**Storicizzazione** — nessuna cancellazione fisica: genitori, bambini, classi, plessi e tipologie
usano flag + data di cancellazione; assegnazioni classe, override tariffari, feedback, movimenti e
log solleciti non vengono mai sovrascritti. Il cambio classe chiude l'assegnazione corrente
(`data_fine`) e ne apre una nuova, così ogni presenza resta riferita alla classe corretta.

---

## 8. Documentazione

- [`docs/API.md`](docs/API.md): endpoint, metodi, payload, ruoli richiesti, formato risposte,
  autenticazione a token Bearer.

---

## 9. Fuori perimetro in questa versione

PagoPA, SPID/CIE, app nativa mobile, notifiche push/SMS/popup, risposta del catering ai feedback,
blocco del servizio per morosità, gestione documentale dei certificati medici.
