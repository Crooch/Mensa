import json
import datetime as dt
import urllib.request
import urllib.error

base = "http://127.0.0.1:18080"
def call(path, payload=None, token=None):
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    req = urllib.request.Request(base + path,
        data=json.dumps(payload).encode() if payload is not None else None, headers=headers)
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.load(resp)["dati"]

def token(username):
    return call("/api/auth/login",
        {"username": username, "password": "Collaudo2026!"})["token"]

admin = token("admin.staging")
catering = token("catering.staging")
assert call("/api/admin/calendari/1", token=admin)["configurazione"]["lunedi"] == 1
report = call("/api/catering/report?data=2026-10-19", token=catering)
infanzia = next(x for x in report["plessi"] if x["plesso_id"] == 1)
sezioni = {x["classe_nome"]: int(x["pasti"]) for x in infanzia["sezioni"]}
assert set(sezioni) == {"Sezione A", "Sezione B", "Sezione C", "Sezione D"}
assert sezioni["Sezione D"] == 0

# Test regressione: mensa dell'infanzia dal 19/10, non dal 15/10.
giorno_prima = "2026-10-15"
scuola_prima = token("scuola.infanzia.staging")
classe_a = next(c for c in call("/api/scuola/classi", token=scuola_prima)
                if c["nome"] == "Sezione A")
giornata_prima = call("/api/scuola/classi/" + str(classe_a["id"]) +
                      "/giornata?data=" + giorno_prima, token=scuola_prima)
assert giornata_prima["servizio_mensa"] == {
    "attivo": False, "motivo": "servizio_non_avviato"
}, "Il 15 ottobre il servizio deve risultare non iniziato"
assert len(giornata_prima["alunni"]) == 2
assert all(a["stato_effettivo"] is None and a["default_calcolato"] is None
           for a in giornata_prima["alunni"]), "Nessun pasto previsto prima dell'avvio"
assert call("/api/catering/report?data=" + giorno_prima, token=catering)["totale_pasti"] == 0
try:
    call("/api/scuola/presenze/override", {
        "bambino_id": int(giornata_prima["alunni"][0]["bambino_id"]),
        "data": giorno_prima, "stato": "presente",
        "motivazione": "Test: nessun pasto prima dell'avvio"
    }, token=scuola_prima)
    raise AssertionError("Correzione consentita in giornata senza servizio")
except urllib.error.HTTPError as exc:
    assert exc.code == 422, "Le modifiche in giorni senza mensa devono essere rifiutate"
assert all(a["stato_effettivo"] is None
           for a in call("/api/scuola/classi/" + str(classe_a["id"]) +
                         "/giornata?data=" + giorno_prima, token=scuola_prima)["alunni"])

giornata_attiva = call("/api/scuola/classi/" + str(classe_a["id"]) +
                       "/giornata?data=2026-10-19", token=scuola_prima)
assert giornata_attiva["servizio_mensa"]["attivo"] is True
assert any(a["stato_effettivo"] == "presente" for a in giornata_attiva["alunni"])
print("OK: Scuola 15/10 senza servizio, niente pasti o correzioni; 19/10 attivo.")
print("OK: login, calendario, report A-B-C-D con sezione D vuota")

# Scenario genitore -> catering -> saldi con una data futura di mensa.
oggi = dt.date.today()
prossimo_lunedi = oggi + dt.timedelta(days=7 - oggi.weekday())
giorno = max(prossimo_lunedi, dt.date(2026, 10, 19)).isoformat()
genitore = token("genitore.staging")
dashboard = call("/api/genitore/dashboard", token=genitore)
figlio = int(dashboard["figli"][0]["id"])
stato_prima = call("/api/genitore/figli/" + str(figlio) +
                   "/giornata?data=" + giorno_prima, token=genitore)
assert stato_prima["servizio_mensa"]["motivo"] == "servizio_non_avviato"
assert stato_prima["stato_effettivo"] is None
assert stato_prima["default_calcolato"] is None
assert stato_prima["pasta_in_bianco"] is False
print("OK: Genitore 15/10 senza pasto, in coerenza con Scuola e Catering.")
calendario_famiglia = call(
    "/api/genitore/figli/" + str(figlio) + "/calendario?anno=2026&mese=10", token=genitore)
giorni = {x["data"]: x for x in calendario_famiglia["giorni"]}
assert len(giorni) == 31, "Il calendario deve contenere tutte le date del mese"
assert giorni["2026-10-19"]["servizio_mensa"]["attivo"] is True
assert giorni["2026-10-20"]["servizio_mensa"]["attivo"] is True
assert giorni["2026-10-17"]["servizio_mensa"]["attivo"] is False
assert giorni["2026-10-17"]["stato_effettivo"] is None
print("OK: calendario familiare completo, giorni attivi e giorni senza mensa distinti")
saldo_iniziale = dashboard["crediti"][0]["saldo"]
# Pasta in bianco è richiedibile soltanto se il bambino mangerà a mensa.
call("/api/genitore/presenze/dichiarazione", {
    "bambino_id": figlio, "data": giorno, "stato": "presente"
}, token=genitore)
call("/api/genitore/pasta-bianco", {
    "bambino_id": figlio, "data": giorno, "richiesta": 1
}, token=genitore)
report = call("/api/catering/report?data=" + giorno, token=catering)
infanzia = next(x for x in report["plessi"] if x["plesso_id"] == 1)
assert any(int(r["bambino_id"]) == figlio for r in infanzia["pasta_in_bianco"])

