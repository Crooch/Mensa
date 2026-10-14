# Piano tecnico UX/UI A–D — Mensa Caltavuturo (dev)

Data: 14 ottobre 2026. Stato: implementazione controllata e test in staging. **Non deployare automaticamente in produzione.**

## Vincoli non negoziabili
- Sviluppare esclusivamente su `Crooch/Mensa:dev`. Non modificare `main`, database/cron della VPS o gli importi storici.
- Il servizio della mensa inizia il 19/10/2026 secondo il calendario di ciascun plesso; non forzare i job finanziari per collaudare le UI.
- Il catering non riceve saldi né anagrafiche non necessarie. La mensa non viene bloccata per saldo insufficiente.
- Conservare tutte le registrazioni e l'audit; mantenere separati i dati sintetici dello staging.

## A — Correttezza, richieste e addebiti
1. Ogni operazione asincrona mostra stato di caricamento ed errore contestuale e impedisce doppi invii dal medesimo modulo.
2. Per gli acquisti di buoni: `richiesta_id` univoco, persistito a DB con migration **additiva**, così un retry non addebita due volte. L'invio ripetuto della medesima richiesta con parametri difformi non è valido. Fallback legacy temporaneo solo per integrazioni precedenti.
3. La schermata Scuola associa ciascun elenco a `classe/data` verificati. Cambiare filtro invalida le azioni sino al nuovo caricamento; risultati obsoleti scartati.
4. Nessun job contabile automatico attivato tramite deploy frontend. Conferma esplicita prima delle esecuzioni manuali.

## B — Componenti comuni e design
1. Sistema visivo CSS coerente, mobile-first, spaziature, tipografia, pulsanti, stati, accessibilità.
2. Feedback di salvataggio con area live, caricamento e retry. Messaggi di errore vicino al modulo, non solo toast a scomparsa.
3. Finestre di dialogo e moduli con gestione focus/tastiera, conferma delle modifiche delicate.
4. Linguaggio quotidiano per i ruoli e stato sempre espresso anche a parole, non solo a colori.

## C — Famiglie
1. Calendario mensile **completo** ottenuto dal calendario reale per plesso, inclusi giorni non attivi e senza righe di presenza; nessun salvataggio durante la consultazione.
2. Stati separati: giorno senza mensa, pasto previsto, pasto non previsto, storico non disponibile. Non confondere assenza con giorno di chiusura.
3. Selezione bambino/giorno; richieste contestuali (assenza, ripristino, pasta bianca, annullamento) e visualizzazione della conferma.
4. Saldo/consumi presentati con importi e diciture comprensibili; nessun acquisto o movimento creato dal calendario.

## D — Scuola / Catering / Comune / Amministrazione
1. Scuola: indicazione chiara della classe/data in lettura, numeri riepilogativi e correzioni motivate, senza addebiti inattesi.
2. Catering: conteggio per scuola, data, classi/tipi di menu e richieste di pasta in bianco; distinzione provvisorio/confermato; niente dati economici.
3. Comune: scheda famiglia e moduli economici leggibili, riepilogo prima di confermare acquisti e correzioni; protezione retry server.
4. Admin: calendari/configurazioni più leggibili, risultati job riassunti per utente anziché JSON tecnico, operazioni distruttive precedute da conferma.

## Verifiche obbligatorie
- Lint PHP e JS, test calendario/crediti, staging HTTP con dati sintetici, regressione richieste asincrone fuori ordine.
- Acquisto con medesima `richiesta_id` ripetuto due volte => **un solo movimento e un solo aumento saldo**.
- Calendario famiglia ottobre 2026: il 19 attivo infanzia/secondaria, il 20 solo infanzia; pagina mensile completa.
- Staging via Docker senza reset del volume. Controllare con l'utente i cinque ruoli su desktop e telefono prima di qualsiasi rilascio.

