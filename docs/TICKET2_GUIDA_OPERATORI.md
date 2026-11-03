# Ticket 2 — guida operativa

Versione candidata, collaudata su dati sintetici. Le funzioni saranno disponibili nell'applicazione comunale soltanto dopo il rilascio autorizzato. Nessuna operazione sui casi reali è stata eseguita durante lo sviluppo.

Le denominazioni riportate sono quelle delle interfacce implementate e verificate. Su telefono la barra delle schede scorre orizzontalmente; le tabelle si adattano oppure scorrono nel proprio riquadro.

## Comune — accesso con ruolo Operatore comunale

### Cercare e gestire molte famiglie

La lista mostra inizialmente **10 famiglie attive per pagina**, ordinate per cognome e nome. Con 100 famiglie ci sono 10 pagine; **Per pagina** permette di mostrarne 25 o 50. **Precedente** e **Successiva** scorrono i risultati e il contatore indica il totale effettivo, anche oltre 200 famiglie.

La ricerca per nome, cognome, nome completo, telefono o email riguarda **l’intero archivio**, non soltanto la pagina visibile. Si aggiorna durante la digitazione; si può anche premere **Cerca** o Invio. Una nuova ricerca o un cambio di filtro riparte dalla prima pagina. **Mostra** consente di scegliere famiglie attive, disattivate oppure tutte.

Su telefono e tablet, **Apri** mostra direttamente la scheda della famiglia. **Torna all’elenco** riporta alla stessa pagina, mantenendo ricerca e filtri. **Registra buoni acquistati** rimane subito visibile; **Altre operazioni** apre modifica dati, aggiunta figlio, verifica/disattivazione, tariffa agevolata e correzione saldo. Su desktop elenco e scheda restano affiancati.

### T01 — Verificare associazioni e disattivare un genitore

Percorso: **Ufficio comunale → Famiglie → Cerca → Apri → Verifica associazioni e disattivazione**.

1. Cercare il genitore per cognome, telefono o email e premere **Apri**. Per cercare anche i genitori disattivati scegliere **Tutte le famiglie** oppure **Famiglie disattivate** nel filtro Mostra.
2. Confrontare nominativo, telefono, email e utenza. Su telefono/tablet aprire **Altre operazioni**. Premere **Verifica associazioni e disattivazione**.
3. Nel riepilogo controllare ID della famiglia/utenza, alunni e relativi ID, classi/plessi, date delle assegnazioni, figli attivi, posizioni con saldo non nullo e numero di movimenti conservati.
4. Esaminare **Possibili omonimie**. La corrispondenza esatta di nome/cognome è un indizio: non dimostra che due record appartengano allo stesso alunno.
5. Se compare **Disattiva genitore**, compilare **Motivazione**, premere il pulsante e confermare il nominativo e l'ID nella finestra di conferma.
6. Verificare la dicitura **Disattivata** nella scheda. Lo storico rimane consultabile; accesso e token del genitore sono revocati. Ripetere la richiesta non crea una seconda disattivazione.

Richiedere assistenza se sono presenti figli attivi, saldi non nulli, utenza condivisa o non di ruolo Genitore, oppure associazioni dubbie. Non cancellare figli o spostare saldi per superare il blocco. Fusione di anagrafiche, trasferimenti e ricostruzioni contabili non sono automatizzati.

### T07 — Verificare buoni e associazioni

Percorso: **Famiglie → Cerca → Apri**.

1. Controllare **Buoni disponibili** per ciascun plesso.
2. Esaminare ogni figlio, **Classe attuale** e **Storico classi**.
3. Aprire **Acquisti, pasti e correzioni dei buoni** per verificare i movimenti conservati.
4. Usare **Verifica associazioni e disattivazione** per gli identificativi, le assegnazioni storiche e le possibili omonimie.
5. Se l'associazione è corretta, il Genitore vede automaticamente tra le posizioni correnti soltanto i plessi dei figli attivi; gli altri pool sono in **Posizioni buoni storiche**. Il Comune continua a vedere l'intera situazione.

Questa verifica non cancella o azzera saldi. Un'associazione errata con presenze o movimenti richiede assistenza tecnica; non usare una rettifica economica solo per nascondere una casella.

### T08 — Correggere nome e cognome di un alunno

Percorso: **Famiglie → Cerca → Apri → Figli → Modifica anagrafica**.

