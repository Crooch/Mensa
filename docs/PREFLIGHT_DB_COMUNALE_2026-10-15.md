# Mensa Caltavuturo — preflight su database comunale e upgrade dello schema SENZA import dati

**Stato:** attività preparatoria, non autorizzazione al rilascio.  
**Perimetro:** `Crooch/Mensa:dev`. Non aggiornare `main` ufficiale,
`/srv/www/mensa/app`, `mensa_db` né il cron di produzione.


> **Vincolo non negoziabile:** il database comunale `mensa_db` e' la fonte
> dei dati reali. Non importare dump sintetici, non usare seed, non riavviare
> lo schema da `001`, non ripristinare dati di staging su produzione. Gli
> eventuali calendari nuovi sono configurazioni da verificare e inserire
> esplicitamente in una fase successiva. Se la struttura rilevata sul server
> e' diversa da quella attesa, non eseguire lo script automaticamente.

## 1. Prima di qualunque copia o modifica strutturale

- Il collaudo delle interfacce è stato dichiarato eseguito dall'utente. Registrare
  separatamente eventuali anomalie e la conferma degli scenari critici.
- Usare il commit `dev` effettivamente collaudato; identificare con
  `git rev-parse HEAD` prima delle prove. Non utilizzare una branch mobile
  durante una migrazione reale senza fissare il commit candidato.
- Confermare con il Comune le regole: due plessi iniziali, calendario, orari
  limite, contabilizzazione al cutoff e recuperi da errore.
- Verificare in modo autonomo che il cron sia **disattivo**; il monitor web del
  job non prova che il cron di sistema sia spento.
- Non attivare e-mail, scheduler e job manuali durante il preflight.

## 2. Diagnosi di sola lettura

File: `database/preflight_readonly.sql` (solo `SELECT` e transazione read-only).
La query mostra esclusivamente nomi dei plessi, aggregati e metadati job:
nessuna anagrafica individuale, nessuna email o saldo nominativo.

Nel clone locale aggiornato sul branch `dev`, verificato l'host SSH corretto:

```bash
git switch dev
git pull --ff-only origin dev
git rev-parse HEAD
ssh ubuntu@HOST_VPS 'sudo mariadb --database=mensa_db --table' < database/preflight_readonly.sql
```

Sostituire `HOST_VPS` con **l'host già verificato** dall'operatore. Il comando
non carica il database fuori dal server e non scrive tabelle. Se `sudo`
richiede un terminale/password o la connessione fallisce, interrompere e
riprendere da una sessione SSH amministrativa autorizzata; non cambiare
permessi o configurazione dell'app per aggirare il problema.

Risultati da annotare:

| ID | Condizione per proseguire |
|---|---|
| 00 | Il database di destinazione è **esattamente** `mensa_db` |
| 01 | Le tabelle base attese sono presenti |
| 02–03 | Schema rilevato: assenza/presenza di migrazioni documentata; non applicare quelle già presenti in modo incontrollato |
| 04–05 | Identificati plessi e cutoff reali; le denominazioni e gli ID vanno confermati per configurare manualmente i calendari dopo il deploy. Nessun seed automatico |
| 06 | Registrati conteggi di baseline |
| 07 | Annotati saldi aggregati, movimenti, acquisti, consumi, rettifiche |
| 08 | Qualsiasi pool non allineato richiede analisi prima del rilascio, non correzione automatica |
| 09 | Qualsiasi doppio consumo bambino/giorno richiede analisi |
| 10–11 | Le esecuzioni `in_corso`/errore vanno riconciliate prima di riavvii |

Non trasferire dump, tabelle anagrafiche, token, configurazioni SMTP o credenziali
in chat, repository GitHub o servizi pubblici. Solo risultati aggregati e privi
di dati personali possono essere usati come evidenza di lavoro.

## 3. Copia isolata e dry-run dell'upgrade **solo strutture**

**Da eseguire soltanto dopo preflight positivo e verifica dello spazio su disco.**

1. Creare un dump cifrato o protetto con permessi ristretti (es. `umask 077`),
   includendo trigger, routine ed eventi. Conservare il backup in area privata
   e verificarne dimensione/integrità; nessun trasferimento in CI pubblica.
2. Disporre di una **copia isolata**, sotto controllo di accesso e con database
   dal nome diverso da `mensa_db`. Non concedere credenziali di questa copia
   all'applicazione in produzione, né esporre via web o e-mail.
