"""Test T01-T10 su Docker locale dedicato e dati sintetici. Mai su produzione."""
import datetime as dt
import hashlib
import json
import os
import subprocess
import urllib.request
import urllib.error
from urllib.parse import urlparse
from zoneinfo import ZoneInfo
BASE=os.environ.get('MENSA_TICKET2_BASE','http://127.0.0.1:18082')
DB=os.environ.get('MENSA_TICKET2_DB_CONTAINER','mensa-ticket2-database-1')
APP=os.environ.get('MENSA_TICKET2_APP_CONTAINER','mensa-ticket2-app-1')
assert urlparse(BASE).hostname in ('localhost','127.0.0.1')
assert DB.startswith('mensa-ticket2-') and APP.startswith('mensa-ticket2-')
def sql(q):
 return subprocess.check_output(['docker','exec','-i',DB,'sh','-c','test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'],input=q,text=True).strip()
def call(path,payload=None,token=None,method=None,expected=200):
 h={'Content-Type':'application/json'}
 if token:h['Authorization']='Bearer '+token
 r=urllib.request.Request(BASE+path,data=json.dumps(payload).encode() if payload is not None else None,headers=h,method=method)
 try:
  with urllib.request.urlopen(r,timeout=30) as f: status=f.status; data=json.load(f)
 except urllib.error.HTTPError as e: status=e.code; data=json.loads(e.read())
 assert status==expected,(path,status,expected,data)
 return data.get('dati',data)
def token(user):return call('/api/auth/login',{'username':user,'password':'Collaudo2026!'})['token']
def ok(msg):print('PASS '+msg,flush=True)
def snapshot(tables):return {t:hashlib.sha256(sql('SELECT * FROM '+t+' ORDER BY 1').encode()).hexdigest() for t in tables}
admin=token('admin.staging'); comune=token('operatore.staging'); school=token('scuola.infanzia.staging'); catering=token('catering.staging'); parent=token('genitore.staging')
today=dt.datetime.now(ZoneInfo('Europe/Rome')).date(); tomorrow=today+dt.timedelta(days=1); monday=tomorrow+dt.timedelta(days=(7-tomorrow.weekday())%7)
confirm={'motivazione':'Collaudo sintetico Ticket 2','conferma':True}
# only domain tables, excluding ephemeral auth token timestamps
protected=['pool_crediti','movimenti_crediti','presenze_giornaliere','assegnazioni_classe','impostazioni','log_solleciti_inviati','menu_giornalieri','menu_settimanali']
original=snapshot(protected)
# additive schema repeated without altering prior rows
migration=open('database/migrations/010_autonomia_operatori.sql').read()
sql(migration);sql(migration);assert snapshot(protected)==original
ok('migrazione 010 ripetibile; dati precedenti invariati')
# complete permission matrix, direct HTTP and anonymous
writes=[('/api/famiglie/1','DELETE',confirm),('/api/bambini/1','PUT',dict(confirm,nome='Tentativo')),('/api/servizio/plessi/1','PUT',dict(confirm,fido_buoni=20)),('/api/servizio/plessi/1/chiusure','POST',dict(confirm,data_inizio=str(tomorrow),data_fine=str(tomorrow))),('/api/personale/plessi/1/regole','POST',dict(confirm,dal=str(tomorrow),giorno=1,categoria='ata',tipologia_menu_id=1,quantita=3))]
for path,method,data in writes:
 for who in (school,catering,parent):call(path,data,who,method,403)
 call(path,data,None,method,401)
for who in (school,catering,parent):
 for path in ('/api/famiglie/1/verifica','/api/servizio/plessi/1','/api/solleciti/consultazione'):call(path,token=who,expected=403)
