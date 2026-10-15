# Mensa Caltavuturo — esito collaudo tecnico `dev`

**Data:** 15 ottobre 2026. **Ambiente:** CI GitHub Actions con Docker
`mensa_staging` sintetico. **Commit applicativo collaudato:** `0d23c8e`.

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

### Evidenze

- [PHP quality checks](https://github.com/Crooch/Mensa/actions/runs/38705356417)
- [Staging Docker HTTP smoke](https://github.com/Crooch/Mensa/actions/runs/38705356476)
- [Staging Browser 5 ruoli](https://github.com/Crooch/Mensa/actions/runs/38705356451)
- Gli screenshot (esclusivamente con dati sintetici) sono allegati come artifact
  nella run del browser. La conservazione in GitHub Actions è limitata a 7 giorni.

I test Chromium esaminano **solo navigazione, visibilità dei pannelli,
overflow e assenza di eccezioni JS**; non sostituiscono l'esame umano della
leggibilità, dell'accessibilità o del corretto flusso operativo.

## Verifiche manuali ancora aperte

- [ ] Verifica visiva su Mac a 1366 px, tablet a 768 px, telefono a circa 375 px.
- [ ] Uso con tastiera, focus, tab, dialoghi e messaggi di errore.
- [ ] Genitore: scelta data/figlio, calendario, assenza e pasta in bianco.
- [ ] Scuola: correzione motivata, gita e controllo dei numeri per classe.
- [ ] Catering: creazione/duplicazione/assegnazione menù settimanali,
      foto e ripristino dopo eccezione giornaliera.
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
