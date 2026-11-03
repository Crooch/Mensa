"""Pagination fixture and HTTP regression checks: disposable localhost staging only."""
import json, os, subprocess, urllib.request, urllib.error
from urllib.parse import urlencode, urlparse
BASE = os.environ.get('MENSA_TICKET2_BASE', 'http://127.0.0.1:18087')
DB = os.environ.get('MENSA_TICKET2_DB_CONTAINER', 'mensa-ticket2-famiglie-database-1')
assert urlparse(BASE).hostname in ('localhost', '127.0.0.1') and DB.startswith('mensa-ticket2-')
def sql(q):
    return subprocess.check_output(['docker', 'exec', '-i', DB, 'sh', '-c', 'test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'], input=q, text=True).strip()
def call(path, token=None, data=None, status=200):
    headers = {'Content-Type': 'application/json'}
    if token: headers['Authorization'] = 'Bearer ' + token
    req = urllib.request.Request(BASE + path, headers=headers, data=json.dumps(data).encode() if data else None)
    try:
        with urllib.request.urlopen(req, timeout=15) as r: code, body = r.status, json.load(r)
    except urllib.error.HTTPError as e: code, body = e.code, json.loads(e.read())
    assert code == status, (path, code, body)
    return body.get('dati', body)
def login(role):
    return call('/api/auth/login', data={'username': role + '.staging', 'password': 'Collaudo2026!'})['token']
# Add only synthetic rows, no accounts/credits/children. Reusable, never delete data.
if sql("SELECT COUNT(*) FROM genitori WHERE cognome='Collaudo Elenco'") == '0':
    values = [f"('Persona {i // 2:03d}', 'Collaudo Elenco', '000{i:06d}', 'famiglia{i}@example.invalid', {int(i >= 215)})" for i in range(220)]
    sql('INSERT INTO genitori (nome,cognome,telefono,email,cancellato_logicamente) VALUES ' + ','.join(values))
assert sql("SELECT COUNT(*) FROM genitori WHERE cognome='Collaudo Elenco'") == '220'
office, admin = login('operatore'), login('admin')
def page(token=office, **params):
    return call('/api/famiglie?' + urlencode(dict(q='Collaudo Elenco', pagina=1, per_pagina=10, stato='attive') | params), token)
ids = []
for n in range(1, 23):
    p = page(pagina=n)
    assert (p['totale'], p['pagine'], p['pagina']) == (215, 22, n)
    assert len(p['famiglie']) == (10 if n < 22 else 5)
    ids += [g['id'] for g in p['famiglie']]
assert len(ids) == len(set(ids)) == 215
assert page(pagina=999)['pagina'] == 22
for size in (10, 25, 50): assert len(page(per_pagina=size)['famiglie']) == size
assert page(stato='tutte')['totale'] == 220
assert page(stato='disattivate')['totale'] == 5
assert page(q='Collaudo Elenco Persona 001')['totale'] == 2
assert page(q='Persona 001 Collaudo Elenco')['totale'] == 2
assert page(q='000000214')['totale'] == 1
assert page(q='famiglia214@example.invalid')['totale'] == 1
for query in ('%', '_', '!', "' OR 1=1 --", 'nessuna-famiglia-sintetica'):
    p = page(q=query)
    assert p['totale'] == 0 and p['famiglie'] == [] and p['pagina'] == 1
assert page(token=admin) == page()
for role in ('scuola.infanzia', 'catering', 'genitore'):
    call('/api/famiglie?pagina=1', login(role), status=403)
call('/api/famiglie?pagina=1', status=401)
for bad in ('pagina=0', 'pagina=-1', 'pagina=abc', 'pagina[]=1', 'pagina=1&per_pagina=200', 'pagina=1&stato=altro', 'pagina=1&q[]=a', 'pagina=1&q='+'x'*121):
    call('/api/famiglie?' + bad, office, status=422)
assert isinstance(call('/api/famiglie?q=Fittizio', office), list)
print('PASS famiglie HTTP: 220 record, 22 pagine senza duplicati/limite 200, ricerca completa e letterale, filtri, pagine 10/25/50, validazione e permessi, compatibilità API')
