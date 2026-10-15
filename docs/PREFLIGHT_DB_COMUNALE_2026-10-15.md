# Mensa Caltavuturo — preflight su database comunale e prova di migrazione

**Stato:** attività preparatoria, non autorizzazione al rilascio.  
**Perimetro:** `Crooch/Mensa:dev`. Non aggiornare `main` ufficiale,
`/srv/www/mensa/app`, `mensa_db` né il cron di produzione.

## 1. Prima di qualunque copia o migrazione

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
| 04–05 | Identificati plessi e cutoff reali; le due denominazioni richieste dalla migration 002 corrispondono una volta ciascuna, oppure correggere il piano prima di ogni prova |
| 06 | Registrati conteggi di baseline |
| 07 | Annotati saldi aggregati, movimenti, acquisti, consumi, rettifiche |
| 08 | Qualsiasi pool non allineato richiede analisi prima del rilascio, non correzione automatica |
| 09 | Qualsiasi doppio consumo bambino/giorno richiede analisi |
| 10–11 | Le esecuzioni `in_corso`/errore vanno riconciliate prima di riavvii |

Non trasferire dump, tabelle anagrafiche, token, configurazioni SMTP o credenziali
in chat, repository GitHub o servizi pubblici. Solo risultati aggregati e privi
di dati personali possono essere usati come evidenza di lavoro.

## 3. Copia isolata e dry-run delle migrazioni

**Da eseguire soltanto dopo preflight positivo e verifica dello spazio su disco.**

1. Creare un dump cifrato o protetto con permessi ristretti (es. `umask 077`),
   includendo trigger, routine ed eventi. Conservare il backup in area privata
   e verificarne dimensione/integrità; nessun trasferimento in CI pubblica.
2. Disporre di una **copia isolata**, sotto controllo di accesso e con database
   dal nome diverso da `mensa_db`. Non concedere credenziali di questa copia
   all'applicazione in produzione, né esporre via web o e-mail.
3. Verificare su copia gli stessi aggregati del punto 2 prima delle migrazioni.
4. Applicare **nell'ordine** `002`, `003`, `004`, `005`, `006`,
   `007`, `008`, `009`. **Non applicare `001_schema.sql`** al database
   preesistente.
5. Ripetere il preflight sulla copia e confrontare *per tabella e per pool* lo
   stato contabile: saldi, movimenti, importi, origini, record precedenti,
   presenze, assegnazioni e job. Le nuove tabelle inizialmente vuote sono
   normali; l'inserimento delle due configurazioni calendario va verificato.
6. Provare il codice candidato contro la **sola copia isolata**, senza cron,
   job di consolidamento, SMTP reale o scritture non controllate. Collaudare
   privilegi tra i ruoli, variazioni menu e intervalli di calendario.
7. Provare esplicitamente il **ripristino del dump** in una seconda base
   protetta e il ripristino dei file immagine. Non considerare sufficiente
   ripristinare solo il codice: le DDL non si annullano necessariamente con
   un `ROLLBACK`.

Se si scoprono movimenti incoerenti, nomi dei plessi discordanti, schemi
parzialmente migrati, differenze contabili o foto non recuperabili: **STOP**.

## 4. Decisione e rilascio

Lo stato rimane **NO-GO** finché non sono documentati:

- Esito del collaudo funzionale manuale e accettazione dei referenti;
- preflight read-only del DB comunale senza anomalie non risolte;
- dry-run su copia isolata con confronti contabili invariati;
- backup/ripristino provati per DB e immagini;
- finestra autorizzata dal Comune e commit candidato fissato;
- decisione distinta per il cron e per le comunicazioni automatiche.

L'avvio del servizio previsto per **19/10/2026** non giustifica forzature:
se il rilascio non è pronto, concordare col Comune la gestione operativa delle
giornate nel frattempo, senza addebiti retroattivi improvvisati.
