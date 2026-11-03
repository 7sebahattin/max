<?php
// CLI synthetic fixture, outside webroot. GPL-3.0-or-later. Never a public integration.
if(PHP_SAPI!=='cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxmail/class/intake.class.php';
set_exception_handler(function($e) {
    $label=preg_match('/^ASSERT [A-Za-z0-9 _-]{1,120}$/D',$e->getMessage())?$e->getMessage():'native intake fixture failure';
    fwrite(STDERR,$label.' '.basename($e->getFile()).':'.$e->getLine()."\n"); exit(1);
});
function ensureM($value,string $label): void { if(!$value) throw new RuntimeException('ASSERT '.$label); }
function rowsM(string $sql): array { global $db; $q=$db->query($sql); ensureM((bool)$q,'fixture query'); $out=[]; while($r=$db->fetch_object($q)) $out[]=$r; return $out; }
function deniedM(callable $call,int $status,string $label): void { try {$call();} catch(Throwable $e) {ensureM($e->getCode()===$status,$label);return;} throw new RuntimeException('ASSERT '.$label); }
function snapshotM(): string {
    global $db; $out=[];
    foreach(['societe','commande','commandedet','expedition','expeditiondet','product','product_stock','stock_mouvement','facture','facturedet','bank_account','bank','paiement','emailcollector_emailcollector','actioncomm'] as $table) {
        $q=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY '.($table==='actioncomm'?'id':'rowid'));
        ensureM((bool)$q,'native snapshot'); $out[$table]=[]; while($r=$db->fetch_array($q)) $out[$table][]=$r;
    }
    $q=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
    while($r=$db->fetch_array($q)) $out['legal'][]=$r;
    return hash('sha256',json_encode($out));
}
ensureM($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1' && is_file(DOL_DATA_ROOT.'/install.lock'),'disposable locked native install');
$user->fetch('','maxadmin'); ensureM($user->admin,'native fixture admin');
$mail=new MaxMailIntake($db); $command=$argv[1]??'';
$sessionFile=DOL_DATA_ROOT.'/mailintake-session.json';
if($command==='setup') {
    ensureM(empty(activateModule('modMaxmail',1,1)['errors']),'actual native module activation'); $conf->setValues($db); $user->getrights('',1);
    ensureM(count(rowsM("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='maxmail'"))===5,'five native rights');
    ensureM(getDolGlobalInt('MAXMAIL_ENABLE_POLLING')!==1,'polling defaults off');
    foreach(['MAXMAIL_ENABLE_POLLING','MAXMAIL_ALLOW_SYNTHETIC_IMAP'] as $key) ensureM(dolibarr_set_const($db,$key,'1','chaine',0,'',1)>0,'disposable intake authorization');
    $conf->setValues($db);
    ensureM(getDolGlobalString('MAIN_DISABLE_ALL_MAILS')==='1' && ini_get('sendmail_path')==='/bin/false','native no send boundary');
    $seed=json_decode(file_get_contents(DOL_DATA_ROOT.'/mailintake-input.json'),true); unlink(DOL_DATA_ROOT.'/mailintake-input.json');
    $state=['boxes'=>[],'accounts'=>[]];
    foreach($seed['mailboxes'] as $box) {
        $state['boxes'][$box['alias']]=$mail->createAccount($user,['label'=>'Sandbox '.$box['alias'],'host'=>'fakeimap','port'=>1143,'transport'=>'synthetic','username'=>$box['login'],'password'=>$box['secret'],'folder'=>'INBOX']);
    }
    $first=$seed['mailboxes'][0];
    $encrypted=rowsM('SELECT secret FROM '.MAIN_DB_PREFIX.'maxmail_account WHERE rowid='.$state['boxes']['alpha'])[0]->secret;
    ensureM(str_starts_with($encrypted,'dolcrypt:') && $encrypted!==$first['secret'] && dolDecrypt($encrypted)===$first['secret'],'native credential encrypted roundtrip');
    $savedKey=$conf->file->instance_unique_id; $savedAlternate=$conf->file->dolcrypt_key??null;
    $conf->file->instance_unique_id=''; $conf->file->dolcrypt_key='';
    deniedM(fn()=>$mail->createAccount($user,['label'=>'No key','host'=>'fakeimap','port'=>1143,'transport'=>'synthetic','username'=>'nokey@intake.invalid','password'=>$first['secret'],'folder'=>'INBOX']),409,'missing key blocks credential persistence');
    $conf->file->instance_unique_id=$savedKey; $conf->file->dolcrypt_key=$savedAlternate;
    $dolibarr_disable_dolcrypt_for_debug=1;
    deniedM(fn()=>$mail->configure($user,$state['boxes']['alpha'],$first['secret'],null),409,'plaintext fallback blocks credential rotation');
    unset($dolibarr_disable_dolcrypt_for_debug);
    deniedM(fn()=>$mail->configure($user,$state['boxes']['alpha'],'dolcrypt:unsupported:synthetic:cipher',null),409,'already encoded input rejected');
    ensureM(count(rowsM('SELECT rowid FROM '.MAIN_DB_PREFIX.'maxmail_account'))===2 && rowsM('SELECT secret FROM '.MAIN_DB_PREFIX.'maxmail_account WHERE rowid='.$state['boxes']['alpha'])[0]->secret===$encrypted,'blocked crypto writes preserve accounts');
    deniedM(fn()=>$mail->createAccount($user,['label'=>'Duplicate','host'=>'fakeimap','port'=>1143,'transport'=>'synthetic','username'=>$first['login'],'password'=>$first['secret'],'folder'=>'INBOX']),409,'duplicate account identity denied');
    $permissions=['denied'=>[],'operator'=>['view','poll'],'reader_alpha'=>['view'],'reader_beta'=>['view'],
        'download_alpha'=>['view','download'],'download_beta'=>['view','download'],'reviewer'=>['view','review','download'],
        'no_scope'=>['view','poll','review','download'],'manager'=>['admin']];
    $rights=['view'=>61015001,'poll'=>61015002,'review'=>61015003,'download'=>61015004,'admin'=>61015005];
    foreach($permissions as $role=>$grants) {
        $person=new User($db); $person->login='mailintake-'.$role; $person->lastname='Synthetic role'; $password=bin2hex(random_bytes(24)); $person->pass=$password;
        ensureM($person->create($user)>0,'native role creation'); $person->clearrights();
        foreach($grants as $right) ensureM($person->addrights($rights[$right])>0,'native MAX role grant');
        $state['accounts'][$role]=['id'=>(int)$person->id,'login'=>$person->login,'password'=>$password];
    }
    $scopes=['operator'=>['alpha'=>['view'=>true,'poll'=>true]],'reader_alpha'=>['alpha'=>['view'=>true]],'reader_beta'=>['beta'=>['view'=>true]],
        'download_alpha'=>['alpha'=>['view'=>true,'download'=>true]],'download_beta'=>['beta'=>['view'=>true,'download'=>true]],
        'reviewer'=>['alpha'=>['view'=>true,'review'=>true,'download'=>true],'beta'=>['view'=>true,'review'=>true,'download'=>true]]];
    foreach($scopes as $role=>$boxes) foreach($boxes as $alias=>$scope) $mail->grant($user,$state['boxes'][$alias],$state['accounts'][$role]['id'],$scope);
    $denied=new User($db); $denied->fetch($state['accounts']['reader_alpha']['id']); $denied->getrights('',1);
    deniedM(fn()=>$mail->entries($denied,$state['boxes']['beta']),403,'cross mailbox view denied');
    deniedM(fn()=>$mail->poll($denied,$state['boxes']['alpha']),403,'read only poll denied');
    deniedM(fn()=>$mail->createAccount($denied,[]),403,'read only administration denied');
    $noScope=new User($db); $noScope->fetch($state['accounts']['no_scope']['id']); $noScope->getrights('',1);
    deniedM(fn()=>$mail->entries($noScope,$state['boxes']['alpha']),403,'global right alone insufficient');
    $external=clone $user; $external->socid=999; ensureM(!MaxMailIntake::right($external,'view'),'external admin denied');
    require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
    $directory=DOL_DATA_ROOT.'/mailintake-fixtures'; if(!is_dir($directory)) mkdir($directory,0700);
    foreach(['unknown','locked'] as $kind) {
        $pdf=new TCPDF(); $pdf->SetCreator('MAX synthetic fixture'); $pdf->AddPage(); $pdf->Write(0,'Synthetic evidence only');
        if($kind==='locked') $pdf->SetProtection(['copy'],bin2hex(random_bytes(12)),bin2hex(random_bytes(12)),0);
        $pdf->Output($directory.'/'.$kind.'.pdf','F'); chmod($directory.'/'.$kind.'.pdf',0600);
    }
    file_put_contents(DOL_DATA_ROOT.'/mailintake-native-before',snapshotM());
    file_put_contents($sessionFile,json_encode($state)); chmod($sessionFile,0600);
    echo "PASS native install roles scoped grants and synthetic PDF fixtures\n"; exit;
}
$state=json_decode(file_get_contents($sessionFile),true);
$actor=new User($db); $role=$argv[3]??'manager';
ensureM(isset($state['accounts'][$role]),'fixture role exists'); $actor->fetch($state['accounts'][$role]['id']); $actor->getrights('',1);
if($command==='poll') {
    $id=(int)($state['boxes'][$argv[2]??'']??0);
    try { $result=$mail->poll($actor,$id); }
    catch(RuntimeException $e) { if($e->getMessage()==='MailBusy') $result=['state'=>'busy']; else throw $e; }
    echo json_encode($result)."\n"; exit;
}
if($command==='retry') { $mail->requestRetry($actor,(int)$argv[2]); echo "PASS explicit bounded retry\n"; exit; }
if($command==='retry-denied') { deniedM(fn()=>$mail->requestRetry($actor,(int)$argv[2]),409,'review blocked retry denied'); echo "PASS retry ceiling enforced\n"; exit; }
if($command==='rotate') {
    $id=$state['boxes'][$argv[2]];
    $seed=json_decode(file_get_contents(DOL_DATA_ROOT.'/mailintake-rotate.json'),true);
    $mail->configure($actor,$id,$seed['secret'],null); unlink(DOL_DATA_ROOT.'/mailintake-rotate.json'); echo "PASS credential rotation retains account identity\n"; exit;
}
if($command==='stats') {
    $entries=rowsM('SELECT rowid,fk_account,imap_uid,uidvalidity,state,attempts,retry_at,error_code,remote_deleted,reviewed_by,CASE WHEN content_hash IS NULL THEN 0 ELSE 1 END AS stored FROM '.MAIN_DB_PREFIX.'maxmail_entry ORDER BY rowid');
    $files=rowsM('SELECT rowid,fk_entry,part_no,state,reason,byte_size,CASE WHEN storage_name IS NULL THEN 0 ELSE 1 END AS stored FROM '.MAIN_DB_PREFIX.'maxmail_file ORDER BY rowid');
    echo json_encode(['entries'=>$entries,'files'=>$files,'disk_files'=>count(glob(DOL_DATA_ROOT.'/maxmail/*.bin')),
        'accounts'=>rowsM('SELECT rowid,uidvalidity,state,error_code,connection_failures FROM '.MAIN_DB_PREFIX.'maxmail_account ORDER BY rowid'),
        'events'=>rowsM('SELECT fk_entry,event,code FROM '.MAIN_DB_PREFIX.'maxmail_event ORDER BY rowid'),
        'shared_count'=>(int)rowsM("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."maxmail_entry WHERE message_id='shared@intake.invalid'")[0]->n])."\n"; exit;
}
if($command==='off' || $command==='on') {
    $r=$command==='off'?unActivateModule('modMaxmail'):activateModule('modMaxmail',1,1); ensureM(empty($r['errors']),'module toggle'); echo "PASS actual native module toggle\n"; exit;
}
if($command==='verify' || $command==='publish-fixture') {
    ensureM(snapshotM()===file_get_contents(DOL_DATA_ROOT.'/mailintake-native-before'),'native ERP agenda legal and collector unchanged');
    ensureM((fileperms(DOL_DATA_ROOT.'/maxmail') & 0777)===0700,'private storage directory mode');
    foreach(glob(DOL_DATA_ROOT.'/maxmail/*.bin') as $file) ensureM(!is_link($file) && (fileperms($file) & 0777)===0600,'private immutable file mode');
    ensureM(count(glob(DOL_DATA_ROOT.'/maxmail/*.part'))===0,'no incomplete file after handled tests');
    ensureM(getDolGlobalString('MAIN_DISABLE_ALL_MAILS')==='1' && ini_get('sendmail_path')==='/bin/false','no send unchanged');
    $columns=['entry'=>['subject','sender','message_id'],'account'=>['username','secret']];
    $events=json_encode(rowsM('SELECT event,code FROM '.MAIN_DB_PREFIX.'maxmail_event'));
    foreach($columns as $table=>$names) foreach(rowsM('SELECT '.implode(',',$names).' FROM '.MAIN_DB_PREFIX.'maxmail_'.$table) as $r) foreach($names as $column) {
        $v=$r->$column; if(is_string($v) && strlen($v)>8) ensureM(!str_contains($events,$v),'audit contains no sensitive values');
    }
    $wanted=[['normal',$state['boxes']['alpha'],101],['quarantine',$state['boxes']['beta'],202],['blocked',$state['boxes']['alpha'],403],['retry',$state['boxes']['alpha'],402]];
    $state['targets']=[];
    foreach($wanted as [$name,$account,$uid]) {
        $rows=rowsM('SELECT rowid FROM '.MAIN_DB_PREFIX.'maxmail_entry WHERE entity=1 AND fk_account='.$account.' AND imap_uid='.$uid.' ORDER BY rowid LIMIT 1');
        ensureM((bool)$rows,'browser source exists');
        $state['targets'][$name]=['id'=>(int)$rows[0]->rowid];
        $files=rowsM('SELECT rowid,part_no,storage_name,content_hash FROM '.MAIN_DB_PREFIX.'maxmail_file WHERE entity=1 AND fk_entry='.(int)$rows[0]->rowid.' ORDER BY part_no');
        foreach($files as $f) {
            if((int)$f->part_no===0) $state['targets'][$name]['original']=['id'=>(int)$f->rowid,'hash'=>$f->content_hash,'storage'=>$f->storage_name];
            elseif(!isset($state['targets'][$name]['attachment'])) $state['targets'][$name]['attachment']=['id'=>(int)$f->rowid,'hash'=>$f->content_hash,'storage'=>$f->storage_name];
        }
    }
    file_put_contents($sessionFile,json_encode($state)); chmod($sessionFile,0600);
    echo "PASS intake no-send payload-free audit native preservation and private fixture\n"; exit;
}
throw new RuntimeException('ASSERT unknown command');
