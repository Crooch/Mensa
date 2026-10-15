# Staging locale isolato — Mensa Caltavuturo

Ambiente **di test**: non importare dati del Comune e non usare per produzione.

## Requisiti
Docker Desktop con `docker compose` e Git.

## Avvio (dal Mac)

```bash
git clone https://github.com/Crooch/Mensa.git
cd Mensa
git switch dev
docker compose -f compose.staging.yml up --build -d
```

Aprire **http://127.0.0.1:18080**.

Credenziali di esempio (solo staging):

| Ruolo | Username | Password |
| --- | --- | --- |
| Admin | `admin.staging` | `Collaudo2026!` |
| Genitore | `genitore.staging` | `Collaudo2026!` |
| Altro genitore | `genitore2.staging` | `Collaudo2026!` |
| Scuola infanzia | `scuola.infanzia.staging` | `Collaudo2026!` |
| Catering | `catering.staging` | `Collaudo2026!` |
| Operatore | `operatore.staging` | `Collaudo2026!` |

I dati creati sono sintetici: 3 plessi (primaria inattiva), sezioni A/B/C/D
dell'infanzia (D priva di alunni), classe 1A della secondaria, cinque alunni
fittizi, cinque ticket di prova.

**Protezione:** la porta web è pubblicata esclusivamente su `127.0.0.1:18080`,
MariaDB non espone alcuna porta, non esiste cron nel container app,
le email vengono intercettate e bloccate. Il seed è autorizzato solo
con `MENSA_STAGING_ONLY=1` e database `mensa_staging`. `config.local.php`
è generato nel container e non proviene da produzione.

Il codice non viene montato sulla VPS e `mensa_db` non viene mai utilizzato.
Il seed **non** viene ripetuto se trova già utenti; non impiega mai `TRUNCATE`.
Le fotografie dei menu sono ora conservate in un volume Docker dedicato, anche se
il container applicativo viene ricreato. Il volume non recupera eventuali foto
perse prima della sua introduzione.

Per verificare lo stato:

```bash
docker compose -f compose.staging.yml ps
docker compose -f compose.staging.yml logs --tail=80 app
```

Per fermare i container conservando le prove:

```bash
docker compose -f compose.staging.yml down
```

### Ripulire soltanto i menu di prova (senza azzerare lo staging)

Dal clone locale sul ramo `dev`, dopo aver aggiornato i file e i container:

```bash
git pull --ff-only origin dev
docker compose -f compose.staging.yml up --build -d --wait
```

Anteprima obbligatoria dei record da eliminare (non modifica il DB):

```bash
docker compose -f compose.staging.yml exec -T app php staging/pulisci_menu.php --preview
```

Per conservare una copia prima della pulizia, esportare il database fittizio:

```bash
docker compose -f compose.staging.yml exec -T database sh -c 'exec mariadb-dump --single-transaction -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' > mensa_staging_backup.sql
```

Se il riepilogo corrisponde alle prove da rimuovere:

```bash
docker compose -f compose.staging.yml exec -T app php staging/pulisci_menu.php --apply --confirm=mensa_staging
```

Lo strumento elimina **soltanto** `menu_giornalieri`,
`menu_settimanali_assegnazioni`, `menu_settimanali_revisioni` e
`menu_settimanali`, oltre alle immagini ancora referenziate e generate
nell'archivio di staging. Mantiene utenti, genitori, alunni, assegnazioni di
classe, calendario, presenze, feedback, crediti e movimenti. Rifiuta
esplicitamente qualsiasi database diverso da `mensa_staging` sul servizio
Docker `database` con variabili di staging abilitate.

**Attenzione:** lo script `python3 staging/smoke.py` effettua scritture vere
sul database fittizio; se lo esegui dopo la pulizia, ritroverai menu di
collaudo e nuove assegnazioni.

Per **cancellare soltanto il database fittizio locale** e ripartire da zero:

```bash
docker compose -f compose.staging.yml down -v
```

`down -v` elimina **il database staging e il volume delle foto** e non deve essere usato su una
configurazione Docker diversa. Accertarsi sempre di essere nella cartella
del clone `Crooch/Mensa`, branch `dev`.

## Casi minimi da provare via browser

- Admin: verificare calendario per infanzia e secondaria al 19/10/2026
- Catering: visualizzare A, B, C, D, inclusa D = zero pasti
- Genitore: assenza per un giorno futuro, poi ripristino della presenza
- Genitore: pasta in bianco su giorno futuro e successivo annullamento
- Catering: vedere la richiesta di pasta in bianco per nome/classe del bambino
- Scuola: modifica prima cutoff, eccezione motivata dopo cutoff
- Sicurezza: l'altro genitore non deve modificare dati del primo
- Crediti: assenza di duplicazioni e rispetto delle regole di addebito

## Avvertenza importante
I test in staging non verificano automaticamente la corrispondenza fra
i **nomi e gli ID dei plessi reali** e le due regole iniziali nel database
del Comune. Prima del rilascio occorre un controllo read-only delle anagrafiche
dei plessi e delle sezioni di produzione.