esito_assenza = call("/api/genitore/presenze/dichiarazione", {
    "bambino_id": figlio, "data": giorno, "stato": "assente"
}, token=genitore)
assert esito_assenza['pasta_annullata'] is True
stato = call("/api/genitore/figli/" + str(figlio) +
             "/giornata?data=" + giorno, token=genitore)
assert stato["stato_effettivo"] == "assente"
assert not stato["pasta_in_bianco"], "L'assenza deve annullare la pasta in bianco"
try:
    call("/api/genitore/pasta-bianco", {
        "bambino_id": figlio, "data": giorno, "richiesta": 1
    }, token=genitore)
    raise AssertionError("La richiesta di pasta in bianco è consentita con bambino assente")
except urllib.error.HTTPError as exc:
    assert exc.code in (400, 403, 409, 422), "Errore atteso per bambino assente"
report = call("/api/catering/report?data=" + giorno, token=catering)
infanzia = next(x for x in report["plessi"] if x["plesso_id"] == 1)
assert all(int(r["bambino_id"]) != figlio for r in infanzia["pasta_in_bianco"])

call("/api/genitore/presenze/dichiarazione", {
    "bambino_id": figlio, "data": giorno, "stato": "presente"
}, token=genitore)
stato = call("/api/genitore/figli/" + str(figlio) +
             "/giornata?data=" + giorno, token=genitore)
assert not stato["pasta_in_bianco"], "La vecchia richiesta non deve riattivarsi"
call("/api/genitore/pasta-bianco", {
    "bambino_id": figlio, "data": giorno, "richiesta": 1
}, token=genitore)
report = call("/api/catering/report?data=" + giorno, token=catering)
infanzia = next(x for x in report["plessi"] if x["plesso_id"] == 1)
assert any(int(r["bambino_id"]) == figlio for r in infanzia["pasta_in_bianco"])
assert call("/api/genitore/dashboard", token=genitore)["crediti"][0]["saldo"] == saldo_iniziale
print("OK: pasta solo se presente, cancellazione con assenza, riattivazione esplicita e saldo invariato")

# Verifica end-to-end Scuola -> Catering -> Genitore, su dati sintetici.
scuola = token("scuola.infanzia.staging")
classi_scuola = call("/api/scuola/classi", token=scuola)
classe_figlio = None
for classe_scuola in classi_scuola:
    vista = call("/api/scuola/classi/" + str(classe_scuola["id"]) +
                 "/giornata?data=" + giorno, token=scuola)
    if any(int(a["bambino_id"]) == figlio for a in vista["alunni"]):
        classe_figlio = classe_scuola["id"]
        break
assert classe_figlio is not None, "Bambino di prova non trovato nelle classi della scuola"
pasti_prima = sum(int(s["pasti"]) for s in infanzia["sezioni"])
esito_scuola = call("/api/scuola/presenze/override", {
    "bambino_id": figlio, "data": giorno,
    "stato": "assente", "motivazione": "Assente, prova integrazione"
}, token=scuola)
assert esito_scuola["stato_effettivo"] == "assente"
vista = call("/api/scuola/classi/" + str(classe_figlio) +
             "/giornata?data=" + giorno, token=scuola)
riga = next(a for a in vista["alunni"] if int(a["bambino_id"]) == figlio)
assert riga["stato_effettivo"] == "assente"
assert riga["override_motivazione"] == "Assente, prova integrazione"
report_assente = call("/api/catering/report?data=" + giorno, token=catering)
infanzia_assente = next(x for x in report_assente["plessi"] if x["plesso_id"] == 1)
assert sum(int(s["pasti"]) for s in infanzia_assente["sezioni"]) == pasti_prima - 1
assert all(int(a["bambino_id"]) != figlio for a in infanzia_assente["pasta_in_bianco"])
esito_scuola = call("/api/scuola/presenze/override", {
    "bambino_id": figlio, "data": giorno,
    "stato": "presente", "motivazione": "Presenza verificata, prova integrazione"
}, token=scuola)
assert esito_scuola["stato_effettivo"] == "presente"
report_presente = call("/api/catering/report?data=" + giorno, token=catering)
infanzia_presente = next(x for x in report_presente["plessi"] if x["plesso_id"] == 1)
assert sum(int(s["pasti"]) for s in infanzia_presente["sezioni"]) == pasti_prima
stato = call("/api/genitore/figli/" + str(figlio) +
             "/giornata?data=" + giorno, token=genitore)
assert stato["stato_effettivo"] == "presente"
assert not stato["pasta_in_bianco"], "La correzione scuola non deve riattivare una pasta annullata"
assert call("/api/genitore/dashboard", token=genitore)["crediti"][0]["saldo"] == saldo_iniziale
print("OK: Scuola -> Catering -> Genitore, motivazione salvata, conteggi e credito invariato")

