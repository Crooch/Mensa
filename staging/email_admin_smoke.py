"""Admin email controls on disposable localhost. SMTP delivery remains simulated."""
import datetime as dt,hashlib,json,os,subprocess,urllib.request,urllib.error,uuid
from zoneinfo import ZoneInfo
from urllib.parse import urlparse
BASE=os.environ.get('MENSA_TICKET2_BASE','http://127.0.0.1:18090');DB=os.environ.get('MENSA_TICKET2_DB_CONTAINER','mensa-ticket2-email-database-1')
assert urlparse(BASE).hostname in ('127.0.0.1','localhost') and DB.startswith('mensa-ticket2-')
def sql(q):return subprocess.check_output(['docker','exec','-i',DB,'sh','-c','test "$MARIADB_DATABASE" = mensa_staging && exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" --batch --skip-column-names mensa_staging'],input=q,text=True).strip()
def call(path,data=None,t=None,status=200):
 h={'Content-Type':'application/json'}
 if t:h['Authorization']='Bearer '+t
 try:
  with urllib.request.urlopen(urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers=h),timeout=30) as f:code=f.status;b=json.load(f)
 except urllib.error.HTTPError as e:code=e.code;b=json.loads(e.read())
 assert code==status,(path,code,b)
 return b.get('dati',b)
def login(u):return call('/api/auth/login',{'username':u+'.staging','password':'Collaudo2026!'})['token']
a=login('admin');roles=[login(x) for x in ('operatore','scuola.infanzia','catering','genitore')]
endpoint='/api/admin/impostazioni'
for t in roles+[None]:
 for path,data in [(endpoint,{'solleciti_automatici_enabled':True}),('/api/admin/smtp-verifica',{}),('/api/admin/email-prova',{'email':'test@example.invalid','plesso_id':1})]:call(path,data,t,403 if t else 401)
def settings():return call(endpoint,t=a)
assert not settings()['solleciti_automatici_enabled'] and settings()['invio_simulato']
call(endpoint,{'solleciti_automatici_enabled':True},a,422)
call('/api/admin/smtp-verifica',{},a,422)
call('/api/admin/email-prova',{'email':'bad\r\nBcc: other@example.invalid','plesso_id':1},a,422)
config={'smtp_host':'smtp.example.invalid','smtp_port':587,'smtp_security':'tls','smtp_from':'mensa@example.invalid','smtp_username':'mensa-user','smtp_password':'unit-password','smtp_from_name':'Mensa prova'}
call(endpoint,config,a)
assert settings()['smtp_password']=='********' and 'unit-password' not in json.dumps(settings())
for field,value in [('smtp_host','host/path'),('smtp_port',0),('smtp_security','bad'),('smtp_from','not an email'),('solleciti_automatici_enabled','false')]:
 call(endpoint,{field:value},a,422)
 assert sql("SELECT valore FROM impostazioni WHERE chiave='smtp_password'")=='unit-password'
call(endpoint,{'smtp_password':'********'},a);assert sql("SELECT valore FROM impostazioni WHERE chiave='smtp_password'")=='unit-password'
# No implicit copies or financial writes even when habitual recipients exist.
def snapshot():return {n:hashlib.sha256(sql('SELECT * FROM '+n+' ORDER BY 1').encode()).hexdigest() for n in ('pool_crediti','movimenti_crediti','log_solleciti_inviati')}
before=snapshot()
model={'plesso_id':1,'oggetto':'Test {plesso} {genitore}','testo':'{genitore}\n{alunno} · saldo {saldo} / fido {fido} · {data}\n<testo>','attivo':False}
call('/api/admin/modelli-email',model,a)
call('/api/admin/destinatari',{'etichetta':'CC sintetico','email':'cc@example.invalid'},a)
call(endpoint,{'solleciti_automatici_enabled':True},a,422)
r=call('/api/admin/smtp-verifica',{},a);assert r=={'simulato':True,'verificata':False}
r=call('/api/admin/email-prova',{'email':'test@example.invalid','plesso_id':1},a)
assert r['simulato'] and not r['inviata'] and r['destinatario']=='test@example.invalid'
assert r['oggetto'].startswith('[PROVA]') and 'Genitore di prova' in r['oggetto']
assert 'Alunno di prova' in r['testo'] and '-15' in r['testo'] and '{' not in r['testo']
call('/api/admin/email-prova',{'email':'test@example.invalid','plesso_id':999999},a,422)
call('/api/admin/modelli-email',dict(model,attivo=True),a)
# Actual job consumes the DB switch; empty fixture schools never generate financial transactions.
for enabled in (True,False):
 call(endpoint,{'solleciti_automatici_enabled':enabled},a);assert settings()['solleciti_automatici_enabled'] is enabled
 assert sql("SELECT valore FROM impostazioni WHERE chiave='solleciti_automatici_enabled'")==('1' if enabled else '0')
 p=call('/api/admin/plessi',{'nome':'ZZ Email '+uuid.uuid4().hex[:6],'cutoff_orario':'00:00:00'},a)['id']
 today=str(dt.datetime.now(ZoneInfo('Europe/Rome')).date())
 req=urllib.request.Request(BASE+f'/api/admin/calendari/{p}/eccezioni/'+today,data=json.dumps({'servizio_attivo':1,'motivazione':'Test locale email'}).encode(),headers={'Content-Type':'application/json','Authorization':'Bearer '+a},method='PUT')
 urllib.request.urlopen(req).close()
 code='require "bootstrap.php"; if(getenv("MENSA_STAGING_ONLY")!=="1" || App\\Core\\Config::get("db.database")!=="mensa_staging")exit(2); echo json_encode((new App\\Core\\Container())->job()->eseguiPerPlesso((int)$argv[1]));'
 job=json.loads(subprocess.check_output(['docker','exec',DB.replace('-database-','-app-'),'php','-r',code,str(p)],text=True))
 assert job['solleciti'].get('disabilitati',False) is not enabled,job
assert snapshot()==before
# Activation did not change the scheduler, nor send a mail immediately.
assert settings()['scheduler_abilitato'] is False
print('PASS Admin email HTTP/DB: role isolation, SMTP validation/masking, inactive template test, synthetic placeholders, explicit recipient, truthful simulation, DB switch drives actual job, default/disable preserved, no financial or reminder-log writes')
