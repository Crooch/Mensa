# Ticket 2 — baseline, collaudo e rilascio

Ricognizione del 3 novembre 2026. Nessun rilascio in produzione effettuato.

## Baseline verificata

- Repository: Crooch/Mensa, branch di partenza `dev`, commit `f7eac7f7477cd91d6015239b89493b8a97c0eabb`.
- La directory Git della VPS `/srv/www/mensa/app` dichiara ancora `82eee1156cabcd0810147ad0c9025d0d6300ed9d` (`main`), ma contiene 42 file tracciati modificati (7.093 inserimenti, 871 eliminazioni) e file aggiuntivi.
- Confronto SHA-256 su tutti i 125 file tracciati in `dev`: i 115 presenti anche sulla VPS coincidono esattamente. I 10 assenti sono `compose.staging.yml` e i file della directory `staging/`. Lo sviluppo è quindi basato sul codice realmente installato, già recuperabile da `dev`, non sul vecchio `main`.
- Controllate in sola lettura le colonne del database comunale (204 righe di metadati); le strutture della migrazione 010 sono nuove. Non sono stati copiati dati personali, configurazioni riservate, dump o immagini della produzione.
- Diagnostica effettiva: scheduler abilitato; una riga attiva nel crontab dell'utente mensa richiama il job; invii automatici di sollecito disabilitati. Nessuno di questi stati è stato modificato. Non è un censimento di tutti i possibili trigger esterni.
- Presenti backup giornalieri: verificati nomi e dimensioni, non il ripristino del database reale. La documentazione di ottobre che indicava cron sospeso non descrive lo stato attuale.

## Implementazione

Le funzioni sono nelle aree esistenti. Nessun nuovo ruolo, utente di sistema o pannello generale.

| Ticket | Funzione predisposta | Natura |
|---|---|---|
| T01 | Verifica associazioni e omonimie, disattivazione logica sicura, revoca accessi | Assistenza anagrafica supportata da correttivo |
| T02 | Riepilogo per intervallo/plesso/ordine, alunni/personale, CSV, stati distinti | Sviluppo evolutivo |
| T03 | Stesso report per Catering, senza anagrafiche o dati economici | Sviluppo evolutivo |
| T04 | Ricorrenze con decorrenza, quantità giornaliere per plesso, rettifiche motivate, consolidamento | Sviluppo evolutivo |
| T05 | Filtri, posizioni oltre fido, storico/esiti/errori; sola consultazione | Completamento funzionale; nessuna attivazione invii |
| T06 | Inserimento chiusure future con controllo intervalli e duplicazioni | Configurazione assistita e permessi |
| T07 | Buoni correnti separati dallo storico; verifica comunale delle associazioni | Correttivo di visualizzazione |
| T08 | Modifica nome/cognome sullo stesso record, conferma, controllo concorrenza, audit | Assistenza anagrafica supportata da interfaccia |
| T09 | Fido del singolo plesso, validazione e audit | Configurazione e permessi |
| T10 | Primo giorno di sospensione; prevalenza sulle aperture, conservazione storico | Configurazione e completamento funzionale |

Tutti i casi reali devono ancora essere eseguiti dagli utenti dopo il rilascio autorizzato. Il collaudo sintetico non attesta la risoluzione delle singole posizioni comunali.

## Migrazione 010

`database/migrations/010_autonomia_operatori.sql` contiene esclusivamente CREATE TABLE IF NOT EXISTS:

1. `audit_amministrativo`: utente, data/ora, operazione, riferimento, prima/dopo, motivazione.
2. `impostazioni_servizio_plesso`: ordine scolastico e primo giorno di sospensione.
3. `pasti_personale_regole`: ricorrenze aggregate con decorrenza, giorno, categoria e tipologia.
4. `pasti_personale_giorni`: eccezioni e quantità consolidate.
5. `presenze_tipologie_storiche`: tipologia fotografata al consolidamento della presenza.

Nessun valore di calendario, fido, ordine scolastico o personale viene precaricato. Non esegue UPDATE, DELETE, INSERT, reset o seed sulle tabelle esistenti. Esecuzione ripetuta collaudata su dati sintetici.

Il codice nuovo richiede la 010. Non usare `bin/install.php`, le migrazioni 001–009, `bin/seed.php` o gli script T0 sulla produzione per questo rilascio.

## Collaudo effettuato

- API T01–T10: percorso positivo, conferme, validazioni, accessi diretti vietati, anonimi, isolamento Scuola, conservazione delle tabelle contabili, ripetizione senza duplicazioni.
- Cinque ruoli in Chrome automatizzato: 375, 768, 1366 px; navigazione, moduli, salvataggi, disattivazione, nome alunno, chiusure, fido/fine servizio, ricorrenze, correzione Scuola, filtri, download CSV; assenza di errori JavaScript e overflow della pagina. Le operazioni complete sono state eseguite a 1366 px; a 375/768 px sono state verificate le schermate, i controlli e le esportazioni. Nessun collaudo su telefono fisico.
- Consolidamento: job sintetico completo, ripetizione con un solo consumo, pasti personale senza addebiti, cambio dieta che conserva lo storico, rettifica motivata, fine servizio che azzera le sole previsioni future e lascia disponibili storico e attività amministrative.
- Regressioni PHP: calendario, report, menù settimanali, idempotenza acquisti, sicurezza presenze/cutoff, blocco retry del job, privacy dei solleciti.
- Regressioni JavaScript esistenti: quattro suite superate.
- Regressione HTTP esistente: superata; aggiornate le date di scrittura dei test menù, prima fissate a ottobre 2026, per restare future.
- Backup/ripristino su database sintetico separato: 32 tabelle confrontate riga per riga, senza differenze. Nessun dump reale utilizzato.
- Sintassi PHP/JS e `git diff --check`.