## Distribuzione
1. Eseguire prima le migrazioni additive sulla copia staging, poi eseguire i test, verificare con il Comune e predisporre backup/rollback.
2. In produzione nessuna azione in questo ciclo; rilascio futuro con procedura separata approvata.
3. **Pacchetto E escluso**: stampa cucina, tour avanzati, funzioni aggiuntive e nuove integrazioni.

## Registro implementazione (branch dev)

| Pacchetto | Modifiche realizzate | Componenti principali |
|---|---|---|
| A | ID unico per acquisti e rettifiche, chiave UNIQUE nullable; verifica importo atteso; blocco del salvataggio su dati scuola non aggiornati; eliminazione delle risposte asincrone obsolete | `005_idempotenza_acquisti.sql`, `CreditoService`, `CreditoRepository`, `OperatoreController`, `scuola.js`, `operatore.js` |
| B | Nuovi componenti per loading/successo/errore; dialoghi di conferma; disabilitazione dei controlli durante le richieste; miglioramento mobile/focus/tabs; schede e notifiche | `api.js`, `app.css`, tutti i template e gli script di ruolo |
| C | Calendario completo del mese, distinzione dei giorni senza mensa, stato non disponibile per date passate non registrate, azioni sul giorno, anteprima della data nel riepilogo, messaggi persistenti | `PresenzaService`, `genitore.js`, `genitore.php` |
| D | Riepilogo pasti Scuola, totali Catering e ora aggiornamento; ricerca e scheda famiglia con caricamento; acquisti/crediti con conferme; sintesi degli esiti del job e panoramica Admin | `scuola.js`, `catering.js`, `operatore.js`, `admin.js` e rispettivi template |

### Checklist di accettazione — dati e comportamento
- [x] Lato server, due acquisti con stessa chiave e stessi dati producono un solo movimento.
- [x] Lato server, due rettifiche con stessa chiave producono un solo movimento.
- [x] Il server rifiuta la conferma se l'importo atteso differisce dal prezzo applicato.
- [x] Le viste Scuola/Catering scartano le vecchie risposte di caricamento.
- [x] Il calendario familiare restituisce ogni giorno del mese senza creare presenze.
- [x] Etichette, notifiche, conferme e dimensioni dei controlli uniformate.
- [ ] Collaudo visivo umano su Mac, iPhone/Android (375 px e 768 px) e tastiera.
- [ ] Collaudo funzionale completo con i cinque ruoli e approvazione del Comune.
- [ ] Backup e dry-run della procedura di rilascio in una copia **non produttiva**.
- [ ] Autorizzazione al rilascio e test post-rilascio read-only in produzione.

### Criteri go/no-go
Bloccare la pubblicazione se anche uno solo dei seguenti casi non è soddisfatto:
- Un acquisto/una rettifica ripetuti producono variazioni economiche duplicate.
- Un'operazione di una scuola riguarda una data o un alunno diverso da quello mostrato.
- Una giornata chiusa o senza servizio permette modifiche dalla famiglia.
- La cucina non vede correttamente sezioni a zero pasti o non distingue le richieste di pasta in bianco.
- La nuova interfaccia non si usa da smartphone nelle operazioni Genitore/Scuola.
- Compare qualsiasi nuova comunicazione automatica verso i genitori o lo scheduler viene riattivato involontariamente.

### Sequenza di rilascio futuro (richiede approvazione separata)
1. Concordare finestra di intervento e verifica dello stato reale del cron.
2. Backup del codice e dump MariaDB, verificandone integrità e ripristinabilità.
3. Applicare la migration `005_idempotenza_acquisti.sql` in modo additivo, con log esecuzione.
4. Distribuire versione codice testata e verificare accesso, calendari e soli endpoint di lettura.
5. Verificare manualmente ruolo per ruolo e mantenere lo scheduler spento salvo decisione separata.
6. In caso di regressioni: riportare il codice precedente; mantenere la colonna/indice additivi senza cancellare movimenti o saldi. Nessun rollback distruttivo sul database.

L'esito dei test automatici **non sostituisce** la verifica visiva e la conferma del Comune.
