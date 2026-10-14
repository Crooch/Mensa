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
