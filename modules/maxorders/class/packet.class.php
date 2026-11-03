<?php
/** MAX-owned provenance only. GPL-3.0-or-later. No effective quantity or ERP writes. */
class MaxOrdersPacket {
 private $db;
 private $entity;
 public function __construct($db) { global $conf; $this->db=$db; $this->entity=(int)$conf->entity; }
 public static function can($actor,$right) {
  if(empty($actor->id) || !empty($actor->socid)) return false;
  // This first slice requires native all-customer read scope; assigned-customer scope is not certified.
  if(!$actor->hasRight('societe','lire') || !$actor->hasRight('societe','client','voir') || !$actor->hasRight('commande','lire')) return false;
  return $actor->hasRight('maxorders',$right) || ($right==='read' && ($actor->hasRight('maxorders','intake') || $actor->hasRight('maxorders','review')));
 }
 private function guard($actor,$right) {
  if(!isModEnabled('maxorders') || !self::can($actor,$right)) throw new RuntimeException('OrdersDenied',403);
 }
 private function q($sql) { $r=$this->db->query($sql); if(!$r) throw new RuntimeException('OrdersStorageError',500); return $r; }
 private function s($value) { return "'".$this->db->escape((string)$value)."'"; }
 private function table($table) { return MAIN_DB_PREFIX.'maxorders_'.$table; }
 private function rows($sql) { $r=$this->q($sql); $rows=[]; while($row=$this->db->fetch_object($r)) $rows[]=$row; return $rows; }
 private function where($id) { return 'entity='.$this->entity.' AND fk_packet='.(int)$id; }
 public static function text($value,$max=80) {
  if(!is_string($value) || trim($value)==='' || mb_strlen($value,'UTF-8')>$max || preg_match('/[\x00-\x1f\x7f<>]/u',$value)!==0) throw new InvalidArgumentException('OrdersInvalid',400);
  return trim($value);
 }
 public static function normalized($value) { return mb_strtoupper(preg_replace('/\s+/u',' ',self::text($value)),'UTF-8'); }
 public static function facts($json) {
  if(!is_string($json) || strlen($json)>4096) throw new InvalidArgumentException('OrdersInvalid',400);
  try { $data=json_decode($json,false,16,JSON_THROW_ON_ERROR); } catch(Throwable $e) { throw new InvalidArgumentException('OrdersInvalid',400); }
  if(!is_object($data)) throw new InvalidArgumentException('OrdersInvalid',400);
  $out=[];
  foreach(get_object_vars($data) as $key=>$value) {
   if(!in_array($key,['quantity','unit_price','sku','variant','packaging','currency'],true) || !is_string($value)) throw new InvalidArgumentException('OrdersInvalid',400);
   if(trim($value)==='') { $out[$key]=''; continue; }
   $value=self::text($value,80);
   if(in_array($key,['quantity','unit_price'],true)) {
    $places=$key==='quantity'?3:4;
    if(!preg_match('/^\d{1,9}(\.\d{1,'.$places.'})?$/D',$value)) throw new InvalidArgumentException('OrdersInvalid',400);
    $parts=explode('.',$value); $whole=ltrim($parts[0],'0'); $fraction=isset($parts[1])?rtrim($parts[1],'0'):'';
    $value=($whole===''?'0':$whole).($fraction!==''?'.'.$fraction:'');
   }
   if($key==='currency' && !preg_match('/^[A-Z]{3}$/D',$value)) throw new InvalidArgumentException('OrdersInvalid',400);
   $out[$key]=$value;
  }
  ksort($out); return $out;
 }
 private function customer($id) {
  require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php'; $c=new Societe($this->db);
  if($c->fetch((int)$id)<=0 || (int)$c->entity!==$this->entity || empty($c->client)) throw new InvalidArgumentException('OrdersCustomerMismatch',400);
 }
 public function packets($actor) { $this->guard($actor,'read'); return $this->rows('SELECT * FROM '.$this->table('packet').' WHERE entity='.$this->entity.' ORDER BY rowid DESC LIMIT 100'); }
 public function packet($actor,$id,$lock=false) {
  $this->guard($actor,'read'); $rows=$this->rows('SELECT * FROM '.$this->table('packet').' WHERE entity='.$this->entity.' AND rowid='.(int)$id.($lock?' FOR UPDATE':''));
  if(!$rows) throw new RuntimeException('OrdersNotFound',404); return $rows[0];
 }
 public function detail($actor,$id) {
  $p=$this->packet($actor,$id); $out=['packet'=>$p];
  foreach(['revision','current','lock','conflict','audit'] as $table) $out[$table]=$this->rows('SELECT * FROM '.$this->table($table).' WHERE '.$this->where($id).' ORDER BY rowid');
  $out['fact']=$this->rows('SELECT f.* FROM '.$this->table('fact').' f JOIN '.$this->table('revision').' r ON r.rowid=f.fk_revision AND r.entity=f.entity WHERE r.'.$this->where($id).' ORDER BY f.rowid');
  return $out;
 }
 private function audit($id,$actor,$event,$revision=0,$field='') {
  $this->q('INSERT INTO '.$this->table('audit').' (entity,fk_packet,fk_revision,event,field_name,fk_user_author,datec) VALUES ('.$this->entity.','.(int)$id.','.(int)$revision.','.$this->s($event).','.$this->s($field).','.(int)$actor->id.','.$this->s($this->db->idate(dol_now())).')');
 }
 private function conflict($id,$revision,$field,$old,$new,$reason,$actor) {
  $result=$this->q('INSERT INTO '.$this->table('conflict').' (entity,fk_packet,fk_revision,field_name,old_value,new_value,reason,fk_user_author,status) VALUES ('.$this->entity.','.(int)$id.','.(int)$revision.','.$this->s($field).','.$this->s($old).','.$this->s($new).','.$this->s($reason).','.(int)$actor->id.",'open') ON DUPLICATE KEY UPDATE rowid=rowid");
  if($this->db->affected_rows($result)>0) $this->audit($id,$actor,'conflict_open',$revision,$field);
 }
 public function ingest($actor,$customer,$reference,$kind,$document,$revision,$json) {
  $this->guard($actor,'intake'); $this->customer($customer);
  $reference=self::text($reference); $document=self::text($document); $facts=self::facts($json);
  if(!in_array($kind,['PO','LL','PC','UNKNOWN'],true) || !is_int($revision) || $revision<1 || $revision>1000000) throw new InvalidArgumentException('OrdersInvalid',400);
  $hash=hash('sha256',json_encode($facts,JSON_UNESCAPED_UNICODE)); $doc=hash('sha256',self::normalized($document));
  $this->db->begin();
  try {
   $this->q('INSERT INTO '.$this->table('packet').' (entity,fk_soc,match_ref,match_key,status,fk_user_author,datec) VALUES ('.$this->entity.','.(int)$customer.','.$this->s($reference).','.$this->s(hash('sha256',self::normalized($reference))).",'provisional',".(int)$actor->id.','.$this->s($this->db->idate(dol_now())).') ON DUPLICATE KEY UPDATE rowid=rowid');
   $p=$this->rows('SELECT * FROM '.$this->table('packet').' WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' AND match_key='.$this->s(hash('sha256',self::normalized($reference))).' FOR UPDATE')[0]; $id=(int)$p->rowid;
   $scope=$this->where($id).' AND kind='.$this->s($kind).' AND doc_key='.$this->s($doc);
   $existing=$this->rows('SELECT * FROM '.$this->table('revision').' WHERE '.$scope.' AND revision_no='.$revision);
   if($existing) {
    $r=$existing[0]; $collision=$r->content_hash!==$hash;
    if($collision) {
     $previous=[];
     foreach($this->rows('SELECT field_name,field_value FROM '.$this->table('fact').' WHERE entity='.$this->entity.' AND fk_revision='.(int)$r->rowid) as $fact) $previous[$fact->field_name]=$fact->field_value;
     foreach(array_unique(array_merge(array_keys($previous),array_keys($facts))) as $field) {
      $old=array_key_exists($field,$previous)?$previous[$field]:'[missing]';
      $new=array_key_exists($field,$facts)?$facts[$field]:'[missing]';
      if($old!==$new) $this->conflict($id,$r->rowid,$field,$old,$new,'revision_collision',$actor);
     }
    }
    $this->db->commit(); return ['packet'=>$id,'revision'=>(int)$r->rowid,'status'=>$collision?'collision':'duplicate'];
   }
   $latest=$this->rows('SELECT MAX(revision_no) AS n FROM '.$this->table('revision').' WHERE '.$scope)[0]->n;
   $status=($kind==='UNKNOWN' || !array_filter($facts,fn($v)=>$v!==''))?'needs_review':($revision<(int)$latest?'archived':'received');
   $this->q('INSERT INTO '.$this->table('revision').' (entity,fk_packet,kind,document_ref,doc_key,revision_no,content_hash,status,fk_user_author,datec) VALUES ('.$this->entity.','.$id.','.$this->s($kind).','.$this->s($document).','.$this->s($doc).','.$revision.','.$this->s($hash).','.$this->s($status).','.(int)$actor->id.','.$this->s($this->db->idate(dol_now())).')');
   $rid=(int)$this->db->last_insert_id($this->table('revision'));
   foreach($facts as $field=>$value) {
    $this->q('INSERT INTO '.$this->table('fact').' (entity,fk_revision,field_name,field_value) VALUES ('.$this->entity.','.$rid.','.$this->s($field).','.$this->s($value).')');
    if($status!=='received' || $value==='') continue;
    $this->q('INSERT INTO '.$this->table('current').' (entity,fk_packet,kind,doc_key,field_name,field_value,revision_no,fk_revision) VALUES ('.$this->entity.','.$id.','.$this->s($kind).','.$this->s($doc).','.$this->s($field).','.$this->s($value).','.$revision.','.$rid.') ON DUPLICATE KEY UPDATE field_value=VALUES(field_value),revision_no=VALUES(revision_no),fk_revision=VALUES(fk_revision)');
    $locks=$this->rows('SELECT * FROM '.$this->table('lock').' WHERE '.$this->where($id).' AND field_name='.$this->s($field).' AND is_locked=1');
    if($locks && $locks[0]->field_value!==$value) $this->conflict($id,$rid,$field,$locks[0]->field_value,$value,'manual_lock',$actor);
   }
   $this->audit($id,$actor,'intake',$rid); $this->db->commit(); return ['packet'=>$id,'revision'=>$rid,'status'=>$status];
  } catch(Throwable $e) { $this->db->rollback(); throw $e; }
 }
 private function setLock($actor,$id,$field,$value,$locked) {
  $this->q('INSERT INTO '.$this->table('lock').' (entity,fk_packet,field_name,field_value,is_locked,fk_user_author,datec) VALUES ('.$this->entity.','.(int)$id.','.$this->s($field).','.$this->s($value).','.(int)$locked.','.(int)$actor->id.','.$this->s($this->db->idate(dol_now())).') ON DUPLICATE KEY UPDATE field_value=VALUES(field_value),is_locked=VALUES(is_locked),fk_user_author=VALUES(fk_user_author),datec=VALUES(datec)');
 }
 public function lockField($actor,$id,$field,$value,$locked=true) {
  $this->guard($actor,'review'); $values=self::facts(json_encode([$field=>$value]));
  if($values[$field]==='') throw new InvalidArgumentException('OrdersInvalid',400);
  $this->db->begin(); try { $this->packet($actor,$id,true); $this->setLock($actor,$id,$field,$values[$field],$locked); $this->audit($id,$actor,$locked?'manual_lock':'manual_unlock',0,$field); $this->db->commit(); }
  catch(Throwable $e) { $this->db->rollback(); throw $e; }
 }
 public function resolve($actor,$id,$conflict,$decision) {
  $this->guard($actor,'review');
  if(!in_array($decision,['keep_manual','accept_source','acknowledge'],true)) throw new InvalidArgumentException('OrdersInvalid',400);
  $this->db->begin(); try {
   $this->packet($actor,$id,true); $rows=$this->rows('SELECT * FROM '.$this->table('conflict').' WHERE '.$this->where($id).' AND rowid='.(int)$conflict.' FOR UPDATE');
   if(!$rows) throw new RuntimeException('OrdersNotFound',404); $c=$rows[0];
   if($c->status==='resolved') { $this->db->commit(); return; }
   if(($c->reason==='revision_collision')!==($decision==='acknowledge')) throw new InvalidArgumentException('OrdersInvalid',400);
   if($c->reason==='manual_lock') {
    $locks=$this->rows('SELECT * FROM '.$this->table('lock').' WHERE '.$this->where($id).' AND field_name='.$this->s($c->field_name));
    // Stale human review must not silently replace a more recent manual decision.
    if(!$locks || !$locks[0]->is_locked || $locks[0]->field_value!==$c->old_value) throw new RuntimeException('OrdersStaleReview',409);
    if($decision==='accept_source') $this->setLock($actor,$id,$c->field_name,$c->new_value,true);
   }
   $this->q('UPDATE '.$this->table('conflict')." SET status='resolved',decision=".$this->s($decision).',fk_user_resolver='.(int)$actor->id.',date_resolved='.$this->s($this->db->idate(dol_now())).' WHERE entity='.$this->entity.' AND rowid='.(int)$conflict);
   $this->audit($id,$actor,'resolve_'.$decision,$c->fk_revision,$c->field_name); $this->db->commit();
  } catch(Throwable $e) { $this->db->rollback(); throw $e; }
 }
 public function link($actor,$id,$orderId,$confirmed) {
  $this->guard($actor,'review'); if($confirmed!==true) throw new InvalidArgumentException('OrdersConfirmRequired',400);
  require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
  $this->db->begin(); try {
   $p=$this->packet($actor,$id,true); $o=new Commande($this->db);
   if($o->fetch((int)$orderId)<=0 || (int)$o->entity!==$this->entity || (int)$o->socid!==(int)$p->fk_soc || self::normalized($o->ref_customer?:$o->ref)!==self::normalized($p->match_ref)) throw new InvalidArgumentException('OrdersOrderMismatch',400);
   if($p->fk_order && (int)$p->fk_order!==(int)$orderId) throw new RuntimeException('OrdersAlreadyLinked',409);
   if((int)$p->fk_order!==(int)$orderId) {
    $this->q('UPDATE '.$this->table('packet')." SET status='linked',fk_order=".(int)$orderId.' WHERE entity='.$this->entity.' AND rowid='.(int)$id);
    $this->audit($id,$actor,'native_link');
   }
   $this->db->commit();
  } catch(Throwable $e) { $this->db->rollback(); throw $e; }
 }
}
