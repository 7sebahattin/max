<?php
/** MAX draft metadata and projections. GPL-3.0-or-later. No regulatory transport. */
class MaxCustomsDeclaration
{
    private $db;
    private int $entity;
    public const FIELDS=['office'=>120,'mode'=>16,'origin'=>120,'destination'=>120,'departure_date'=>10,'arrival_date'=>10,'goods_summary'=>2000,'references'=>512];
    public const RESTRICTED=['contact'=>'contact_data','analysis'=>'analysis_data','source'=>'source_data'];
    public function __construct($db) { global $conf; $this->db=$db; $this->entity=(int)$conf->entity; }
    public static function permits($actor,string $right): bool
    {
        if(empty($actor->id) || !empty($actor->socid)) return false;
        return $actor->hasRight('maxcustoms','admin') || $actor->hasRight('maxcustoms',$right);
    }
    private function authorize($actor,string $right): void
    {
        if(!isModEnabled('maxcustoms') || !self::permits($actor,'view') || !self::permits($actor,$right)
            || !$actor->hasRight('societe','lire') || !$actor->hasRight('societe','client','voir')
            || !$actor->hasRight('commande','lire') || !$actor->hasRight('expedition','lire')) {
            throw new RuntimeException('CustomsDenied',403);
        }
    }
    private function table(string $name): string { return MAIN_DB_PREFIX.'maxcustoms_'.$name; }
    private function quoted($value): string { return "'".$this->db->escape((string)$value)."'"; }
    private function execute(string $sql) { $result=$this->db->query($sql); if(!$result) throw new RuntimeException('CustomsStorageError',500); return $result; }
    private function records(string $sql): array { $result=$this->execute($sql); $out=[]; while($r=$this->db->fetch_object($result)) $out[]=$r; return $out; }
    private function scope(int $id): string { return 'entity='.$this->entity.' AND fk_declaration='.$id; }
    private function stamp(): string { return $this->quoted($this->db->idate(dol_now())); }
    public static function text($value,int $limit,bool $multiline=false,bool $empty=true): string
    {
        if(!is_string($value) || mb_strlen($value,'UTF-8')>$limit || !mb_check_encoding($value,'UTF-8')) throw new InvalidArgumentException('CustomsInvalid',400);
        $value=trim($value);
        if((!$empty && $value==='') || preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u':'/[\x00-\x1f\x7f<>]/u',$value)!==0) throw new InvalidArgumentException('CustomsInvalid',400);
        return $value;
    }
    public static function object($json): array
    {
        if(!is_string($json) || strlen($json)>20000) throw new InvalidArgumentException('CustomsInvalid',400);
        try { $data=json_decode($json,false,12,JSON_THROW_ON_ERROR); } catch(Throwable $e) { throw new InvalidArgumentException('CustomsInvalid',400); }
        if(!is_object($data)) throw new InvalidArgumentException('CustomsInvalid',400);
        return get_object_vars($data);
    }
    private function validatePublic(array $patch): array
    {
        $result=[];
        foreach($patch as $key=>$value) {
            if(!isset(self::FIELDS[$key])) throw new InvalidArgumentException('CustomsInvalid',400);
            $result[$key]=self::text($value,self::FIELDS[$key],in_array($key,['goods_summary','references'],true));
            if($key==='mode' && $result[$key]!=='' && !in_array($result[$key],['road','sea','air','rail','other'],true)) throw new InvalidArgumentException('CustomsInvalid',400);
            if(str_ends_with($key,'_date') && $result[$key]!=='') {
                $parts=explode('-',$result[$key]);
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$result[$key]) || !checkdate((int)($parts[1]??0),(int)($parts[2]??0),(int)$parts[0])) throw new InvalidArgumentException('CustomsInvalid',400);
            }
        }
        return $result;
    }
    private function validateRestricted($actor,array $patch): array
    {
        foreach($patch as $key=>$value) if(!isset(self::RESTRICTED[$key])) throw new InvalidArgumentException('CustomsInvalid',400);
        // Authorize keys before inspecting values; even an empty clearing attempt is forbidden.
        foreach($patch as $key=>$value) if(!self::permits($actor,$key)) throw new RuntimeException('CustomsDenied',403);
        $result=[];
        foreach($patch as $key=>$value) $result[$key]=self::text($value,$key==='source'?4096:2000,true);
        return $result;
    }
    private function row($actor,int $id,bool $lock=false)
    {
        $this->authorize($actor,'view');
        $rows=$this->records('SELECT * FROM '.$this->table('declaration').' WHERE entity='.$this->entity.' AND rowid='.$id.($lock?' FOR UPDATE':''));
        if(!$rows) throw new RuntimeException('CustomsNotFound',404);
        return $rows[0];
    }
    private function mutable($actor,int $id,int $version)
    {
        $row=$this->row($actor,$id,true);
        if((int)$row->version!==$version) throw new RuntimeException('CustomsStale',409);
        if($row->status!=='draft') throw new RuntimeException('CustomsDraftOnly',409);
        return $row;
    }
    private function bump($actor,int $id): void
    {
        $this->execute('UPDATE '.$this->table('declaration').' SET version=version+1,fk_user_editor='.(int)$actor->id.',datem='.$this->stamp().' WHERE entity='.$this->entity.' AND rowid='.$id);
    }
    private function history($actor,int $id,string $event,string $category='public',string $field='',string $previous='',string $next=''): void
    {
        $this->execute('INSERT INTO '.$this->table('history').' (entity,fk_declaration,event,category,field_name,previous_status,next_status,fk_user,datec) VALUES ('.$this->entity.','.$id.','.$this->quoted($event).','.$this->quoted($category).','.$this->quoted($field).','.$this->quoted($previous).','.$this->quoted($next).','.(int)$actor->id.','.$this->stamp().')');
    }
    public function listing($actor): array
    {
        $this->authorize($actor,'view');
        return $this->records('SELECT rowid,ref,status,version FROM '.$this->table('declaration').' WHERE entity='.$this->entity.' ORDER BY rowid DESC LIMIT 100');
    }
    public function projection($actor,int $id): array
    {
        $r=$this->row($actor,$id);
        $out=['id'=>(int)$r->rowid,'ref'=>$r->ref,'status'=>$r->status,'version'=>(int)$r->version,
            'customer_id'=>(int)$r->fk_soc,'order_id'=>(int)$r->fk_order,'order_reference'=>$r->order_reference,
            'fields'=>json_decode($r->public_data,true),'shipments'=>[],'history'=>[]];
        foreach($this->records('SELECT fk_shipment FROM '.$this->table('shipment').' WHERE '.$this->scope($id).' ORDER BY fk_shipment') as $s) $out['shipments'][]=(int)$s->fk_shipment;
        $allowed=[];
        foreach(self::RESTRICTED as $capability=>$column) if(self::permits($actor,$capability)) $allowed[$capability]=$column;
        if($allowed) {
            $rows=$this->records('SELECT '.implode(',',array_values($allowed)).' FROM '.$this->table('private').' WHERE '.$this->scope($id));
            $out['restricted']=[];
            foreach($allowed as $key=>$column) $out['restricted'][$key]=$rows?$rows[0]->$column:'';
        }
        if(isset($allowed['source'])) $out['evidence']=$this->records('SELECT rowid,display_name,mime,byte_size,status,content_hash FROM '.$this->table('evidence').' WHERE '.$this->scope($id).' ORDER BY rowid');
        foreach($this->records('SELECT event,category,field_name,previous_status,next_status,fk_user,datec FROM '.$this->table('history').' WHERE '.$this->scope($id).' ORDER BY rowid') as $h) {
            if($h->category!=='public' && !isset($allowed[$h->category])) continue;
            $out['history'][]=['event'=>$h->event,'field'=>$h->field_name,'from'=>$h->previous_status,'to'=>$h->next_status,'actor'=>(int)$h->fk_user,'at'=>$h->datec];
        }
        return $out;
    }
    public function create($actor,$reference,array $public,array $private=[]): int
    {
        $this->authorize($actor,'create');
        $ref=self::text($reference,80,false,false);
        $data=$this->validatePublic($public); $restricted=$this->validateRestricted($actor,$private);
        $key=hash('sha256',mb_strtoupper(preg_replace('/\s+/u',' ',$ref),'UTF-8'));
        $this->db->begin();
        try {
            if($this->records('SELECT rowid FROM '.$this->table('declaration').' WHERE entity='.$this->entity.' AND ref_key='.$this->quoted($key))) throw new RuntimeException('CustomsDuplicate',409);
            $this->execute('INSERT INTO '.$this->table('declaration').' (entity,ref,ref_key,public_data,status,version,fk_user_author,datec,fk_user_editor,datem) VALUES ('.$this->entity.','.$this->quoted($ref).','.$this->quoted($key).','.$this->quoted(json_encode($data,JSON_UNESCAPED_UNICODE)).",'draft',1,".(int)$actor->id.','.$this->stamp().','.(int)$actor->id.','.$this->stamp().')');
            $id=(int)$this->db->last_insert_id($this->table('declaration'));
            $this->execute('INSERT INTO '.$this->table('private').' (entity,fk_declaration) VALUES ('.$this->entity.','.$id.')');
            foreach($restricted as $name=>$value) {
                $this->execute('UPDATE '.$this->table('private').' SET '.self::RESTRICTED[$name].'='.$this->quoted($value).' WHERE '.$this->scope($id));
                $this->history($actor,$id,'restricted_edit',$name,$name);
            }
            $this->history($actor,$id,'created','public','','','draft');
            $this->db->commit(); return $id;
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function edit($actor,int $id,int $version,array $public,array $private=[]): void
    {
        $this->authorize($actor,'edit');
        $patch=$this->validatePublic($public); $restricted=$this->validateRestricted($actor,$private);
        $this->db->begin();
        try {
            $row=$this->mutable($actor,$id,$version);
            $data=array_replace(json_decode($row->public_data,true),$patch);
            $this->execute('UPDATE '.$this->table('declaration').' SET public_data='.$this->quoted(json_encode($data,JSON_UNESCAPED_UNICODE)).' WHERE entity='.$this->entity.' AND rowid='.$id);
            if($patch) $this->history($actor,$id,'public_edit');
            foreach($restricted as $name=>$value) {
                $this->execute('UPDATE '.$this->table('private').' SET '.self::RESTRICTED[$name].'='.$this->quoted($value).' WHERE '.$this->scope($id));
                $this->history($actor,$id,'restricted_edit',$name,$name);
            }
            $this->bump($actor,$id); $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function matchNative($actor,int $id,int $version,int $customer,int $order,array $shipments,$reference,bool $confirmed): void
    {
        $this->authorize($actor,'match');
        if(!$confirmed || count($shipments)>20) throw new InvalidArgumentException('CustomsConfirm',400);
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
        require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
        $c=new Societe($this->db); $o=new Commande($this->db);
        $ref=self::text($reference,80,false,false);
        $normalize=fn($s)=>mb_strtoupper(preg_replace('/\s+/u',' ',trim((string)$s)),'UTF-8');
        if($c->fetch($customer)<=0 || (int)$c->entity!==$this->entity || $o->fetch($order)<=0 || (int)$o->entity!==$this->entity || (int)$o->socid!==$customer || $normalize($o->ref_customer?:$o->ref)!==$normalize($ref)) throw new InvalidArgumentException('CustomsMatchFailed',400);
        $ids=[];
        foreach($shipments as $sid) {
            if(!is_int($sid) || $sid<1) throw new InvalidArgumentException('CustomsMatchFailed',400);
            $s=new Expedition($this->db);
            if($s->fetch($sid)<=0 || (int)$s->entity!==$this->entity || (int)$s->socid!==$customer || (int)$s->origin_id!==$order || $s->origin_type!=='commande') throw new InvalidArgumentException('CustomsMatchFailed',400);
            $ids[$sid]=$sid;
        }
        $this->db->begin();
        try {
            $this->mutable($actor,$id,$version);
            $this->execute('UPDATE '.$this->table('declaration').' SET fk_soc='.$customer.',fk_order='.$order.',order_reference='.$this->quoted($ref).' WHERE entity='.$this->entity.' AND rowid='.$id);
            $this->execute('DELETE FROM '.$this->table('shipment').' WHERE '.$this->scope($id));
            foreach($ids as $sid) $this->execute('INSERT INTO '.$this->table('shipment').' (entity,fk_declaration,fk_shipment) VALUES ('.$this->entity.','.$id.','.$sid.')');
            $this->history($actor,$id,'native_match'); $this->bump($actor,$id); $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function transition($actor,int $id,int $version,string $target,bool $confirmed): void
    {
        $this->authorize($actor,'status');
        if(!$confirmed) throw new InvalidArgumentException('CustomsConfirm',400);
        $this->db->begin();
        try {
            $r=$this->row($actor,$id,true);
            if((int)$r->version!==$version) throw new RuntimeException('CustomsStale',409);
            $normal=['draft'=>['reviewed','cancelled'],'reviewed'=>['approved','cancelled'],'approved'=>['closed'],'closed'=>[],'cancelled'=>[]];
            $correction=in_array($r->status,['reviewed','approved'],true) && $target==='draft';
            $cancelApproved=$r->status==='approved' && $target==='cancelled';
            if(($correction || $cancelApproved) && !self::permits($actor,'admin')) throw new RuntimeException('CustomsDenied',403);
            if(!$correction && !$cancelApproved && !in_array($target,$normal[$r->status]??[],true)) throw new RuntimeException('CustomsTransitionDenied',409);
            if($target==='reviewed') {
                $fields=json_decode($r->public_data,true);
                foreach(['office','mode','origin','destination','goods_summary'] as $key) if(empty($fields[$key])) throw new RuntimeException('CustomsIncomplete',409);
                if(!$r->fk_order) throw new RuntimeException('CustomsIncomplete',409);
                if($this->records('SELECT rowid FROM '.$this->table('evidence').' WHERE '.$this->scope($id)." AND status='needs_review'")) throw new RuntimeException('CustomsEvidenceReview',409);
            }
            $this->execute('UPDATE '.$this->table('declaration').' SET status='.$this->quoted($target).' WHERE entity='.$this->entity.' AND rowid='.$id);
            $this->history($actor,$id,$correction?'correction':'status_change','public','status',$r->status,$target);
            $this->bump($actor,$id); $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    private function directory(): string
    {
        $root=DOL_DATA_ROOT.'/maxcustoms';
        if(is_link($root) || (!is_dir($root) && !mkdir($root,0700,true))) throw new RuntimeException('CustomsStorageError',500);
        if(!chmod($root,0700)) throw new RuntimeException('CustomsStorageError',500);
        $real=realpath($root); $web=realpath(DOL_DOCUMENT_ROOT);
        if(!$real || !$web || str_starts_with($real.'/', $web.'/')) throw new RuntimeException('CustomsStorageError',500);
        return $real;
    }
    public function addEvidence($actor,int $id,int $version,string $name,string $bytes,string $declaredMime): int
    {
        $this->authorize($actor,'edit');
        if(!self::permits($actor,'source')) throw new RuntimeException('CustomsDenied',403);
        if(strlen($bytes)===0 || strlen($bytes)>8192 || !preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_ .-]{0,70}\.txt$/D',$name) || str_contains($name,'..') || !in_array($declaredMime,['text/plain','application/octet-stream'],true)) throw new InvalidArgumentException('CustomsInvalidEvidence',400);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!in_array($mime,['text/plain','application/octet-stream'],true)) throw new InvalidArgumentException('CustomsInvalidEvidence',400);
        $status=mb_check_encoding($bytes,'UTF-8') && !str_contains($bytes,"\0")?'ready':'needs_review';
        $hash=hash('sha256',$bytes); $written=null;
        $this->db->begin();
        try {
            $this->mutable($actor,$id,$version);
            $existing=$this->records('SELECT rowid FROM '.$this->table('evidence').' WHERE '.$this->scope($id).' AND content_hash='.$this->quoted($hash));
            if($existing) { $this->db->commit(); return (int)$existing[0]->rowid; }
            $filename=bin2hex(random_bytes(20)).'.bin'; $written=$this->directory().'/'.$filename;
            $handle=fopen($written,'x');
            if(!$handle) throw new RuntimeException('CustomsStorageError',500);
            chmod($written,0600); $size=fwrite($handle,$bytes); fclose($handle);
            if($size!==strlen($bytes)) throw new RuntimeException('CustomsStorageError',500);
            $this->execute('INSERT INTO '.$this->table('evidence').' (entity,fk_declaration,content_hash,storage_name,display_name,mime,byte_size,status,fk_user,datec) VALUES ('.$this->entity.','.$id.','.$this->quoted($hash).','.$this->quoted($filename).','.$this->quoted($name).','.$this->quoted($mime).','.strlen($bytes).','.$this->quoted($status).','.(int)$actor->id.','.$this->stamp().')');
            $eid=(int)$this->db->last_insert_id($this->table('evidence'));
            $this->history($actor,$id,'evidence_added','source'); $this->bump($actor,$id); $this->db->commit(); return $eid;
        } catch(Throwable $e) { $this->db->rollback(); if($written && is_file($written)) unlink($written); throw $e; }
    }
    public function acknowledgeEvidence($actor,int $id,int $version,int $evidence,bool $confirmed): void
    {
        $this->authorize($actor,'status');
        if(!self::permits($actor,'source')) throw new RuntimeException('CustomsDenied',403);
        if(!$confirmed) throw new InvalidArgumentException('CustomsConfirm',400);
        $this->db->begin();
        try {
            $this->mutable($actor,$id,$version);
            $rows=$this->records('SELECT rowid,status FROM '.$this->table('evidence').' WHERE '.$this->scope($id).' AND rowid='.$evidence);
            if(!$rows) throw new RuntimeException('CustomsNotFound',404);
            if($rows[0]->status==='needs_review') {
                $this->execute('UPDATE '.$this->table('evidence')." SET status='reviewed_opaque' WHERE ".$this->scope($id).' AND rowid='.$evidence);
                $this->history($actor,$id,'evidence_acknowledged','source'); $this->bump($actor,$id);
            }
            $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function download($actor,int $id,int $evidence): array
    {
        $this->authorize($actor,'view');
        if(!self::permits($actor,'source')) throw new RuntimeException('CustomsDenied',403);
        $this->row($actor,$id);
        $rows=$this->records('SELECT storage_name,display_name,content_hash,byte_size FROM '.$this->table('evidence').' WHERE '.$this->scope($id).' AND rowid='.$evidence);
        if(!$rows) throw new RuntimeException('CustomsNotFound',404);
        $r=$rows[0];
        if(!preg_match('/^[a-f0-9]{40}\.bin$/D',$r->storage_name)) throw new RuntimeException('CustomsStorageError',500);
        $file=$this->directory().'/'.$r->storage_name;
        if(is_link($file) || !is_file($file) || filesize($file)!==(int)$r->byte_size || hash_file('sha256',$file)!==$r->content_hash) throw new RuntimeException('CustomsEvidenceUnavailable',409);
        return ['path'=>$file,'name'=>$r->display_name];
    }
    public function export($actor,int $id): string
    {
        $projection=$this->projection($actor,$id);
        unset($projection['evidence']);
        $stream=fopen('php://temp','w+'); fputcsv($stream,['field','value'],',','"','');
        $emit=function($key,$value) use($stream) {
            $value=is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):(string)$value;
            if(preg_match('/^[\s\p{Z}\x{FEFF}]*[=+@\x2d]/u',$value)) $value="'".$value;
            fputcsv($stream,[$key,$value],',','"','');
        };
        foreach($projection as $key=>$value) {
            if(in_array($key,['fields','restricted'],true)) foreach($value as $field=>$v) $emit($field,$v);
            else $emit($key,$value);
        }
        rewind($stream); $csv=stream_get_contents($stream); fclose($stream); return $csv;
    }
}
