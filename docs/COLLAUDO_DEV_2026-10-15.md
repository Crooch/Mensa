# Mensa Caltavuturo — esito collaudo tecnico `dev`

**Data:** 15 ottobre 2026. **Ambiente:** CI GitHub Actions con Docker
`mensa_staging` sintetico. **Ramo:** `Crooch/Mensa:dev`.
I test sono stati aggiornati progressivamente per la gestione dei menù e la
preparazione al rilascio. I risultati seguenti si riferiscono a dati fittizi;
la produzione non è stata modificata.

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
| MariaDB CI | Migrazioni 002–008 applicate due volte; saldi e movimenti preesistenti sintetici invariati | SUPERATO |
| Docker CI | Backup dello staging con ripristino in un **secondo** database sintetico, verifica struttura e conteggi, eliminazione copia temporanea | SUPERATO |
| Catering | Variazioni giornaliere: ordinamento data futura per prima, 10 per pagina, annullamento e nessun messaggio ridondante | SUPERATO |
| Percorso browser | Genitore → Scuola → Catering: richiesta pasta in bianco, assenza e ripristino, conteggi plesso sintetico **4→3→4** senza doppi pasti | SUPERATO |

### Evidenze più recenti

- [GitHub PHP quality checks](https://github.com/Crooch/Mensa/actions/runs/38719791383):
  controlli PHP/JS, integrazione e migrazioni sul database sintetico.
- [Staging Docker HTTP e ripristino backup](https://github.com/Crooch/Mensa/actions/runs/38719791629):
  prova HTTP, dump e ripristino in una seconda base; confronto tabelle e saldi.
- [Browser Chrome/Chromium — cinque ruoli e percorso incrociato](https://github.com/Crooch/Mensa/actions/runs/38721219047):
  test automatici su 375, 768 e 1366 px; Genitore, Scuola e Catering con modifiche
  su bambino sintetico e verifica dei conteggi aggiornati.
- Screenshot dei test browser disponibili negli artifact delle relative
  pipeline GitHub, con conservazione limitata; nessun dato reale di minori.

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

Per la prova visiva/operativa con utenti, seguire la
[guida di collaudo manuale dello staging](COLLAUDO_MANUALE_STAGING_2026-10-15.md).

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
- [x] Prova **sintetica** di dump e ripristino su seconda base Docker separata.
- [ ] Prova di migrazione e ripristino su **copia isolata e protetta del database comunale**, senza trasferire dati personali in CI pubblica.
- [ ] Autorizzazione specifica prima di toccare `main` o la VPS.

## Regole di sicurezza del collaudo

- Staging Docker isolato: database `mensa_staging`, senza anagrafiche reali.
- Nessun comando del collaudo e stato eseguito su `mensa_db` di produzione.
- Nessun cron di produzione riattivato.
- Nessuna email inviata ai genitori.
- **Esito per il rilascio: NO-GO finche' le verifiche manuali non sono completate.**
