# Mensa Caltavuturo — piano di verifica e rilascio di `dev`

Ultimo aggiornamento: 15 ottobre 2026. **Preparazione del rilascio, non autorizzazione a eseguirlo.**

Stato: **NO-GO** fino al collaudo funzionale/visivo con i cinque profili, alla
verifica delle anagrafiche reali dei plessi, al backup e ripristino provato
su copia isolata e all'autorizzazione esplicita del Comune e del referente
al rilascio. `main`, database `mensa_db`, cron e VPS di produzione restano
invariati durante questo lavoro.

I test automatici aggiornati si trovano nelle pipeline di GitHub Actions
del ramo `dev`. Il loro esito positivo riguarda esclusivamente database
sintetici, non una migrazione già provata sui dati di produzione.

## Situazione conosciuta

- Repository sorgente Comune: `ComuneDiCaltavuturo/Mensa`, branch `main` (non modificato).
- Fork di lavoro: `Crooch/Mensa`, branch `dev`.
- VPS: applicazione `/srv/www/mensa/app`; NON utilizzare la directory di produzione come ambiente di sviluppo.
- Cron utente `mensa`: sospeso dalla manutenzione del 14/10/2026, mantenere sospeso.
- Database produzione `mensa_db`: già riallineati 112 ticket su 70 pool con movimenti compensativi; mantenuti gli originali. Totale saldo verificato: +10.
- Backup DB post-riallineamento verificato: `/var/backups/mensa/post_riallineamento_20261014_151702.sql.gz`.
- Nessuna migration del branch `dev` è stata finora applicata in produzione.

## Novità introdotte

1. Calendarizzazione per plesso; data di avvio 19/10/2026, giorni del servizio e chiusure/aperture straordinarie, con weekend e festività nazionali esclusi.
2. Protezione scheduler e comando Admin: blocco prima della data di avvio, fuori servizio, prima del cutoff; lock MariaDB per evitare sovrapposizioni e controllo idempotenza.
3. Genitori: scelta di una data futura per presenza/assenza; pasta in bianco come richiesta distinta per figlio e giorno.
4. Catering: conteggi per plesso, tipologia e classe/sezione (anche sezioni con zero pasti), prima/dopo job, elenco nominativo delle sole richieste pasta in bianco per bambini presenti.
5. Report giorni fuori calendario: non presentare consumi del periodo preliminare come pasti da preparare.

## Migrazioni necessarie, da eseguire nell'ordine soltanto dopo approvazione

**Non eseguire `001_schema.sql` sul database comunale esistente.**
Lo script di installazione generico non sostituisce la procedura incrementale
per la produzione. Le modifiche da prevedere sono le seguenti:

| N. | File | Effetto sul database esistente |
|---|---|---|
| 002 | `002_calendario_mensa.sql` | Calendari per plesso ed eccezioni; inserimento iniziale limitato ai nomi previsti |
| 003 | `003_richieste_pasta_bianco.sql` | Tabella richieste giornaliere indipendenti dalla presenza |
| 004 | `004_audit_operazioni_presenze.sql` | Registro operazioni su presenze/richieste |
| 005 | `005_idempotenza_acquisti.sql` | Colonna facoltativa e indice univoco per impedire doppi acquisti/rettifiche |
| 006 | `006_menu_settimanali.sql` | Modelli, revisioni e assegnazioni settimanali |
| 007 | `007_storico_nomi_modelli.sql` | Nome del modello conservato nelle revisioni |
| 008 | `008_foto_menu_settimanali.sql` | Fotografie per giorno conservate nelle revisioni |
| 009 | `009_intervalli_calendario.sql` | Estensione additiva delle eccezioni a intervalli di date (`giorno_fine` nullable); i record a giorno singolo restano invariati |

Le migrazioni 002–009 vengono eseguite **due volte** sulla base sintetica
di GitHub CI, per verificarne la ripetibilità. La CI controlla anche che
movimenti e saldi fittizi *preesistenti* non cambino. Ciò non sostituisce
la prova su una copia controllata del database comunale: prima di approvare,
verificare struttura reale, collazioni, nomi plessi, dati preesistenti e
compatibilità MariaDB. Le operazioni DDL non vanno considerate reversibili
con un semplice `ROLLBACK` SQL: occorre un backup ripristinabile.

### Controllo di configurazione obbligatorio

Dopo la migration 002, verificare su STAGING che esistano due righe in `calendari_servizio`:

- Infanzia Via Catania: lunedì–venerdì, inizio 19/10/2026.
- Secondaria di I grado: lunedì e giovedì, inizio 19/10/2026.

Scuola primaria: **nessun calendario abilitato**. Un plesso non configurato non esegue addebiti (fail-closed).

