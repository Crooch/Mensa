# Mensa Caltavuturo — adeguamento del job/cron durante il rilascio

**Obiettivo:** pubblicare la nuova logica di chiusura giornaliera **senza
importare dati di test, azzerare crediti o modificare le esecuzioni storiche**.
Il job fa parte del codice PHP; il cron è una pianificazione del sistema
operativo e **non** è una migrazione SQL.

**Stato:** procedura preparatoria. Nessun intervento qui descritto è stato
eseguito sulla VPS o su `mensa_db`.

## 1. Come funziona la nuova versione

- Entry point automatico: `bin/cron_daily.php` da **PHP CLI**.
- Frequenza **proposta**: ogni **5 minuti, tutti i 7 giorni** (anche weekend).
  La frequenza non è l'orario di addebito: ciascun plesso viene elaborato
  **dopo il proprio cutoff**, solo quando il calendario è aperto.
  Aperture straordinarie prevalgono su weekend e festività.
- Uno stesso plesso/giorno completato non è rielaborato. Un errore pregresso
  `errore` genera `errore_precedente` e impedisce i retry automatici.
  Lo stato `in_corso` non viene riavviato automaticamente.
- Per ogni presente: congelamento della presenza e contabilizzazione di un
  buono pasto; inoltre conteggi catering e potenziali solleciti email. Il job
  **scrive su dati reali**, quindi non deve mai essere avviato come test.
- `bin/prepara_giornata.php` è uno strumento **distinto** che crea record di
  presenze: non aggiungerlo al cron di produzione senza analisi separata.
- Le API Admin di chiusura sono anch'esse operazioni reali; il nuovo
  interruttore blocca sia scheduler automatico sia esecuzioni manuali,
  lasciando leggibili stato e storico.

## 2. Interruttore di sicurezza — disattivo per default

Il codice di `dev` usa `app.scheduler_enabled`, il cui default in
`config/config.php` è **`false`**. Anche `/scheduler?key=...` è bloccato
fino all'abilitazione esplicita; il cron via CLI è preferibile all'URL.

**Non sovrascrivere** la configurazione esistente della VPS:
`config/config.local.php` è locale e contiene le credenziali DB.
Per attivare **in un momento successivo approvato**, inserire/aggiornare
la sola voce nel blocco `app` di quel file:

```php
'scheduler_enabled' => true,
```

Per disabilitare, ripristinare `false`. Un valore mancante equivale a spento.
Non pubblicare o incollare `config.local.php` o la chiave scheduler in chat.

Il comando **sicuro in sola lettura**, disponibile **dopo aver distribuito
il nuovo codice**, è:

```bash
sudo -u mensa /usr/bin/php /srv/www/mensa/app/bin/cron_daily.php --check
```

Mostra stato dello switch, data, timezone e nome DB configurato.
**Non apre connessioni al DB e non registra consumi.**
Non eseguire lo stesso comando senza `--check` come prova: con lo switch
attivo registrerebbe i pasti della giornata.

## 3. Inventario del cron attuale — SOLO LETTURA, prima del deploy

Il cron utente `mensa` risultava sospeso alla ricognizione del 14/10/2026.
Verificare dal server lo stato *attuale*, non considerare l'informazione
storica come prova:

```bash
sudo crontab -u mensa -l
sudo crontab -l
sudo grep -R -nE 'cron_daily\.php|prepara_giornata\.php|/scheduler' \
  /etc/crontab /etc/cron.d /etc/cron.daily /etc/cron.hourly 2>/dev/null || true
systemctl list-timers --all
command -v php
```

Se nel crontab è presente una chiamata HTTP con `?key=...`, **non
condividere l'output senza oscurare la chiave**. Cercare eventuali righe
duplicate, script vecchi, timer concorrenti e il reale utente che esegue
le attività. Non modificare nulla in questa fase.

Verificare che l'utente `mensa` possa leggere il codice e la configurazione
e scrivere in `storage/` (anche i log); non alterare ownership/ACL
alla cieca.