3. Verificare su copia gli stessi aggregati del punto 2 prima delle migrazioni.
4. Applicare **esclusivamente** lo script di solo schema
   `database/deploy/upgrade_schema_only_2026_10.sql`, e solo sulla copia
   isolata. Lo script crea le sette nuove tabelle e aggiunge le colonne e
   l'indice mancanti; **non esegue INSERT, UPDATE, DELETE, import o reset**.
   **Non applicare `001_schema.sql` né gli script 002–009 originali
   al database comunale**: il file 002 include INSERT di calendari e il 007
   contiene un UPDATE delle revisioni.
5. Ripetere il preflight sulla copia e confrontare *per tabella e per pool* lo
   stato contabile: saldi, movimenti, importi, origini, record precedenti,
   presenze, assegnazioni e job. Le nuove tabelle inizialmente vuote sono
   normali; in particolare **`calendari_servizio` deve rimanere vuota**
   dopo lo script se non esistevano configurazioni precedenti. Le regole
   di servizio saranno inserite successivamente dagli amministratori,
   soltanto dopo verifica delle indicazioni del Comune.
6. Provare il codice candidato contro la **sola copia isolata**, senza cron,
   job di consolidamento, SMTP reale o scritture non controllate. Collaudare
   privilegi tra i ruoli, variazioni menu e intervalli di calendario.
7. Provare esplicitamente il **ripristino del dump** in una seconda base
   protetta e il ripristino dei file immagine. Non considerare sufficiente
   ripristinare solo il codice: le DDL non si annullano necessariamente con
   un `ROLLBACK`.

Se si scoprono movimenti incoerenti, nomi dei plessi discordanti, schemi
parzialmente migrati, differenze contabili o foto non recuperabili: **STOP**.

## 4. Pubblicazione del codice senza trasferire dati di staging

Il rilascio futuro riguarda il **codice applicativo selezionato** dal commit
collaudato, non la directory o il database dello staging. Prima della
pubblicazione sul server identificare i file realmente gestiti da Git e
le esclusioni dell'ambiente in esercizio:

- **Mantenere** il database `mensa_db` esistente e tutte le sue anagrafiche,
  saldi, movimenti, presenze e menu storici.
- **Mantenere** la configurazione riservata `config/config.local.php` della VPS
  e verificare eventuali parametri di `config/config.php`. Non trasferire
  credenziali/configurazioni sintetiche dello staging.
- **Mantenere** le immagini e gli allegati reali di `storage/menu/`, oltre
  ai log e alle directory di lavoro. Il repository contiene solo un file
  segnaposto in `storage/menu/`.
- Non eseguire `git clean -fdx`, `rsync --delete` indiscriminato, reset
  del database, import dei dump sintetici, `001_schema.sql` o seed.
- Se serve aggiungere strutture, applicare soltanto
  `database/deploy/upgrade_schema_only_2026_10.sql`, **dopo** preflight,
  prova protetta e backup; non eseguire gli script numerati originali.
- La nuova tabella `calendari_servizio` non viene precompilata. Dopo il
  rilascio, l'Admin configura esplicitamente i calendari approvati dal Comune;
  **fino alla configurazione, i plessi senza calendario non devono
  generare consumi automatici**. Il cron resta sospeso fino all'ok finale.
- Verificare il diff del codice candidato e lo stato dei file locali
  **prima** di pubblicare; in caso di discrepanze, fermarsi e riconciliare
  manualmente le modifiche (non sovrascrivere file non identificati).

## 5. Decisione e rilascio

Lo stato rimane **NO-GO** finché non sono documentati:

- Esito del collaudo funzionale manuale e accettazione dei referenti;
- preflight read-only del DB comunale senza anomalie non risolte;
- dry-run di **sole CREATE/ALTER** su copia isolata con confronti contabili invariati;
- accertamento che nessun dato fittizio/staging sia stato importato;
- definizione esplicita di quali calendari nuovi creare tramite UI Admin dopo l'aggiornamento, senza modificare i dati storici;
- backup/ripristino provati per DB e immagini;
- finestra autorizzata dal Comune e commit candidato fissato;
- decisione distinta per il cron e per le comunicazioni automatiche.

L'avvio del servizio previsto per **19/10/2026** non giustifica forzature:
se il rilascio non è pronto, concordare col Comune la gestione operativa delle
giornate nel frattempo, senza addebiti retroattivi improvvisati.
