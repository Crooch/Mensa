"""Staff write/read contract and authorization against disposable localhost only."""
import datetime as dt, hashlib, json, os, subprocess, urllib.request, urllib.error, uuid
from pathlib import Path
from urllib.parse import urlparse
from zoneinfo import ZoneInfo
BASE=os.environ.get('MENSA_TICKET2_BASE','http://127.0.0.1:18089')
DB=os.environ.get('MENSA_TICKET2_DB_CONTAINER','mensa-ticket2-coerenza-database-1')
assert urlparse(BASE).hostname in ('localhost','127.0.0.1') and DB.startswith('mensa-ticket2-')
def sql(q):
 return subprocess.check_output(['docker','exec','-i',DB,'sh','-c','test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'],input=q,text=True).strip()
def call(path,data=None,t=None,method=None,status=200):
 h={'Content-Type':'application/json'}
 if t:h['Authorization']='Bearer '+t
 r=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers=h,method=method)
 try:
  with urllib.request.urlopen(r,timeout=30) as f:c=f.status;b=json.load(f)
 except urllib.error.HTTPError as e:c=e.code;b=json.loads(e.read())
 assert c==status,(path,c,b)
 return b.get('dati',b)
def token(u):return call('/api/auth/login',{'username':u,'password':'Collaudo2026!'})['token']
def snap():return {n:hashlib.sha256(sql('SELECT * FROM '+n+' ORDER BY 1').encode()).hexdigest() for n in ('pool_crediti','movimenti_crediti','presenze_giornaliere','assegnazioni_classe','log_solleciti_inviati')}
admin=token('admin.staging');office=token('operatore.staging');cat=token('catering.staging');parent=token('genitore.staging')
before=snap();today=dt.datetime.now(ZoneInfo('Europe/Rome')).date()
def day(n):return str(today+dt.timedelta(days=n))
uid=uuid.uuid4().hex[:8]
pid=call('/api/admin/plessi',{'nome':'ZZ Coerenza '+uid,'cutoff_orario':'00:00:00'},admin)['id']
username='coerenza.'+uid
call('/api/admin/utenti',{'ruolo':'scuola','username':username,'password':'Collaudo2026!','nome_completo':'Scuola sintetica','plesso_id':pid},admin)
school=token(username);root=f'/api/personale/plessi/{pid}'
confirm={'motivazione':'Prova coerenza sintetica','conferma':True}
def read(d=day(2),who=school):return call(root+'?data='+d,t=who)
def rule(q,tid=1,**extra):
 return call(root+'/regole',dict(confirm,dal=day(0),giorni=list(range(1,8)),categoria='insegnanti',tipologia_menu_id=tid,quantita=q,**extra),office)
def edit(d,rows,who=school,previous=None,status=200,**extra):
 return call(root+'/giorno',dict(confirm,data=d,righe=rows,precedente=read(d)['righe'] if previous is None else previous,**extra),who,'PUT',status)
def row(q,tid=1,cat='insegnanti'):return {'categoria':cat,'tipologia_menu_id':tid,'quantita':q}
def total(d=day(2)):return sum(r['quantita'] for r in read(d)['righe'])
def audits():return sql(f"SELECT COUNT(*) FROM audit_amministrativo WHERE riferimento='{pid}' OR riferimento LIKE '{pid}:%'")
for n in (-1,0,1,2,3,4,8,15):
 call(f'/api/admin/calendari/{pid}/eccezioni/'+day(n),{'servizio_attivo':1,'motivazione':'Test isolato'},admin,'PUT')
# No rules is unknown; explicitly entering zero is a real daily record.
assert not read()['disponibile']
assert edit(day(2),[])['modificato'] and read()['disponibile']
edit(day(2),[],ripristina=True);assert not read()['disponibile']
rule(9);rule(3,2)
call(root+'/regole',dict(confirm,dal=day(0),giorni=list(range(1,8)),**row(0,1,'ata')),office)
# Explicit zero + numeric strings + changed row order are equivalent quantities.
p=read();a=audits();original=p['righe'];reordered=[{k:str(v) if k!='categoria' else v for k,v in r.items()} for r in reversed(original) if r['quantita']>0]+[row(0,1,'asacom')]
assert edit(day(2),reordered,previous=list(reversed(original)))['modificato'] is False
assert audits()==a and read()['fonte']=='Da programmazione'
assert sql(f"SELECT COUNT(*) FROM pasti_personale_giorni WHERE plesso_id={pid}")=='0'
assert rule(9)['modificato'] is False and audits()==a
version=p['versione_programmazione'];rule(10);assert total()==13
call(root+'/regole',dict(confirm,dal=day(0),giorni=[1],categoria='insegnanti',tipologia_menu_id=1,quantita=99,versione_precedente=version),office,status=422)
# Real override affects just one date, persists across recurrence changes, reason survives reads.
reason='Assenza personale <img src=x onerror=alert(1)>'
assert edit(day(2),[row(7),row(3,2)],motivazione=reason)['modificato']
assert total()==10 and total(day(3))==13
p=read();assert p['storico_giorno'][0]['motivazione']==reason
stamp=dt.datetime.fromisoformat(p['storico_giorno'][0]['registrato_il']);assert abs((dt.datetime.now(ZoneInfo('Europe/Rome'))-stamp).total_seconds())<30
assert stamp.utcoffset()==dt.datetime.now(ZoneInfo('Europe/Rome')).utcoffset()
stored=json.loads(sql(f"SELECT righe FROM pasti_personale_giorni WHERE plesso_id={pid} AND data='{day(2)}'"));assert stored==p['righe']
assert sql(f"SELECT motivazione FROM audit_amministrativo WHERE riferimento='{pid}:{day(2)}' ORDER BY id DESC LIMIT 1")==reason
rule(11);assert total()==10 and total(day(3))==14
assert read(who=cat)['storico_giorno']==[] and read(who=cat)['storico_programmazione']==[]
for who in (office,admin,school,cat):
 result=call(f'/api/report/pasti?dal={day(2)}&al={day(2)}&plesso_id={pid}',t=who)
 assert result['righe'][0]['personale']==10 and result['righe'][0]['tipologie_personale']==stored
