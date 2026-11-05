<?php
/** Private content ledger and explicit human association. GPL-3.0-or-later. */
require_once __DIR__.'/packet.class.php';
require_once __DIR__.'/runner.class.php';
require_once __DIR__.'/lines.class.php';
class MaxOrdersDocument {
 private $db;
 private $entity;
 public function __construct($db) { global $conf; $this->db=$db; $this->entity=(int)$conf->entity; }
 private function guard($actor,$right) { global $conf; if($this->entity<1 || $this->entity!==(int)$conf->entity || !isModEnabled('maxorders') || !MaxOrdersPacket::can($actor,$right)) throw new RuntimeException('OrdersDenied',403); }
 private function table() { return MAIN_DB_PREFIX.'maxorders_upload'; }
 private function q($sql) { $r=$this->db->query($sql); if(!$r) throw new RuntimeException('OrdersStorageError',500); return $r; }
 private function s($value) { return "'".$this->db->escape((string)$value)."'"; }
 private function rows($sql) { $r=$this->q($sql); $out=[]; while($v=$this->db->fetch_object($r)) $out[]=$v; return $out; }
 private function scope() { return 'entity='.$this->entity; }
 public function listing($actor) { $this->guard($actor,'read'); return $this->rows('SELECT rowid,original_name,format,analysis_status,fk_packet,link_status,datec FROM '.$this->table().' WHERE '.$this->scope().' ORDER BY rowid DESC LIMIT 100'); }
 public function get($actor,$id,$lock=false) {
  $this->guard($actor,'read');
  $rows=$this->rows('SELECT * FROM '.$this->table().' WHERE '.$this->scope().' AND rowid='.(int)$id.($lock?' FOR UPDATE':''));
  if(!$rows) throw new RuntimeException('OrdersNotFound',404); return $rows[0];
 }
 private function root() {
  $data=realpath(DOL_DATA_ROOT); $web=realpath(DOL_DOCUMENT_ROOT);
  if(!$data || !$web || $data===$web || str_starts_with($data,$web.'/')) throw new RuntimeException('OrdersStorageError',500);
  foreach(['/maxorders-private','/maxorders-private/entity-'.$this->entity] as $suffix) {
   $dir=$data.$suffix;
   if(!is_dir($dir) && !@mkdir($dir,0700) && !is_dir($dir)) throw new RuntimeException('OrdersStorageError',500);
   if(is_link($dir) || realpath($dir)!==$dir || !chmod($dir,0700)) throw new RuntimeException('OrdersStorageError',500);
  }
  return $data.'/maxorders-private/entity-'.$this->entity;
 }
 private function path($record) {
  if(!preg_match('/^[a-f0-9]{64}$/D',$record->content_hash)) throw new RuntimeException('OrdersStorageError',500);
  return $this->root().'/'.$record->content_hash.'.blob';
 }
 public function receive($actor,$name,$bytes) {
  $this->guard($actor,'intake');
  $analysis=MaxOrdersRunner::inspect($name,$bytes);
  $json=json_encode($analysis,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  if(strlen($json)>262144) throw new InvalidArgumentException('OrdersFileInvalid',400);
  $hash=hash('sha256',$bytes); $created=false; $path='';
  if(!$this->db->begin()) throw new RuntimeException('OrdersStorageError',500);
  try {
   $this->q('INSERT INTO '.$this->table().' (entity,content_hash,original_name,format,byte_size,analysis_status,analysis_json,link_status,fk_user_author,datec) VALUES ('.$this->entity.','.$this->s($hash).','.$this->s($name).','.$this->s($analysis['format']).','.strlen($bytes).','.$this->s($analysis['status']).','.$this->s($json).",'unlinked',".(int)$actor->id.','.$this->s($this->db->idate(dol_now())).') ON DUPLICATE KEY UPDATE rowid=rowid');
   $record=$this->rows('SELECT * FROM '.$this->table().' WHERE '.$this->scope().' AND content_hash='.$this->s($hash).' FOR UPDATE')[0];
   $path=$this->path($record);
   if(is_link($path)) throw new RuntimeException('OrdersStorageError',500);
   if(is_file($path)) {
    if(filesize($path)!==strlen($bytes) || hash_file('sha256',$path)!==$hash) throw new RuntimeException('OrdersStorageError',500);
   } else {
    $handle=fopen($path,'x+b'); if(!$handle) throw new RuntimeException('OrdersStorageError',500);
    $created=true;
    try { if(!chmod($path,0600) || fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle)) throw new RuntimeException('OrdersStorageError',500); }
    finally { fclose($handle); }
   }
   (new MaxOrdersLines($this->db))->capture($actor,(int)$record->rowid);
   if(!$this->db->commit()) throw new RuntimeException('OrdersStorageError',500);
   return ['id'=>(int)$record->rowid,'duplicate'=>!$created];
  } catch(Throwable $e) { $this->db->rollback(); if($created && is_file($path) && !unlink($path)) throw new RuntimeException('OrdersStorageError',500); throw $e; }
 }
 public function download($actor,$id) {
  $record=$this->get($actor,$id); $path=$this->path($record);
  if(is_link($path) || !is_file($path) || realpath(dirname($path))!==$this->root() || filesize($path)!==(int)$record->byte_size || hash_file('sha256',$path)!==$record->content_hash) throw new RuntimeException('OrdersNotFound',404);
  return ['path'=>$path,'size'=>(int)$record->byte_size,'format'=>$record->format];
 }
 public function suggestions($actor,$id) {
  $record=$this->get($actor,$id); $analysis=json_decode($record->analysis_json,true,32,JSON_THROW_ON_ERROR);
  $v=MaxOrdersExtraction::unique($analysis);
  if(empty($v['order_reference']) || (empty($v['customer_reference']) && empty($v['customer_name']))) return [];
  $packets=new MaxOrdersPacket($this->db); $out=[];
  // Same native all-customer read scope as Orders 0.1; never broaden it.
  $customers=$this->rows('SELECT rowid,code_client,nom FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND client>0 ORDER BY rowid LIMIT 501');
  if(count($customers)>500) return []; // Never hide possible ambiguity at a cap.
  foreach($customers as $customer) {
   try {
    if(!empty($v['customer_reference']) && MaxOrdersPacket::normalized($customer->code_client?:'∅')!==MaxOrdersPacket::normalized($v['customer_reference'])) continue;
    if(!empty($v['customer_name']) && MaxOrdersPacket::normalized($customer->nom)!==MaxOrdersPacket::normalized($v['customer_name'])) continue;
   } catch(InvalidArgumentException $e) { continue; }
   foreach($this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'maxorders_packet WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer->rowid.' AND match_key='.$this->s(hash('sha256',MaxOrdersPacket::normalized($v['order_reference']))).' ORDER BY rowid LIMIT 2') as $packet) $out[]=$packet;
  }
  return $out;
 }
 public function associate($actor,$id,$packetId,$customer,$reference,$kind,$document,$revision,$facts,$confirmed) {
  $this->guard($actor,'review'); $this->guard($actor,'intake');
  if($confirmed!==true) throw new InvalidArgumentException('OrdersConfirmRequired',400);
  $reference=MaxOrdersPacket::text($reference); $document=MaxOrdersPacket::text($document);
  $values=MaxOrdersPacket::facts($facts);
  $decision=json_encode(['packet'=>(int)$packetId,'customer'=>(int)$customer,'reference'=>MaxOrdersPacket::normalized($reference),'kind'=>$kind,'document'=>$document,'revision'=>$revision,'facts'=>$values],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
  $hash=hash('sha256',$decision);
  if(!$this->db->begin()) throw new RuntimeException('OrdersStorageError',500);
  try {
   $record=$this->get($actor,$id,true);
   if($record->fk_packet) {
    if(!hash_equals((string)$record->link_hash,$hash)) throw new RuntimeException('OrdersDocumentLinked',409);
    $this->db->commit(); return ['packet'=>(int)$record->fk_packet,'revision'=>$record->fk_revision===null?null:(int)$record->fk_revision,'status'=>$record->link_status];
   }
   $service=new MaxOrdersPacket($this->db); $packet=$service->packet($actor,$packetId,true);
   if((int)$packet->fk_soc!==(int)$customer || MaxOrdersPacket::normalized($packet->match_ref)!==MaxOrdersPacket::normalized($reference)) throw new InvalidArgumentException('OrdersOrderMismatch',400);
   // Supported native transaction nesting keeps packet+file association atomic.
   // The existing service preserves revision collisions, source quantities/locks.
   $analysis=json_decode($record->analysis_json,true,40,JSON_THROW_ON_ERROR);
   if(($analysis['template']??'')==='table-v03') {
    if($values) throw new InvalidArgumentException('OrdersNoLineAggregation',400);
    $result=(new MaxOrdersLines($this->db))->associate($actor,$record,(int)$packetId,$kind,$document,$revision);
   } else {
    $result=$service->ingest($actor,(int)$customer,$packet->match_ref,$kind,$document,$revision,json_encode((object)$values));
   }
   if($result['packet']!==(int)$packetId) throw new RuntimeException('OrdersStorageError',500);
   $this->q('UPDATE '.$this->table().' SET fk_packet='.(int)$packetId.',fk_revision='.($result['revision']===null?'NULL':(int)$result['revision']).',link_status='.$this->s($result['status']).',link_hash='.$this->s($hash).',link_json='.$this->s($decision).',fk_user_reviewer='.(int)$actor->id.',date_linked='.$this->s($this->db->idate(dol_now())).' WHERE '.$this->scope().' AND rowid='.(int)$id);
   if(!$this->db->commit()) throw new RuntimeException('OrdersStorageError',500);
   return $result;
  } catch(Throwable $e) { $this->db->rollback(); throw $e; }
 }
}