## Collaudo UX/UI — pacchetti A, B, C, D (14/10/2026)

Piano e decisioni: [docs/PIANO_UX_UI_A_D_2026-10-14.md](../docs/PIANO_UX_UI_A_D_2026-10-14.md).
Le implementazioni del pacchetto E (stampa cucina, tour avanzati, ecc.) restano escluse.

Per aggiornare lo staging **senza cancellare le prove**:

```bash
cd ~/Projects/mensa-staging
git pull --ff-only origin dev
docker compose -f compose.staging.yml up --build -d --wait
python3 staging/smoke.py
```

Non utilizzare `down -v`: elimina il volume contenente i dati sintetici.

### Verifiche browser consigliate
1. **Genitore:** aprire ottobre 2026, controllare 19/10 (mensa), sabato 17/10 (nessuna mensa), martedì 20/10 (infanzia attiva); selezionare il 19, segnalare assenza, verificare feedback e ripristinare. Richiedere e annullare pasta in bianco per la stessa data. Provare a cambiare data nel riepilogo e verificare che i comandi seguano il giorno selezionato.
2. **Scuola:** cambiare rapidamente classe/data; verificare che riepilogo e tabella corrispondano sempre al filtro scelto e che nessuna modifica sia possibile su dati precedenti. Provare una correzione motivata in un giorno futuro.
3. **Catering:** alternare rapidamente il 19 e 20 ottobre e controllare la data/ora visualizzata, i conteggi provvisori e la sezione D a zero pasti. Verificare che non compaiano saldi finanziari.
4. **Operatore:** aprire una famiglia e verificare le conferme esplicite per acquisti/rettifiche. Tutti i test economici modificano **solo dati fittizi dello staging**. Controllare che click ripetuti non duplicano movimenti.
5. **Admin:** controllare riepilogo scuole, calendari e accessibilità dei pulsanti. Non eseguire il conteggio finanziario manuale nel collaudo UI se non con uno scenario dedicato e isolato.
6. Ripetere almeno Genitore e Scuola da smartphone o con finestra stretta (375 px), usando anche il tabulatore e i tasti freccia per cambiare scheda.

### Modifiche del modello dati
`005_idempotenza_acquisti.sql` aggiunge `richiesta_id` nullable e un vincolo unico ai movimenti.
I movimenti esistenti restano invariati. Il client usa un token dedicato per
ogni modulo di acquisto o rettifica; lo stesso token impedisce doppi addebiti
in caso di ripetizione involontaria o connessione instabile.

I test automatici verificano la migration due volte, acquistano e rettificano
buoni di prova con token ripetuto, e confermano che lo stesso movimento
non venga duplicato.

### Nessun deploy automatico
Questa branch non è direttamente pubblicata sulla VPS. Prima del rilascio reale
servono backup verificato, piano di rollback, autorizzazione del Comune e
collaudo manuale per ruolo. Lo scheduler di produzione resta spento finché
non viene espressamente deciso di attivarlo.


## Collaudo browser dei cinque ruoli

La workflow CI `Staging Browser 5 ruoli` testa automaticamente Chromium
in ambiente Docker sintetico per **Admin, Operatore, Scuola, Catering e Genitore**
alle larghezze 375, 768 e 1366 pixel. Controlla accesso, schede, overflow
orizzontale, errori JavaScript e dimensioni della foto del menu.
Gli screenshot sintetici sono negli artifact della run GitHub Actions,
senza riferimenti ad alunni o famiglie reali.

Il test non sostituisce l'esame visivo su Mac e smartphone reali.
Da fare sul Mac, nello staging locale già avviato
(`http://127.0.0.1:18080`), **senza usare la VPS**:

1. Accedere con i cinque account di staging indicati sopra; verificare tutte le schede,
   anche a larghezza circa 375 px (telefono), 768 px (tablet) e desktop.
2. **Genitore**: consultare il calendario completo; distinguere il 19/10
   (mensa prevista) dal 17/10 (senza servizio); verificare assenza e pasta in bianco.
3. **Scuola**: verificare classe/data, totali, sezione senza pasti,
   motivazione di una correzione e caso gita (solo sui dati sintetici).
4. **Catering**: controllare report pasti e classi a zero;
   creare un modello settimanale, duplicarlo, aggiungere/rimuovere foto e
   verificare le anteprime e la X, anche da smartphone.
5. **Operatore**: cercare una famiglia sintetica, aprire la scheda e
   verificare conferme e messaggi; non usare dati reali.
6. **Amministratore**: verificare plessi, calendario, classi e utenze.
   Non avviare i job finanziari per il collaudo UI.
7. Da tastiera verificare Tab, Shift+Tab, Escape sui dialoghi,
   focus visibile e nessuno scorrimento orizzontale della pagina.

I risultati vanno classificati come **Passato**, **Da correggere** o
**Non verificato**, distinguendo CI automatica da collaudo manuale.
Non effettuare merge su `main` o deploy in produzione prima di
completare le verifiche e autorizzare espressamente il rilascio.