1. Nel dialogo verificare ID e nominativo dell'alunno, ID e nome della famiglia, plesso e classe.
2. Correggere **Nome** e/o **Cognome**, senza ricreare l'alunno.
3. Compilare **Motivazione** e premere **Salva anagrafica**.
4. Confermare il riepilogo con nominativo precedente e nuovo.
5. Verificare il nome aggiornato nella scheda: ID, associazioni, presenze e buoni restano gli stessi.

I campi accettano da 1 a 80 caratteri. Se compare «I dati sono cambiati», riaprire la scheda e verificare la modifica effettuata dall'altro operatore. Duplicati o trasferimenti tra famiglie richiedono assistenza; il Genitore non può eseguire questa correzione.

### T06 — Inserire chiusure straordinarie

Percorso: **Calendario e servizio → Plesso → Apri configurazione → Chiusure straordinarie**.

1. Selezionare il plesso e premere **Apri configurazione**. Controllare nome e ID riportati sopra i moduli.
2. Compilare **Dal**, **Al (compreso)** e **Motivazione**.
3. Premere **Inserisci chiusura** e confermare plesso, date ed estremi inclusi.
4. Verificare il messaggio **Chiusura registrata e riletta** e la presenza dell'intervallo in **Periodi registrati**.
5. Ripetere per gli altri plessi interessati. La configurazione riguarda soltanto quello selezionato.

Per il Ticket 2, l'interpretazione dell'anno scolastico 2026/27 è: 7 dicembre 2026; 23 dicembre 2026–8 gennaio 2027; 25–30 marzo 2027. Il Comune deve verificare queste date prima di inserirle. Non sono state precaricate.

Sono ammesse solo chiusure future. Sovrapposizioni incompatibili sono rifiutate; la ripetizione dello stesso intervallo di chiusura restituisce il periodo già esistente. **Modificare o eliminare una chiusura futura:** in **Periodi registrati** premere **Modifica / elimina** sulla chiusura del plesso. Correggere **Dal** e **Al (compreso)**, indicare la **Motivazione della modifica o eliminazione**, premere **Salva chiusura** e confermare. Per rimuoverla usare **Elimina chiusura**, motivare e confermare: torna applicabile il calendario ordinario. Controllare il periodo riletto o la sua scomparsa. **Annulla** chiude il modulo senza salvare. Non sono modificabili da questa funzione aperture, chiusure già iniziate o passate. Le sovrapposizioni e i dati cambiati da un altro operatore sono rifiutati. Le elaborazioni storiche non vengono riscritte.

### T09 — Configurare il fido dell'Infanzia

Percorso: **Calendario e servizio → Plesso → Apri configurazione → Impostazioni del servizio**.

1. Selezionare il plesso dell'Infanzia verificandone nome e ID.
2. Se non configurato, scegliere **Ordine scolastico: infanzia**.
3. Nel campo **Fido concesso (buoni)** inserire **20**: il valore positivo rappresenta la soglia di **-20 buoni**.
4. Lasciare invariata l'eventuale data di sospensione se non si intende modificarla.
5. Compilare **Motivazione**, premere **Salva impostazioni** e controllare i valori precedenti/nuovi nella conferma.
6. Dopo la conferma verificare **Impostazioni salvate e rilette** e il valore 20.

Non inserire -20: il backend lo rifiuta. La modifica non riscrive consumi o saldi e non impedisce il pasto agli alunni con saldo negativo. L'eventuale attivazione degli invii di sollecito è separata.

### T10 — Configurare la conclusione del servizio

Percorso: **Calendario e servizio → Plesso → Apri configurazione → Impostazioni del servizio**.

1. Controllare plesso, ordine scolastico e fido.
2. Compilare **Primo giorno di sospensione**. Per sospendere dal 1° giugno 2027, indicare **01/06/2027**: il 31 maggio è l'ultimo giorno potenzialmente operativo secondo il calendario.
3. Compilare **Motivazione**, premere **Salva impostazioni** e confermare il riepilogo.
4. Verificare che la data sia stata riletta correttamente.
5. In **Riepilogo pasti**, selezionare quella data e il plesso: compare **Fuori servizio** e nessuna nuova previsione di pasti, anche in presenza di un'apertura straordinaria.

Il Comune deve confermare che il ticket richieda la fine dell'erogazione mensa, non lo spegnimento degli accessi, prima di configurare la data reale. Nessuna data è stata precaricata. Storico e operazioni amministrative restano disponibili; il Genitore conserva la consultazione. Per riaprire un servizio la cui sospensione è già iniziata occorre assistenza tecnica.