## 4. Nuova voce di cron prevista — NON ATTIVARE ADESSO

Da installare/sostituire nel **crontab dell'utente `mensa`**, una sola volta,
nel corso della finestra autorizzata, **dopo** aver rimosso/neutralizzato
la vecchia pianificazione e gli eventuali altri trigger:

```cron
*/5 * * * * /usr/bin/php /srv/www/mensa/app/bin/cron_daily.php >> /srv/www/mensa/app/storage/cron.log 2>&1
```

La direttiva dei minuti `*/5` va anche su sabato, domenica e festivi:
la decisione è applicativa, per plesso e calendario, non basata sul crontab.
Il percorso `/usr/bin/php` e la directory dell'app sono **ipotesi da
confermare sul server**. Non copiare automaticamente la voce in presenza
di un cron esistente: confrontare e sostituire.

**Importante:** se lo switch viene abilitato **dopo il cutoff di una
giornata aperta**, la prima esecuzione può consolidare e addebitare
**quella stessa giornata**. Pianificare attivazione e controllo degli
orari per evitare un'addebito non voluto. Il CLI automatico **non accetta
date retroattive**.

## 5. Sequenza di rilascio concordata

1. Confermare il commit candidato e il collaudo; fare preflight di sola
   lettura e backup ripristinabile di database, configurazione e foto.
2. Se mancano strutture, applicare **solo** `database/deploy/upgrade_schema_only_2026_10.sql`
   prima su copia protetta e, dopo autorizzazione, sul DB comunale.
   Non usare `001`, seed, dump/staging o script numerati con `INSERT/UPDATE`.
3. Distribuire il **codice PHP** incluso il nuovo `bin/cron_daily.php`
   e mantenere `app.scheduler_enabled = false`. Non sostituire
   `config.local.php`, foto, log, backup o database.
4. Con switch ancora spento, eseguire `--check` via PHP CLI come
   `mensa` e controllare versione PHP, timezone e database configurato.
   Controllare via interfaccia che Admin veda lo scheduler disattivato.
5. Configurare attraverso l'Admin **soltanto i calendari approvati**
   per i plessi, dopo confronto con il Comune. La nuova tabella del
   calendario è vuota dopo il solo upgrade strutturale, se prima assente.
6. Verificare cutoff e risultati di consultazione con una data futura;
   riconciliare eventuali job `errore`/`in_corso` o movimenti non
   allineati. Verificare anche gli indirizzi destinatari e le condizioni
   di invio dei solleciti (evitare comunicazioni di prova).
7. Ispezionare il crontab originale; sostituire/aggiungere **una sola**
   esecuzione CLI ogni 5 minuti; verificare che nessun vecchio URL,
   preparazione presenze o altro scheduler possa avviarsi in parallelo.
   Conservare lo switch ancora `false`.
8. Con **autorizzazione esplicita**, e solo in una finestra concordata
   rispetto ai cutoff, modificare la voce locale a `true`.
   Monitorare log e stato dell'Admin alla prima esecuzione prevista,
   confrontando presenze e movimenti contabili. Non lanciare un job
   manuale per accelerare il collaudo.
9. Se anomalia: riportare subito lo switch a `false`, neutralizzare il
   cron, conservare i log e riconciliare movimenti/presenze **prima**
   di qualunque ripetizione o ripristino. Non ricaricare un vecchio
   dump sovrascrivendo acquisti e presenze reali intercorsi.

## 6. Criteri GO / NO-GO

**NO-GO** se il crontab attuale è ignoto, esistono più trigger, la versione
del codice e del DB non coincidono, manca la configurazione dei calendari,
sono presenti job irrisolti, non sono stati verificati cutoff/permessi/log,
non è disponibile un backup utilizzabile o non c'è autorizzazione.

L'upgrade strutturale **non** richiede alcuna modifica alla tabella
`esecuzioni_job`: le righe storiche restano intatte. Il job si adegua con
il deploy del nuovo codice e con la configurazione pianificata del cron.
