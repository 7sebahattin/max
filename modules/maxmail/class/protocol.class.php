<?php
/** Checked adapter over the pinned bundled PUBLIC protocol API. GPL-3.0-or-later. */
class MaxMailProtocolFailure extends RuntimeException
{
    public string $reason;
    public bool $retryable;
    public function __construct(string $reason,bool $retryable=false) { parent::__construct('MailProtocolBlocked',409); $this->reason=$reason; $this->retryable=$retryable; }
}
class MaxMailProtocol
{
    public const MAX_MESSAGE=2097152;
    private $client;
    private $connection;
    private string $folder;
    public function __construct($account)
    {
        $autoload=DOL_DOCUMENT_ROOT.'/includes/webklex/php-imap/vendor/autoload.php';
        if(!is_file($autoload)) throw new MaxMailProtocolFailure('blocked_native_api');
        require_once $autoload;
        require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
        $plain=$account->transport==='synthetic';
        if($plain && (getDolGlobalInt('MAXMAIL_ALLOW_SYNTHETIC_IMAP')!==1 || $account->host!=='fakeimap' || (int)$account->port!==1143)) throw new MaxMailProtocolFailure('blocked_plaintext');
        if(!$plain && !in_array($account->transport,['ssl','tls'],true)) throw new MaxMailProtocolFailure('blocked_transport');
        try {
            $manager=new \Webklex\PHPIMAP\ClientManager();
            $this->client=$manager->make(['host'=>$account->host,'port'=>(int)$account->port,'encryption'=>$plain?false:$account->transport,
                'validate_cert'=>true,'timeout'=>5,'protocol'=>'imap','username'=>$account->username,'password'=>dolDecrypt($account->secret),'authentication'=>'login']);
            $this->client->connect();
            $this->client->setTimeout(5);
            $this->connection=$this->client->getConnection();
            foreach(['requestAndResponse','examineFolder'] as $method) if(!is_callable([$this->connection,$method])) throw new MaxMailProtocolFailure('blocked_native_api');
            $this->folder=$account->folder;
        } catch(MaxMailProtocolFailure $e) { throw $e; }
        catch(Throwable $e) { throw new MaxMailProtocolFailure('connect_failed',true); }
    }
    public function epoch(): int
    {
        $status=$this->connection->examineFolder($this->folder);
        if(!is_array($status) || !isset($status['uidvalidity']) || (int)$status['uidvalidity']<1) throw new MaxMailProtocolFailure('blocked_uidvalidity');
        return (int)$status['uidvalidity'];
    }
    public function uids(): array
    {
        $reply=$this->connection->requestAndResponse('UID SEARCH',['UNDELETED']);
        if(!is_array($reply)) throw new MaxMailProtocolFailure('search_failed',true);
        $uids=[]; $found=false;
        foreach($reply as $line) if(is_array($line) && ($line[0]??'')==='SEARCH') {
            $found=true;
            foreach(array_slice($line,1) as $uid) {
                if(!ctype_digit((string)$uid) || (int)$uid<1 || (int)$uid>4294967295) throw new MaxMailProtocolFailure('blocked_uid_response');
                $uids[(int)$uid]=(int)$uid;
            }
        }
        if(!$found || count($uids)>100) throw new MaxMailProtocolFailure('blocked_scan_limit');
        sort($uids); return $uids;
    }
    private function fetchFields(int $uid,string $fields): array
    {
        // Unlike the native collector's array fetch helper, this API checks the FINAL tagged status.
        $reply=$this->connection->requestAndResponse('UID FETCH',[(string)$uid,$fields]);
        if($reply===false) throw new MaxMailProtocolFailure('fetch_no',true);
        if(!is_array($reply)) throw new MaxMailProtocolFailure('blocked_fetch_status');
        $matches=[];
        foreach($reply as $line) {
            if(!is_array($line) || ($line[1]??'')!=='FETCH' || !is_array($line[2]??null)) continue;
            $items=$line[2]; $pairs=[];
            if(count($items)%2!==0) throw new MaxMailProtocolFailure('blocked_fetch_shape');
            for($i=0;$i<count($items);$i+=2) {
                if(!is_string($items[$i]) || array_key_exists($items[$i],$pairs)) throw new MaxMailProtocolFailure('blocked_fetch_shape');
                $pairs[$items[$i]]=$items[$i+1];
            }
            if((int)($pairs['UID']??0)!==$uid) throw new MaxMailProtocolFailure('blocked_wrong_uid');
            $matches[]=$pairs;
        }
        if(count($matches)!==1) throw new MaxMailProtocolFailure('blocked_fetch_incomplete');
        return $matches[0];
    }
    public function raw(int $uid,int $epoch): string
    {
        try {
            $meta=$this->fetchFields($uid,'(UID RFC822.SIZE)');
            $size=$meta['RFC822.SIZE']??null;
            if(!ctype_digit((string)$size) || (int)$size<1 || (int)$size>self::MAX_MESSAGE) throw new MaxMailProtocolFailure('blocked_message_size');
            $data=$this->fetchFields($uid,'(UID RFC822.SIZE BODY.PEEK[])');
            $raw=$data['BODY[]']??null;
            if(!is_string($raw) || strlen($raw)!==(int)$size || (int)($data['RFC822.SIZE']??0)!==(int)$size) throw new MaxMailProtocolFailure('blocked_fetch_incomplete');
            if($this->epoch()!==$epoch) throw new MaxMailProtocolFailure('blocked_epoch_changed');
            if(strpos($raw,"\r\n\r\n")===false || strpos($raw,"\r\n\r\n")>32768) throw new MaxMailProtocolFailure('blocked_header_shape');
            return $raw;
        } catch(MaxMailProtocolFailure $e) { throw $e; }
        catch(Throwable $e) { throw new MaxMailProtocolFailure('blocked_transport_unverified'); }
    }
    public function parsed(string $raw,int $uid): array
    {
        if(preg_match_all('/^Content-Type:\s*multipart\//im',$raw)>12) throw new MaxMailProtocolFailure('blocked_mime_complexity');
        [$header,$body]=explode("\r\n\r\n",$raw,2);
        try {
            // Public in-memory factory; explicit option 0 and empty flags prevent flag writes.
            $message=\Webklex\PHPIMAP\Message::make($uid,0,$this->client,$header."\r\n\r\n",$body,[],0,\Webklex\PHPIMAP\IMAP::ST_UID);
            $attachments=$message->getAttachments()->all();
            if(count($attachments)>10) throw new MaxMailProtocolFailure('blocked_attachment_count');
            $files=[];
            foreach($attachments as $attachment) $files[]=['name'=>(string)$attachment->getName(),'bytes'=>$attachment->getContent()];
            $attrs=$message->getAttributes();
            return ['subject'=>(string)($attrs['subject']??''),'sender'=>(string)($attrs['from']??''),'message_id'=>(string)($attrs['message_id']??''),'files'=>$files];
        } catch(MaxMailProtocolFailure $e) { throw $e; }
        catch(Throwable $e) { throw new MaxMailProtocolFailure('blocked_mime_unverified'); }
    }
    public function close(): void { if($this->client) { try {$this->client->disconnect();} catch(Throwable $e) {} } }
    public function __destruct() { $this->close(); }
}
