<?php
/** Mailbox-scoped private ledger. GPL-3.0-or-later. No downstream business actions. */
require_once __DIR__.'/protocol.class.php';
class MaxMailIntake
{
    private $db;
    private int $entity;
    private string $installation;
    private ?string $heldLock=null;
    public function __construct($db)
    {
        global $conf,$dolibarr_main_db_name;
        $this->db=$db; $this->entity=(int)$conf->entity;
        $this->installation=substr(hash('sha256',$dolibarr_main_db_name.'|'.MAIN_DB_PREFIX),0,20);
    }
    public static function right($actor,string $right): bool
    {
        return !empty($actor->id) && empty($actor->socid) && ($actor->hasRight('maxmail','admin') || $actor->hasRight('maxmail',$right));
    }
    private function globalGuard($actor,string $right): void
    {
        if(!isModEnabled('maxmail') || !self::right($actor,'view') || !self::right($actor,$right)) throw new RuntimeException('MailDenied',403);
    }
    private function t(string $name): string { return MAIN_DB_PREFIX.'maxmail_'.$name; }
    private function q(string $sql) { $r=$this->db->query($sql); if(!$r) throw new RuntimeException('MailStorageError',500); return $r; }
    private function s($value): string { return "'".$this->db->escape((string)$value)."'"; }
    private function rows(string $sql): array { $r=$this->q($sql); $out=[]; while($v=$this->db->fetch_object($r)) $out[]=$v; return $out; }
    private function value(string $sql) { $r=$this->q($sql); return $this->db->fetch_row($r)[0]; }
    private function scope(int $account): string { return 'entity='.$this->entity.' AND fk_account='.$account; }
    private function now(): int { return (int)dol_now(); }
    private function date(): string { return $this->s($this->db->idate($this->now())); }
    public static function text($value,int $limit,bool $empty=false): string
    {
        if(!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value,'UTF-8')>$limit || preg_match('/[\x00-\x1f\x7f]/u',$value)!==0 || (!$empty && trim($value)==='')) throw new InvalidArgumentException('MailInvalid',400);
        return trim($value);
    }
    private function account($actor,int $id,string $right='view')
    {
        $this->globalGuard($actor,$right);
        $rows=$this->rows('SELECT * FROM '.$this->t('account').' WHERE entity='.$this->entity.' AND rowid='.$id);
        if(!$rows) throw new RuntimeException('MailNotFound',404);
        if(!self::right($actor,'admin')) {
            $column=['view'=>'can_view','poll'=>'can_poll','review'=>'can_review','download'=>'can_download'][$right]??null;
            if(!$column) throw new RuntimeException('MailDenied',403);
            $grants=$this->rows('SELECT can_view,'.$column.' permission FROM '.$this->t('grant').' WHERE '.$this->scope($id).' AND fk_user='.(int)$actor->id);
            if(!$grants || !$grants[0]->can_view || !$grants[0]->permission) throw new RuntimeException('MailDenied',403);
        }
        return $rows[0];
    }
    public function accounts($actor): array
    {
        $this->globalGuard($actor,'view');
        $sql='SELECT a.rowid,a.label,a.active,a.state,a.error_code FROM '.$this->t('account').' a';
        if(!self::right($actor,'admin')) $sql.=' JOIN '.$this->t('grant').' g ON g.fk_account=a.rowid AND g.entity=a.entity AND g.fk_user='.(int)$actor->id.' AND g.can_view=1';
        return $this->rows($sql.' WHERE a.entity='.$this->entity.' ORDER BY a.rowid');
    }
    private function event($actor,int $account,int $entry,string $event,string $code=''): void
    {
        $this->q('INSERT INTO '.$this->t('event').' (entity,fk_account,fk_entry,event,code,fk_user,datec) VALUES ('.$this->entity.','.$account.','.$entry.','.$this->s($event).','.$this->s($code).','.(int)$actor->id.','.$this->date().')');
    }
    private function lock(int $account): void
    {
        if($this->heldLock!==null || $this->db->type!=='mysqli') throw new RuntimeException('MailLockBlocked',409);
        $name='maxmail:'.$this->installation.':'.$this->entity.':'.$account;
        if((int)$this->value('SELECT GET_LOCK('.$this->s($name).',0)')!==1) throw new RuntimeException('MailBusy',409);
        $this->heldLock=$name;
    }
    private function fence(): void
    {
        if($this->heldLock===null || (int)$this->value('SELECT IS_USED_LOCK('.$this->s($this->heldLock).')=CONNECTION_ID()')!==1) throw new RuntimeException('MailLockLost',409);
    }
    private function unlock(): void
    {
        if($this->heldLock!==null) { $this->db->query('SELECT RELEASE_LOCK('.$this->s($this->heldLock).')'); $this->heldLock=null; }
    }
    public function capabilities($actor,int $account): array
    {
        $this->account($actor,$account); $out=['view'=>true];
        foreach(['poll','review','download'] as $right) {
            try { $this->account($actor,$account,$right); $out[$right]=true; }
            catch(RuntimeException $e) { if($e->getCode()!==403) throw $e; $out[$right]=false; }
        }
        return $out;
    }
    public function createAccount($actor,array $data): int
    {
        $this->globalGuard($actor,'admin');
        if(array_diff(array_keys($data),['label','host','port','transport','username','password','folder'])) throw new InvalidArgumentException('MailInvalid',400);
        $label=self::text($data['label']??null,80); $host=strtolower(self::text($data['host']??null,253));
        $username=self::text($data['username']??null,128); $folder=self::text($data['folder']??null,128);
        $port=$data['port']??0; $transport=$data['transport']??''; $password=$data['password']??null;
        if(!preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/D',$host) || !is_int($port) || $port<1 || $port>65535 || !in_array($transport,['ssl','tls','synthetic'],true)
            || !is_string($password) || strlen($password)<1 || strlen($password)>256 || preg_match('/[\x00-\x1f\x7f]/',$password)) throw new InvalidArgumentException('MailInvalid',400);
        if($transport==='synthetic' && (getDolGlobalInt('MAXMAIL_ALLOW_SYNTHETIC_IMAP')!==1 || $host!=='fakeimap' || $port!==1143)) throw new RuntimeException('MailSyntheticDisabled',409);
        $key=hash('sha256',json_encode([$host,$port,$transport,$username,$folder],JSON_UNESCAPED_UNICODE));
        require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
        $this->db->begin();
        try {
            if($this->rows('SELECT rowid FROM '.$this->t('account').' WHERE entity='.$this->entity.' AND identity_hash='.$this->s($key))) throw new RuntimeException('MailDuplicateAccount',409);
            $this->q('INSERT INTO '.$this->t('account').' (entity,label,identity_hash,host,port,transport,username,secret,folder,fk_user,datec) VALUES ('.$this->entity.','.$this->s($label).','.$this->s($key).','.$this->s($host).','.$port.','.$this->s($transport).','.$this->s($username).','.$this->s(dolEncrypt($password)).','.$this->s($folder).','.(int)$actor->id.','.$this->date().')');
            $id=(int)$this->db->last_insert_id($this->t('account'));
            $this->event($actor,$id,0,'account_created'); $this->db->commit(); return $id;
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function grant($actor,int $account,int $target,array $permissions): void
    {
        $this->account($actor,$account,'admin');
        if(array_diff(array_keys($permissions),['view','poll','review','download'])) throw new InvalidArgumentException('MailInvalid',400);
        $u=new User($this->db);
        if($u->fetch($target)<=0 || !empty($u->socid) || !in_array((int)$u->entity,[0,$this->entity],true) || (int)$u->statut!==1) throw new InvalidArgumentException('MailInvalid',400);
        $bits=[]; foreach(['view','poll','review','download'] as $p) { $v=$permissions[$p]??false; if(!is_bool($v)) throw new InvalidArgumentException('MailInvalid',400); $bits[]=$v?1:0; }
        $this->lock($account);
        $this->db->begin();
        try {
            $this->fence();
            $this->q('INSERT INTO '.$this->t('grant').' (entity,fk_account,fk_user,can_view,can_poll,can_review,can_download) VALUES ('.$this->entity.','.$account.','.$target.','.implode(',',$bits).') ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_poll=VALUES(can_poll),can_review=VALUES(can_review),can_download=VALUES(can_download)');
            $this->event($actor,$account,0,'scope_changed'); $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; } finally { $this->unlock(); }
    }
    public function configure($actor,int $account,?string $newSecret,?bool $active): void
    {
        $this->account($actor,$account,'admin');
        if($newSecret!==null && (strlen($newSecret)<1 || strlen($newSecret)>256 || preg_match('/[\x00-\x1f\x7f]/',$newSecret))) throw new InvalidArgumentException('MailInvalid',400);
        if($newSecret===null && $active===null) throw new InvalidArgumentException('MailInvalid',400);
        $this->lock($account); $this->db->begin();
        try {
            $this->fence();
            if($newSecret!==null) {
                require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
                $this->q('UPDATE '.$this->t('account').' SET secret='.$this->s(dolEncrypt($newSecret)).",connection_failures=0,retry_at=0,error_code='',state='pending' WHERE entity=".$this->entity.' AND rowid='.$account);
                $this->event($actor,$account,0,'credential_rotated');
            }
            if($active!==null) {
                $this->q('UPDATE '.$this->t('account').' SET active='.($active?1:0).' WHERE entity='.$this->entity.' AND rowid='.$account);
                $this->event($actor,$account,0,$active?'account_enabled':'account_disabled');
            }
            $this->db->commit();
        } catch(Throwable $error) { $this->db->rollback(); throw $error; } finally { $this->unlock(); }
    }
    public function entries($actor,int $account): array
    {
        $this->account($actor,$account);
        return $this->rows('SELECT rowid,imap_uid,uidvalidity,state,subject,sender,attempts,error_code,remote_deleted FROM '.$this->t('entry').' WHERE '.$this->scope($account).' ORDER BY rowid DESC LIMIT 100');
    }
    private function entry($actor,int $id,string $right='view')
    {
        $this->globalGuard($actor,$right);
        $rows=$this->rows('SELECT * FROM '.$this->t('entry').' WHERE entity='.$this->entity.' AND rowid='.$id);
        if(!$rows) { $this->globalGuard($actor,$right); throw new RuntimeException('MailNotFound',404); }
        $this->account($actor,(int)$rows[0]->fk_account,$right); return $rows[0];
    }
    public function detail($actor,int $id): array
    {
        $e=$this->entry($actor,$id);
        return ['id'=>(int)$e->rowid,'account'=>(int)$e->fk_account,'uid'=>(int)$e->imap_uid,'uidvalidity'=>(int)$e->uidvalidity,
            'state'=>$e->state,'attempts'=>(int)$e->attempts,'retry_at'=>(int)$e->retry_at,'error_code'=>$e->error_code,
            'subject'=>$e->subject,'sender'=>$e->sender,'message_id'=>$e->message_id,'remote_deleted'=>(bool)$e->remote_deleted,
            'reviewed_by'=>(int)$e->reviewed_by,'reviewed_at'=>$e->reviewed_at,
            'files'=>$this->rows('SELECT rowid,part_no,display_name,detected_mime,byte_size,content_hash,state,reason FROM '.$this->t('file').' WHERE entity='.$this->entity.' AND fk_entry='.$id.' ORDER BY part_no'),
            'events'=>$this->rows('SELECT event,code,fk_user,datec FROM '.$this->t('event').' WHERE entity='.$this->entity.' AND fk_entry='.$id.' ORDER BY rowid')];
    }
    private function root(): string
    {
        $root=DOL_DATA_ROOT.'/maxmail';
        if(is_link($root) || (!is_dir($root) && !mkdir($root,0700,true)) || !chmod($root,0700)) throw new RuntimeException('MailStorageError',500);
        $path=realpath($root); $web=realpath(DOL_DOCUMENT_ROOT);
        if(!$path || !$web || str_starts_with($path.'/', $web.'/')) throw new RuntimeException('MailStorageError',500);
        return $path;
    }
    private function file($actor,int $account,int $entry,int $part,string $name,string $bytes,string $state,string $reason,array &$written): void
    {
        $this->fence();
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)?:'application/octet-stream';
        $name=self::metadata($name,120); $hash=hash('sha256',$bytes); $storage=null;
        if(strlen($bytes)<=($part===0?MaxMailProtocol::MAX_MESSAGE:524288)) {
            $storage=hash('sha256',$this->installation.'|'.$this->entity.'|'.$account.'|'.$entry.'|'.$part.'|'.$hash).'.bin';
            $path=$this->root().'/'.$storage;
            if(file_exists($path)) {
                if(is_link($path) || !is_file($path) || filesize($path)!==strlen($bytes) || hash_file('sha256',$path)!==$hash) throw new RuntimeException('MailStorageError',500);
            } else {
                $temporary=$this->root().'/'.bin2hex(random_bytes(16)).'.part';
                $handle=fopen($temporary,'x'); if(!$handle) throw new RuntimeException('MailStorageError',500);
                chmod($temporary,0600); $count=fwrite($handle,$bytes); fflush($handle); fclose($handle);
                if($count!==strlen($bytes) || !rename($temporary,$path)) { @unlink($temporary); throw new RuntimeException('MailStorageError',500); }
                $written[]=$path;
            }
        } else { $state='quarantined'; $reason='attachment_oversized'; }
        $this->q('INSERT INTO '.$this->t('file').' (entity,fk_account,fk_entry,part_no,display_name,detected_mime,byte_size,content_hash,storage_name,state,reason) VALUES ('.$this->entity.','.$account.','.$entry.','.$part.','.$this->s($name).','.$this->s($mime).','.strlen($bytes).','.$this->s($hash).','.($storage===null?'NULL':$this->s($storage)).','.$this->s($state).','.$this->s($reason).')');
    }
    public static function metadata($value,int $max): string
    {
        if(!is_string($value) || !mb_check_encoding($value,'UTF-8')) return '';
        return mb_substr(preg_replace('/[\x00-\x1f\x7f]/u',' ',$value),0,$max,'UTF-8');
    }
    private function persist($actor,$account,$entry,int $epoch,string $raw,MaxMailProtocol $protocol): void
    {
        $id=(int)$entry->rowid; $aid=(int)$account->rowid; $written=[];
        try { $parsed=$protocol->parsed($raw,(int)$entry->imap_uid); $parseCode=''; }
        catch(MaxMailProtocolFailure $e) { $parsed=['subject'=>'','sender'=>'','message_id'=>'','files'=>[]]; $parseCode=$e->reason; }
        $review=$parseCode!=='' || ((int)$account->uidvalidity!==0 && (int)$account->uidvalidity!==$epoch);
        $this->db->begin();
        try {
            $this->fence();
            $this->file($actor,$aid,$id,0,'original.eml',$raw,'private','raw_source',$written);
            foreach($parsed['files'] as $part=>$f) {
                $bytes=$f['bytes']; $name=$f['name'];
                if(!is_string($bytes)) throw new MaxMailProtocolFailure('blocked_attachment_shape');
                $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes)?:'application/octet-stream';
                $reason='';
                if(strlen($bytes)>524288) $reason='attachment_oversized';
                elseif(str_contains($name,'/') || str_contains($name,chr(92)) || preg_match('/[\x00-\x1f\x7f]/',$name) || $name==='' || str_contains($name,'..')) $reason='unsafe_filename';
                elseif(str_ends_with(strtolower($name),'.pdf') || $mime==='application/pdf') {
                    $reason=!str_starts_with($bytes,'%PDF-') || !str_contains(substr($bytes,-1024),'%%EOF')?'pdf_invalid_signature':(str_contains($bytes,'/Encrypt')?'pdf_encrypted':'pdf_unknown_type');
                } elseif($mime!=='text/plain' || !mb_check_encoding($bytes,'UTF-8') || str_contains($bytes,"\0")) $reason='unsupported_content';
                if($reason!=='') $review=true;
                $this->file($actor,$aid,$id,$part+1,$name,$bytes,$reason===''?'validated':'quarantined',$reason,$written);
            }
            $state=$review?'review_required':'fetched';
            $code=$parseCode!==''?$parseCode:($review?'review_untrusted_source':'');
            $this->q('UPDATE '.$this->t('entry').' SET state='.$this->s($state).',attempts=attempts+1,content_hash='.$this->s(hash('sha256',$raw)).',subject='.$this->s(self::metadata($parsed['subject'],255)).',sender='.$this->s(self::metadata($parsed['sender'],255)).',message_id='.$this->s(self::metadata($parsed['message_id'],255)).',error_code='.$this->s($code).',retry_at=0 WHERE entity='.$this->entity.' AND rowid='.$id);
            $this->event($actor,$aid,$id,'fetch_committed',$code); $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); foreach($written as $path) if(is_file($path)) unlink($path); throw $e; }
    }
    private function fail($actor,int $account,$entry,MaxMailProtocolFailure $failure): void
    {
        $this->fence(); $id=(int)$entry->rowid; $attempts=(int)$entry->attempts+1;
        $retry=$failure->retryable && $attempts<3;
        $this->db->begin();
        try {
            $this->q('UPDATE '.$this->t('entry')." SET state='failed',attempts=".$attempts.',error_code='.$this->s($failure->reason).' WHERE entity='.$this->entity.' AND rowid='.$id);
            $this->event($actor,$account,$id,'fetch_failed',$failure->reason);
            $this->q('UPDATE '.$this->t('entry').' SET state='.$this->s($retry?'retry_due':'review_required').',retry_at='.($retry?$this->now()+60*$attempts:0).' WHERE entity='.$this->entity.' AND rowid='.$id);
            $this->event($actor,$account,$id,$retry?'retry_scheduled':'manual_review',$failure->reason);
            $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    private function retire($actor,int $account,int $id,string $reason): void
    {
        $this->db->begin();
        try {
            $this->fence();
            $result=$this->q('UPDATE '.$this->t('entry')." SET remote_deleted=1,state='review_required',error_code=".$this->s($reason).' WHERE '.$this->scope($account).' AND rowid='.$id.' AND remote_deleted=0');
            if($this->db->affected_rows($result)>0) $this->event($actor,$account,$id,'identity_retired',$reason);
            $this->db->commit();
        } catch(Throwable $e) { $this->db->rollback(); throw $e; }
    }
    public function poll($actor,int $account): array
    {
        $a=$this->account($actor,$account,'poll');
        if(getDolGlobalInt('MAXMAIL_ENABLE_POLLING')!==1) throw new RuntimeException('MailPollingDisabled',409);
        if(!$a->active) throw new RuntimeException('MailAccountDisabled',409);
        $this->lock($account); $protocol=null;
        try {
            $this->fence(); $a=$this->account($actor,$account,'poll');
            if((int)$a->connection_failures>=3 || (int)$a->retry_at>$this->now()) throw new RuntimeException('MailConnectionReview',409);
            try { $protocol=new MaxMailProtocol($a); $epoch=$protocol->epoch(); $uids=$protocol->uids(); }
            catch(MaxMailProtocolFailure $e) {
                $this->fence();
                $n=(int)$a->connection_failures+1;
                $this->q('UPDATE '.$this->t('account').' SET state='.$this->s($e->retryable && $n<3?'retry_due':'review_required').',error_code='.$this->s($e->reason).',connection_failures='.$n.',retry_at='.($e->retryable && $n<3?$this->now()+60*$n:0).' WHERE entity='.$this->entity.' AND rowid='.$account);
                $this->event($actor,$account,0,'poll_failed',$e->reason);
                return ['state'=>'blocked','code'=>$e->reason,'created'=>0];
            }
            if((int)$a->uidvalidity>0 && (int)$a->uidvalidity!==$epoch) {
                $old=$this->rows('SELECT rowid FROM '.$this->t('entry').' WHERE '.$this->scope($account).' AND uidvalidity<>'.$epoch.' AND remote_deleted=0');
                foreach($old as $r) {
                    $this->retire($actor,$account,(int)$r->rowid,'uidvalidity_changed');
                }
            }
            $created=0;
            foreach($uids as $uid) {
                $this->fence();
                $key=$this->scope($account).' AND uidvalidity='.$epoch.' AND imap_uid='.$uid;
                $existing=$this->rows('SELECT * FROM '.$this->t('entry').' WHERE '.$key);
                if(!$existing) {
                    $this->q('INSERT INTO '.$this->t('entry').' (entity,fk_account,uidvalidity,imap_uid,state,fk_user,datec) VALUES ('.$this->entity.','.$account.','.$epoch.','.$uid.",'pending',".(int)$actor->id.','.$this->date().')');
                    $existing=$this->rows('SELECT * FROM '.$this->t('entry').' WHERE '.$key); $created++;
                    $this->event($actor,$account,(int)$existing[0]->rowid,'identity_created');
                }
                $e=$existing[0];
                if($e->content_hash || $e->remote_deleted || $e->state==='review_required' || (int)$e->retry_at>$this->now()) continue;
                try {
                    $raw=$protocol->raw($uid,$epoch);
                    $this->persist($actor,$a,$e,$epoch,$raw,$protocol);
                } catch(MaxMailProtocolFailure $e2) { $this->fail($actor,$account,$e,$e2); }
            }
            $all=$this->rows('SELECT rowid,imap_uid FROM '.$this->t('entry').' WHERE '.$this->scope($account).' AND uidvalidity='.$epoch.' AND remote_deleted=0');
            foreach($all as $e) if(!in_array((int)$e->imap_uid,$uids,true)) {
                $this->retire($actor,$account,(int)$e->rowid,'remote_missing');
            }
            $this->fence();
            $blocked=(int)$this->value('SELECT COUNT(*) FROM '.$this->t('entry').' WHERE '.$this->scope($account)." AND state IN ('failed','retry_due','review_required')");
            $retry=(int)$this->value('SELECT COUNT(*) FROM '.$this->t('entry').' WHERE '.$this->scope($account)." AND state IN ('failed','retry_due')");
            $unverified=(int)$this->value('SELECT COUNT(*) FROM '.$this->t('entry').' WHERE '.$this->scope($account)." AND state='review_required' AND content_hash IS NULL AND remote_deleted=0");
            $state=$retry?'retry_due':($blocked?'review_required':'fetched');
            $this->q('UPDATE '.$this->t('account').' SET uidvalidity='.$epoch.',state='.$this->s($state).',error_code='.$this->s($blocked?'intake_attention':'').',connection_failures=0,retry_at=0 WHERE entity='.$this->entity.' AND rowid='.$account);
            return ['state'=>$state,'created'=>$created,'attention'=>$blocked,'transport_error'=>(bool)($retry || $unverified)];
        } finally { if($protocol) $protocol->close(); $this->unlock(); }
    }
    public function requestRetry($actor,int $id): void
    {
        $e=$this->entry($actor,$id,'review'); $account=(int)$e->fk_account;
        $this->lock($account);
        $this->db->begin();
        try {
            $this->fence(); $e=$this->entry($actor,$id,'review');
            if(!in_array($e->state,['failed','retry_due'],true) || $e->content_hash || $e->remote_deleted || (int)$e->attempts>=3) throw new RuntimeException('MailRetryBlocked',409);
            $this->q('UPDATE '.$this->t('entry')." SET state='retry_due',retry_at=0 WHERE entity=".$this->entity.' AND rowid='.$id);
            $this->event($actor,$account,$id,'manual_retry_requested');
            $this->db->commit();
        } catch(Throwable $error) { $this->db->rollback(); throw $error; } finally { $this->unlock(); }
    }
    public function acknowledge($actor,int $id): void
    {
        $e=$this->entry($actor,$id,'review'); $account=(int)$e->fk_account;
        $this->lock($account);
        $this->db->begin();
        try {
            $this->fence(); $e=$this->entry($actor,$id,'review');
            if($e->state!=='review_required') throw new RuntimeException('MailReviewOnly',409);
            if(!$e->reviewed_by) {
                $this->q('UPDATE '.$this->t('entry').' SET reviewed_by='.(int)$actor->id.',reviewed_at='.$this->date().' WHERE entity='.$this->entity.' AND rowid='.$id);
                $this->event($actor,$account,$id,'review_noted');
            }
            $this->db->commit();
        } catch(Throwable $error) { $this->db->rollback(); throw $error; } finally { $this->unlock(); }
    }
    public function download($actor,int $id,int $file): array
    {
        $e=$this->entry($actor,$id,'download');
        $rows=$this->rows('SELECT * FROM '.$this->t('file').' WHERE entity='.$this->entity.' AND fk_entry='.$id.' AND rowid='.$file);
        if(!$rows) throw new RuntimeException('MailNotFound',404); $f=$rows[0];
        if($f->state!=='validated') $this->account($actor,(int)$e->fk_account,'review');
        if(!$f->storage_name || !preg_match('/^[a-f0-9]{64}\.bin$/D',$f->storage_name)) throw new RuntimeException('MailFileBlocked',409);
        $path=$this->root().'/'.$f->storage_name;
        if(is_link($path) || !is_file($path) || filesize($path)!==(int)$f->byte_size || hash_file('sha256',$path)!==$f->content_hash) throw new RuntimeException('MailFileBlocked',409);
        return ['path'=>$path,'size'=>(int)$f->byte_size];
    }
}