### T04 — Programmare i pasti del personale

Percorso: **Pasti del personale → Programmazione abituale**.

Selezionare una sola volta **Scuola** e **Data di riferimento**. La schermata si aggiorna automaticamente; **Mostra pasti personale** consente anche un aggiornamento manuale. Due pulsanti separano **Programmazione abituale** e **Pasti della data scelta**.

La programmazione mostra sette schede, con totale e categorie per ogni giorno della settimana. **Dettaglio** espande le quantità per menù. Le regole mostrate sono quelle in vigore alla data di riferimento: non rappresentano i pasti effettivi delle singole date, che dipendono anche dal calendario e dalle variazioni. **0 pasti** indica una quantità esplicitamente impostata; **non impostato** indica assenza di regole. Le **Modifiche successive alla data scelta** riportano decorrenza, giorni e voci che cambieranno.

1. Aprire **Modifica programmazione abituale**. La scuola è quella già selezionata. Compilare **Da quando**, **Categoria**, **Menù**, **Pasti per ciascun giorno selezionato**, **In quali giorni** e **Motivazione**.
2. La decorrenza può essere oggi o futura. Selezionare insieme tutti i giorni interessati (per esempio lunedì–venerdì). La quantità si ripete automaticamente ogni settimana fino a una nuova regola: non va reinserita giorno per giorno o settimana per settimana.
3. Premere **Salva ricorrenza** e confermare plesso, decorrenza, giorno, categoria, menù e quantità.
4. Ripetere soltanto per le altre categorie/menù o per giorni con quantità diverse. La programmazione si applica soltanto quando il calendario prevede servizio.
5. Dopo il salvataggio la vista viene riletta automaticamente: controllare le schede dei giorni oppure le modifiche future. Per verificare un pasto effettivo scegliere la data e aprire **Pasti della data scelta**.
6. Per terminare una voce, salvarla con **Quantità: 0** dalla nuova decorrenza. Non riscrivere le ricorrenze pregresse.

Quantità indicate nel ticket, da verificare e inserire dal Comune:

| Plesso | Giorni di servizio | Categoria | Quantità |
|---|---|---|---:|
| Infanzia | Lunedì–venerdì | Insegnanti | 9 |
| Infanzia | Lunedì–venerdì | ATA | 3 |
| Infanzia | Lunedì–venerdì | Assistenti mensa | 2 |
| Secondaria | Secondo calendario effettivo | Insegnanti | 5 complessivi |
| Secondaria | Secondo calendario effettivo | ATA | 3 |
| Secondaria | Lunedì e giovedì | ASACOM | 3 |

Il lunedì della Secondaria, uno dei cinque insegnanti è vegetariano: inserire **4 insegnanti Standard + 1 insegnante Vegetariano**, non 5+1. Se la tipologia Vegetariano manca, richiederne la predisposizione all'Admin tramite la gestione tipologie esistente. Verificare i giorni effettivi del servizio prima di creare le altre ricorrenze. Nessuna anagrafica nominativa del personale è richiesta.

Per correggere una giornata: **Pasti del personale → Scuola → Data di riferimento → Pasti della data scelta → Modifica solo questa data**; modificare le quantità totali necessarie (non la differenza), compilare **Motivazione**, premere **Salva pasti del giorno**, controllare il totale nella conferma e confermare. Verificare il messaggio e i valori riletti. Il personale non ha cutoff. Scuola, Comune e Admin possono correggere oggi e le date future di servizio; soltanto Comune e Admin possono rettificare il passato. La variazione prevale sulle regole per quella sola data. Quando compare **Ripristina programmazione**, indicare una motivazione e premere il pulsante per rimuovere la variazione di oggi o futura e tornare alle regole abituali. L'operazione modifica solo il personale, senza consumi o rimborsi di buoni familiari.

Le **Modifiche e motivazioni della programmazione** e le **Modifiche e motivazioni di questa data** mostrano la motivazione salvata, data/ora, profilo che ha operato e quantità interessate. L'ultima modifica è visibile; le precedenti si espandono. Sono mostrate le ultime 20 modifiche per ciascun elenco; le altre restano conservate nello storico. Comune/Admin e Scuola (solo il proprio plesso) possono consultarle. Il Catering consulta le quantità, senza motivazioni.