## Sequenza consigliata (nessun passo di produzione eseguito)

1. Conservare una versione/tag univoco del commit `dev` collaudato; verificare
   GitHub Actions (PHP, HTTP Docker, Chromium) e l'assenza di nuovi errori.
2. Completare la verifica manuale sui cinque ruoli: Admin, Operatore, Scuola,
   Catering e Genitore, sia da desktop sia da dispositivo mobile. Per Catering
   includere il menù settimanale, la variazione giornaliera, le foto, l'annullamento
   e le pagine con oltre dieci variazioni.
3. Verificare i nomi effettivi dei plessi e le regole settimanali sul sistema
   comunale, **in sola lettura**: la migration 002 non crea calendari quando
   i nomi non corrispondono esattamente.
4. Preparare una **copia isolata e protetta** del database, senza caricarla
   su repository o CI pubbliche. Fare un backup della copia e verificare
   che sia ripristinabile.
5. Su questa copia eseguire 002, 003, 004, 005, 006, 007, 008 e **009** in
   ordine. Confrontare prima e dopo: numero e saldo dei pool, numero/tipo/
   delta/importi dei movimenti, presenze, assegnazioni e utenti;
   verificare struttura e indici creati, nonché la coerenza dei calendari.
6. Pubblicare sulla sola installazione di staging il codice candidato;
   verificare login, confini di accesso fra ruoli e plessi, menù di giornata,
   foto e tutte le operazioni sui dati fittizi. Tenere la posta esterna
   disabilitata e lo scheduler fermo.
7. Provare il **ripristino della copia di staging** e della versione precedente
   del codice, documentando tempi e risultato. Per database modificato non
   presumere che il solo ritorno al vecchio codice sia sempre sufficiente.
8. Condividere checklist ed esiti con i referenti comunali e attendere
   l'autorizzazione esplicita prima di qualsiasi intervento su `main`,
   VPS, database o cron di produzione.
9. Solo durante una futura finestra autorizzata: effettuare nuovi backup
   verificati, applicare il piano testato e controllare inizialmente endpoint
   non distruttivi. **Non riattivare il cron automaticamente**; farlo soltanto
   dopo una decisione specifica sui conteggi e sulle condizioni di avvio.
10. In caso di regressioni: mettere in sicurezza le elaborazioni, usare
    la procedura di ripristino collaudata e riconciliare contabilità/presenze
    prima di riprendere le attività.

## Scenari minimi di collaudo

- 14/10: job automatico e manuale NON addebitano né congelano.
- 19/10 (lunedì): servizio attivo per infanzia e secondaria; prima delle 09:15 il job non parte; dopo cutoff deve eseguire una volta.
- 20/10 (martedì): infanzia attiva, secondaria esclusa.
- 22/10 (giovedì): entrambi attivi.
- 24–25/10 (weekend): nessun servizio nel calendario ordinario; una **apertura straordinaria esplicita**, invece, prevale sul weekend e abilita il servizio. Controllare il comportamento di job, presenze e conteggi in entrambi i casi.
- 08/12: festività nazionale esclusa **in assenza di eccezioni**; un'apertura straordinaria esplicita abilita il servizio anche nel festivo.
- Chiusura straordinaria di una data futura: niente addebito né pasti.
- Periodi straordinari: intervalli 12–14 e 14–16 con lo stesso stato ammessi; intervallo sovrapposto 13–16 rifiutato; stesso confine tra apertura e chiusura rifiutato. Nessuna modifica ai periodi già salvati quando l'API rifiuta la richiesta.
- Admin «Verifica il servizio per una data»: risultati per tutte le scuole e classi dell'anno selezionato, ricerca per scuola o classe, filtro aperte/chiuse, paginazione, assenza di dati degli alunni. La verifica non deve produrre addebiti.
- Genitore: dichiara oggi e in una data futura; verifica che giorni fuori servizio vengano rifiutati.
- Pasta in bianco: due figli e due date, richiedi/annulla in modo indipendente; il catering vede nome e classe SOLO del figlio presente con richiesta attiva.
- Infanzia: classi A/B/C/D mostrate con totali separati, anche con zero presenti.
- Prima del job: conteggio presenti calcolato anche per i bambini senza `presenze_giornaliere`.
- Doppia invocazione cron/comando manuale: non genera doppio consumo.
- Doppia dichiarazione uguale del genitore: un solo evento audit. Cambio di stato futuro: entrambi gli eventi registrati.
- Tentativo di modifica dopo cutoff: rifiuto server senza ricalcoli economici, anche prima del passaggio del job.
- Scuola senza plesso assegnato: accesso negato. Scuola assegnata: azioni limitate al plesso.
- Scuola: conferma motivata di un pasto servito dopo il cutoff, una sola registrazione contabile e nessun rimborso automatico in caso di successiva segnalazione di assenza.
- Integrità contabile: `SUM(pool_crediti.saldo) = SUM(movimenti_crediti.delta)`, acquisti e rettifiche originari invariati; nessuna modifica ai 112 movimenti precedenti e ai 70 storni.
- Consegna: niente nomi, dati economici o crediti dei familiari fuori dalle API autorizzate.

