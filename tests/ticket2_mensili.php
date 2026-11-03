<?php
require dirname(__DIR__).'/bootstrap.php';
use App\Service\AutonomiaService;
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function row($d,$o,$s,$a,$p){return ['data'=>$d,'ordine'=>$o,'stato'=>$s,'alunni'=>$a,'personale'=>$p,'totale'=>$a===null||$p===null?null:$a+$p];}
$rows=[row('2026-11-01','infanzia','Confermato',10,2),row('2026-11-01','infanzia','Confermato',20,3),row('2026-11-02','infanzia','Confermato',5,null),row('2026-11-03','infanzia','Previsto',4,1),row('2026-11-04','infanzia','Non elaborato',null,4),row('2026-11-05','infanzia','Fuori servizio',0,0),row('2026-11-06','infanzia','Previsto',4,null),row('2026-11-02','secondaria','Confermato',7,1),row('2026-12-01','infanzia','Confermato',3,1)];
$m=AutonomiaService::riepiloghiMensili($rows,'2026-11-01','2026-12-10');
check(count($m)===3,'ordine e mese separati');
check($m[0]['alunni']===35 && $m[0]['personale']===5 && $m[0]['totale']===35,'somma due plessi; dati incompleti esclusi dal totale');
check($m[0]['previsti']===5 && $m[0]['giornate_complete']===2 && $m[0]['giornate_incomplete']===3 && $m[0]['giornate_previste']===2,'previsioni e mancanti separati');
check($m[0]['al']==='2026-11-30' && $m[2]['al']==='2026-12-10','limiti mese parziale');
check(AutonomiaService::riepiloghiMensili([],'2026-11-01','2026-11-30')===[],'vuoto');
echo "PASS mensili: più plessi, ordini, mesi, periodo parziale, previsioni, dati mancanti e zero\n";