Salvare quantità invariate non crea una variazione giornaliera e non registra una nuova motivazione: il messaggio lo indica esplicitamente. La data continua a seguire la programmazione abituale. Una variazione già presente, invece, resta prioritaria anche dopo una modifica alle regole: per oggi/futuro usare **Ripristina programmazione** quando si vuole tornare ai valori abituali. Le registrazioni pregresse non vengono cancellate automaticamente dall'aggiornamento. Se un menù già registrato è stato disattivato, il modulo conserva le sue quantità e lo indica come **non più attiva**; non va aggiunto a nuove voci.

Se compare «La programmazione è cambiata», ricaricare prima di salvare: un altro operatore ha aggiornato le regole dopo l'apertura della schermata.

Se compare «I conteggi sono cambiati», ricaricare la giornata prima di correggere. Per giornate pregresse prive di regole valide alla data e registrazioni compare **Quantità da impostare**: verificare le quantità effettive prima della rettifica. **Quantità abituali** indica i valori ricorrenti validi alla data; **Variazione per questa data** una correzione specifica; **Quantità già registrate** identifica le registrazioni della versione precedente. Quando la mensa non è prevista compare **Mensa non prevista**, con la causa effettiva del calendario (giorno senza mensa, festività, chiusura, servizio non ancora iniziato o terminato, scuola disattivata o calendario mancante). Non indica un guasto del sistema o personale assente. Il messaggio spiega perché non si possono inserire pasti; per le date passate della Scuola e per il Catering indica chi può effettuare le modifiche.

### T02 — Consultare ed esportare i report

Percorso: **Riepilogo pasti**.

1. Usare **Seleziona un mese** per compilare inizio/fine mese, oppure compilare **Dal** e **Al**. Per un giorno usare la stessa data. L'intervallo massimo è 93 giorni.
2. Selezionare eventualmente **Plesso** e **Ordine scolastico**; premere **Mostra riepilogo**.
3. Controllare **Riepilogo mensile per ordine scolastico**: una riga per mese e ordine, aggregando i plessi autorizzati. Sono indicati alunni elaborati, personale delle giornate elaborate, totale disponibile, pasti previsti, giornate da verificare e previste. Il totale disponibile somma solo giornate complete; se mancano dati o restano previsioni è segnalato come parziale. Il periodo indicato chiarisce se è stato selezionato un mese incompleto. Le giornate sono conteggiate per plesso. Il **Dettaglio giornaliero** permette di verificare i singoli valori.
4. Leggere lo stato di ciascuna riga: **Previsto**, **Confermato**, **Non elaborato**, **Fuori servizio**. Il dato non disponibile non equivale a zero.
5. Aprire **Dettaglio** per tipologie degli alunni e categorie/menù del personale.
6. Premere **Esporta CSV** per il dettaglio giornaliero oppure **Esporta Excel** per il dettaglio e il riepilogo mensile per ordine in due fogli. Si scarica esattamente il risultato caricato, con stati, totali, dettagli e note. Excel produce un file `.xlsx` con periodo e filtri, intestazioni evidenziate e bloccate, filtri sulle colonne, righe alternate, date e quantità native. I dati mancanti restano “Non disponibile”. Se si cambiano i filtri, i dati precedenti vengono rimossi e i pulsanti di esportazione disabilitati: premere nuovamente **Mostra riepilogo**. Dopo una modifica a personale, calendario o chiusure, tornando a **Riepilogo pasti** i dati vengono ricaricati con i filtri impostati. Anche le modifiche eseguite nel calendario Admin aggiornano le viste Personale e Calendario e servizio quando vi si ritorna, senza dover ricaricare l’intera pagina.

Se l'ordine compare come **Da configurare**, impostarlo in **Calendario e servizio**. Per «Non elaborato» verificare l’elaborazione degli alunni; per personale «Da configurare» verificare le regole o registrare le quantità. I conteggi del personale sono disponibili senza attendere il job degli alunni e restano rettificabili secondo i permessi. Non considerare definitivo un totale con giornate incomplete o previste. Non lanciare ripetutamente il job. Le tipologie delle presenze pregresse prive di fotografia compaiono come **Non storicizzata**, senza inventare la dieta originaria.

### T05 — Consultare i solleciti

Percorso: **Solleciti pagamento**.

