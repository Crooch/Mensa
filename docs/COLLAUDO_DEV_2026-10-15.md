# Mensa Caltavuturo — esito collaudo tecnico `dev`

**Data:** 15 ottobre 2026. **Ambiente:** CI GitHub Actions con Docker
`mensa_staging` sintetico. **Ultima revisione dell'interfaccia collaudata:** `67b6127` (Catering compatto). **Test PHP di integrazione aggiornati:** `f214600`.

## Risultati automatici

| Area | Controllo | Esito |
|---|---|---|
| API / PHP | Syntax, regressioni, integrazioni DB MariaDB sintetico | SUPERATO |
| Docker HTTP | Calendario, presenze, pasta in bianco, menu, foto, pulizia staging | SUPERATO |
| Immagini | File `/storage/menu/` accessibile solo a utente autenticato | SUPERATO |
| Browser | Admin: login, navigazione schede, JS e overflow a 375, 768, 1366 px | SUPERATO |
| Browser | Operatore: login, navigazione schede, JS e overflow a 375, 768, 1366 px | SUPERATO |
| Browser | Scuola: login, navigazione schede, JS e overflow a 375, 768, 1366 px | SUPERATO |
| Browser | Catering: login, navigazione schede, JS e overflow a 375, 768, 1366 px | SUPERATO |
| Browser | Genitore: login, navigazione schede, JS e overflow a 375, 768, 1366 px | SUPERATO |
| Browser | Catering: foto in bozza, cornice 280×180 px, X geometrica, rimozione | SUPERATO |
| Catering | Pasti da preparare: schede scuola compatte, quantità per classe/tipologia, pasta in bianco già inclusa nel totale | SUPERATO |
| Catering | Browser con due scuole, quattro classi con 12 varianti ciascuna, desktop 1366 px, tablet 768 px e mobile 375 px | SUPERATO |
| Catering | Altri tipi di menu espandibili senza campo di ricerca, nessuno scorrimento orizzontale | SUPERATO |
| MariaDB CI | Tipologie alimentari miste per classe, pasta in bianco associata correttamente a dieta e sezione | SUPERATO |

### Evidenze

- [PHP quality checks aggiornati](https://github.com/Crooch/Mensa/actions/runs/38708436322) — test di integrazione anche per diete miste.
- [Staging Docker HTTP smoke aggiornato](https://github.com/Crooch/Mensa/actions/runs/38708352704) — verifica delle somme fra classe, tipologie e pasta in bianco.
- [Staging Browser 5 ruoli aggiornato](https://github.com/Crooch/Mensa/actions/runs/38708352738) — report compatto a tre larghezze e numerose tipologie.
- Gli screenshot (esclusivamente con dati sintetici) sono allegati come artifact
  nella run del browser. La conservazione in GitHub Actions è limitata a 7 giorni.

I test Chromium esaminano **solo navigazione, visibilità dei pannelli,
overflow e assenza di eccezioni JS**; non sostituiscono l'esame umano della
leggibilità, dell'accessibilità o del corretto flusso operativo.

## Situazione del database locale

- [ ] Pulizia dei menu di prova nel database di staging sul Mac: **non ancora eseguita**.
- È disponibile lo strumento `staging/pulisci_menu.php`, con modalità anteprima, backup consigliato e conferma esplicita.
- Lo strumento agisce solo su `mensa_staging` Docker e non è in grado di intervenire autonomamente sul database del Mac dall'ambiente GitHub Actions.

## Console Chrome

- Il messaggio `A listener indicated an asynchronous response by returning true, but the message channel closed before a response was received` è spesso emesso da un'estensione del browser. Non è stato riprodotto nel browser Chromium senza estensioni usato in CI.
- Controllare eventualmente in una finestra senza estensioni prima di attribuirlo al codice applicativo. Nessuna modifica arbitraria all'app è stata introdotta per tale avviso.

## Verifiche manuali ancora aperte

- [ ] Verifica visiva su Mac a 1366 px, tablet a 768 px, telefono a circa 375 px.
- [ ] Uso con tastiera, focus, tab, dialoghi e messaggi di errore.
- [ ] Genitore: scelta data/figlio, calendario, assenza e pasta in bianco.
- [ ] Scuola: correzione motivata, gita e controllo dei numeri per classe.
- [ ] Catering: conferma visiva della nuova schermata compatta con operatori reali, creazione/duplicazione/assegnazione menù settimanali, foto e ripristino dopo eccezione giornaliera.
- [ ] Operatore: ricerca famiglia, scheda e controlli di conferma economica
      su soli dati fittizi.
- [ ] Admin: plessi, calendario, classi, utenze e comportamento senza job.
- [ ] Verifica con il Comune delle regole operative e approvazione del rilascio.
- [ ] Backup, prova del rollback in un ambiente non produttivo e autorizzazione
      specifica prima di toccare `main` o la VPS.

## Regole di sicurezza del collaudo

- Staging Docker isolato: database `mensa_staging`, senza anagrafiche reali.
- Nessun comando del collaudo e stato eseguito su `mensa_db` di produzione.
- Nessun cron di produzione riattivato.
- Nessuna email inviata ai genitori.
- **Esito per il rilascio: NO-GO finche' le verifiche manuali non sono completate.**
