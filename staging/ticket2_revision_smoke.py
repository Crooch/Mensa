"""Revised T04/T02/T03/T06, synthetic localhost only; no production data."""
import datetime as dt,json,os,subprocess,urllib.request,urllib.error,hashlib
from urllib.parse import urlparse
from zoneinfo import ZoneInfo
BASE=os.environ.get('MENSA_TICKET2_BASE','http://127.0.0.1:18086')
DB=os.environ.get('MENSA_TICKET2_DB_CONTAINER','mensa-ticket2-revisione-database-1')
assert urlparse(BASE).hostname in ('localhost','127.0.0.1') and DB.startswith('mensa-ticket2-')
def sql(q):return subprocess.check_output(['docker','exec','-i',DB,'sh','-c','test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'],input=q,text=True).strip()
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
admin=token('admin.staging');office=token('operatore.staging');school=token('scuola.infanzia.staging');cat=token('catering.staging');parent=token('genitore.staging')
today=dt.datetime.now(ZoneInfo('Europe/Rome')).date();yesterday=today-dt.timedelta(days=1);tomorrow=today+dt.timedelta(days=1)
def day(n):return str(today+dt.timedelta(days=n))
confirm={'motivazione':'Revisione sintetica','conferma':True}
def read(d):return call('/api/personale/plessi/1?data='+str(d),t=school)
def edit(d,qty,t=school,**extra):
 p=read(d);return call('/api/personale/plessi/1/giorno',dict(confirm,data=str(d),righe=[{'categoria':'insegnanti','tipologia_menu_id':1,'quantita':qty}],precedente=p['righe'],**extra),t,'PUT')
def snap():return {name:hashlib.sha256(sql('SELECT * FROM '+name+' ORDER BY 1').encode()).hexdigest() for name in ('pool_crediti','movimenti_crediti','presenze_giornaliere','assegnazioni_classe','log_solleciti_inviati')}
before=snap()
# All dates deliberately opened inside this disposable fixture only.
for d in [yesterday,today,tomorrow,today+dt.timedelta(days=8),today+dt.timedelta(days=15)]:
 call('/api/admin/calendari/1/eccezioni/'+str(d),{'servizio_attivo':1,'motivazione':'Test revisione'},admin,'PUT')
rule=dict(confirm,dal=str(today),giorni=[1,2,3,4,5,6,7],categoria='insegnanti',tipologia_menu_id=1,quantita=9)
call('/api/personale/plessi/1/regole',rule,office)
for n in (1,8,15):assert next(r['quantita'] for r in read(day(n))['righe'] if r['categoria']=='insegnanti' and r['tipologia_menu_id']==1)==9
assert 'cutoff' not in read(today)
# School cannot configure weekly rules or alter prior days / other schools.
for who in (school,cat,parent):call('/api/personale/plessi/1/regole',rule,who,status=403)
call('/api/personale/plessi/1/regole',dict(rule,dal=str(yesterday)),office,status=422)
old=sql('SELECT cutoff_orario FROM plessi WHERE id=1');sql("UPDATE plessi SET cutoff_orario='00:00:00' WHERE id=1")
edit(today,8);assert read(today)['fonte']=='Variazione giornaliera'
edit(tomorrow,7);assert sum(r['quantita'] for r in read(tomorrow)['righe'])==7
p=read(tomorrow)
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(tomorrow),righe=[],precedente=[]),school,'PUT',422)
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(yesterday),righe=[],precedente=[]),school,'PUT',403)
edit(yesterday,6,t=office)
call('/api/personale/plessi/3/giorno',dict(confirm,data=str(tomorrow),righe=[],precedente=[]),school,'PUT',403)
for who in (cat,parent):call('/api/personale/plessi/1/giorno',dict(confirm,data=str(tomorrow),righe=[],precedente=[]),who,'PUT',403)
# Rule changes affect recurrence, never daily overrides or earlier dates.
past=sql("SELECT * FROM pasti_personale_giorni WHERE data<'"+str(today)+"' ORDER BY plesso_id,data")
call('/api/personale/plessi/1/regole',dict(rule,dal=day(8),quantita=10),office)
assert sum(r['quantita'] for r in read(tomorrow)['righe'])==7
assert next(r['quantita'] for r in read(day(15))['righe'] if r['categoria']=='insegnanti' and r['tipologia_menu_id']==1)==10
call('/api/personale/plessi/1/giorno',dict(confirm,data=str(tomorrow),ripristina=True,precedente=p['righe']),school,'PUT')
assert read(tomorrow)['fonte']=='Da programmazione'
assert next(r['quantita'] for r in read(tomorrow)['righe'] if r['categoria']=='insegnanti' and r['tipologia_menu_id']==1)==9
assert sql("SELECT * FROM pasti_personale_giorni WHERE data<'"+str(today)+"' ORDER BY plesso_id,data")==past
# Closure edit/delete guarded by role, school, old state, future bounds, overlaps.
payload=dict(confirm,data_inizio=day(32),data_fine=day(34))
cid=call('/api/servizio/plessi/1/chiusure',payload,office)['id'];url=f'/api/servizio/plessi/1/chiusure/{cid}'
previous={k:payload[k] for k in ('data_inizio','data_fine','motivazione')}
change=dict(confirm,data_inizio=day(33),data_fine=day(35),precedente=previous)
for who in (school,cat,parent,None):
 for method in ('PUT','DELETE'):call(url,change,who,method,401 if who is None else 403)
call(f'/api/servizio/plessi/3/chiusure/{cid}',change,office,'PUT',404)
call(url,dict(change,data_inizio=str(today)),office,'PUT',422)
call(url,change,office,'PUT')
call(url,dict(change,data_inizio=day(34)),office,'PUT',422)
current={'data_inizio':day(33),'data_fine':day(35),'motivazione':confirm['motivazione']}
other=call('/api/servizio/plessi/1/chiusure',dict(confirm,data_inizio=day(37),data_fine=day(39)),office)['id']
call(url,dict(change,precedente=current,data_fine=day(38)),office,'PUT',422)
call(url,dict(confirm,precedente=current),office,'DELETE')
call(url,dict(confirm,precedente=current),office,'DELETE',404)
# Inactive day is never editable, even with a previously stored future variation.
d=day(37);call('/api/personale/plessi/1/giorno',dict(confirm,data=d,righe=[],precedente=[]),school,'PUT',422)
# Cannot modify begun closures or openings through the office closure endpoints.
call('/api/admin/calendari/1/eccezioni/'+str(yesterday),{'servizio_attivo':0,'motivazione':'Test passato'},admin,'PUT')
idpast=int(sql("SELECT id FROM eccezioni_calendario WHERE plesso_id=1 AND giorno='"+str(yesterday)+"'"))
call(f'/api/servizio/plessi/1/chiusure/{idpast}',dict(confirm,precedente={}),office,'DELETE',422)
# Monthly report agrees for office/catering; school scope retained.
url='/api/report/pasti?dal='+str(today)+'&al='+day(40)
r=call(url,t=office);assert r==call(url,t=cat)
assert sum(m['alunni'] for m in r['mensili'])==r['totali']['alunni_confermati']
assert sum(m['totale'] for m in r['mensili'])==r['totali']['totale_confermato']
sr=call(url,t=school);assert all(x['plesso_id']==1 for x in sr['righe'])
assert snap()==before
sql("UPDATE plessi SET cutoff_orario='"+old+"' WHERE id=1")
print('PASS revisione HTTP: ricorrenze persistenti, decorrenza, variazioni oggi/future senza cutoff, passato Comune, ripristino, storico, chiusure CRUD/permesse/conflitti, mensili e contabilità invariata')