## Controlli obbligatori pre-rilascio e blocco dell'avvio

**Al 15/10/2026 il cron di produzione risulta sospeso e nessuna migration 002–009 risulta applicata al DB `mensa_db`.** La data di avvio del servizio prevista per il 19/10/2026 non costituisce un'autorizzazione a distribuire codice o riattivare elaborazioni. Concordare con il Comune una procedura operativa per le giornate eventualmente gestite prima del rilascio; non creare contabilizzazioni retroattive non verificate.

### Preflight di sola lettura

- Identificare esattamente commit, ambiente e utente operativo; controllare stato cron **dall'host**, perché `GET /api/job/stato` non attesta che lo scheduler esterno sia acceso.
- Verificare i nomi e gli ID dei plessi esistenti, gli anni scolastici delle classi e i cutoff applicati. La migration 002 usa **corrispondenze esatte dei nomi**: se divergono, fermarsi e concordare la configurazione, senza modifiche automatiche ai dati.
- Registrare conteggi e checksum del dump protetto; confrontare `pool_crediti`, `movimenti_crediti`, `presenze_giornaliere`, `esecuzioni_job` e le assegnazioni prima/dopo la prova su copia isolata.
- Verificare separatamente i file in `storage/menu`: il backup del solo DB non è sufficiente a ripristinare le fotografie.
- Accertare che i test di sicurezza fra ruoli/plessi e i test di calendario sulle festività e sulle eccezioni abbiano esito positivo.

### Job `in_corso` e recupero dopo errore

In caso di stato `in_corso` persistente o `errore`, **non** eliminare la riga di esecuzione, non riavviare ripetutamente il job e non correggere direttamente crediti o presenze per farlo passare. Prima esaminare log, record di presenza congelati, movimenti di consumo per bambino/data, stato dei solleciti e bilanci dei pool. Il codice impedisce alcuni duplicati ma il recupero va verificato come scenario dedicato su una copia protetta. Se non è possibile riconciliare completamente i dati, mantenere il job sospeso e richiedere una valutazione tecnica prima di qualsiasi ripresa.

### Esito delle verifiche

Registrare per ciascun scenario `PASS`, `FAIL` o `NON PROVATO` con data, ruolo, dispositivo, ambiente, versione/commit e prova sintetica. L'esito automatico della CI **non equivale** al collaudo manuale. Un solo caso critico `FAIL` o `NON PROVATO` (contabilità, sicurezza ruoli, calendario, ripristino DB) mantiene lo stato **NO-GO**.

## Ambiti da verificare con attenzione

- La CI verifica sintassi, regressioni, idempotenza degli acquisti, migrazioni 002–008 ripetute, flussi HTTP e browser sintetico per i cinque ruoli. Rimangono i collaudi manuali funzionali, di accessibilità e su dispositivi fisici.
- Le impostazioni settimanali oggi sono per plesso, non versionate per anno scolastico; evitare modifiche retroattive dopo le prime esecuzioni finché non si introduce una storia delle configurazioni.
- Se un processo crasha lasciando il job in stato `in_corso`, la protezione blocca ulteriori tentativi automatici per lo stesso giorno: accertare manualmente l'esito prima di gestire un recupero, senza reinviare addebiti.

## Principio gestionale da confermare con il Comune

Il ticket viene contabilizzato in base al pasto previsto al cutoff; una presenza effettiva accertata dalla scuola, quando non prevista, genera un solo ticket. Le assenze comunicate a cutoff superato non determinano rimborso automatico. Confermare che queste regole coincidano con la disciplina comunale di prenotazione e pagamento prima del rilascio.

## Regola confermata: pasta in bianco

La famiglia puo richiedere liberamente la pasta in bianco per il singolo figlio in un giorno di effettivo servizio mensa, senza motivazione, prescrizione o approvazione da parte della scuola o del Comune. La richiesta puo essere annullata autonomamente. Restano validi soltanto i limiti operativi generali: data odierna prima del cutoff oppure una data futura di mensa. La scelta non cambia la tipologia dietetica abituale e non determina alcuna variazione del numero o del valore dei ticket. Il catering visualizza per il giorno interessato la richiesta e il nome/classe del bambino, se presente.
