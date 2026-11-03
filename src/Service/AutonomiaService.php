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
 public function personale(array $u,int $id,string $data): array {
  $p=$this->ambito($u,$id,true); self::data($data);
  $g=$this->r->giorno($id,$data); $attivo=$this->c->calendario()->previsto($id,$data)['attivo'];
  $righe=(!$attivo && (!$g || !(int)$g['consolidato'])) ? [] : ($g ? json_decode($g['righe'],true,512,JSON_THROW_ON_ERROR) : $this->ricorrenti($id,$data));
  return ['plesso_id'=>$id,'data'=>$data,'attivo'=>$attivo,'righe'=>$righe,'consolidato'=>$g?(bool)$g['consolidato']:false,'modificabile'=>in_array($u['ruolo'],['admin','operatore'],true) || ($u['ruolo']==='scuola' && $data===date('Y-m-d') && $attivo && !$this->c->presenzaService()->oltreCutoff($p,$data)), 'cutoff'=>substr($p['cutoff_orario'],0,5),'regole'=>in_array($u['ruolo'],['admin','operatore'],true)?$this->r->regole($id):[]];
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
  $dal=self::data((string)($v['dal']??'')); $g=filter_var($v['giorno']??null,FILTER_VALIDATE_INT);
  if ($dal<=date('Y-m-d') || $g===false || $g<1 || $g>7) throw HttpException::nonValido('Le nuove ricorrenze devono iniziare da domani; seleziona il giorno della settimana.');
  $riga=$this->riga($v)+['dal'=>$dal,'giorno'=>$g];
  return Database::inTransazione(function() use($u,$id,$riga,$motivo) {
   $this->r->lockPlesso($id); $prima=$this->r->regole($id);
   $this->r->salvaRegola($id,$riga); $dopo=$this->r->regole($id);
   $this->r->audit((int)$u['id'],'ricorrenza_personale',(string)$id,$prima,$dopo,$motivo);
   return ['salvato'=>true];
  });
 }
 public function correggiGiorno(array $u,int $id,array $v): array {
  $p=$this->ambito($u,$id);
  if ($u['ruolo']==='catering') throw HttpException::nonAutorizzato();
  $motivo=$this->conferma($v); $data=self::data((string)($v['data']??''));
  if ($u['ruolo']==='scuola' && ($data!==date('Y-m-d') || $this->c->presenzaService()->oltreCutoff($p,$data))) throw HttpException::nonAutorizzato('La Scuola può correggere solo oggi prima del cutoff. Richiedere una rettifica motivata al Comune.');
  if (!$this->c->calendario()->previsto($id,$data)['attivo'] && !$this->r->giorno($id,$data)) throw HttpException::nonValido('Nessun servizio previsto per questa data.');
  $righe=[]; $chiavi=[];
  if (!is_array($v['righe']??null) || count($v['righe'])>100) throw HttpException::nonValido('Elenco pasti non valido.');
  foreach ($v['righe'] as $r) {
   if (!is_array($r)) throw HttpException::nonValido('Riga non valida.');
   $r=$this->riga($r); $k=$r['categoria'].':'.$r['tipologia_menu_id'];
   if (isset($chiavi[$k])) throw HttpException::nonValido('Categoria e tipologia duplicate: indica un unico totale.');
   $chiavi[$k]=true; $righe[]=$r;
  }
  return Database::inTransazione(function() use($u,$id,$data,$righe,$v,$motivo,$p) {
   $this->r->lockPlesso($id);
   if ($u['ruolo']==='scuola' && ($data!==date('Y-m-d') || $this->c->presenzaService()->oltreCutoff($p,$data))) throw HttpException::nonAutorizzato('Cutoff raggiunto durante il salvataggio. Richiedere una rettifica al Comune.');
   $prima=$this->personale($u,$id,$data)['righe'];
   if (!array_key_exists('precedente',$v) || $v['precedente']!=$prima) throw HttpException::nonValido('I conteggi sono cambiati. Ricarica prima di salvare.');
   if ($prima!=$righe) {
    $this->r->salvaGiorno($id,$data,$righe,$this->c->presenzaService()->oltreCutoff($p,$data));
    $this->r->audit((int)$u['id'],'personale_giorno',$id.':'.$data,$prima,$righe,$motivo);
   }
   return ['salvato'=>true];
  });
 }
 public function consolidaPersonale(int $id,string $data): void {
  Database::inTransazione(function() use($id,$data) {
   $this->r->lockPlesso($id); $this->r->storicizzaTipologie($id,$data); $g=$this->r->giorno($id,$data);
   if (!$g || !(int)$g['consolidato']) $this->r->salvaGiorno($id,$data,$g?json_decode($g['righe'],true):$this->ricorrenti($id,$data),true);
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
    $pg=$this->personale($u,$id,$d); $pc=$pg['consolidato'];
    $personale=($ok && !$pc)?null:array_sum(array_column($pg['righe'],'quantita'));
    $righe[]=['data'=>$d,'plesso_id'=>$id,'plesso_nome'=>$p['nome'],'ordine'=>$s['ordine_scolastico']??'Da configurare','stato'=>$stato,'alunni'=>$alunni,'personale'=>$personale,'totale'=>($alunni===null || $personale===null)?null:$alunni+$personale,'tipologie_alunni'=>$tipi,'tipologie_personale'=>$pg['righe'],'personale_consolidato'=>$pc,'nota'=>$ok && in_array(null,array_column($tipi,'tipologia_id'),true)?'Tipologie pregresse non storicizzate: totale pasti disponibile, dettaglio dieta non ricostruibile con certezza.':''];
    if ($ok) { $tot['alunni_confermati']+=$alunni; if ($pc) { $tot['personale_confermato']+=$personale; $tot['totale_confermato']+=$alunni+$personale; } }
    if ($stato==='Previsto' || $stato==='Non elaborato' || ($ok && !$pc)) $tot['giorni_non_consolidati']++;
   }
  }
  return ['righe'=>$righe,'totali'=>$tot];
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
