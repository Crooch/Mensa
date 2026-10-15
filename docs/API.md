# API REST — Mensa Scolastica Caltavuturo

Tutte le funzionalità dell'applicazione passano da questo layer: il frontend web incluso nel
progetto è solo un client. Le API sono progettate per essere consumate senza modifiche anche da
una futura app nativa Android/iOS.

## Convenzioni

- Base URL: `https://dominio{base_path}` — es. `https://dominio/api/auth/login`
- Formato: JSON in richiesta e risposta (eccezione: pubblicazione menu, `multipart/form-data`)
- Autenticazione: header `Authorization: Bearer <token>`
  (fallback accettati: `?token=` in query e cookie `mensa_token`, usati dalla web UI per le immagini)
- Risposta di successo: `{ "ok": true, "dati": <payload> }`
- Risposta di errore: `{ "ok": false, "errore": "messaggio", "dettagli": [] }`
- Codici: `200` ok · `401` non autenticato/token scaduto · `403` ruolo non autorizzato ·
  `404` non trovato · `405` metodo non consentito · `422` validazione · `500` errore interno
- Date: `YYYY-MM-DD`. Orari: `HH:MM`. Importi: decimali con punto. Buoni: interi.
- Ruoli: `admin`, `operatore`, `scuola`, `catering`, `genitore`

---

## Autenticazione

| Metodo | Endpoint | Ruoli | Descrizione |
|---|---|---|---|
| POST | `/api/auth/login` | pubblico | Emette il token |
| POST | `/api/auth/logout` | tutti | Revoca il token corrente |
| GET | `/api/auth/profilo` | tutti | Profilo dell'utente autenticato |
| POST | `/api/auth/password` | tutti | Cambio password dell'utente corrente |

**POST /api/auth/login**
```json
{ "username": "lobianco.g", "password": "Genitore2026!" }
```
```json
{ "ok": true, "dati": { "token": "…64 hex…",
  "utente": { "id": 7, "ruolo": "genitore", "username": "lobianco.g",
              "nome_completo": "Lo Bianco Giuseppe", "plesso_id": null, "genitore_id": 1 } } }
```
Il token ha validità configurabile (`auth.token_durata_ore`, default 12 ore).

---

## Riferimenti comuni

| Metodo | Endpoint | Ruoli | Descrizione |
|---|---|---|---|
| GET | `/api/riferimenti` | tutti | Plessi, tipologie menu e classi attive |

---

## Area Genitore

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/genitore/dashboard` | Saldi per plesso (con stato colore), figli con classe/dieta e stato di oggi, movimenti, feedback |
| GET | `/api/genitore/figli/{id}/calendario?anno&mese` | Calendario mensile con le tre fonti e lo stato effettivo |
| GET | `/api/genitore/figli/{id}/giornata?data` | Stato di una singola giornata + informazioni di cutoff |
| POST | `/api/genitore/presenze/dichiarazione` | Dichiarazione di eccezione presenza/assenza |
| GET | `/api/genitore/feedback` | Storico dei feedback inseriti |
| POST | `/api/genitore/feedback` | Nuovo feedback (voto 1–5 e/o commento) |
| GET | `/api/genitore/menu?data` | Menu pubblicati per i plessi dei propri figli |

**POST /api/genitore/presenze/dichiarazione**
```json
{ "bambino_id": 1, "data": "2026-09-15", "stato": "assente" }
```
```json
{ "ok": true, "dati": { "stato_effettivo": "assente", "fuori_cutoff": false, "effetto": "applicato" } }
```
`fuori_cutoff: true` + `effetto: "registrato_senza_effetto"` indica che la dichiarazione è stata
salvata per tracciabilità ma non modifica il conteggio della giornata. Le dichiarazioni su date
passate sono rifiutate (`422`).

**POST /api/genitore/feedback**
```json
{ "bambino_id": 1, "data": "2026-09-15", "voto": 4, "commento": "Buono il primo piatto" }
```
Almeno uno tra `voto` e `commento` deve essere presente. I feedback non sono mai sovrascritti.

---

## Area Operatore comunale (accessibile anche ad Admin)

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/famiglie?q=&includi_cancellati=0|1` | Ricerca famiglie per cognome/nome/telefono/email |
| GET | `/api/famiglie/{id}` | Scheda: genitore, figli con storico classi, override tariffari, crediti, movimenti |
| POST | `/api/famiglie` | Nuovo genitore **con relativa utenza** |
| PUT | `/api/famiglie/{id}` | Modifica dati genitore |
| DELETE | `/api/famiglie/{id}` | Cancellazione **logica** |
| POST | `/api/bambini` | Nuovo alunno (con prima assegnazione classe opzionale) |
| PUT | `/api/bambini/{id}` | Modifica nome/cognome/tipologia menu |
| DELETE | `/api/bambini/{id}` | Cancellazione **logica** |
| POST | `/api/bambini/{id}/assegnazioni` | Cambio classe con storicizzazione |
| GET | `/api/bambini/{id}/assegnazioni` | Storico assegnazioni |
| GET | `/api/tariffe/attiva?genitore_id&plesso_id&data` | Tariffa applicabile e sua origine |
| POST | `/api/tariffe/override` | Nuovo override tariffario motivato |
| POST | `/api/crediti/acquisti` | Acquisto buoni (calcolo importo lato server) |
| POST | `/api/crediti/rettifiche` | Rettifica manuale motivata del saldo |
| GET | `/api/crediti/situazione` | Situazione crediti di tutte le famiglie |
| GET | `/api/solleciti` | Log dei solleciti inviati |

