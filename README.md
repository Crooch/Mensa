# Mensa Caltavuturo

Applicazione web per la gestione del **servizio di mensa scolastica del Comune di Caltavuturo (PA)**. Il sistema riunisce anagrafiche, plessi e classi, presenze, buoni mensa, menù e comunicazioni operative, con accessi distinti per Amministrazione, uffici comunali, scuole, ditta di ristorazione e famiglie.

Il progetto utilizza PHP, MySQL/MariaDB e JavaScript senza un framework applicativo o dipendenze Composer. L'interfaccia è responsive e le funzioni sono esposte tramite API REST.

> **Regola fondamentale:** un saldo insufficiente **non impedisce mai** di erogare il pasto. Eventuali debiti sono gestiti attraverso gli strumenti di controllo e comunicazione agli uffici.

## Funzionalità principali

| Area | Funzioni |
|---|---|
| **Amministrazione** | Plessi, classi, tipologie alimentari, utenti, calendari mensa, orari limite, configurazione email e conteggi giornalieri |
| **Operatore comunale** | Anagrafiche familiari, assegnazioni classe, acquisto e rettifica buoni, tariffe e situazione crediti |
| **Scuola** | Elenco alunni e pasti previsti, correzioni motivate, assenze dell'intera classe |
| **Catering** | Pasti per scuola, classe e tipologia alimentare; richieste di pasta in bianco; menù settimanali e variazioni giornaliere; valutazioni |
| **Genitore** | Saldo e movimenti, calendario mensa, comunicazione presenza/assenza, richiesta di pasta in bianco e valutazione del pasto |

### Menù e pasti

- Un **menù settimanale** può essere duplicato, modificato e assegnato a uno o più plessi. Le modifiche alle settimane tipo valgono dai giorni successivi, preservando lo storico.
- Una **variazione giornaliera** riguarda esclusivamente la data, il plesso e la tipologia scelti. Può essere programmata, modificata e annullata finché gli orari e lo stato del servizio lo consentono.
- **Il menù del giorno prevale su quello settimanale**, quando entrambi sono previsti per la stessa data, scuola e tipologia.
- La pasta in bianco è una richiesta riferita al singolo bambino: è già inclusa nel totale dei pasti e viene esposta separatamente alla cucina, senza contare due volte il pasto.
- In assenza di un modello specifico, il riepilogo mostra la tipologia prevista (ad esempio **Menù standard**); non inventa l'elenco delle pietanze.

Ogni plesso dispone di un calendario del servizio e di un **orario limite** per le variazioni. I conteggi tengono conto delle giornate senza mensa e delle modifiche già consolidate.

## Requisiti

- **PHP 8.1 o successivo**, con PDO MySQL, JSON, OpenSSL e Fileinfo;
- **MySQL 8 / MariaDB**, database con charset `utf8mb4`;
- server web con PHP e riscrittura URL (ad esempio Apache);
- accesso a un database dedicato e alla directory scrivibile `storage/menu`;
- per lo **staging locale**: Git e Docker con Compose.

Il codice non richiede Composer. I file CSS e i caratteri tipografici utilizzano anche risorse esterne: verificare la disponibilità delle rispettive CDN e le eventuali prescrizioni per la pubblicazione istituzionale.

## Avvio locale consigliato: staging isolato

Per verificare l'applicazione, utilizzare l'ambiente **con dati fittizi**, separato dal database comunale:

```bash
git clone https://github.com/ComuneDiCaltavuturo/Mensa.git
cd Mensa
docker compose -f compose.staging.yml up --build -d --wait
```

Aprire `http://127.0.0.1:18080/login`. Le utenze dimostrative e le istruzioni per arrestare i container conservando i dati sono riportate in [staging/README.md](staging/README.md).

**Attenzione:** `docker compose ... down -v` elimina i volumi dello staging; **non** usarlo per un normale aggiornamento. I comandi del README dello staging sono destinati soltanto all'ambiente di test.

## Installazione e configurazione

Questa sezione descrive i componenti, **non autorizza l'aggiornamento della produzione**. Prima di qualsiasi installazione su un server comunale verificare backup, permessi, configurazione, email, scheduler e finestra di intervento.

1. Configurare il server web con **`public/` come document root**. Tenere `config/`, `database/`, `bin/`, `src/` e `storage/` fuori dalla document root.
2. Creare un database e un utente dedicati, con i permessi strettamente necessari.
3. Copiare `config/config.local.example.php` in `config/config.local.php`; impostare i collegamenti al database, la chiave dello scheduler e, se necessario, `app.base_path`. **Non pubblicare il file locale né le password su Git.**
4. **Soltanto per un database nuovo e vuoto**, utilizzare la procedura di installazione dello schema dopo averne controllato gli effetti:
   ```bash
   php bin/install.php
   ```
   Lo script esegue **tutte** le migrazioni SQL presenti, nell'ordine: comprende anche inizializzazioni del calendario previste per Caltavuturo. Non va usato indiscriminatamente per aggiornare un database esistente.
