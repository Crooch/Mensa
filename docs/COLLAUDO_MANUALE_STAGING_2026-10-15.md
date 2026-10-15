# Mensa Caltavuturo — collaudo funzionale guidato (staging)

**Data:** 15/10/2026 · **Ramo:** `Crooch/Mensa:dev` · **Stato:** in attesa di verifica umana.

Questo documento integra i test automatici. Va utilizzato **soltanto**
sullo staging locale `http://127.0.0.1:18080`, con utenze e dati fittizi.
Non usare il sito del Comune o anagrafiche di bambini reali.

## Prima di iniziare

Da Terminale, **nella cartella del clone di staging** (senza `down -v`):

```bash
git switch dev
git pull --ff-only origin dev
docker compose -f compose.staging.yml up --build -d --wait
```

Controllare che l'URL sia `http://127.0.0.1:18080` e non quello pubblico.
Le credenziali sintetiche sono in [staging/README.md](../staging/README.md).
Se le prove precedenti hanno cambiato i menù, non cancellarli senza
backup e consenso: verificare prima lo stato con
`php staging/pulisci_menu.php --preview` nel container, come da README.

## Prova operativa: un profilo alla volta

Segnare per ogni scenario **OK / Errore / Non verificato**, indicando
eventuale screenshot, dispositivo e comportamento osservato.

| Profilo | Cosa fare sullo staging | Cosa deve succedere |
|---|---|---|
| Genitore | Aprire calendario, selezionare figlio e un lunedì futuro con mensa | Giorni con/senza mensa distinti, pasto previsto correttamente |
| Genitore | Chiedere pasta in bianco, poi segnalare assenza | La richiesta compare nella cucina; con l'assenza scompare e non aggiunge pasti |
| Genitore | Ripristinare la presenza | Il pasto torna previsto, la pasta in bianco non riappare automaticamente |
| Scuola | Aprire la stessa classe e data, confrontare nominativi e totale | Il numero dei pasti coincide con le scelte delle famiglie |
| Scuola | Provare una correzione motivata o una gita su un'altra data futura | Conferma chiara, modifica limitata alla classe e al giorno scelti |
| Catering | Visualizzare la data: controllo per plesso, classe e tipo alimentare | Totali coerenti, pasta in bianco separata ma **già compresa** nei pasti |
| Catering | Creare e assegnare un modello settimanale fittizio | Nome e descrizione del giorno sono visibili per la tipologia nel plesso scelto |
| Catering | Inserire una variazione su un giorno futuro, poi modificarla e annullarla | La variazione prevale sul modello; l'annullamento ripristina il modello ove presente |
| Catering | Inserire oltre 10 variazioni fittizie oppure consultare un elenco ampio | Date più lontane per prime, 10 per pagina, pulsanti pagina funzionanti |
| Operatore | Ricercare una famiglia fittizia e aprire i moduli senza salvare | Nominativi, plesso, buoni e conferme coerenti; nessuna azione accidentale |
| Operatore | Solo con prova dedicata: acquistare buoni sintetici e aggiornare il riepilogo | Un acquisto genera una sola variazione di saldo anche dopo click ripetuto |
| Amministratore | Controllare plessi, classi, orari limite e calendario | Configurazioni coerenti; i giorni senza mensa non generano pasti |
| Amministratore | Visualizzare utenze e risultati del job | Ruoli corretti, nessun avvio involontario dei conteggi o dello scheduler |

## Verifica finale di usabilità

Ripetere i passaggi **Genitore e Catering** almeno su finestra desktop e
smartphone reale. Controllare leggibilità, pulsanti e dialoghi, nessuno
scorrimento orizzontale, nomi dei menù e pulsante per annullare una
variazione. Da tastiera verificare `Tab`, `Shift+Tab` e i dialoghi.
Per errori riportare **passaggi di riproduzione**, dispositivo/browser,
data della schermata e screenshot **solo con dati fittizi**.

## Condizioni di arresto

- **NO-GO** se saldi/crediti sono incoerenti, se la cucina somma due volte
  le paste in bianco, se un ruolo vede dati non autorizzati, se una modifica
  interessa un giorno/plesso/figlio differente o se la pagina mobile è
  inutilizzabile.
- La prova di backup/ripristino in GitHub CI riguarda solo dati sintetici.
  Il ripristino di una copia protetta e autorizzata del database comunale
  è una verifica **distinta**.
- Non trasferire database/anagrafiche su GitHub o servizi esterni. Nessun
  merge su `main`, deploy sulla VPS o riattivazione cron senza approvazione.