1. Compilare **Dal**, **Al**, eventualmente **Famiglia** e **Plesso**.
2. Premere **Filtra solleciti**.
3. Consultare **Posizioni oltre fido**: si riferisce alla situazione corrente, filtrata per famiglia/plesso. Il periodo filtra invece lo storico degli invii.
4. Nella tabella verificare data, famiglia, plesso, saldo al momento dell'invio, esito, eventuale errore e destinatari registrati.
5. Se viene indicato il limite di 1.000 risultati, restringere il periodo o gli altri filtri.

La sezione non invia email e non abilita nuovi automatismi. L'attivazione, la configurazione SMTP e le prove sono disponibili al solo Admin in **Email e solleciti**. L'assenza di invii non prova che non esistano posizioni oltre fido.

## Scuole — accesso con ruolo Scuola

### T04 — Correggere i pasti del proprio personale

Percorso: **Pasti del personale**.

1. La vista **Programmazione abituale** mostra le regole della propria scuola e le modifiche future. Scegliere una data odierna o futura e aprire **Pasti della data scelta**.
2. Confrontare **Totale per questa data** e **Quantità abituale**, verificando origine, categorie e menù. Non occorre salvare se le quantità programmate sono corrette.
3. In caso di assenze o personale aggiuntivo, aprire **Modifica solo questa data**. Inserire i totali necessari nei gruppi per categoria; il totale da salvare si aggiorna mentre si scrive. Il totale vegetariano è compreso nel numero complessivo, non aggiuntivo.
4. Compilare **Motivazione**, premere **Salva pasti del giorno** e confermare data, plesso e totale.
5. Verificare **Pasti del personale salvati** e i valori riletti.

Per le date passate la schermata è in sola lettura: richiedere al Comune una rettifica motivata. Oggi e le date future non hanno limite orario, anche se il job degli alunni è già stato eseguito. Nei giorni senza servizio non si inseriscono nuovi pasti. **Ripristina programmazione** rimuove una variazione odierna o futura, con motivazione e conferma, senza cambiare le regole settimanali. Una Scuola priva di plesso assegnato deve rivolgersi all'Admin; non può operare su altri plessi neppure tramite API.

La scheda **Riepilogo pasti** consente consultazione ed esportazione del solo proprio plesso con gli stessi campi e stati descritti per il Comune. La Scuola non configura ricorrenze, chiusure, fido o fine servizio e non modifica anagrafiche o saldi.

## Catering — accesso con ruolo Catering

### Pasti da preparare

La scheda include alunni e personale nel totale generale e in quello di ogni scuola. Le quantità per tipologia di menù comprendono entrambi; sotto le classi compare **Pasti del personale**, distinto per categoria e dieta, con il menù efficace della data. Anche una scuola senza classi può avere pasti del personale.

Le quantità del personale derivano dalle regole abituali o dalla variazione della singola data, che prevale. Chiusure e sospensioni escludono i pasti della giornata. **Aggiorna i pasti** rilegge le modifiche; la lettura si aggiorna anche tornando alla scheda. Il cutoff e il conteggio confermato si riferiscono agli alunni: il personale resta modificabile oggi e in futuro dalla scuola, e nel passato dal Comune. Se il personale non è configurato, il totale è segnalato come incompleto; uno zero esplicito è invece un dato disponibile.

### T03 — Riepilogo e rendicontazione

Percorso: **Riepilogo pasti**.

1. Impostare **Dal**, **Al**, eventualmente **Plesso** e **Ordine scolastico**.
2. Premere **Mostra riepilogo** e controllare gli stati delle giornate.
3. Aprire **Dettaglio** per le tipologie degli alunni e i pasti del personale.
4. Premere **Esporta CSV** oppure **Esporta Excel** (dettaglio e riepilogo in due fogli). Il motore e i conteggi sono gli stessi del Comune; non vengono esposti saldi, fidi, pagamenti o anagrafiche delle famiglie.

Per una rendicontazione mensile attendere che le giornate interessate siano complete. Segnalare al Comune giornate non elaborate, personale da configurare o ordine scolastico da configurare.

### T04 — Consultare il personale

Percorso: **Pasti del personale → Scuola → Data di riferimento**. **Programmazione abituale** mostra i sette giorni e le modifiche future; **Pasti della data scelta** mostra i conteggi effettivi e il confronto con la quantità abituale.

Verificare categorie, menù, quantità e totale. Il Catering non può modificare le quantità o le ricorrenze. Le rettifiche sono di competenza della Scuola per oggi e futuro, senza cutoff, e del Comune anche per il passato.

