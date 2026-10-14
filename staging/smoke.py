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
print("OK: login, calendario, report A-B-C-D con sezione D vuota")

# Scenario genitore -> catering -> saldi con una data futura di mensa.
oggi = dt.date.today()
prossimo_lunedi = oggi + dt.timedelta(days=7 - oggi.weekday())
giorno = max(prossimo_lunedi, dt.date(2026, 10, 19)).isoformat()
genitore = token("genitore.staging")
dashboard = call("/api/genitore/dashboard", token=genitore)
figlio = int(dashboard["figli"][0]["id"])
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
