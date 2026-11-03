<?php
// Disposable native installation only. No customer or regulatory data. GPL-3.0-or-later.
if(PHP_SAPI!=='cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxcustoms/class/declaration.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
set_exception_handler(function($e) {
    $message=preg_match('/^ASSERT [A-Za-z0-9 _-]{1,120}$/D',$e->getMessage())?$e->getMessage():'customs runtime failure';
    fwrite(STDERR,$message.' '.basename($e->getFile()).':'.$e->getLine()."\n"); exit(1);
});
function verifyC($condition,string $label): void { if(!$condition) throw new RuntimeException('ASSERT '.$label); echo 'PASS '.$label."\n"; }
function queryC(string $sql): array { global $db; $q=$db->query($sql); verifyC((bool)$q,'fixture query'); $rows=[]; while($r=$db->fetch_object($q)) $rows[]=$r; return $rows; }
function denyC(callable $call,int $code,string $label): void { try {$call();} catch(Throwable $e) { verifyC($e->getCode()===$code,$label); return; } throw new RuntimeException('ASSERT '.$label); }
function snapshotC(): string {
    global $db; $data=[];
    foreach(['societe','commande','commandedet','expedition','expeditiondet','product','product_stock','stock_mouvement','facture','facturedet','bank_account','bank','paiement'] as $table) {
        $q=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid'); verifyC((bool)$q,'native snapshot');
        $data[$table]=[]; while($r=$db->fetch_array($q)) $data[$table][]=$r;
    }
    $q=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
    while($r=$db->fetch_array($q)) $data['legal'][]=$r;
    return hash('sha256',json_encode($data));
}
verifyC($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1' && is_file(DOL_DATA_ROOT.'/install.lock'),'pinned disposable installation');
$user->fetch('','maxadmin'); verifyC($user->admin,'native admin');
$service=new MaxCustomsDeclaration($db); $mode=$argv[1]??'';
if($mode==='off' || $mode==='on') { $r=$mode==='off'?unActivateModule('modMaxcustoms'):activateModule('modMaxcustoms',1,1); verifyC(empty($r['errors']),'module toggle'); exit; }
if($mode==='verify') {
    verifyC(snapshotC()===file_get_contents(DOL_DATA_ROOT.'/customs-native-before'),'HTTP preserved native records and issuer');
    verifyC(!queryC("SELECT rowid FROM ".MAIN_DB_PREFIX."maxcustoms_declaration WHERE ref='CSRF-FORBIDDEN'"),'invalid CSRF created no draft');
    verifyC(!queryC("SELECT rowid FROM ".MAIN_DB_PREFIX."maxcustoms_declaration WHERE public_data LIKE '%FORBIDDEN-UPDATE%'"),'denied HTTP mutation absent');
    echo "PASS final customs native preservation\n"; exit;
}
verifyC($mode==='setup','setup command');
verifyC(empty(activateModule('modMaxcustoms',1,1)['errors']),'native module activation');
$conf->setValues($db); $user->getrights('',1);
verifyC(count(queryC("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='maxcustoms'"))===9,'nine native module rights');
$order=new Commande($db); verifyC($order->fetch((int)queryC('SELECT rowid FROM '.MAIN_DB_PREFIX.'commande ORDER BY rowid LIMIT 1')[0]->rowid)>0,'native sales order');
$shipment1=(int)queryC('SELECT rowid FROM '.MAIN_DB_PREFIX.'expedition ORDER BY rowid LIMIT 1')[0]->rowid;
$warehouse=(int)queryC("SELECT rowid FROM ".MAIN_DB_PREFIX."entrepot WHERE ref='P2-IN'")[0]->rowid;
$second=new Expedition($db); $second->socid=$order->socid; $second->origin='commande'; $second->origin_id=$order->id; $second->date_shipping=dol_now();
verifyC($second->addline($warehouse,$order->lines[0]->id,1)>0,'second partial shipment line');
verifyC($second->create($user)>0 && $second->valid($user)>0,'native second partial shipment fixture');
$shipment2=(int)$second->id;
$order->fetch($order->id); $customer=(int)$order->socid; $reference=$order->ref_customer?:$order->ref;
$accounts=[]; $actors=[];
$roles=['denied'=>[],'viewer'=>['view'],'editor'=>['view','create','edit'],'matcher'=>['view','match'],
    'reviewer'=>['view','status'],'contact'=>['view','contact'],'analysis'=>['view','analysis'],
    'source'=>['view','edit','status','source'],'manager'=>['admin'],'no-native'=>['admin']];
$ids=['view'=>61016001,'create'=>61016002,'edit'=>61016003,'status'=>61016004,'match'=>61016005,'contact'=>61016006,'analysis'=>61016007,'source'=>61016008,'admin'=>61016009];
foreach($roles as $role=>$permissions) {
    $person=new User($db); $person->login='customs-'.$role; $person->lastname='Synthetic role'; $password=bin2hex(random_bytes(24)); $person->pass=$password;
    verifyC($person->create($user)>0,'native role creation'); $person->clearrights();
    foreach($permissions as $permission) verifyC($person->addrights($ids[$permission])>0,'MAX role grant');
    if($role!=='no-native') foreach([['societe','lire',''],['societe','client','voir'],['commande','lire',''],['expedition','lire','']] as [$module,$perms,$sub]) {
        $rows=queryC("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$module."' AND perms='".$perms."'".($sub!==''?" AND subperms='".$sub."'":" AND (subperms IS NULL OR subperms='')"));
        verifyC(count($rows)===1 && $person->addrights($rows[0]->id)>0,'native read scope grant');
    }
    $person->getrights('',1); $actors[$role]=$person; $accounts[$role]=['login'=>$person->login,'password'=>$password];
}
$before=snapshotC();
$public=['office'=>'Synthetic office','mode'=>'road','origin'=>'ORIGIN-DEMO','destination'=>'DESTINATION-DEMO','departure_date'=>'2026-11-03','arrival_date'=>'2026-11-04','goods_summary'=>'Synthetic goods only','references'=>'=1+1'];
$private=['contact'=>'CONTACT_RESTRICTED_SENTINEL','analysis'=>'ANALYSIS_RESTRICTED_SENTINEL','source'=>'SOURCE_RESTRICTED_SENTINEL'];
$id=$service->create($user,'CUSTOMS-DEMO',$public,$private);
$draft=$service->projection($user,$id); verifyC($draft['status']==='draft','local draft creation');
$read=$service->projection($actors['viewer'],$id);
verifyC(!isset($read['restricted']) && !isset($read['evidence']) && !str_contains(json_encode($read),'RESTRICTED_SENTINEL'),'general projection omits restricted sections');
foreach(['contact','analysis','source'] as $role) {
    $p=$service->projection($actors[$role],$id);
    verifyC(array_keys($p['restricted'])===[$role] && $p['restricted'][$role]===$private[$role],'granular field projection');
}
$csv=$service->export($actors['viewer'],$id);
verifyC(!str_contains($csv,'RESTRICTED_SENTINEL') && !str_contains($csv,'restricted_edit') && str_contains($csv,"'=1+1"),'CSV redaction and formula guard');
$old=$service->projection($user,$id);
foreach(['contact','analysis','source'] as $key) denyC(fn()=>$service->edit($actors['editor'],$id,$old['version'],[],[$key=>'']),403,'restricted clearing denied');
verifyC($service->projection($user,$id)===$old,'denied updates preserve private state');

denyC(fn()=>$service->create($actors['viewer'],'NO-CREATE',$public),403,'view does not grant create');
denyC(fn()=>$service->listing($actors['denied']),403,'no MAX permission denied');
denyC(fn()=>$service->listing($actors['no-native']),403,'native scope required');
$external=clone $user; $external->socid=$customer; verifyC(!MaxCustomsDeclaration::permits($external,'view'),'external admin rejected');

denyC(fn()=>$service->matchNative($actors['matcher'],$id,$old['version'],$customer,$order->id,[],$reference,false),400,'matching requires human confirmation');
denyC(fn()=>$service->matchNative($actors['matcher'],$id,$old['version'],$customer,$order->id,[],'WRONG-REF',true),400,'native reference checked');
denyC(fn()=>$service->matchNative($actors['matcher'],$id,$old['version'],$customer+999999,$order->id,[],$reference,true),400,'native customer checked');
denyC(fn()=>$service->matchNative($actors['matcher'],$id,$old['version'],$customer,$order->id,[999999],$reference,true),400,'native shipment checked');
$service->matchNative($actors['matcher'],$id,$old['version'],$customer,$order->id,[],$reference,true);
$p=$service->projection($user,$id); verifyC($p['shipments']===[],'zero shipment link');
$service->matchNative($actors['matcher'],$id,$p['version'],$customer,$order->id,[$shipment1],$reference,true);
$p=$service->projection($user,$id); verifyC($p['shipments']===[$shipment1],'first partial shipment link');
$service->matchNative($actors['matcher'],$id,$p['version'],$customer,$order->id,[$shipment1,$shipment2,$shipment1],$reference,true);
$p=$service->projection($user,$id); verifyC(count($p['shipments'])===2,'many native shipment links deduplicated');
$evidence=$service->addEvidence($actors['source'],$id,$p['version'],'unreadable.txt',"\xff\0opaque",'application/octet-stream');
$p=$service->projection($user,$id); verifyC($p['evidence'][0]->status==='needs_review','unreadable text retained for review');
$ev2=$service->addEvidence($actors['source'],$id,$p['version'],'unreadable.txt',"\xff\0opaque",'application/octet-stream');
verifyC($ev2===$evidence && count($service->projection($user,$id)['evidence'])===1,'evidence retry creates no duplicate');
denyC(fn()=>$service->download($actors['viewer'],$id,$evidence),403,'private evidence requires raw source permission');
$file=$service->download($actors['source'],$id,$evidence); verifyC(hash_file('sha256',$file['path'])===hash('sha256',"\xff\0opaque"),'authorized evidence integrity');
denyC(fn()=>$service->addEvidence($actors['source'],$id,$p['version'],'../../escape.txt','test','text/plain'),400,'evidence path rejected');
denyC(fn()=>$service->addEvidence($actors['source'],$id,$p['version'],'document.pdf','%PDF-broken','application/pdf'),400,'broad PDF upload not implemented');
denyC(fn()=>$service->transition($actors['reviewer'],$id,$p['version'],'reviewed',true),409,'unreadable evidence blocks review');
$service->acknowledgeEvidence($actors['source'],$id,$p['version'],$evidence,true);
$p=$service->projection($user,$id); $service->transition($actors['reviewer'],$id,$p['version'],'reviewed',true);
$p=$service->projection($user,$id); denyC(fn()=>$service->edit($actors['editor'],$id,$p['version'],['office'=>'NO']),409,'reviewed record immutable');
denyC(fn()=>$service->transition($actors['reviewer'],$id,$p['version'],'draft',true),403,'correction requires administrator');
$service->transition($actors['manager'],$id,$p['version'],'draft',true);
$p=$service->projection($user,$id); $service->edit($actors['editor'],$id,$p['version'],['goods_summary'=>'Edited synthetic goods']);
denyC(fn()=>$service->edit($actors['editor'],$id,$p['version'],['office'=>'STALE']),409,'stale optimistic edit denied');
$p=$service->projection($user,$id); $service->transition($actors['reviewer'],$id,$p['version'],'reviewed',true);
$p=$service->projection($user,$id); $service->transition($actors['reviewer'],$id,$p['version'],'approved',true);
$p=$service->projection($user,$id); $service->transition($actors['reviewer'],$id,$p['version'],'closed',true);
$p=$service->projection($user,$id); denyC(fn()=>$service->transition($actors['manager'],$id,$p['version'],'draft',true),409,'closed draft terminal');
verifyC(count(array_filter($p['history'],fn($h)=>$h['event']==='status_change'))===4,'append only status history');
verifyC(count(array_filter($p['history'],fn($h)=>$h['actor']===(int)$actors['reviewer']->id && $h['at']!==''))>=4,'status actor and timestamps');
$browserPublic=$public; $browserPublic['goods_summary']='Synthetic <script>window.customsLeak=1</script>';
$browserId=$service->create($user,'CUSTOMS-BROWSER',$browserPublic,$private);
$browserP=$service->projection($user,$browserId);
$browserEvidence=$service->addEvidence($actors['source'],$browserId,$browserP['version'],'private.txt','PRIVATE_EVIDENCE_SENTINEL','text/plain');
$native=$conf->entity; $conf->entity=2;
denyC(fn()=>(new MaxCustomsDeclaration($db))->projection($user,$id),404,'entity boundary'); $conf->entity=$native;
verifyC(snapshotC()===$before,'customs operations preserve native ERP and issuer');
$counts=[]; foreach(['declaration','private','shipment','history','evidence'] as $table) $counts[$table]=queryC('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'maxcustoms_'.$table)[0]->n;
verifyC(empty(unActivateModule('modMaxcustoms')['errors']),'native disable'); $conf->setValues($db);
denyC(fn()=>$service->listing($user),403,'disabled service denied');
verifyC(empty(activateModule('modMaxcustoms',1,1)['errors']),'native re-enable'); $conf->setValues($db);
foreach($counts as $table=>$n) verifyC(queryC('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'maxcustoms_'.$table)[0]->n===$n,'disable preserves module data');
verifyC(snapshotC()===$before,'toggle preserves native ERP');
file_put_contents(DOL_DATA_ROOT.'/customs-native-before',$before);
file_put_contents(DOL_DATA_ROOT.'/customs-session.json',json_encode(['accounts'=>$accounts,'id'=>$browserId,'closed_id'=>$id,'evidence'=>$browserEvidence,'customer'=>$customer,'order'=>$order->id,'reference'=>$reference,'shipments'=>[$shipment1,$shipment2]])); chmod(DOL_DATA_ROOT.'/customs-session.json',0600);
echo "PASS customs installed lifecycle privacy workflow and native links\n";
