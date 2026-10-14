import json
import urllib.request

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
