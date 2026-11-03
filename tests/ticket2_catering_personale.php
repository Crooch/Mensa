<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
if (getenv('MENSA_STAGING_ONLY')!=='1' || Config::get('db.database')!=='mensa_staging' || Config::get('db.host')!=='database') exit(2);
$c=new Container(); $db=Database::pdo(); $a=$c->autonomia();
function check(bool $ok,string $msg): void {if(!$ok) throw new RuntimeException($msg);echo "PASS $msg\n";}
$u=$db->query("SELECT * FROM utenti WHERE ruolo='operatore' LIMIT 1")->fetch();
$d=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$db->exec("INSERT INTO plessi (nome,cutoff_orario) VALUES ('Catering personale e alunni','00:00:00')");$pid=(int)$db->lastInsertId();
$anno=(int)substr($d,0,4)-((int)substr($d,5,2)<9?1:0);$as=$anno.'/'.($anno+1);
$db->exec("INSERT INTO classi_sezioni (plesso_id,nome,anno_scolastico) VALUES ($pid,'Classe test','$as')");$cid=(int)$db->lastInsertId();
$db->exec("INSERT INTO genitori (nome,cognome,telefono) VALUES ('Test','Catering','000')");$gid=(int)$db->lastInsertId();
foreach([1,2] as $tid){
 $db->exec("INSERT INTO bambini (nome,cognome,genitore_id,tipologia_menu_id) VALUES ('Test','Alunno',$gid,$tid)");$bid=(int)$db->lastInsertId();
 $db->exec("INSERT INTO assegnazioni_classe (bambino_id,classe_sezione_id,data_inizio) VALUES ($bid,$cid,'$d')");
 if($tid===1)$c->presenze()->salvaPastaBianco($bid,$d,true,(int)$u['id']);
}
$c->calendario()->salvaPeriodo($pid,['data_inizio'=>$d,'servizio_attivo'=>1,'motivazione'=>'Test']);
$financial=fn()=>array_map(fn($t)=>$db->query('SELECT * FROM '.$t.' ORDER BY 1')->fetchAll(),['pool_crediti','movimenti_crediti','presenze_giornaliere']);$before=$financial();
$read=fn()=>$c->catering()->report($d,$pid);
$r=$read();check($r['totale_pasti']===2 && !$r['plessi'][0]['personale_disponibile'],'personale non configurato segnalato senza inventare quantità');
foreach([[1,3,'insegnanti'],[2,2,'ata']] as [$tid,$n,$cat]) $a->regola($u,$pid,['dal'=>$d,'giorno'=>(int)(new DateTimeImmutable($d))->format('N'),'categoria'=>$cat,'tipologia_menu_id'=>$tid,'quantita'=>$n,'motivazione'=>'Test','conferma'=>true]);
$c->catering()->pubblicaMenu($pid,2,$d,'Riso e verdure',null,(int)$u['id']);
$r=$read();$p=$r['plessi'][0];
check($r['totale_pasti']===7 && $r['totale_alunni']===2 && $r['totale_personale']===5,'catering somma alunni e personale una sola volta');
check(array_sum(array_column($p['sezioni'],'pasti'))===2 && $p['totale_pasta_in_bianco']===1 && $p['pasti_ordinari']===6,'classi e pasta in bianco restano riferite ai soli alunni');
$tipi=array_column($p['tipologie'],null,'tipologia_id');
check($tipi[1]['pasti']===4 && $tipi[1]['pasti_ordinari']===3 && $tipi[2]['pasti']===3,'preparazione per dieta include entrambi senza duplicare pasta in bianco');
$staff=array_column($p['personale'],null,'tipologia_id');check($staff[2]['menu_descrizione']==='Riso e verdure','menù efficace del personale coincide con il menù della dieta');
$rp=$a->report($u,$d,$d,$pid)['righe'][0];
check($rp['alunni']===2 && $rp['personale']===5 && $rp['totale']===7 && array_sum(array_column($rp['tipologie_alunni'],'pasti'))===2,'riepilogo ed export non ricontano il personale fra gli alunni');
$prima=$a->personale($u,$pid,$d)['righe'];
$a->correggiGiorno($u,$pid,['data'=>$d,'righe'=>[['categoria'=>'assistenti','tipologia_menu_id'=>2,'quantita'=>4]],'precedente'=>$prima,'motivazione'=>'Variazione','conferma'=>true]);
$r=$read();check($r['totale_personale']===4 && $r['totale_pasti']===6 && $r['plessi'][0]['personale'][0]['categoria']==='assistenti','variazione giornaliera sostituisce le ricorrenze nella preparazione');
$db->exec('UPDATE tipologie_menu SET cancellata_logicamente=1 WHERE id=2');
try {check($read()['totale_personale']===4,'tipologia disattivata già registrata conservata nel catering');}
finally {$db->exec('UPDATE tipologie_menu SET cancellata_logicamente=0 WHERE id=2');}
$a->correggiGiorno($u,$pid,['data'=>$d,'righe'=>[],'precedente'=>$a->personale($u,$pid,$d)['righe'],'motivazione'=>'Zero esplicito','conferma'=>true]);
$r=$read();check($r['totale_pasti']===2 && $r['plessi'][0]['personale_disponibile'] && $r['totale_personale']===0,'zero esplicito distinto dal personale non configurato');
check($financial()===$before,'integrazione catering non modifica presenze o contabilità');
