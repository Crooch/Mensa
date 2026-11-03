<?php
declare(strict_types=1);
// Esecuzione solo nel container mensa-ticket2, mai su DB comunale.
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
if (getenv('MENSA_STAGING_ONLY')!=='1' || Config::get('db.database')!=='mensa_staging' || Config::get('db.host')!=='database') exit(2);
$c=new Container();$db=Database::pdo();$oggi=date('Y-m-d');
function check(bool $v,string $msg): void {if(!$v)throw new RuntimeException($msg);echo "PASS $msg\n";}
$u=$db->query("SELECT * FROM utenti WHERE ruolo='operatore' LIMIT 1")->fetch();
// A separate synthetic plesso avoids interactions with the UI/HTTP fixtures.
$db->exec("INSERT INTO plessi (nome,cutoff_orario) VALUES ('Collaudo consolidamento','00:00:00')");$p=(int)$db->lastInsertId();
$db->exec("INSERT INTO classi_sezioni (plesso_id,nome,anno_scolastico) VALUES ($p,'TEST','2026/2027')");$cl=(int)$db->lastInsertId();
$db->exec("INSERT INTO genitori (nome,cognome,telefono) VALUES ('Genitore','Consolidamento','000')");$g=(int)$db->lastInsertId();
$db->exec("INSERT INTO bambini (nome,cognome,genitore_id,tipologia_menu_id) VALUES ('Alunno','Consolidamento',$g,1)");$b=(int)$db->lastInsertId();
$db->exec("INSERT INTO assegnazioni_classe (bambino_id,classe_sezione_id,data_inizio) VALUES ($b,$cl,'$oggi')");
$c->calendario()->salvaPeriodo($p,['data_inizio'=>$oggi,'data_fine'=>$oggi,'servizio_attivo'=>1,'motivazione'=>'Test sintetico']);
$a=$c->autonomia();$righe=[['categoria'=>'ata','tipologia_menu_id'=>1,'quantita'=>3]];
$a->correggiGiorno($u,$p,['data'=>$oggi,'righe'=>$righe,'precedente'=>[],'motivazione'=>'Test','conferma'=>true]);
$r=$c->job()->eseguiPerPlesso($p,$oggi);
check($c->jobRepo()->esecuzione($p,$oggi)['esito']==='ok','job sintetico concluso');
$report=$a->report($u,$oggi,$oggi,$p)['righe'][0];
check($report['alunni']===1 && $report['personale']===3 && $report['totale']===4,'consolidamento alunni + personale corretto');
check($report['tipologie_alunni'][0]['tipologia_id']===1,'dieta storicizzata al consolidamento');
$db->exec("INSERT INTO tipologie_menu (nome) VALUES ('Dieta successiva')");$tid=(int)$db->lastInsertId();
$a->modificaBambino($u,$b,['tipologia_menu_id'=>$tid,'motivazione'=>'Cambio successivo','conferma'=>true]);
$report2=$a->report($u,$oggi,$oggi,$p)['righe'][0];
check($report2['tipologie_alunni']===$report['tipologie_alunni'],'cambio dieta non riscrive il report consolidato');
$saldo=$db->query("SELECT saldo FROM pool_crediti WHERE genitore_id=$g AND plesso_id=$p")->fetchColumn();
$c->job()->eseguiPerPlesso($p,$oggi);
check((int)$db->query("SELECT COUNT(*) FROM movimenti_crediti m JOIN pool_crediti pc ON pc.id=m.pool_id WHERE pc.genitore_id=$g")->fetchColumn()===1,'job ripetuto: un solo consumo alunno');
check((int)$saldo===-1,'personale non consuma buoni e fido non impedisce pasto');
$a->correggiGiorno($u,$p,['data'=>$oggi,'righe'=>[['categoria'=>'ata','tipologia_menu_id'=>1,'quantita'=>2]],'precedente'=>$righe,'motivazione'=>'Rettifica motivata','conferma'=>true]);
check($a->report($u,$oggi,$oggi,$p)['righe'][0]['totale']===3,'rettifica personale dopo cutoff aggiorna report');
check((int)$db->query("SELECT saldo FROM pool_crediti WHERE genitore_id=$g AND plesso_id=$p")->fetchColumn()===-1,'rettifica personale lascia contabilità invariata');
$domani=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$c->calendario()->salvaPeriodo($p,['data_inizio'=>$domani,'servizio_attivo'=>1,'motivazione'=>'Test futuro']);
$a->correggiGiorno($u,$p,['data'=>$domani,'righe'=>$righe,'precedente'=>[],'motivazione'=>'Test futuro','conferma'=>true]);
$a->configura($u,$p,['primo_giorno_sospensione'=>$domani,'motivazione'=>'Fine servizio','conferma'=>true]);
check($a->report($u,$domani,$domani,$p)['righe'][0]['totale']===0,'sospensione annulla previsioni future del personale già inserite');
check($a->report($u,$oggi,$oggi,$p)['righe'][0]['totale']===3,'sospensione conserva conteggi storici');
// Simulate an already reached end only on the synthetic plesso.
$db->exec("UPDATE impostazioni_servizio_plesso SET primo_giorno_sospensione='$oggi' WHERE plesso_id=$p");
try {$c->feedback()->inserisci($g,$b,$oggi,4,'Test');throw new RuntimeException('Feedback consentito dopo fine');}catch(\App\Core\HttpException $e){check($e->getCode()===422,'Genitore sola lettura dopo fine servizio');}
$a->configura($u,$p,['fido_buoni'=>20,'primo_giorno_sospensione'=>$oggi,'motivazione'=>'Attività amministrativa','conferma'=>true]);
check((int)$c->plessi()->trova($p)['fido_buoni']===20,'attività amministrative disponibili dopo fine servizio');
