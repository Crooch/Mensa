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

Per verificare lo stato:

```bash
docker compose -f compose.staging.yml ps
docker compose -f compose.staging.yml logs --tail=80 app
```

Per fermare i container conservando le prove:

```bash
docker compose -f compose.staging.yml down
```

Per **cancellare soltanto il database fittizio locale** e ripartire da zero:

```bash
docker compose -f compose.staging.yml down -v
```

`down -v` elimina **il database staging** e non deve essere usato su una
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