**POST /api/famiglie**
```json
{ "nome": "Anna", "cognome": "Russo", "telefono": "3331234567",
  "email": "anna.russo@example.it", "username": "russo.a", "password": "Password2026" }
```
`telefono` obbligatorio, `email` facoltativa (usata per l'escalation debito).

**POST /api/bambini**
```json
{ "nome": "Marco", "cognome": "Russo", "genitore_id": 11,
  "tipologia_menu_id": 1, "classe_sezione_id": 3, "data_inizio": "2026-09-15" }
```

**POST /api/bambini/{id}/assegnazioni**
```json
{ "classe_sezione_id": 5, "data_inizio": "2027-01-15" }
```
Chiude l'assegnazione corrente al giorno precedente e apre la nuova.

**GET /api/tariffe/attiva** → `{ "tariffa": 1.8, "origine": "override|base", "override": {…}|null }`

**POST /api/crediti/acquisti**
```json
{ "genitore_id": 3, "plesso_id": 1, "numero_buoni": 10, "note": "Incasso allo sportello" }
```
```json
{ "ok": true, "dati": { "movimento_id": 141, "importo": 18.0, "tariffa": 1.8,
                        "origine_tariffa": "override", "saldo": 26 } }
```
L'importo non è accettato in input: viene sempre calcolato come `numero_buoni × tariffa attiva`.

**POST /api/tariffe/override**
```json
{ "genitore_id": 3, "plesso_id": 1, "tariffa": 1.2,
  "motivazione": "Agevolazione servizi sociali - delibera G.C. n. 84/2026" }
```

**POST /api/crediti/rettifiche**
```json
{ "genitore_id": 3, "plesso_id": 1, "delta": -12, "note": "Riporto debito pregresso" }
```

---

## Area Scuola (vincolata al plesso dell'utenza; admin/operatore senza vincolo)

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/scuola/classi?plesso_id&anno_scolastico` | Classi visibili all'utenza |
| GET | `/api/scuola/classi/{id}/giornata?data` | Vista giornaliera: tre fonti, stato effettivo, cutoff, assenza di massa |
| POST | `/api/scuola/assenze-massa` | Assenza di massa per classe/data |
| GET | `/api/scuola/assenze-massa?data_da&data_a&plesso_id` | Elenco assenze di massa |
| POST | `/api/scuola/presenze/override` | Override motivato di un singolo alunno |

**GET /api/scuola/classi/{id}/giornata**
```json
{ "ok": true, "dati": {
  "classe": { "id": 3, "nome": "1ª A", "plesso_nome": "Scuola Primaria - Plesso Centrale" },
  "data": "2026-09-15", "cutoff_orario": "09:30", "oltre_cutoff": false,
  "assenza_massa": null,
  "alunni": [ { "bambino_id": 12, "nome": "Noemi", "cognome": "Panzarella",
                "tipologia_nome": "Dieta in bianco (temporanea)",
                "default_calcolato": "presente", "dichiarazione_genitore": null,
                "override_scuola": null, "override_motivazione": null,
                "stato_effettivo": "presente", "fuori_cutoff": 0, "congelato": 0 } ] } }
```

**POST /api/scuola/presenze/override**
```json
{ "bambino_id": 12, "data": "2026-09-15", "stato": "assente",
  "motivazione": "Comunicazione telefonica della famiglia" }
```
`motivazione` obbligatoria. Se l'alunno non appartiene al plesso dell'utenza → `403`.

**POST /api/scuola/assenze-massa**
```json
{ "classe_sezione_id": 4, "data": "2026-09-16", "motivo": "Uscita didattica a Himera" }
```

---

## Area Catering

| Metodo | Endpoint | Ruoli | Descrizione |
|---|---|---|---|
| GET | `/api/catering/report?data&plesso_id` | catering, admin, operatore | Pasti da preparare per plesso, classe, tipologia e richiesta di pasta in bianco |
| GET | `/api/catering/menu?data&plesso_id` | catering, admin, operatore, scuola | Menù effettivi per il giorno: variazione giornaliera, altrimenti modello settimanale |
| POST | `/api/catering/menu` | catering, admin | Crea o modifica la variazione di una singola data (`multipart/form-data`) |
| GET | `/api/catering/menu/variazioni?pagina=1` | catering, admin | Variazioni programmate, ordinate dalla data più lontana; 10 per pagina |
| POST | `/api/catering/menu/annulla` | catering, admin | Annulla una variazione; se presente, torna il modello settimanale |
| POST | `/api/catering/menu/ripristina` | catering, admin | Ripristina il modello settimanale del giorno, se esistente |
| GET | `/api/catering/menu-settimanali` | catering, admin | Catalogo dei modelli settimanali |
| POST | `/api/catering/menu-settimanali` | catering, admin | Nuovo modello settimanale, inclusi allegati fotografici |
| PUT | `/api/catering/menu-settimanali/{id}` | catering, admin | Modifica un modello (crea revisione) |
| POST | `/api/catering/menu-settimanali/{id}/aggiorna` | catering, admin | Modifica tramite invio multipart, inclusi allegati fotografici |
| POST | `/api/catering/menu-settimanali/{id}/duplica` | catering, admin | Duplica un modello settimanale |
| GET | `/api/catering/menu-settimanali/assegnazioni` | catering, admin | Assegnazioni attive per plesso e tipologia |
| POST | `/api/catering/menu-settimanali/assegnazioni` | catering, admin | Assegna un modello a uno o più plessi |
| GET | `/api/catering/feedback?data_da&data_a&plesso_id&classe_id&tipologia_id` | catering, admin, operatore | Feedback in sola lettura |

**GET /api/catering/report**
```json
{ "ok": true, "dati": { "data": "2026-09-15", "totale_pasti": 15, "plessi": [
  { "plesso_id": 2, "plesso_nome": "Scuola Primaria - Plesso Centrale",
    "cutoff_orario": "09:30", "definitivo": true, "totale_pasti": 7,
    "tipologie": [ { "tipologia_id": 1, "tipologia_nome": "Standard", "pasti": 6 },
                   { "tipologia_id": 3, "tipologia_nome": "Vegano", "pasti": 1 } ] } ] } }
```
`definitivo: false` = cutoff non ancora superato, conteggio provvisorio.
Il report non contiene alcuna informazione economica o di credito: un alunno presente genera
sempre un pasto, qualunque sia il saldo della famiglia.

Il risultato completo comprende anche `sezioni`, quantità `pasti_ordinari` e
`pasta_in_bianco`, richieste con nominativo indispensabile alla distribuzione,
oltre a `menu_nome`, `menu_descrizione` e `menu_origine` per le tipologie.
**La pasta in bianco è già inclusa nel totale:** non deve essere aggiunta una
seconda volta. Il nome di riserva «Menù standard» è una dicitura
dell'interfaccia, non una pietanza registrata automaticamente nel database.

**POST /api/catering/menu** (`multipart/form-data`)

| Campo | Tipo | Note |
|---|---|---|
| `plesso_id` | int | obbligatorio |
| `tipologia_menu_id` | int | obbligatorio |
| `data` | date | default oggi |
| `descrizione` | string | almeno uno tra descrizione e foto |
| `foto` | file | jpg/png/webp, max 4 MB |
| `rimuovi_foto` | `0` o `1` | consente la rimozione esplicita della foto; l'assenza del campo non elimina la foto salvata |

L'archivio immagini è gestito dal servizio applicativo. Le immagini vengono
consultate con percorsi `/storage/menu/...` **solo dopo autenticazione**:
non costruire il percorso assumendo che tutti i file siano JPEG.

Le operazioni di pubblicazione e annullamento sono valide esclusivamente
per una data futura o per oggi **prima dell'orario limite del plesso**;
non possono alterare giornate senza servizio o già consolidate.

**GET /api/catering/menu/variazioni?pagina=1** — esempio di struttura:
```json
{
  "ok": true,
  "dati": {
    "righe": [{ "id": 12, "data": "2026-11-09", "plesso_id": 1,
      "tipologia_menu_id": 1, "descrizione": "Piatto del giorno",
      "modificabile": true, "ha_modello_settimanale": true }],
    "pagina": 1, "per_pagina": 10, "totale": 1, "pagine": 1
  }
}
```
I campi di ciascuna riga includono anche il nome del plesso, della tipologia
e l'eventuale riferimento alla fotografia.

**POST /api/catering/menu/annulla**
```json
{ "plesso_id": 1, "tipologia_menu_id": 1, "data": "2026-11-09" }
```

**Modelli settimanali:** `giorni` indica le descrizioni dei giorni da 1
(lunedì) a 7 (domenica), in JSON o come stringa JSON nel multipart;
`foto_giorno_1` … `foto_giorno_7` sono file facoltativi.
`rimuovi_foto` elenca i giorni per cui viene chiesta esplicitamente la
rimozione. L'assegnazione usa `menu_id`, `tipologia_menu_id` e
`plesso_ids`. Le revisioni e le date di decorrenza preservano lo storico
dei giorni già serviti.

Il feedback restituito al catering usa dati sintetici/aggregati o nominativi
ridotti alle iniziali secondo le regole applicative: non include saldi e crediti.

---

## Area Admin

| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET/POST | `/api/admin/plessi` | Elenco / creazione plessi |
| PUT/DELETE | `/api/admin/plessi/{id}` | Modifica / cancellazione logica |
| GET/POST | `/api/admin/classi` | Elenco / creazione classi-sezioni |
| PUT/DELETE | `/api/admin/classi/{id}` | Modifica / cancellazione logica |
| GET/POST | `/api/admin/tipologie` | Elenco / creazione tipologie menu |
| PUT/DELETE | `/api/admin/tipologie/{id}` | Modifica / cancellazione logica |
| GET/POST | `/api/admin/utenti` | Elenco / creazione utenze |
| PUT | `/api/admin/utenti/{id}` | Attivazione-disattivazione, nome, plesso, reset password |
| GET/POST | `/api/admin/modelli-email` | Modelli di sollecito (uno per plesso) |
| GET/POST | `/api/admin/destinatari` | Lista unica destinatari in CC |
| PUT | `/api/admin/destinatari/{id}` | Modifica/attivazione destinatario |
| GET/POST | `/api/admin/impostazioni` | Configurazione SMTP ed email protocollo |
| POST | `/api/admin/email-prova` | Invio email di prova |

**POST /api/admin/plessi**
```json
{ "nome": "Scuola Primaria - Plesso Centrale", "tariffa_base_pasto": 3.00,
  "fido_buoni": 15, "cutoff_orario": "09:30",
  "dirigente_email": "dirigente@iccaltavuturo.edu.it", "email_protocollo": null }
```

**POST /api/admin/modelli-email**
```json
{ "plesso_id": 2, "oggetto": "Mensa {plesso} - posizione oltre fido ({saldo} buoni)",
  "testo": "Gentile {genitore}, …", "attivo": true }
```
Placeholder: `{genitore}`, `{alunno}`, `{plesso}`, `{saldo}`, `{fido}`, `{data}`.

**POST /api/admin/impostazioni** — chiavi ammesse: `smtp_host`, `smtp_port`, `smtp_security`
(`tls|ssl|none`), `smtp_username`, `smtp_password`, `smtp_from`, `smtp_from_name`,
`email_protocollo`. In lettura la password è mascherata (`********`) e inviarla mascherata non
sovrascrive il valore memorizzato.

---

## Calendario mensa e periodi straordinari

Il calendario ordinario (giorni settimanali, data di inizio e data di fine
facoltativa) rimane associato al plesso. L'interfaccia identifica l'anno
scolastico della data di avvio, ad esempio **2026/2027**: non viene creato
automaticamente un nuovo calendario ogni settembre. Se non viene impostata
una fine, la configurazione ordinaria rimane valida anche negli anni successivi.

| Metodo | Endpoint | Ruoli | Descrizione |
|---|---|---|---|
| GET | `/api/admin/calendari/riepilogo?data=YYYY-MM-DD` | admin | Situazione di tutte le scuole attive e classi dell'anno scolastico, **sola lettura** |
| GET | `/api/admin/calendari/{id}` | admin | Configurazione e periodi per la scuola |
| PUT | `/api/admin/calendari/{id}` | admin | Salva il calendario ordinario |
| GET | `/api/admin/calendari/{id}/verifica?data=YYYY-MM-DD` | admin | Verifica il servizio nella data, senza addebiti |
| POST | `/api/admin/calendari/{id}/periodi` | admin | Crea un'apertura o una chiusura di uno o più giorni |
| PUT | `/api/admin/calendari/{id}/periodi/{periodo_id}` | admin | Modifica un periodo già registrato |
| DELETE | `/api/admin/calendari/{id}/periodi/{periodo_id}` | admin | Elimina un periodo, ripristinando le regole ordinarie |

Esempio `POST /api/admin/calendari/1/periodi`:

```json
{
  "data_inizio": "2026-12-23",
  "data_fine": "2027-01-06",
  "servizio_attivo": 0,
  "motivazione": "Vacanze natalizie"
}
```

Per una data singola è sufficiente omettere `data_fine` o valorizzarla
con la stessa data iniziale. In tabella i record storici a giorno singolo
rimangono validi con `giorno_fine = NULL`; è richiesta la migrazione
`database/migrations/009_intervalli_calendario.sql`.

**Verifica complessiva:** `GET /api/admin/calendari/riepilogo?data=2026-10-19`
restituisce `data`, `anno_scolastico`, `totali` (scuole e classi
con e senza servizio) e `scuole[]`. Ogni scuola comprende `plesso_id`,
`plesso_nome`, `attivo`, `motivo`, `totale_classi` e `classi[]`
(con soli ID e nomi). Non sono restituiti dati personali degli alunni,
né sono creati movimenti o esecuzioni del job.

**Il calendario è per scuola:** tutte le classi della medesima scuola
ereditano l'esito, non esiste una configurazione autonoma per classe.
Sono mostrate esclusivamente le classi attive con `anno_scolastico`
corrispondente alla data richiesta (settembre–agosto). Una scuola può
essere aperta anche se non ha classi registrate per quell'anno;
l'assenza di classi non equivale alla chiusura del servizio.
La pagina Amministratore applica filtri e paginazione lato browser,
con otto scuole per pagina e classi espandibili.

**Precedenza:** ogni chiusura o apertura straordinaria esplicita prevale
sul calendario ordinario, anche su fine settimana, festività nazionali e
date di inizio/fine servizio. Un'apertura straordinaria è dunque
un'operazione che può autorizzare la contabilizzazione anche in giornate
normalmente escluse. I plessi disattivati restano invece esclusi.

**Sovrapposizioni:** due periodi possono condividere soltanto il giorno
finale/inziale, e soltanto se hanno lo stesso tipo (due aperture o due
chiusure). Qualsiasi intersezione interna, o anche il solo confine con
stati diversi, genera HTTP `422` e un messaggio con le date del periodo
già esistente. La verifica avviene nel servizio PHP con un lock per scuola,
quindi non è aggirabile inviando richieste direttamente alle API.

Gli endpoint storici `/eccezioni/{data}` rimangono compatibili per
giorni singoli, ma non possono sovrascrivere o cancellare accidentalmente
un periodo di più giorni.

---

## Job giornaliero

| Metodo | Endpoint | Ruoli | Descrizione |
|---|---|---|---|
| GET | `/api/job/stato` | admin | Stato della chiusura di oggi per ogni scuola attiva; **sola lettura**, nessun addebito |
| POST | `/api/job/scheduler` | admin | Controllo manuale di tutte le scuole, con le stesse regole dello scheduler automatico |
| POST | `/api/job/plessi/{id}` | admin | Esecuzione manuale di una scuola (`{"data": "YYYY-MM-DD"}` opzionale); rispetta comunque calendario, orario limite e protezioni sui consumi |
| GET | `/api/job/esecuzioni` | admin, operatore | Storico delle elaborazioni e dettagli aggregati |
| GET | `/scheduler?key=…` | — (chiave) | Endpoint per cron HTTP del pannello hosting |

Esito tipico di un'esecuzione:
```json
{ "plesso_id": 2, "plesso": "Scuola Primaria - Plesso Centrale", "stato": "eseguito",
  "presenze": { "presente": 7, "assente": 1 }, "consumi": 7, "pasti_report": 7,
  "solleciti": { "controllati": 3, "inviati": 3, "errori": 0, "dettagli": [ … ] } }
```
Stati possibili: `eseguito`, `gia_eseguito`, `cutoff_non_raggiunto`,
`fuori_calendario`, `in_corso`, `errore`, `errore_precedente`.
Il job è idempotente: i consumi non vengono duplicati e i solleciti sono inviati una sola volta
per genitore/plesso/giorno.

**Recupero dopo un errore:** lo scheduler restituisce `errore_precedente` per una
scuola/giorno con esito `errore` già registrato, **senza** riavviare
automaticamente la chiusura. Occorre prima riconciliare consumi, presenze
congelate, solleciti e log; un successivo intervento manuale da Admin è
una nuova operazione reale, da autorizzare dopo le verifiche. Per
`in_corso` persistente non esiste alcun ripristino automatico.

Il monitoraggio usa `GET /api/job/stato` e non richiama il job. Restituisce:
`data`, `scuole[]`, `plesso_id`, `plesso_nome`, `orario_limite`,
`stato`, `pasti_confermati`, `buoni_utilizzati`,
`solleciti_inviati`, `errori_comunicazioni` e `concluso_il`.
Gli aggregati numerici sono disponibili **solo dopo una chiusura completata**.
Gli stati possibili sono: `non_prevista`, `in_attesa`, `da_eseguire`,
`in_corso`, `completata`, `errore`.

I valori `consumi`/`buoni_utilizzati` indicano gli **addebiti registrati
nell'esecuzione**, non un secondo conteggio da sottrarre allo stato dei conti.
Inoltre un esito `ok` può avere `solleciti.errori > 0`: in quel caso i pasti
risultano contabilizzati, ma le comunicazioni devono essere verificate.

**Importante:** l'applicazione non può dedurre dall'API di monitoraggio se
il cron esterno sul server sia attivo. La periodicità (ad esempio 5 minuti)
dipende dalla configurazione effettiva del sistema e deve essere verificata
separatamente. Non avviare scheduler o comandi manuali sulla produzione per
verificarne lo stato.