5. Creare l'utente amministratore iniziale con una password robusta e un hash PHP (`password_hash`), secondo una procedura autorizzata; configurare i plessi, le classi, il calendario, le tipologie e gli orari limite.
6. Verificare i permessi della cartella `storage/menu`, l'accesso protetto alle immagini e l'esito del login prima di valutare l'avvio di operazioni automatiche.
7. Configurare email e pianificazione **solo dopo collaudo**, con l'autorizzazione dei referenti.

Per un'installazione **già in uso**, non eseguire il comando precedente come scorciatoia: seguire il [piano di verifica e rilascio](docs/RILASCIO_DEV_2026-10-19.md). Se mancano tabelle o colonne, usare **solo l'upgrade di schema** `database/deploy/upgrade_schema_only_2026_10.sql` dopo una prova su copia protetta, senza seed, import o modifiche ai dati esistenti. Il job/cron richiede anche il [piano di aggiornamento dedicato](docs/RILASCIO_JOB_2026-10.md).

### Script dimostrativi: non usare sui dati reali

`bin/seed.php` **svuota le tabelle applicative e inserisce dati di esempio**. Deve essere riservato a un database di prova dedicato, mai al database comunale: non è un comando di installazione ordinaria. Preferire lo staging Docker protetto, il cui seed è distinto e controllato.

Anche `bin/avvia_demo.sh` è uno strumento di dimostrazione: può reimpostare il database di esempio ed è inadatto al server di produzione.

## Gestione quotidiana e sicurezza

L'elaborazione giornaliera, implementata in `bin/cron_daily.php`, considera l'orario limite di ciascuna scuola. Le operazioni possono consolidare presenze, registrare consumi di buoni e produrre comunicazioni: **non avviare cron o job manuali senza verificare calendario, data e contabilità**. Il nuovo interruttore `app.scheduler_enabled` è **false per default** e blocca sia CLI/HTTP sia esecuzioni Admin. Per verificare in sola lettura lo stato dopo il deploy: `php bin/cron_daily.php --check`. Per aggiornare la pianificazione cron della VPS seguire la [procedura dedicata](docs/RILASCIO_JOB_2026-10.md).

La configurazione delle email è disponibile nell'area Amministrazione. Prima di abilitare gli invii, controllare destinatari, indirizzi istituzionali, modelli e servizio SMTP. Un ambiente di test non deve inviare comunicazioni a destinatari reali.

Per la manutenzione: effettuare un backup ripristinabile di database e immagini, aggiornare il codice soltanto dopo i test, verificare eventuali nuove migrazioni e controllare il risultato con i diversi profili. Una modifica allo schema non è necessariamente annullabile con il solo ripristino del codice precedente.

## Organizzazione del repository

```text
bin/                 comandi di installazione, sviluppo e pianificazione
config/              configurazione di base ed esempio locale
database/migrations/ schema e aggiornamenti SQL
docs/                API, piani di verifica e verbali di collaudo
public/              unico punto di ingresso web, CSS e JavaScript
routes/              rotte API e autorizzazioni
src/Core/            infrastruttura PHP
src/Controller/      ricezione delle richieste
src/Service/         regole applicative
src/Repository/      accesso al database
src/View/            pagine e componenti PHP
staging/             ambiente Docker e test sintetici
tests/               test di regressione e integrazione
storage/menu/        immagini caricate, protette dall'accesso pubblico
```

Le API sono definite in `routes/api.php`: i controller delegano ai servizi, che utilizzano i repository per l'accesso ai dati. L'autorizzazione è basata sul ruolo dell'utente autenticato.

## Documentazione e test

- [API REST](docs/API.md): autenticazione, endpoint, ruoli e payload.
- [Staging locale](staging/README.md): Docker, utenti fittizi e comandi non distruttivi.
- [Collaudo funzionale guidato](docs/COLLAUDO_MANUALE_STAGING_2026-10-15.md): scenari per i cinque profili.
- [Verbale tecnico](docs/COLLAUDO_DEV_2026-10-15.md): risultati dei test automatici e verifiche manuali ancora da svolgere.
- [Piano di verifica e rilascio](docs/RILASCIO_DEV_2026-10-19.md): attività e prerequisiti specifici prima di qualsiasi aggiornamento del servizio comunale.
- [Piano storico UX/UI](docs/PIANO_UX_UI_A_D_2026-10-14.md): decisioni e verifiche dell'interfaccia.

Le GitHub Actions verificano PHP e JavaScript, scenari SQL su MariaDB **sintetico**, flussi HTTP e schermate Chromium a più larghezze. **Un test CI positivo non sostituisce il collaudo manuale né autorizza il deploy.** Gli screenshot di prova possono essere consultati tra gli artifact delle pipeline.

## Licenza

Il repository include la [European Union Public Licence v1.2 (EUPL)](LICENSE).

## Funzioni non incluse nell'attuale applicazione

Pagamenti PagoPA integrati, accesso SPID/CIE, app mobile nativa e notifiche push. Non considerarle disponibili finché non sono effettivamente implementate e collaudate.
