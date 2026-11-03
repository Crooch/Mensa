<?php
declare(strict_types=1);
namespace App\Repository;
use App\Core\Repository;
final class AutonomiaRepository extends Repository
{
 public function audit(int $utente, string $op, string $ref, $prima, $dopo, string $motivo): void {
  if ($prima === $dopo) return;
  $this->inserisci('audit_amministrativo',['utente_id'=>$utente,'operazione'=>$op,'riferimento'=>$ref,'precedente'=>json_encode($prima,JSON_UNESCAPED_UNICODE),'successivo'=>json_encode($dopo,JSON_UNESCAPED_UNICODE),'motivazione'=>$motivo]);
 }
 public function lockPlesso(int $id): ?array { return $this->uno('SELECT * FROM plessi WHERE id=:id FOR UPDATE',['id'=>$id]); }
 public function impostazioni(int $id): array { return $this->uno('SELECT * FROM impostazioni_servizio_plesso WHERE plesso_id=:id',['id'=>$id]) ?? ['plesso_id'=>$id,'ordine_scolastico'=>null,'primo_giorno_sospensione'=>null]; }
 public function salvaImpostazioni(int $id,array $v): void {
  $this->esegui('INSERT INTO impostazioni_servizio_plesso (plesso_id,ordine_scolastico,primo_giorno_sospensione) VALUES (:id,:ordine,:fine) ON DUPLICATE KEY UPDATE ordine_scolastico=VALUES(ordine_scolastico), primo_giorno_sospensione=VALUES(primo_giorno_sospensione)',['id'=>$id,'ordine'=>$v['ordine_scolastico'],'fine'=>$v['primo_giorno_sospensione']]);
 }
 public function fido(int $id,int $v): void { $this->aggiorna('plessi',$id,['fido_buoni'=>$v]); }
 public function famiglia(int $id,bool $lock=false): ?array { return $this->uno('SELECT * FROM genitori WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>$id]); }
 public function bambino(int $id,bool $lock=false): ?array { return $this->uno('SELECT * FROM bambini WHERE id=:id'.($lock?' FOR UPDATE':''),['id'=>$id]); }
 public function verificaFamiglia(int $id): array {
  return ['figli'=>$this->tutti('SELECT b.id,b.nome,b.cognome,b.cancellato_logicamente,ac.classe_sezione_id,ac.data_inizio,ac.data_fine,cs.nome AS classe_nome,p.nome AS plesso_nome FROM bambini b LEFT JOIN assegnazioni_classe ac ON ac.bambino_id=b.id LEFT JOIN classi_sezioni cs ON cs.id=ac.classe_sezione_id LEFT JOIN plessi p ON p.id=cs.plesso_id WHERE b.genitore_id=:id ORDER BY b.id,ac.data_inizio',['id'=>$id]),
  'possibili_duplicati'=>$this->tutti('SELECT DISTINCT x.id,x.nome,x.cognome,x.genitore_id,g.nome AS genitore_nome,g.cognome AS genitore_cognome,x.cancellato_logicamente FROM bambini b JOIN bambini x ON LOWER(TRIM(b.nome))=LOWER(TRIM(x.nome)) AND LOWER(TRIM(b.cognome))=LOWER(TRIM(x.cognome)) AND b.id<>x.id JOIN genitori g ON g.id=x.genitore_id WHERE b.genitore_id=:id',['id'=>$id]),
  'figli_attivi'=>(int)$this->valore('SELECT COUNT(*) FROM bambini WHERE genitore_id=:id AND cancellato_logicamente=0',['id'=>$id]),
  'saldi_non_zero'=>(int)$this->valore('SELECT COUNT(*) FROM pool_crediti WHERE genitore_id=:id AND saldo<>0',['id'=>$id]),
  'movimenti'=>(int)$this->valore('SELECT COUNT(*) FROM movimenti_crediti m JOIN pool_crediti p ON p.id=m.pool_id WHERE p.genitore_id=:id',['id'=>$id])];
 }
 public function utenzaDisattivabile(array $g): bool {
  if (!$g['utente_id']) return true;
  return $this->valore('SELECT ruolo FROM utenti WHERE id=:id',['id'=>$g['utente_id']])==='genitore' && (int)$this->valore('SELECT COUNT(*) FROM genitori WHERE utente_id=:id',['id'=>$g['utente_id']])===1;
 }
 public function disattivaFamiglia(array $g): void {
  $this->aggiorna('genitori',(int)$g['id'],['cancellato_logicamente'=>1,'data_cancellazione'=>date('Y-m-d H:i:s')]);
  if ($g['utente_id']) {
   $this->aggiorna('utenti',(int)$g['utente_id'],['attivo'=>0]);
   $this->esegui('DELETE FROM api_token WHERE utente_id=:id',['id'=>$g['utente_id']]);
  }
 }
 public function nome(int $id,array $v): void { $this->aggiorna('bambini',$id,$v); }
 public function regole(int $id): array { return $this->tutti('SELECT * FROM pasti_personale_regole WHERE plesso_id=:id ORDER BY dal,giorno,categoria,tipologia_menu_id',['id'=>$id]); }
 public function salvaRegola(int $id,array $v): void {
  $this->esegui('INSERT INTO pasti_personale_regole (plesso_id,dal,giorno,categoria,tipologia_menu_id,quantita) VALUES (:id,:dal,:giorno,:categoria,:tipologia_menu_id,:quantita) ON DUPLICATE KEY UPDATE quantita=VALUES(quantita)',['id'=>$id]+$v);
 }
 public function storicoPersonale(int $id,string $data,bool $programmazione): array {
  $operazioni=$programmazione?"a.operazione='ricorrenza_personale'":"a.operazione IN ('personale_giorno','ripristino_personale')";
  return $this->tutti("SELECT a.id,a.operazione,a.precedente,a.successivo,a.motivazione,UNIX_TIMESTAMP(a.registrato_il) AS registrato_timestamp,u.ruolo FROM audit_amministrativo a JOIN utenti u ON u.id=a.utente_id WHERE {$operazioni} AND a.riferimento=:ref ORDER BY a.id DESC LIMIT 21",['ref'=>$programmazione?(string)$id:$id.':'.$data]);
 }
 public function giorno(int $id,string $data): ?array { return $this->uno('SELECT * FROM pasti_personale_giorni WHERE plesso_id=:id AND data=:d',['id'=>$id,'d'=>$data]); }
 public function salvaGiorno(int $id,string $data,array $righe,bool $consolidato,string $origine='variazione'): void {
  $this->esegui('INSERT INTO pasti_personale_giorni (plesso_id,data,righe,consolidato,origine) VALUES (:id,:d,:r,:c,:o) ON DUPLICATE KEY UPDATE righe=VALUES(righe),consolidato=VALUES(consolidato),origine=VALUES(origine)',['id'=>$id,'d'=>$data,'r'=>json_encode($righe),'c'=>(int)$consolidato,'o'=>$origine]);
 }
 public function eliminaGiorno(int $id,string $data): void { $this->esegui('DELETE FROM pasti_personale_giorni WHERE plesso_id=:id AND data=:d',['id'=>$id,'d'=>$data]); }
 public function pastiConfermati(int $id,string $data): array {
  return $this->tutti("SELECT st.tipologia_menu_id AS tipologia_id,COALESCE(st.tipologia_nome,'Non storicizzata') AS tipologia_nome,COUNT(DISTINCT pg.id) AS pasti FROM presenze_giornaliere pg JOIN bambini b ON b.id=pg.bambino_id LEFT JOIN presenze_tipologie_storiche st ON st.presenza_id=pg.id JOIN assegnazioni_classe ac ON ac.bambino_id=b.id AND ac.data_inizio<=pg.data AND (ac.data_fine IS NULL OR ac.data_fine>=pg.data) JOIN classi_sezioni cs ON cs.id=ac.classe_sezione_id WHERE cs.plesso_id=:id AND pg.data=:d AND pg.congelato=1 AND pg.stato_effettivo='presente' GROUP BY st.tipologia_menu_id,st.tipologia_nome",['id'=>$id,'d'=>$data]);
 }
 public function storicizzaTipologie(int $id,string $data): void {
  $this->esegui('INSERT IGNORE INTO presenze_tipologie_storiche (presenza_id,tipologia_menu_id,tipologia_nome) SELECT pg.id,b.tipologia_menu_id,t.nome FROM presenze_giornaliere pg JOIN bambini b ON b.id=pg.bambino_id JOIN tipologie_menu t ON t.id=b.tipologia_menu_id JOIN assegnazioni_classe ac ON ac.bambino_id=b.id AND ac.data_inizio<=pg.data AND (ac.data_fine IS NULL OR ac.data_fine>=pg.data) JOIN classi_sezioni cs ON cs.id=ac.classe_sezione_id WHERE cs.plesso_id=:id AND pg.data=:d AND pg.congelato=1',['id'=>$id,'d'=>$data]);
 }
 public function blocchiBambino(int $id): bool {
  return (int)$this->valore('SELECT COUNT(*) FROM presenze_giornaliere WHERE bambino_id=:id',['id'=>$id])>0 || (int)$this->valore('SELECT COUNT(*) FROM assegnazioni_classe WHERE bambino_id=:id AND (data_fine IS NULL OR data_fine>=CURRENT_DATE)',['id'=>$id])>0;
 }
 public function disattivaBambino(int $id): void { $this->aggiorna('bambini',$id,['cancellato_logicamente'=>1,'data_cancellazione'=>date('Y-m-d H:i:s')]); }
 public function solleciti(string $dal,string $al,?int $pid,string $q): array {
  return $this->tutti('SELECT l.*,g.nome AS genitore_nome,g.cognome AS genitore_cognome,p.nome AS plesso_nome FROM log_solleciti_inviati l JOIN genitori g ON g.id=l.genitore_id JOIN plessi p ON p.id=l.plesso_id WHERE l.data_invio BETWEEN :dal AND :al AND (:pid IS NULL OR l.plesso_id=:pid) AND CONCAT(g.cognome," ",g.nome) LIKE :q ORDER BY l.data_invio DESC,l.id DESC LIMIT 1001',['dal'=>$dal,'al'=>$al,'pid'=>$pid,'q'=>'%'.$q.'%']);
 }
}