# Stale saves must not silently overwrite more recent changes.
edit(day(2),[row(6)],previous=original,status=422)
edit(day(2),[],previous=p['righe'],ripristina=True);assert total()==14 and read()['fonte']=='Da programmazione'
assert read()['storico_giorno'][0]['operazione']=='ripristino_personale'
# Permissions enforced on server, including midnight cutoff and a direct API bypass attempt.
for n in (0,1):
 assert read(day(n))['modificabile'];edit(day(n),[row(5+n)]);assert total(day(n))==5+n
assert not read(day(-1))['modificabile']
edit(day(-1),[row(5)],status=403)
edit(day(-1),[row(4)],who=office);edit(day(-1),[row(3)],who=admin)
assert total(day(-1))==3
edit(day(-1),[],who=office,ripristina=True,status=422)
past=sql(f"SELECT righe FROM pasti_personale_giorni WHERE plesso_id={pid} AND data='{day(-1)}'")
rule(12);assert sql(f"SELECT righe FROM pasti_personale_giorni WHERE plesso_id={pid} AND data='{day(-1)}'")==past
for who,code in ((cat,403),(parent,403),(None,401)):
 call(root+'/giorno',dict(confirm,data=day(2),righe=[],precedente=[]),who,'PUT',code)
call('/api/personale/plessi/1?data='+day(2),t=school,status=403)
call('/api/personale/plessi/1/giorno',dict(confirm,data=day(2),righe=[],precedente=[]),school,'PUT',403)
for who in (school,cat,parent):call(root+'/regole',dict(confirm,dal=day(0),giorni=[1],**row(1)),who,status=403)
call(root+'/regole',dict(confirm,dal=day(-1),giorni=[1],**row(1)),office,status=422)
edit(day(2),[row(1)],motivazione='',status=422);edit(day(2),[row(1)],conferma=False,status=422)
call(f'/api/admin/calendari/{pid}/eccezioni/'+day(4),{'servizio_attivo':0,'motivazione':'Chiusura sintetica'},admin,'PUT')
assert not read(day(4))['modificabile'];edit(day(4),[row(2)],status=422);edit(day(4),[row(2)],who=office,status=422)
# New effective date and selected weekdays only; recurring motivation visible.
future=dict(confirm,dal=day(8),giorni=[(today+dt.timedelta(days=8)).isoweekday()],**row(20))
call(root+'/regole',future,office)
assert total(day(3))==15 and total(day(8))==23 and total(day(15))==23
assert read()['storico_programmazione'][0]['righe'][0]['dal']==day(8)
# Retired menu already present must survive unrelated edits, never added to a new category.
tid=call('/api/admin/tipologie',{'nome':'ZZ Ritirata '+uid},admin)['id']
edit(day(2),[row(12),row(3,2),row(2,tid)])
call('/api/admin/tipologie/'+str(tid),t=admin,method='DELETE')
assert any(int(t['id'])==tid and int(t['cancellata_logicamente']) for t in read()['tipologie_personale'])
edit(day(2),[row(13),row(3,2),row(2,tid)])
edit(day(3),[row(1,tid)],status=422)
assert total()==18
assert snap()==before
Path('staging/ticket2-artifacts').mkdir(exist_ok=True)
Path('staging/ticket2-artifacts/coerenza-fixture.json').write_text(json.dumps({'pid':pid,'username':username,'date':day(2),'past':day(-1),'today':day(0),'tomorrow':day(1),'retired':tid}))
print('PASS coerenza HTTP/SQL: no-op order/strings/zero; recurring and daily priority; readback/reports/history; stale writes; role/date/scope/closure guards; retired menus preserved; accounting unchanged')