## Genitori — consultazione dei buoni

Nella propria area i buoni dei plessi associati ai figli attivi restano nelle posizioni correnti. Aprire **Posizioni buoni storiche** per gli altri saldi conservati; i movimenti precedenti restano consultabili. Questa separazione non trasferisce né cancella buoni.

Per nominativi o associazioni errate rivolgersi al Comune. Dopo la conclusione del servizio rimangono disponibili storico e dati pregressi; non sono ammesse nuove dichiarazioni, richieste di pasta in bianco o valutazioni per il servizio concluso.

## Matrice dei permessi

| Funzione | Admin | Comune | Scuola | Catering | Genitore |
|---|---|---|---|---|---|
| Verifica famiglie/associazioni | Sì | Sì | No | No | Solo propri dati già disponibili |
| Disattivazione genitore sicura | Sì | Sì | No | No | No |
| Modifica nome/cognome alunno | Sì | Sì | No | No | No |
| Inserimento, modifica ed eliminazione chiusure future | Sì | Sì | No | No | No |
| Fido, ordine, sospensione | Sì | Sì | No | No | No |
| Ricorrenze personale | Sì | Sì | No | No | No |
| Quantità giornaliere personale | Sì, motivate | Sì, motivate | Proprio plesso, oggi e futuro senza cutoff | Lettura | No |
| Riepilogo pasti, CSV ed Excel | Tutti | Tutti | Proprio plesso | Dati operativi, tutti i plessi previsti dal ruolo esistente | No |
| Posizioni oltre fido/storico solleciti | Sì | Sì | No | No | No |
| Saldi familiari | Tutti | Tutti | No | No | Solo propri, correnti e storici |
| Gestione utenti privilegiati/SMTP/job | Ruolo esistente | No | No | No | No |

Admin mantiene gli accessi precedenti; per le funzioni di famiglia e i solleciti utilizza il collegamento **Ufficio comunale — famiglie e solleciti** nella propria area. Le impostazioni e le prove email sono riservate al profilo Admin.


## Admin — configurare, provare e abilitare le email

Percorso: **Admin → Email e solleciti**.

1. Nelle impostazioni SMTP inserire server, porta, sicurezza, utente/password, nome mittente e indirizzo mittente; indicare il protocollo se previsto. Premere **Salva configurazione**. La password viene mascherata dopo il salvataggio: mantenere gli asterischi per conservarla.
2. Premere **Verifica connessione SMTP** per verificare connessione, sicurezza e autenticazione configurata senza spedire messaggi. Gli errori vengono mostrati nella pagina.
3. Selezionare la scuola, compilare oggetto e testo del modello e salvarlo. **Usa questo modello per i solleciti automatici della scuola** consente di abilitarlo o disabilitarlo per quel plesso.
4. In **Prova un modello**, scegliere un modello salvato e l'indirizzo email di prova, quindi **Invia modello di prova**. Funziona anche con automatismi o modello disattivati. Usa dati dimostrativi nei segnaposto, oggetto con prefisso `[PROVA]` e un solo destinatario: non aggiunge famiglia, protocollo, dirigente o CC. Il contenuto della prova è visibile nella pagina. Il test non compare nello storico dei solleciti alle famiglie e non modifica saldi o buoni.
5. Se ci sono modifiche non salvate a SMTP o modello, salvarle prima delle prove. L'esito positivo significa che il server SMTP ha accettato il messaggio: controllare anche la casella destinataria e lo spam.
6. Per avviare gli automatismi, selezionare **Abilita l'invio automatico dei solleciti** e premere **Salva attivazione**. Servono SMTP completo e almeno un modello attivo. Per fermarli, deselezionare e salvare. Il salvataggio non invia email né recupera giornate già elaborate: la scelta vale per i prossimi conteggi utili del job giornaliero.

L'attivazione salvata dall'Admin prevale sul precedente interruttore email nel file di configurazione. Dopo il rilascio non occorre modificare quel file per attivare o disattivare i solleciti. Il cron del servizio deve essere già configurato e attivo: il pannello segnala quando il job è disabilitato, ma non cambia la pianificazione dei pasti o dei buoni.

**Demo locale:** connessioni e invii restano simulati, anche compilando SMTP. La pagina lo indica espressamente e mostra il contenuto della prova; nessuna email lascia la demo. La nuova funzione non abilita automaticamente gli invii al rilascio.
