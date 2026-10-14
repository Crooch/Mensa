# Mensa Caltavuturo — piano di verifica e rilascio di `dev`

Data preparazione: 14 ottobre 2026. **Non eseguire i passi di rilascio senza collaudo.**

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

## Migrazioni, da applicare nell'ordine

- `database/migrations/002_calendario_mensa.sql`: crea `calendari_servizio` ed `eccezioni_calendario`, inserisce le regole iniziali per infanzia e secondaria SOLO se il nome del plesso coincide.
- `database/migrations/003_richieste_pasta_bianco.sql`: crea `richieste_pasta_bianco` con FK verso `bambini` e `utenti`.

Entrambe sono **additive** e testate per doppia applicazione su un database vuoto di CI; NON disabilitano FK e NON contengono `TRUNCATE` o cancellazioni di movimenti. Non applicare `001_schema.sql` al DB di produzione durante questa manutenzione.

### Controllo di configurazione obbligatorio

Dopo la migration 002, verificare su STAGING che esistano due righe in `calendari_servizio`:

- Infanzia Via Catania: lunedì–venerdì, inizio 19/10/2026.
- Secondaria di I grado: lunedì e giovedì, inizio 19/10/2026.

Scuola primaria: **nessun calendario abilitato**. Un plesso non configurato non esegue addebiti (fail-closed).

## Sequenza consigliata

1. Preparare ambiente isolato (staging) con dati di prova **sintetici**. Non esportare anagrafiche di minori in servizi pubblici di CI o repository.
2. Creare backup dello staging. Applicare 002 e 003, nell'ordine, sul solo database staging.
3. Pubblicare il codice del branch `dev` su staging; configurare SMTP per **non inviare email reali**.
4. Eseguire `php tests/calendario.php` e il lint PHP. Verificare GitHub Actions `PHP quality checks`.
5. Effettuare test applicativi con utenze Admin, Genitore, Catering e due classi di prova; verificare API e interfacce.
6. Documentare risultati e predisporre Pull Request verso `main` solo dopo revisione/autorizzazione di Corrado.
7. Eseguire nuovo backup di produzione e congelare le modifiche operative al momento del deploy.
8. Applicare le sole migration 002/003 e poi i file rilasciati, con accessi e permessi coerenti. Verificare collegamenti fra config DB e codice.
9. **Cron ancora sospeso** fino a conferma delle condizioni del 19/10 e del cutoff; verificare manualmente che il job NON addebiti fuori calendario prima di ripristinare la pianificazione.
10. Dopo l'avvio controllare esecuzioni, conteggi, email, saldi e stato presenze; in caso di problemi fermare il job prima di correggere.

## Scenari minimi di collaudo

- 14/10: job automatico e manuale NON addebitano né congelano.
- 19/10 (lunedì): servizio attivo per infanzia e secondaria; prima delle 09:15 il job non parte; dopo cutoff deve eseguire una volta.
- 20/10 (martedì): infanzia attiva, secondaria esclusa.
- 22/10 (giovedì): entrambi attivi.
- 24–25/10: nessun consumo anche con apertura straordinaria tentata.
- 08/12: festivo nazionale escluso.
- Chiusura straordinaria di una data futura: niente addebito né pasti.
- Genitore: dichiara oggi e in una data futura; verifica che giorni fuori servizio vengano rifiutati.
- Pasta in bianco: due figli e due date, richiedi/annulla in modo indipendente; il catering vede nome e classe SOLO del figlio presente con richiesta attiva.
- Infanzia: classi A/B/C/D mostrate con totali separati, anche con zero presenti.
- Prima del job: conteggio presenti calcolato anche per i bambini senza `presenze_giornaliere`.
- Doppia invocazione cron/mandato manuale: non genera doppio consumo.
- Integrità contabile: `SUM(pool_crediti.saldo) = SUM(movimenti_crediti.delta)`, acquisti e rettifiche originari invariati; nessuna modifica ai 112 movimenti precedenti e ai 70 storni.
- Consegna: niente nomi, dati economici o crediti dei familiari fuori dalle API autorizzate.

## Ambiti da verificare con attenzione

- Non è ancora stato effettuato un collaudo funzionale completo su database staging con utenti reali simulati.
- Le impostazioni settimanali oggi sono per plesso, non versionate per anno scolastico; evitare modifiche retroattive dopo le prime esecuzioni finché non si introduce una storia delle configurazioni.
- Se un processo crasha lasciando il job in stato `in_corso`, la protezione blocca ulteriori tentativi automatici per lo stesso giorno: accertare manualmente l'esito prima di gestire un recupero, senza reinviare addebiti.
