<?php
/** Immutable source proposals; never native order lines. GPL-3.0-or-later. */
require_once __DIR__.'/packet.class.php';
require_once __DIR__.'/extraction.class.php';
class MaxOrdersLines {
 private $db;
 private $entity;
 public function __construct($db) { global $conf; $this->db=$db; $this->entity=(int)$conf->entity; }
 private function guard($actor,$right='read') {
  global $conf;
  if($this->entity<1 || $this->entity!==(int)$conf->entity || !isModEnabled('maxorders') || !MaxOrdersPacket::can($actor,$right)) throw new RuntimeException('OrdersDenied',403);
 }
 private function s($value) { return "'".$this->db->escape((string)$value)."'"; }
 private function rows($sql) {
  $result=$this->db->query($sql); if(!$result) throw new RuntimeException('OrdersStorageError',500);
  if(!str_starts_with($sql,'SELECT ')) return [];
  $rows=[]; while($row=$this->db->fetch_object($result)) $rows[]=$row; return $rows;
 }
 private function table($name) { return MAIN_DB_PREFIX.'maxorders_'.$name; }
 private function scope() { return 'entity='.$this->entity; }
 /** Called only while the upload row is locked inside receive's transaction. */
 public function capture($actor,$upload) {
  $this->guard($actor,'intake');
  $records=$this->rows('SELECT * FROM '.$this->table('upload').' WHERE '.$this->scope().' AND rowid='.(int)$upload.' FOR UPDATE');
  if(!$records) throw new RuntimeException('OrdersNotFound',404);
  $record=$records[0]; $analysis=json_decode($record->analysis_json,true,40,JSON_THROW_ON_ERROR);
  if(($analysis['template']??'')!=='table-v03') return;
  $values=MaxOrdersExtraction::unique($analysis);
  foreach($analysis['lines'] as $line) {
   // Use the first stored analysis, even if template configuration later changes.
   $this->rows('INSERT INTO '.$this->table('source_line').' (entity,fk_upload,content_hash,source_row,source_sheet,line_key,declared_kind,declared_revision,proposal_state,review_status,source_json) VALUES ('.$this->entity.','.(int)$upload.','.$this->s($record->content_hash).','.(int)$line['row'].','.$this->s($line['sheet']).','.$this->s($line['key']).','.$this->s($values['document_kind']??'').','.(isset($values['document_revision'])?(int)$values['document_revision']:'NULL').','.$this->s($line['state']).",'pending',".$this->s(json_encode($line,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).') ON DUPLICATE KEY UPDATE rowid=rowid');
  }
 }
 public function forUpload($actor,$id) {
  $this->guard($actor);
  return $this->rows('SELECT * FROM '.$this->table('source_line').' WHERE '.$this->scope().' AND fk_upload='.(int)$id.' ORDER BY source_row LIMIT 50');
 }
 /** Upload + packet locks are held by Document::associate. No active scalar facts. */
 public function associate($actor,$record,$packet,$kind,$document,$revision) {
  $this->guard($actor,'intake'); $this->guard($actor,'review');
  if((int)$record->entity!==$this->entity || !in_array($kind,['PO','LL','PC'],true) || !is_int($revision) || $revision<1 || $revision>999999) throw new InvalidArgumentException('OrdersInvalid',400);
  $key=hash('sha256',MaxOrdersPacket::normalized($document));
  $where=$this->scope().' AND fk_packet='.(int)$packet.' AND kind='.$this->s($kind).' AND doc_key='.$this->s($key);
  $versions=$this->rows('SELECT revision_no FROM '.$this->table('document_source').' WHERE '.$where.' UNION ALL SELECT revision_no FROM '.$this->table('revision').' WHERE '.$where);
  $max=0; $collision=false;
  foreach($versions as $version) { $max=max($max,(int)$version->revision_no); if((int)$version->revision_no===$revision) $collision=true; }
  $status=$collision?'collision':($revision<$max?'archived':'received');
  $this->rows('INSERT INTO '.$this->table('document_source').' (entity,fk_upload,fk_packet,kind,document_ref,doc_key,revision_no,content_hash,status,fk_user_reviewer,date_reviewed) VALUES ('.$this->entity.','.(int)$record->rowid.','.(int)$packet.','.$this->s($kind).','.$this->s($document).','.$this->s($key).','.$revision.','.$this->s($record->content_hash).','.$this->s($status).','.(int)$actor->id.','.$this->s($this->db->idate(dol_now())).')');
  $source=(int)$this->db->last_insert_id($this->table('document_source'));
  $this->rows('UPDATE '.$this->table('source_line')." SET review_status='linked',fk_user_reviewer=".(int)$actor->id.',date_reviewed='.$this->s($this->db->idate(dol_now())).' WHERE '.$this->scope().' AND fk_upload='.(int)$record->rowid);
  return ['packet'=>(int)$packet,'revision'=>null,'source'=>$source,'status'=>$status];
 }
 public function history($actor,$packet) {
  $this->guard($actor); (new MaxOrdersPacket($this->db))->packet($actor,$packet);
  return $this->rows('SELECT s.*,u.original_name FROM '.$this->table('document_source').' s JOIN '.$this->table('upload').' u ON u.rowid=s.fk_upload AND u.entity=s.entity WHERE s.entity='.$this->entity.' AND s.fk_packet='.(int)$packet.' ORDER BY s.rowid DESC LIMIT 100');
 }
 public function comparisons($actor,$packet) {
  $this->guard($actor); (new MaxOrdersPacket($this->db))->packet($actor,$packet);
  $rows=$this->rows('SELECT l.source_json,l.content_hash,l.source_row,s.kind,s.revision_no,s.status,s.fk_upload FROM '.$this->table('source_line').' l JOIN '.$this->table('document_source').' s ON s.fk_upload=l.fk_upload AND s.entity=l.entity WHERE s.entity='.$this->entity.' AND s.fk_packet='.(int)$packet.' ORDER BY s.rowid,l.source_row LIMIT 501');
  if(count($rows)>500) return ['limited'=>true,'rows'=>[]];
  $groups=[]; foreach($rows as $row) { $line=json_decode($row->source_json,true,40,JSON_THROW_ON_ERROR); if($line['state']!=='blank') $groups[$line['key']][]=[$row,$line]; }
  $out=[]; foreach($groups as $items) {
   $variants=[]; foreach($items as [$row,$line]) $variants[json_encode(array_intersect_key($line['values'],array_flip(['quantity','unit_price','currency'])))]=true;
   foreach($items as [$row,$line]) $out[]=(object)['kind'=>$row->kind,'revision_no'=>$row->revision_no,'status'=>$row->status,'source'=>$row->content_hash.' / '.$line['sheet'].'!'.$line['row'],'description'=>$line['values']['description']??'','quantity'=>$line['values']['quantity']??'','unit_price'=>$line['values']['unit_price']??'','currency'=>$line['values']['currency']??'','comparison'=>count($variants)>1?'OrdersSourceDifference':'OrdersProposalOnly'];
  }
  return ['limited'=>false,'rows'=>$out];
 }
 /** Only explicit source product references may suggest a native catalog entry. */
 public function catalog($actor,$analysis) {
  $this->guard($actor); if(!$actor->hasRight('produit','lire')) return [];
  $out=[]; foreach($analysis['lines']??[] as $line) {
   $ref=$line['values']['product_reference']??''; if($ref==='') continue;
   $rows=$this->rows('SELECT rowid,ref,label FROM '.MAIN_DB_PREFIX.'product WHERE entity='.$this->entity.' AND ref='.$this->s($ref).' ORDER BY rowid LIMIT 6');
   foreach($rows as $row) if(MaxOrdersTable::normalized($row->ref)===MaxOrdersTable::normalized($ref)) $out[]=(object)['source'=>$line['sheet'].'!'.$line['row'],'ref'=>$row->ref,'label'=>$row->label,'status'=>'suggested'];
  } return $out;
 }
}