I test sono riproducibili con `.github/workflows/ticket2.yml`. Gli script rifiutano destinazioni HTTP non locali; il test di consolidamento richiede esplicitamente il DB sintetico e il contesto di staging. Credenziali fittizie di staging restano quelle già documentate nel repository.

## Limiti espliciti e assistenza tecnica

- Omonimie non equivalgono a duplicati certi. Il sistema non fonde record, trasferisce figli, cancella movimenti o sposta saldi. Genitori con figli attivi, saldo non nullo o utenza condivisa/privilegiata non sono disattivabili; richiedono assistenza. I figli con presenze o assegnazioni correnti/future non sono disattivabili da questa procedura.
- L'ordine scolastico va scelto dal Comune per ciascun plesso. Fino ad allora compare «Da configurare»: nessuna deduzione automatica dal nome della scuola.
- La baseline non registrava la dieta al momento del pasto. Per presenze pregresse senza fotografia compare «Non storicizzata»; il totale dei pasti confermati resta ricavato dalle presenze congelate e dalle assegnazioni alla data. Non vengono inventati dati storici. Il nuovo dettaglio viene conservato per le nuove elaborazioni.
- I pasti del personale delle giornate precedenti non sono inventati: se manca il consolidamento, il report mostra dato non disponibile e giornata incompleta. Eventuali rettifiche richiedono una quantità effettivamente verificata dal Comune e una motivazione.
- Le ricorrenze decorrono dal futuro. Per oggi si usa la correzione giornaliera. La Scuola corregge solo oggi prima del cutoff; il Comune può rettificare con motivazione. Una voce ricorrente termina impostando quantità zero da una nuova decorrenza.
- Per modificare/eliminare una chiusura già inserita usare la gestione calendario Admin esistente con verifica tecnica degli effetti; l'interfaccia comunale introdotta inserisce chiusure future. Non modifica periodi storici.
- La riapertura dopo una sospensione già iniziata richiede assistenza. Non introdotta un'attivazione automatica del nuovo anno.
- Nessun invio manuale o automatico aggiunto al Comune. L'eventuale attivazione dei solleciti rimane una decisione separata, con verifica dei destinatari.
- Concorrenza: serializzazione delle scritture del personale per plesso, controllo del valore precedente per le rettifiche giornaliere e per la modifica del nominativo. Le configurazioni amministrative sono tracciate; coordinare le modifiche contemporanee tra operatori.

## Piano di rilascio — da autorizzare

1. Review e approvazione della PR verso `dev`. Non fare merge del vecchio `main` sulla produzione e non usare `git pull main` come deploy.
2. Ricontrollare gli hash della VPS rispetto alla baseline per intercettare modifiche intervenute dopo la ricognizione; confrontare lo schema reale con le precondizioni 001–009.
3. Preparare una copia protetta di staging e verificare il ripristino di un backup recente del DB, dei file menù e della configurazione. Non pubblicare dati personali su GitHub o CI. Questa prova sui dati reali non è stata eseguita nell'incarico.
4. Concordare una finestra fuori dai cutoff e dalle operazioni comunali. Conservare l'esatto stato di cron/switch/email; la sospensione temporanea del solo job Mensa e l'eventuale manutenzione rientrano nella futura finestra autorizzata, senza toccare altri servizi.
5. Backup verificato immediatamente precedente; applicare esclusivamente la 010 dopo approvazione esplicita alla migrazione reale. Il DDL non è annullabile con un semplice ROLLBACK SQL.
6. Distribuire il codice del commit approvato preservando `config.local.php`, flag scheduler, foto e log. Nessun seed o import di staging. Non alterare le impostazioni effettive di posta.
7. Verificare login e letture con i ruoli effettivi; nessun job finanziario manuale come test. Ripristinare la pianificazione nello stato concordato dopo le verifiche, controllando l'assenza di doppie esecuzioni.
8. Consegnare la guida al Comune, alle Scuole e al Catering. Il Comune verifica i casi e inserisce autonomamente ordine, ricorrenze, chiusure, fido e sospensione; non applicare i valori al suo posto.
9. Monitorare le prime elaborazioni autorizzate e confrontare conteggi alunni/personale e movimenti. L'esito degli interventi sui casi reali va registrato separatamente dal collaudo delle funzioni.

## Rollback

- Prima dell'uso delle nuove funzioni: ripristinare il codice precedente verificato e mantenere le cinque nuove tabelle (ignorate dalla versione precedente). Non cancellarle e non ripristinare un vecchio dump sopra nuove operazioni.
- Dopo l'uso: bloccare le nuove scritture nella finestra concordata, conservare audit e nuove configurazioni; valutare gli effetti prima di tornare al vecchio codice, che non applica la nuova sospensione né conta il personale. Il rollback potrebbe richiedere una chiusura equivalente nel calendario precedente, da concordare esplicitamente.
- Un ripristino del DB richiede backup collaudato e riconciliazione delle operazioni successive. Non effettuare automaticamente DROP delle nuove tabelle o rettifiche dei saldi.

Stato rilascio: NON ESEGUITO. La verifica di ripristino su copia protetta e l'autorizzazione al deploy/migrazione restano prerequisiti del rilascio, non prove già effettuate.
