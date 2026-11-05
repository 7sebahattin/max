<?php
// Issue #20: one disposable DB, existing module/fixture APIs read-only. GPL-3.0-or-later.
if(PHP_SAPI!=='cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
function needE($value,string $label): void { if(!$value) throw new RuntimeException('ASSERT '.$label); }
set_exception_handler(function($e) {
    $data=['type'=>get_class($e),'code'=>$e->getCode(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'trace'=>[]];
    if(preg_match('/^ASSERT [A-Za-z0-9 _-]{1,140}$/D',$e->getMessage())) $data['assertion']=$e->getMessage();
    foreach($e->getTrace() as $frame) $data['trace'][]=['file'=>basename($frame['file']??''),'line'=>$frame['line']??0,'function'=>$frame['function']??''];
    fwrite(STDERR,'COENABLED_NATIVE_ERROR '.json_encode($data)."\n"); exit(1);
});
function rowsE(string $sql): array { global $db; $q=$db->query($sql); needE((bool)$q,'read query'); $out=[]; while($r=$db->fetch_array($q)) $out[]=$r; return $out; }
function snapshotE(array $tables,string $legalPosition='last'): string {
    $out=[];
    if($legalPosition==='first') $out['legal']=rowsE("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
    foreach($tables as $table) $out[$table]=rowsE('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY '.($table==='actioncomm'?'id':'rowid'));
    if($legalPosition==='last') $out['legal']=rowsE("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
    return hash('sha256',json_encode($out));
}
function nativeE(): string { $hash=snapshotE(['societe','socpeople','product','product_stock','product_batch','product_lot','stock_mouvement','commande','commandedet','expedition','expeditiondet','facture','facturedet','bank_account','bank','paiement','paiement_facture','emailcollector_emailcollector','actioncomm']); return hash('sha256',$hash.json_encode(rowsE("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name IN ('MAIN_APPLICATION_TITLE','MAIN_HTML_TITLE','MAIN_FAVICON_URL') ORDER BY name,entity"))); }
function moduleStateE(): string {
    return snapshotE(['maxorders_packet','maxorders_revision','maxorders_fact','maxorders_current','maxorders_lock','maxorders_conflict','maxorders_audit','maxcustoms_declaration','maxcustoms_private','maxcustoms_shipment','maxcustoms_history','maxcustoms_evidence'],'none');
}
function activationE(): array {
    global $conf,$db;
    $expect=['maxcore'=>[610120,3,3],'maxmobile'=>[610130,0,0],'maxorders'=>[610140,3,1],'maxcustoms'=>[610160,9,1],'maxmail'=>[610150,5,1]];
    $result=[]; $ids=[]; $rights=[]; $menus=[];
    foreach($expect as $name=>[$id,$permissionCount,$menuCount]) {
        needE(isModEnabled($name),'all five modules enabled');
        $class='mod'.ucfirst($name); require_once DOL_DOCUMENT_ROOT.'/custom/'.$name.'/core/modules/'.$class.'.class.php'; $module=new $class($db);
        needE((int)$module->numero===$id && !in_array($id,$ids,true),'unique module id'); $ids[]=$id;
        $r=rowsE("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$name."' ORDER BY id"); needE(count($r)===$permissionCount,'exact native permission count');
        foreach($r as $row) {needE(!in_array($row['id'],$rights,true),'cross module rights uniqueness'); $rights[]=$row['id'];}
        $m=rowsE("SELECT rowid,type,mainmenu,url FROM ".MAIN_DB_PREFIX."menu WHERE module='".$name."' ORDER BY rowid"); needE(count($m)===$menuCount,'exact native menu count');
        foreach($m as $row) { $key=$row['type'].'|'.$row['mainmenu'].'|'.$row['url']; needE(!in_array($key,$menus,true),'cross module menu uniqueness'); $menus[]=$key; }
        $result[$name]=['module_id'=>$id,'rights'=>count($r),'menus'=>count($m),'hooks'=>$module->module_parts['hooks']??[]];
    }
    needE(in_array('all',$result['maxcore']['hooks'],true) && in_array('all',$result['maxmobile']['hooks'],true),'both shared hooks registered');
    return $result;
}
needE($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1' && is_file(DOL_DATA_ROOT.'/install.lock'),'pinned locked disposable install');
$user->fetch('','maxadmin'); needE((bool)$user->admin,'native fixture admin'); $user->getrights('',1);
$mode=$argv[1]??''; $root=DOL_DATA_ROOT;
if($mode==='bootstrap') {
    foreach(['Maxcore','Maxmobile','Maxorders','Maxcustoms','Maxmail'] as $name) needE(empty(activateModule('mod'.$name,1,1)['errors']),'native activation');
    $conf->setValues($db); $user->getrights('',1); activationE();
    // Compatibility account names are required by unchanged Core browser helpers.
    $accounts=[];
    foreach(['test'=>null,'denied'=>0,'reader'=>61012001,'editor'=>61012002,'manager'=>61012003] as $role=>$right) {
        $p=new User($db); $p->login='maxcore-'.$role; $p->lastname='Synthetic coexistence role'; $pass=bin2hex(random_bytes(24)); $p->pass=$pass;
        if($role==='test') $p->admin=1;
        needE($p->create($user)>0,'native Core fixture user');
        if($role!=='test') { $p->clearrights(); if($right) needE($p->addrights($right)>0,'native Core fixture grant'); $accounts[$role]=['login'=>$p->login,'password'=>$pass]; }
        else {file_put_contents($root.'/coenabled-core-admin',$pass); chmod($root.'/coenabled-core-admin',0600);}
    }
    file_put_contents($root.'/maxcore-accounts.json',json_encode($accounts)); chmod($root.'/maxcore-accounts.json',0600);
    foreach(['MAIN_INFO_SOCIETE_IDPROF1'=>'SYNTHETIC-TAX-COENABLED','MAIN_INFO_SOCIETE_ADDRESS'=>'Synthetic address only'] as $key=>$value) needE(dolibarr_set_const($db,$key,$value,'chaine',0,'',1)>0,'synthetic legal fixture');
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
    $bank=new Account($db); needE($bank->fetch((int)rowsE('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank_account ORDER BY rowid LIMIT 1')[0]['rowid'])>0,'native synthetic bank fixture');
    $bank->iban='TEST-IBAN-COENABLED'; needE($bank->update_bban($user)>0,'native synthetic nonbank IBAN fixture');
    require_once DOL_DOCUMENT_ROOT.'/custom/maxcore/lib/maxcore.lib.php';
    maxCoreSave(['MAXCORE_DISPLAY_NAME'=>'Coenabled Initial','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'logo'],$user);
    $image=imagecreatetruecolor(32,32); imagefill($image,0,0,imagecolorallocate($image,30,90,130)); imagejpeg($image,$root.'/coenabled-logo.jpg',85); imagedestroy($image); chmod($root.'/coenabled-logo.jpg',0600);
    echo "PASS all five modules installed and Core roles prepared\n"; exit;
}
if($mode==='freeze') {
    needE(!is_file($root.'/coenabled-native-before'),'immutable baseline created once');
    activationE();
    foreach(['societe','product','product_stock','product_lot','commande','expedition','facture','bank_account','bank'] as $t) needE(count(rowsE('SELECT rowid FROM '.MAIN_DB_PREFIX.$t))>0,'populated commercial fixture');
    needE(count(rowsE('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE fk_statut<>0'))===0,'all invoices draft only');
    needE(count(rowsE("SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account WHERE iban_prefix='TEST-IBAN-COENABLED'"))===1,'populated fictional IBAN protected by baseline');
    // Every commercial fixture is now complete. Rebase original helper hashes ONCE
    // to this populated DB, before any acceptance/UI/intake action. No source edits.
    $contracts=[
        'orders-before'=>snapshotE(['societe','commande','commandedet','product','stock_mouvement','product_stock','expedition','facture','bank','paiement']),
        'customs-native-before'=>snapshotE(['societe','commande','commandedet','expedition','expeditiondet','product','product_stock','stock_mouvement','facture','facturedet','bank_account','bank','paiement']),
        'mailintake-native-before'=>snapshotE(['societe','commande','commandedet','expedition','expeditiondet','product','product_stock','stock_mouvement','facture','facturedet','bank_account','bank','paiement','emailcollector_emailcollector','actioncomm']),
        'coenabled-native-before'=>nativeE()
    ];
    foreach($contracts as $name=>$hash) {file_put_contents($root.'/'.$name,$hash); chmod($root.'/'.$name,0400);}
    needE(str_starts_with(rowsE('SELECT VERSION() AS version')[0]['version'],'11.4.13'),'pinned MariaDB version');
    $identity=['version'=>DOL_VERSION,'php'=>PHP_VERSION,'database'=>'max_demo','host'=>'db','database_server'=>rowsE('SELECT VERSION() AS version,@@hostname AS host')[0], 'entity'=>(int)$conf->entity,'installation_fingerprint'=>hash('sha256',$conf->file->instance_unique_id),'baseline'=>nativeE(),'modules'=>activationE()];
    file_put_contents($root.'/coenabled-identity.json',json_encode($identity)); echo "PASS immutable populated single DB baseline frozen\n"; exit;
}
needE(nativeE()===file_get_contents($root.'/coenabled-native-before'),'immutable native legal bank stock order invoice finance snapshot');
if($mode==='chain') {
    require_once DOL_DOCUMENT_ROOT.'/custom/maxorders/class/packet.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/maxcustoms/class/declaration.class.php';
    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
    $f=json_decode(file_get_contents($root.'/customs-session.json'),true); $o=new Commande($db); needE($o->fetch($f['order'])>0,'native chain order');
    $o->loadExpeditions(1); needE(array_sum($o->expeditions)>0 && array_sum($o->expeditions)<array_sum(array_map(fn($l)=>(float)$l->qty,$o->lines)),'native partial shipment remains partial');
    $orders=new MaxOrdersPacket($db); $ll=$orders->ingest($user,$f['customer'],$f['reference'],'LL','CO-LL',1,'{"quantity":"5"}');
    $po=$orders->ingest($user,$f['customer'],$f['reference'],'PO','CO-PO',2,'{"quantity":"10"}'); needE($ll['packet']===$po['packet'],'LL then PO one packet');
    $orders->link($user,$ll['packet'],$o->id,true); $before=$orders->detail($user,$ll['packet']);
    $replay=$orders->ingest($user,$f['customer'],$f['reference'],'PO','CO-PO',2,'{"quantity":"10.000"}'); $orders->link($user,$ll['packet'],$o->id,true);
    needE($replay['status']==='duplicate' && $orders->detail($user,$ll['packet'])==$before,'packet replay link has no duplicate side effect');
    $customs=new MaxCustomsDeclaration($db); $id=$customs->create($user,'COENABLED-CHAIN',['office'=>'Synthetic','mode'=>'road','origin'=>'Demo','destination'=>'Demo','goods_summary'=>'Synthetic chain']);
    $d=$customs->projection($user,$id); $customs->matchNative($user,$id,$d['version'],$f['customer'],$o->id,$f['shipments'],$f['reference'],true); $d=$customs->projection($user,$id);
    needE((int)$orders->packet($user,$ll['packet'])->fk_order===(int)$o->id && $d['order_id']===(int)$o->id && $d['customer_id']===(int)$f['customer'] && count($d['shipments'])===2 && $d['status']==='draft','customer order partial shipment packet customs chain');
    file_put_contents($root.'/coenabled-chain.json',json_encode(['customer'=>$f['customer'],'order'=>$o->id,'shipments'=>$d['shipments'],'packet'=>$ll['packet'],'declaration'=>$id,'status'=>'draft']));
    needE(nativeE()===file_get_contents($root.'/coenabled-native-before'),'chain only writes MAX records'); echo "PASS native chain linked without ERP writes or duplicate packet\n"; exit;
}
if($mode==='mail-before') { needE(!is_file($root.'/coenabled-mail-before'),'mail isolation checkpoint once'); file_put_contents($root.'/coenabled-mail-before',moduleStateE()); chmod($root.'/coenabled-mail-before',0400); }
if($mode==='mail-after' || $mode==='final') {
    needE(moduleStateE()===file_get_contents($root.'/coenabled-mail-before'),'Mail creates no implicit Orders or Customs writes');
    foreach(['maxmail','maxcustoms'] as $dir) { $p=realpath($root.'/'.$dir); needE($p && !str_starts_with($p,realpath(DOL_DOCUMENT_ROOT).'/') && !is_link($root.'/'.$dir),'distinct private storage outside webroot'); needE((fileperms($p)&0777)===0700,'private directory permissions'); }
    needE(realpath($root.'/maxmail')!==realpath($root.'/maxcustoms'),'private storage separation');
}
$report=['modules'=>activationE(),'baseline_unchanged'=>true,'no_send'=>getDolGlobalString('MAIN_DISABLE_ALL_MAILS')==='1' && ini_get('sendmail_path')==='/bin/false']; needE($report['no_send'],'no SMTP send boundary');
if($mode==='final') file_put_contents($root.'/coenabled-final.json',json_encode($report));
echo 'PASS coexistence checkpoint '.$mode."\n";
