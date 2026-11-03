<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\Container;
use App\Core\Database;
use App\Core\HttpException;
use App\Repository\AutonomiaRepository;
final class AutonomiaService
{
 private AutonomiaRepository $r;
 public function __construct(private Container $c) { $this->r=new AutonomiaRepository(); }
 public static function data(string $v): string {
  $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
  if (!$d || $d->format('Y-m-d')!==$v) throw HttpException::nonValido('Data non valida.');
  return $v;
 }
 private function ufficio(array $u): void { if (!in_array($u['ruolo'],['admin','operatore'],true)) throw HttpException::nonAutorizzato(); }
 public function ambito(array $u,int $id,bool $storico=false): array {
  if (!in_array($u['ruolo'],['admin','operatore','scuola','catering'],true) || ($u['ruolo']==='scuola' && (int)($u['plesso_id']??0)!==$id)) throw HttpException::nonAutorizzato('Operazione non consentita per questo plesso.');
  $p=$this->c->plessi()->trova($id);
  if (!$p || (!$storico && (int)$p['cancellato_logicamente'])) throw HttpException::nonTrovato('Plesso non attivo.');
  return $p;
 }
 private function conferma(array $v): string {
  if (!is_string($v['motivazione']??null)) throw HttpException::nonValido('Motivazione testuale obbligatoria.');
  $motivo=trim($v['motivazione']);
  if (($v['conferma']??false)!==true || $motivo==='' || mb_strlen($motivo)>255) throw HttpException::nonValido('Conferma l’operazione e indica una motivazione entro 255 caratteri.');
  return $motivo;
 }
 public function verificaFamiglia(array $u,int $id): array {
  $this->ufficio($u);
  $g=$this->r->famiglia($id);
  if (!$g) throw HttpException::nonTrovato('Famiglia non trovata.');
  $v=$this->r->verificaFamiglia($id);
  $v['genitore']=$g;
  $v['disattivabile']=$v['figli_attivi']===0 && $v['saldi_non_zero']===0 && $this->r->utenzaDisattivabile($g);
  $v['avvertenza']='Le omonimie sono indizi, non duplicati certi. Fusioni, trasferimenti di alunni o saldi richiedono assistenza tecnica.';
  return $v;
 }
 public function disattiva(array $u,int $id,array $v): array {
  $this->ufficio($u); $motivo=$this->conferma($v);
  return Database::inTransazione(function() use($u,$id,$motivo) {
   $g=$this->r->famiglia($id,true);
   if (!$g) throw HttpException::nonTrovato('Famiglia non trovata.');
   if ((int)$g['cancellato_logicamente']) return ['disattivato'=>true];
   $check=$this->r->verificaFamiglia($id);
   if (!$this->r->utenzaDisattivabile($g)) throw HttpException::nonValido('Utenza condivisa o non di ruolo genitore. Richiedere assistenza.');
   if ($check['figli_attivi'] || $check['saldi_non_zero']) throw HttpException::nonValido('Disattivazione bloccata: sono presenti figli attivi o saldi non nulli. Richiedere assistenza; non cancellare o trasferire dati per aggirare il blocco.');
   $this->r->disattivaFamiglia($g);
   $this->r->audit((int)$u['id'],'disattivazione_genitore',(string)$id,['attivo'=>true],['attivo'=>false],$motivo);
   return ['disattivato'=>true];
  });
 }
 public function modificaBambino(array $u,int $id,array $v): array {
  $this->ufficio($u); $motivo=$this->conferma($v);
  $ammessi=['nome','cognome','tipologia_menu_id','conferma','motivazione','precedente'];
  if (array_diff(array_keys($v),$ammessi)) throw HttpException::nonValido('È possibile modificare solo nome, cognome e dieta.');
  return Database::inTransazione(function() use($u,$id,$v,$motivo) {
   $b=$this->r->bambino($id,true);
   if (!$b) throw HttpException::nonTrovato('Alunno non trovato.');
   $nuovo=[]; $prima=[];
   foreach (['nome','cognome','tipologia_menu_id'] as $k) if (array_key_exists($k,$v)) {
    if ($k!=='tipologia_menu_id' && !is_string($v[$k])) throw HttpException::nonValido('Nome e cognome devono essere testo.');
    $val=trim((string)$v[$k]);
    if ($k==='tipologia_menu_id') {
     if (!ctype_digit($val) || !$this->c->tipologie()->trova((int)$val)) throw HttpException::nonValido('Tipologia non valida.');
     $val=(int)$val;
    } elseif ($val==='' || mb_strlen($val)>80 || preg_match('/[\x00-\x1f\x7f]/u',$val)) throw HttpException::nonValido('Nome e cognome devono contenere da 1 a 80 caratteri.');
    $prima[$k]=$b[$k]; $nuovo[$k]=$val;
   }
   if (!$nuovo) throw HttpException::nonValido('Nessun dato da modificare.');
   if (isset($v['precedente']) && $v['precedente']!=$prima) throw HttpException::nonValido('I dati sono cambiati. Riapri la scheda e verifica.');
   if ($prima!=$nuovo) { $this->r->nome($id,$nuovo); $this->r->audit((int)$u['id'],'modifica_alunno',(string)$id,$prima,$nuovo,$motivo); }
   return ['aggiornato'=>true];
  });
 }
 public function disattivaBambino(array $u,int $id,array $v): array {
  $this->ufficio($u); $motivo=$this->conferma($v);
  return Database::inTransazione(function() use($u,$id,$motivo) {
   $b=$this->r->bambino($id,true);
   if (!$b) throw HttpException::nonTrovato('Alunno non trovato.');
   if ((int)$b['cancellato_logicamente']) return ['disattivato'=>true];
   if ($this->r->blocchiBambino($id)) throw HttpException::nonValido('Sono presenti assegnazioni correnti/future o presenze. Richiedere assistenza prima di disattivare l’alunno.');
   $this->r->disattivaBambino($id);$this->r->audit((int)$u['id'],'disattivazione_alunno',(string)$id,true,false,$motivo);
   return ['disattivato'=>true];
  });
 }
 public function configurazione(array $u,int $id): array {
  $this->ufficio($u); $p=$this->ambito($u,$id);
  return ['plesso'=>['id'=>$id,'nome'=>$p['nome'],'fido_buoni'=>(int)$p['fido_buoni']],'servizio'=>$this->r->impostazioni($id),'calendario'=>$this->c->calendario()->dettaglio($id)];
 }
 public function configura(array $u,int $id,array $v): array {
  $this->ufficio($u); $this->ambito($u,$id); $motivo=$this->conferma($v);
  return Database::inTransazione(function() use($u,$id,$v,$motivo) {
   $p=$this->r->lockPlesso($id); $prima=$this->r->impostazioni($id); $dopo=$prima;
   if (array_key_exists('ordine_scolastico',$v)) {
    if (!in_array($v['ordine_scolastico'],['infanzia','primaria','secondaria'],true)) throw HttpException::nonValido('Seleziona un ordine scolastico.');
    $dopo['ordine_scolastico']=$v['ordine_scolastico'];
   }
   if (array_key_exists('primo_giorno_sospensione',$v)) {
    $fine=$v['primo_giorno_sospensione'] ?: null;
    if ($fine!==null) { self::data((string)$fine); if ($fine<=date('Y-m-d') && $fine!==$prima['primo_giorno_sospensione']) throw HttpException::nonValido('La sospensione deve iniziare da una data futura.'); }
    if ($prima['primo_giorno_sospensione']!==null && $prima['primo_giorno_sospensione']<=date('Y-m-d') && $fine!==$prima['primo_giorno_sospensione']) throw HttpException::nonValido('Servizio già concluso: per la riapertura richiedere assistenza.');
    $dopo['primo_giorno_sospensione']=$fine;
   }
   if (array_key_exists('fido_buoni',$v)) {
    $n=filter_var($v['fido_buoni'],FILTER_VALIDATE_INT);
    if ($n===false || $n<0 || $n>1000) throw HttpException::nonValido('Il fido deve essere un numero intero da 0 a 1000. Per la soglia -20 inserisci 20.');
    $this->r->fido($id,$n);
    $this->r->audit((int)$u['id'],'fido',(string)$id,(int)$p['fido_buoni'],$n,$motivo);
   }
   $this->r->salvaImpostazioni($id,$dopo);
   $this->r->audit((int)$u['id'],'servizio',(string)$id,$prima,$dopo,$motivo);
   return ['salvato'=>true];
  });
 }
 public function chiusura(array $u,int $id,array $v): array {
  $this->ufficio($u); $this->ambito($u,$id); $motivo=$this->conferma($v);
  $dal=self::data((string)($v['data_inizio']??'')); $al=self::data((string)($v['data_fine']??$dal));
  if ($dal<=date('Y-m-d') || $al<$dal) throw HttpException::nonValido('Inserisci un intervallo futuro valido. Le giornate trascorse restano invariate.');
  return Database::inTransazione(function() use($u,$id,$dal,$al,$motivo) {
   $this->r->lockPlesso($id);
   foreach ($this->c->calendario()->dettaglio($id)['eccezioni'] as $e) if ($e['giorno']===$dal && ($e['giorno_fine']?:$e['giorno'])===$al && !(int)$e['servizio_attivo']) return ['id'=>(int)$e['id']];
   $val=['data_inizio'=>$dal,'data_fine'=>$al,'servizio_attivo'=>0,'motivazione'=>$motivo];
   $eid=$this->c->calendario()->salvaPeriodo($id,$val);
   $this->r->audit((int)$u['id'],'chiusura',(string)$id,null,$val,$motivo);
   return ['id'=>$eid];
  });
 }
 public function modificaChiusura(array $u,int $id,int $periodo,array $v,bool $elimina=false): array {
  $this->ufficio($u); $this->ambito($u,$id); $motivo=$this->conferma($v);
  $repo=new \App\Repository\CalendarioRepository();
  if (!$repo->acquisisciLockPeriodi($id)) throw HttpException::nonValido('Calendario in modifica. Riprova.');
  try {
   return Database::inTransazione(function() use($u,$id,$periodo,$v,$elimina,$motivo,$repo) {
    $p=$repo->trovaPeriodo($id,$periodo);
    if (!$p) throw HttpException::nonTrovato('Chiusura non trovata per questa scuola. Ricarica il calendario.');
    if ((int)$p['servizio_attivo'] || $p['giorno']<=date('Y-m-d')) throw HttpException::nonValido('Si possono modificare o eliminare solo chiusure non ancora iniziate.');
    $prima=['data_inizio'=>$p['giorno'],'data_fine'=>$p['fine_effettiva'],'motivazione'=>$p['motivazione']];
    if (($v['precedente']??null)!=$prima) throw HttpException::nonValido('La chiusura è cambiata. Ricarica il calendario.');
    $dopo=null;
    if ($elimina) $this->c->calendario()->cancellaPeriodo($id,$periodo);
    else {
     $dal=self::data((string)($v['data_inizio']??'')); $al=self::data((string)($v['data_fine']??''));
     if ($dal<=date('Y-m-d') || $al<$dal) throw HttpException::nonValido('Indica un intervallo interamente futuro.');
     $dopo=['data_inizio'=>$dal,'data_fine'=>$al,'motivazione'=>$motivo];
     $this->c->calendario()->salvaPeriodo($id,$dopo+['servizio_attivo'=>0],$periodo);
    }
    $this->r->audit((int)$u['id'],$elimina?'eliminazione_chiusura':'modifica_chiusura',$id.':'.$periodo,$prima,$dopo,$motivo);
    return ['salvato'=>true];
   });
  } finally {$repo->rilasciaLockPeriodi($id);}
 }
 public function personale(array $u,int $id,string $data): array {
  $this->ambito($u,$id,true); self::data($data);
  $g=$this->r->giorno($id,$data); $calendario=$this->c->calendario()->previsto($id,$data); $attivo=$calendario['attivo'];
  $base=$this->ricorrenti($id,$data);
  $storico=$g && (bool)$g['consolidato'] && $data<=date('Y-m-d');
  $usaGiorno=$g && ($g['origine']!=='programmazione' || $data<date('Y-m-d'));
  $righe=$usaGiorno?json_decode($g['righe'],true,512,JSON_THROW_ON_ERROR):$base;
  $disponibile=$usaGiorno || count($base)>0;
  $fonte=$usaGiorno?($g['origine']==='programmazione'?'Da programmazione':($g['origine']==='registrato'?'Registrato':'Variazione giornaliera')):($disponibile?'Da programmazione':'Da configurare');
  if (!$attivo && !$storico) { $righe=[]; $disponibile=true; $fonte='Fuori servizio'; }
  $ufficio=in_array($u['ruolo'],['admin','operatore'],true);
  $modificabile=($attivo || ($ufficio && $storico)) && ($ufficio || ($u['ruolo']==='scuola' && $data>=date('Y-m-d')));
  return ['plesso_id'=>$id,'data'=>$data,'attivo'=>$attivo,'motivo_servizio'=>$calendario['motivo'],'righe'=>$righe,'disponibile'=>$disponibile,'fonte'=>$fonte,
   'consolidato'=>$g?(bool)$g['consolidato']:false,'modificabile'=>$modificabile,
   'ripristinabile'=>$modificabile && $data>=date('Y-m-d') && $g && $g['origine']!=='programmazione',
   'regole'=>$ufficio?$this->r->regole($id):[],
   // Quantità aggregate consultabili anche da Scuola (nel proprio ambito) e Catering.
   'programmazione'=>array_map(static fn($r)=>array_intersect_key($r,array_flip(['dal','giorno','categoria','tipologia_menu_id','quantita'])),$this->r->regole($id)),
   'programmate'=>$base];
 }
 private function ricorrenti(int $id,string $data): array {
  $out=[]; $giorno=(int)(new \DateTimeImmutable($data))->format('N');
  foreach($this->r->regole($id) as $r) if ($r['dal']<=$data && (int)$r['giorno']===$giorno) $out[$r['categoria'].':'.$r['tipologia_menu_id']]=['categoria'=>$r['categoria'],'tipologia_menu_id'=>(int)$r['tipologia_menu_id'],'quantita'=>(int)$r['quantita']];
  return array_values($out);
 }
 private function riga(array $v): array {
  if (!in_array($v['categoria']??'', ['insegnanti','ata','assistenti','asacom'],true)) throw HttpException::nonValido('Categoria personale non valida.');
  $q=filter_var($v['quantita']??null,FILTER_VALIDATE_INT); $tid=filter_var($v['tipologia_menu_id']??null,FILTER_VALIDATE_INT);
  $t=$tid?$this->c->tipologie()->trova($tid):null;
  if ($q===false || $q<0 || $q>1000 || !$t || (int)$t['cancellata_logicamente']) throw HttpException::nonValido('Quantità intera da 0 a 1000 e tipologia attiva obbligatorie.');
  return ['categoria'=>$v['categoria'],'tipologia_menu_id'=>$tid,'quantita'=>$q];
 }
 public function regola(array $u,int $id,array $v): array {
  $this->ufficio($u); $this->ambito($u,$id); $motivo=$this->conferma($v);
  $dal=self::data((string)($v['dal']??'')); $giorni=$v['giorni']??[$v['giorno']??null];
  if ($dal<date('Y-m-d') || !is_array($giorni) || !$giorni || count($giorni)>7) throw HttpException::nonValido('La decorrenza deve essere oggi o futura. Seleziona almeno un giorno.');
  foreach($giorni as &$g) { $g=filter_var($g,FILTER_VALIDATE_INT); if ($g===false || $g<1 || $g>7) throw HttpException::nonValido('Giorno della settimana non valido.'); } unset($g);
  $giorni=array_unique($giorni); $riga=$this->riga($v);
  return Database::inTransazione(function() use($u,$id,$riga,$dal,$giorni,$motivo) {
   $this->r->lockPlesso($id); $prima=$this->r->regole($id);
   foreach($giorni as $g) $this->r->salvaRegola($id,$riga+['dal'=>$dal,'giorno'=>$g]);
   $dopo=$this->r->regole($id);
   // Una fotografia automatica odierna segue le nuove regole; le variazioni esplicite restano prioritarie.
   $oggi=date('Y-m-d'); $snapshot=$this->r->giorno($id,$oggi);
   if ($dal===$oggi && $snapshot && $snapshot['origine']==='programmazione') $this->r->salvaGiorno($id,$oggi,$this->ricorrenti($id,$oggi),true,'programmazione');
   $this->r->audit((int)$u['id'],'ricorrenza_personale',(string)$id,$prima,$dopo,$motivo);
   return ['salvato'=>true];
  });
 }
 public function correggiGiorno(array $u,int $id,array $v): array {
  $this->ambito($u,$id);
  if (!in_array($u['ruolo'],['admin','operatore','scuola'],true)) throw HttpException::nonAutorizzato();
  $motivo=$this->conferma($v); $data=self::data((string)($v['data']??''));
  $ripristina=($v['ripristina']??false)===true;
  $righe=[]; $chiavi=[];
  if (!$ripristina) {
   if (!is_array($v['righe']??null) || count($v['righe'])>100) throw HttpException::nonValido('Elenco pasti non valido.');
   foreach ($v['righe'] as $r) {
    if (!is_array($r)) throw HttpException::nonValido('Riga non valida.');
    $r=$this->riga($r); $k=$r['categoria'].':'.$r['tipologia_menu_id'];
    if (isset($chiavi[$k])) throw HttpException::nonValido('Categoria e tipologia duplicate: indica un unico totale.');
    $chiavi[$k]=true; $righe[]=$r;
   }
  }
  return Database::inTransazione(function() use($u,$id,$data,$righe,$v,$motivo,$ripristina) {
   $this->r->lockPlesso($id);
   if ($u['ruolo']==='scuola' && $data<date('Y-m-d')) throw HttpException::nonAutorizzato('Per le date passate richiedere una rettifica al Comune.');
   $stato=$this->personale($u,$id,$data); $prima=$stato['righe'];
   if (!$stato['modificabile']) throw HttpException::nonValido('Nessun servizio modificabile per questa data.');
   if (!array_key_exists('precedente',$v) || $v['precedente']!=$prima) throw HttpException::nonValido('I conteggi sono cambiati. Ricarica prima di salvare.');
   $g=$this->r->giorno($id,$data);
   if ($ripristina) {
    if ($data<date('Y-m-d')) throw HttpException::nonValido('Per il passato indicare le quantità effettive, senza ripristinare la programmazione.');
    if ($g && $g['origine']!=='programmazione') {
     $this->r->eliminaGiorno($id,$data);
     $this->r->audit((int)$u['id'],'ripristino_personale',$id.':'.$data,['righe'=>$prima,'fonte'=>$stato['fonte']],['righe'=>$this->ricorrenti($id,$data),'fonte'=>'Da programmazione'],$motivo);
    }
   } elseif ($prima!=$righe || !$stato['disponibile']) {
    $this->r->salvaGiorno($id,$data,$righe,$data<date('Y-m-d') || (bool)($g['consolidato']??false),'variazione');
    $this->r->audit((int)$u['id'],'personale_giorno',$id.':'.$data,['righe'=>$prima,'disponibile'=>$stato['disponibile']],['righe'=>$righe,'disponibile'=>true],$motivo);
   }
   return ['salvato'=>true];
  });
 }
 public function consolidaPersonale(int $id,string $data): void {
  Database::inTransazione(function() use($id,$data) {
   $this->r->lockPlesso($id); $this->r->storicizzaTipologie($id,$data); $g=$this->r->giorno($id,$data);
   $base=$this->ricorrenti($id,$data);
   // Nessuna configurazione non equivale a zero pasti. Il job non limita le correzioni della Scuola.
   if (!$g && $base) $this->r->salvaGiorno($id,$data,$base,true,'programmazione');
   elseif ($g && !(int)$g['consolidato']) $this->r->salvaGiorno($id,$data,json_decode($g['righe'],true),true,$g['origine']);
  });
 }
 public function report(array $u,string $dal,string $al,?int $pid,string $ordine=''): array {
  self::data($dal); self::data($al);
  if ($al<$dal || (new \DateTimeImmutable($dal))->diff(new \DateTimeImmutable($al))->days>92) throw HttpException::nonValido('Seleziona un intervallo massimo di 93 giorni.');
  if (!in_array($ordine,['','infanzia','primaria','secondaria'],true)) throw HttpException::nonValido('Ordine non valido.');
  if ($u['ruolo']==='scuola') { $this->ambito($u,(int)($u['plesso_id']??0)); if ($pid && $pid!==(int)$u['plesso_id']) throw HttpException::nonAutorizzato(); $pid=(int)$u['plesso_id']; }
  if ($pid) $this->ambito($u,$pid,true);
  $righe=[]; $tot=['alunni_confermati'=>0,'personale_confermato'=>0,'totale_confermato'=>0,'giorni_non_consolidati'=>0];
  foreach ($this->c->plessi()->elenco(false) as $p) {
   $id=(int)$p['id']; if ($pid && $pid!==$id) continue;
   $s=$this->r->impostazioni($id); if ($ordine!=='' && $s['ordine_scolastico']!==$ordine) continue;
   for($d=$dal;$d<=$al;$d=(new \DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d')) {
    $job=$this->c->jobRepo()->esecuzione($id,$d); $ok=$job && $job['esito']==='ok';
    $attivo=$this->c->calendario()->previsto($id,$d)['attivo'];
    $stato=$ok?'Confermato':(!$attivo?'Fuori servizio':($this->c->presenzaService()->oltreCutoff($p,$d)?'Non elaborato':'Previsto'));
    $tipi=[]; $alunni=null;
    if ($ok) { $tipi=$this->r->pastiConfermati($id,$d); $alunni=array_sum(array_column($tipi,'pasti')); }
    elseif ($stato==='Previsto') { $rp=$this->c->catering()->report($d,$id); $alunni=$rp['totale_pasti']; $tipi=$rp['plessi'][0]['tipologie']??[]; }
    elseif ($stato==='Fuori servizio') $alunni=0;
    $pg=$this->personale($u,$id,$d); $pc=$pg['disponibile'];
    $personale=$pc?array_sum(array_column($pg['righe'],'quantita')):null;
    $righe[]=['data'=>$d,'plesso_id'=>$id,'plesso_nome'=>$p['nome'],'ordine'=>$s['ordine_scolastico']??'Da configurare','stato'=>$stato,'alunni'=>$alunni,'personale'=>$personale,'totale'=>($alunni===null || $personale===null)?null:$alunni+$personale,'tipologie_alunni'=>$tipi,'tipologie_personale'=>$pg['righe'],'personale_consolidato'=>$pc,'fonte_personale'=>$pg['fonte'],'nota'=>$ok && in_array(null,array_column($tipi,'tipologia_id'),true)?'Tipologie pregresse non storicizzate: totale pasti disponibile, dettaglio dieta non ricostruibile con certezza.':''];
    if ($ok) { $tot['alunni_confermati']+=$alunni; if ($pc) { $tot['personale_confermato']+=$personale; $tot['totale_confermato']+=$alunni+$personale; } }
    if ($stato==='Previsto' || $stato==='Non elaborato' || ($ok && !$pc)) $tot['giorni_non_consolidati']++;
   }
  }
  return ['righe'=>$righe,'totali'=>$tot,'mensili'=>self::riepiloghiMensili($righe,$dal,$al)];
 }
 public static function riepiloghiMensili(array $righe,string $dal,string $al): array {
  $out=[];
  foreach($righe as $r) {
   $mese=substr($r['data'],0,7); $key=$mese.':'.$r['ordine'];
   if (!isset($out[$key])) $out[$key]=['mese'=>$mese,'ordine'=>$r['ordine'],'dal'=>max($dal,$mese.'-01'),'al'=>min($al,(new \DateTimeImmutable($mese.'-01'))->format('Y-m-t')),'alunni'=>0,'personale'=>0,'totale'=>0,'previsti'=>0,'giornate_complete'=>0,'giornate_incomplete'=>0,'giornate_previste'=>0];
   $m=&$out[$key];
   if ($r['stato']==='Confermato') {
    $m['alunni']+=$r['alunni'];
    if ($r['personale']!==null) {$m['personale']+=$r['personale']; $m['totale']+=$r['totale']; $m['giornate_complete']++;}
    else $m['giornate_incomplete']++;
   } elseif ($r['stato']==='Previsto') {
    $m['giornate_previste']++; if ($r['totale']!==null) $m['previsti']+=$r['totale']; else $m['giornate_incomplete']++;
   } elseif ($r['stato']==='Non elaborato') $m['giornate_incomplete']++;
   unset($m);
  }
  ksort($out); return array_values($out);
 }
 public function solleciti(array $u,array $v): array {
  $this->ufficio($u); $dal=self::data((string)($v['dal']??date('Y-m-01'))); $al=self::data((string)($v['al']??date('Y-m-d')));
  if ($al<$dal) throw HttpException::nonValido('Intervallo non valido.');
  $pid=!empty($v['plesso_id'])?(int)$v['plesso_id']:null; $q=trim((string)($v['q']??''));
  $logs=$this->r->solleciti($dal,$al,$pid,$q);
  $pos=array_values(array_filter($this->c->creditoService()->situazioneGenerale(),fn($r)=>(!$pid || (int)$r['plesso_id']===$pid) && (int)$r['saldo']<0 && (int)$r['saldo']<=-(int)$r['fido_buoni'] && ($q==='' || stripos($r['genitore_cognome'].' '.$r['genitore_nome'],$q)!==false)));
  return ['posizioni'=>$pos,'log'=>array_slice($logs,0,1000),'limite_raggiunto'=>count($logs)>1000,'invii'=>'Consultazione: nessun invio attivato da questa funzione.'];
 }
}