# Regressione gita: il messaggio "tutta la classe non mangera'" deve
# prevalere sulle precedenti conferme di famiglia/scuola, e avere effetto
# in Scuola, Catering e Genitore senza ricariche del database.
gita_giorno = (dt.date.fromisoformat(giorno) + dt.timedelta(days=1)).isoformat()
vista_gita = call("/api/scuola/classi/" + str(classe_figlio) +
                  "/giornata?data=" + gita_giorno, token=scuola)
assert vista_gita["servizio_mensa"]["attivo"] is True
assert len(vista_gita["alunni"]) == 2
figli_gita = [int(a["bambino_id"]) for a in vista_gita["alunni"]]
assert figlio in figli_gita
altro_figlio = next(b for b in figli_gita if b != figlio)

call("/api/genitore/presenze/dichiarazione", {
    "bambino_id": figlio, "data": gita_giorno, "stato": "presente"
}, token=genitore)
call("/api/scuola/presenze/override", {
    "bambino_id": altro_figlio, "data": gita_giorno, "stato": "presente",
    "motivazione": "Presenza verificata prima della gita"
}, token=scuola)
call("/api/genitore/pasta-bianco", {
    "bambino_id": figlio, "data": gita_giorno, "richiesta": 1
}, token=genitore)

prima_gita = call("/api/catering/report?data=" + gita_giorno, token=catering)
plesso_prima = next(x for x in prima_gita["plessi"] if x["plesso_id"] == 1)
sezione_prima = next(x for x in plesso_prima["sezioni"] if x["classe_id"] == classe_figlio)
assert int(sezione_prima["pasti"]) == 2

esito_gita = call("/api/scuola/assenze-massa", {
    "classe_sezione_id": classe_figlio, "data": gita_giorno,
    "motivo": "Gita scolastica: nessun pasto"
}, token=scuola)
assert int(esito_gita["id"]) > 0

dopo_gita = call("/api/scuola/classi/" + str(classe_figlio) +
                 "/giornata?data=" + gita_giorno, token=scuola)
assert dopo_gita["assenza_massa"]["motivo"] == "Gita scolastica: nessun pasto"
assert all(a["stato_effettivo"] == "assente" for a in dopo_gita["alunni"])
assert any(a["dichiarazione_genitore"] == "presente" for a in dopo_gita["alunni"])
assert any(a["override_scuola"] == "presente" for a in dopo_gita["alunni"])

report_gita = call("/api/catering/report?data=" + gita_giorno, token=catering)
plesso_gita = next(x for x in report_gita["plessi"] if x["plesso_id"] == 1)
sezione_gita = next(x for x in plesso_gita["sezioni"] if x["classe_id"] == classe_figlio)
assert int(sezione_gita["pasti"]) == 0
assert int(plesso_gita["totale_pasti"]) == int(plesso_prima["totale_pasti"]) - 2
assert all(b["bambino_id"] not in figli_gita for b in plesso_gita["pasta_in_bianco"])

stato_gita = call("/api/genitore/figli/" + str(figlio) +
                  "/giornata?data=" + gita_giorno, token=genitore)
assert stato_gita["servizio_mensa"] == {"attivo": False, "motivo": "assenza_classe"}
assert stato_gita["stato_effettivo"] is None
assert stato_gita["pasta_in_bianco"] is False
mese_gita = call("/api/genitore/figli/" + str(figlio) +
                 "/calendario?anno=" + gita_giorno[:4] +
                 "&mese=" + str(int(gita_giorno[5:7])), token=genitore)
riga_gita = next(x for x in mese_gita["giorni"] if x["data"] == gita_giorno)
assert riga_gita["servizio_mensa"]["motivo"] == "assenza_classe"
assert riga_gita["modificabile"] is False

def rifiuta_gita(path, payload, bearer, descrizione):
    try:
        call(path, payload, token=bearer)
        raise AssertionError("La gita non ha bloccato " + descrizione)
    except urllib.error.HTTPError as exc:
        assert exc.code == 422, descrizione + ": atteso errore di validazione"

rifiuta_gita("/api/genitore/presenze/dichiarazione", {
    "bambino_id": figlio, "data": gita_giorno, "stato": "presente"
}, genitore, "nuova presenza familiare")
rifiuta_gita("/api/scuola/presenze/override", {
    "bambino_id": figlio, "data": gita_giorno, "stato": "presente",
    "motivazione": "Conferma per errore"
}, scuola, "nuova conferma scuola")
rifiuta_gita("/api/genitore/pasta-bianco", {
    "bambino_id": figlio, "data": gita_giorno, "richiesta": 1
}, genitore, "pasta in bianco")
rifiuta_gita("/api/scuola/assenze-massa", {
    "classe_sezione_id": classe_figlio, "data": gita_giorno,
    "motivo": "Inserimento duplicato"
}, scuola, "gita duplicata")
assert call("/api/genitore/dashboard", token=genitore)["crediti"][0]["saldo"] == saldo_iniziale
print("OK: gita prevale su famiglia/scuola, pasti zero, niente pasta o nuovi interventi.")