call('/api/report/pasti',token=parent,expected=403)
call('/api/personale/plessi/3',token=school,expected=403)
call('/api/report/pasti?plesso_id=3',token=school,expected=403)
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(today),righe=[],precedente=[]),catering,'PUT',403)
assert snapshot(protected)==original
ok('matrice ruoli, anonimi e isolamento Scuola: dinieghi API senza effetti')
# family verification and guarded deletion
v=call('/api/famiglie/1/verifica',token=comune);assert v['figli_attivi']>0 and not v['disattivabile']
call('/api/famiglie/1',confirm,comune,'DELETE',422)
# safe standalone synthetic account, token revocation, repeated operation
suffix=str(dt.datetime.now().timestamp()).replace('.','')
g=call('/api/famiglie',{'nome':'Solo','cognome':'Autonomia'+suffix,'telefono':'000','username':'ticket2.'+suffix,'password':'Collaudo2026!'},comune)['id']
gtoken=token('ticket2.'+suffix)
call('/api/famiglie/'+str(g),confirm,comune,'DELETE');call('/api/famiglie/'+str(g),confirm,comune,'DELETE')
call('/api/genitore/dashboard',token=gtoken,expected=401)
call('/api/auth/login',{'username':'ticket2.'+suffix,'password':'Collaudo2026!'},expected=401)
call('/api/bambini',{'genitore_id':g,'nome':'X','cognome':'Y','tipologia_menu_id':1},comune,expected=422)
assert sql(f"SELECT COUNT(*) FROM audit_amministrativo WHERE operazione='disattivazione_genitore' AND riferimento='{g}'")=='1'
ok('T01 verifica, blocco conflitti, disattivazione sicura e revoca accessi idempotente')
# correct name without touching links or finance
b=call('/api/famiglie/1',token=comune)['figli'][0]
newname='Mario collaudo '+suffix[-4:]
body=dict(confirm,nome=newname,cognome=b['cognome'],precedente={'nome':b['nome'],'cognome':b['cognome']})
call('/api/bambini/'+str(b['id']),body,comune,'PUT')
assert call('/api/famiglie/1',token=comune)['figli'][0]['id']==b['id']
call('/api/bambini/'+str(b['id']),dict(confirm,nome=' '),comune,'PUT',422)
call('/api/bambini/'+str(b['id']),dict(confirm,nome='X',genitore_id=g),comune,'PUT',422)
call('/api/bambini/'+str(b['id']),dict(confirm,nome='X',precedente={'nome':'stale'}),comune,'PUT',422)
call('/api/bambini/'+str(b['id']),dict(confirm,nome=b['nome']),comune,'PUT')
assert snapshot(protected)==original
ok('T08 nome corretto sullo stesso ID, validazioni e conflitto di modifica; contabilità invariata')
# fido/order changes only selected plesso
p3=sql('SELECT * FROM plessi WHERE id=3')
call('/api/servizio/plessi/1',dict(confirm,fido_buoni=20,ordine_scolastico='infanzia'),comune,'PUT')
assert call('/api/servizio/plessi/1',token=comune)['plesso']['fido_buoni']==20
call('/api/servizio/plessi/1',dict(confirm,fido_buoni=-20),comune,'PUT',422)
call('/api/servizio/plessi/1',dict(confirm,fido_buoni=1.2),comune,'PUT',422)
assert sql('SELECT * FROM plessi WHERE id=3')==p3
ok('T09 fido positivo 20 = soglia -20, nessun effetto su altri plessi e buoni')
# closures future, exact replay and incompatible overlap
closure=today+dt.timedelta(days=45)
v=dict(confirm,data_inizio=str(closure),data_fine=str(closure+dt.timedelta(days=2)))
a=call('/api/servizio/plessi/1/chiusure',v,comune);assert call('/api/servizio/plessi/1/chiusure',v,comune)==a
call('/api/servizio/plessi/1/chiusure',dict(confirm,data_inizio=str(closure+dt.timedelta(days=1)),data_fine=str(closure+dt.timedelta(days=4))),comune,expected=422)
call('/api/servizio/plessi/1/chiusure',dict(confirm,data_inizio=str(today)),comune,expected=422)
ok('T06 chiusure future, ripetizione senza duplicazione, sovrapposizioni e retroattività bloccate')
# recurring staff with vegetarian included in total
veggie=call('/api/admin/tipologie',{'nome':'Vegetariano test '+suffix},admin)['id']
for q,t in [(4,1),(1,veggie)]:call('/api/personale/plessi/1/regole',dict(confirm,dal=str(tomorrow),giorno=1,categoria='insegnanti',tipologia_menu_id=t,quantita=q),comune)
call('/api/admin/calendari/1/eccezioni/'+str(monday),{'servizio_attivo':1,'motivazione':'Ricorrenza su giornata sintetica'},admin,'PUT')
p=call('/api/personale/plessi/1?data='+str(monday),token=comune)
assert sum(x['quantita'] for x in p['righe'])==5
assert sum(x['quantita'] for x in p['righe'] if x['tipologia_menu_id']==veggie)==1
call('/api/personale/plessi/1/regole',dict(confirm,dal=str(today),giorno=1,categoria='ata',tipologia_menu_id=1,quantita=3),comune,expected=422)
# prepare isolated today's calendar and cutoff; never production
oldcut=sql('SELECT cutoff_orario FROM plessi WHERE id=1')
sql("UPDATE plessi SET cutoff_orario='23:59:59' WHERE id=1")
call('/api/admin/calendari/1/eccezioni/'+str(today),{'servizio_attivo':1,'motivazione':'Giornata sintetica'},admin,'PUT')
p=call('/api/personale/plessi/1?data='+str(today),token=school)
righe=[{'categoria':'ata','tipologia_menu_id':1,'quantita':3}]
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(today),righe=righe,precedente=p['righe']),school,'PUT')
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(today),righe=righe,precedente=righe),school,'PUT')
call('/api/personale/plessi/3/giorno',dict(confirm,data=str(today),righe=righe,precedente=[]),school,'PUT',403)
sql("UPDATE plessi SET cutoff_orario='00:00:00' WHERE id=1")
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(today),righe=[],precedente=righe),school,'PUT',403)
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(today),righe=[dict(righe[0],quantita=2)],precedente=righe),comune,'PUT')
sql("UPDATE plessi SET cutoff_orario='"+oldcut+"' WHERE id=1")
assert snapshot(protected)==original
ok('T04 ricorrenze, vegetariano incluso, Scuola proprio plesso prima cutoff, rettifica Comune dopo cutoff, nessun addebito')
# reports and privacy
query='/api/report/pasti?dal='+str(today)+'&al='+str(monday)
r=call(query,token=comune);assert r==call(query,token=catering)
assert all(x['alunni'] is None for x in r['righe'] if x['stato']=='Non elaborato')
encoded=json.dumps(r);assert all(k not in encoded for k in ('genitore','saldo','telefono','email','tariffa','fido','bambino_nome'))
refs=call('/api/riferimenti',token=catering);assert 'fido_buoni' not in json.dumps(refs) and 'email' not in json.dumps(refs)
sr=call(query,token=school);assert all(x['plesso_id']==1 for x in sr['righe'])
call('/api/report/pasti?dal=2026-02-30&al=2026-03-01',token=comune,expected=422)
ok('T02/T03 report Comune=Catering, filtri, stato non elaborato, privacy e ambito Scuola')
# historical pool remains present and conserved
sql('INSERT IGNORE INTO pool_crediti (genitore_id,plesso_id,saldo) VALUES (1,3,0)')
d=call('/api/genitore/dashboard',token=parent)
assert any(x['plesso_id']==3 for x in d['crediti_storici']) and all(x['plesso_id']!=3 for x in d['crediti'])
assert call('/api/famiglie/1',token=comune)['crediti']
ok('T07 posizioni correnti/storiche separate, visibilità completa al Comune, saldo conservato')
# reminder reads are side effect-free, no automatic send
before=snapshot(['log_solleciti_inviati','impostazioni','pool_crediti','movimenti_crediti'])
call('/api/solleciti/consultazione?plesso_id=1&q=Fittizio',token=comune)
call('/api/solleciti/consultazione?plesso_id=1&q=Fittizio',token=comune)
assert snapshot(list(before))==before
ok('T05 filtri e consultazione senza nuovi invii o modifiche SMTP')
# service stop beats exceptional opening; history remains unchanged
stop=today+dt.timedelta(days=90)
call('/api/servizio/plessi/1',dict(confirm,primo_giorno_sospensione=str(stop)),comune,'PUT')
call('/api/admin/calendari/1/eccezioni/'+str(stop),{'servizio_attivo':1,'motivazione':'Test precedenza sospensione'},admin,'PUT')
past=snapshot(['presenze_giornaliere','movimenti_crediti','assegnazioni_classe'])
call('/api/genitore/presenze/dichiarazione',{'bambino_id':1,'data':str(stop),'stato':'presente'},parent,expected=422)
call('/api/genitore/pasta-bianco',{'bambino_id':1,'data':str(stop),'richiesta':True},parent,expected=422)
rr=call('/api/report/pasti?dal='+str(stop)+'&al='+str(stop)+'&plesso_id=1',token=comune)
assert rr['righe'][0]['stato']=='Fuori servizio'
assert snapshot(list(past))==past
call('/api/genitore/dashboard',token=parent)
ok('T10 sospensione prevale sulle aperture, operazioni future bloccate, storico disponibile')
print('ALL T01-T10 HTTP TESTS PASSED')
