<?php
// Synthetic installed-runtime tests only. GPL-3.0-or-later.
if(PHP_SAPI!=='cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxorders/class/packet.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
function ok($v,$label) { if(!$v) throw new RuntimeException('FAIL '.$label); echo 'PASS '.$label."\n"; }
function rowsO($sql) { global $db; $q=$db->query($sql); ok((bool)$q,'fixture query'); $out=[]; while($r=$db->fetch_object($q)) $out[]=$r; return $out; }
function deniesO($call,$code,$label) { try { $call(); } catch(Throwable $e) { ok($e->getCode()===$code,$label); return; } throw new RuntimeException('FAIL missing denial '.$label); }
function snapshotO() {
 global $db; $out=[];
 foreach(['societe','commande','commandedet','product','stock_mouvement','product_stock','expedition','facture','bank','paiement'] as $table) {
  $q=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid'); ok((bool)$q,'snapshot '.$table); $out[$table]=[];
  while($r=$db->fetch_array($q)) $out[$table][]=$r;
 }
 $q=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 while($r=$db->fetch_array($q)) $out['legal'][]=$r;
 return hash('sha256',json_encode($out));
}
ok($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1' && is_file(DOL_DATA_ROOT.'/install.lock'),'isolated pinned runtime');
$user->fetch('','maxadmin'); ok($user->admin,'native admin');
$mode=$argv[1]??'setup';
if($mode==='toggle') {
 $before=rowsO('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'maxorders_revision')[0]->n;
 ok(empty(unActivateModule('modMaxorders')['errors']),'disable native lifecycle'); $conf->setValues($db);
 deniesO(fn()=>(new MaxOrdersPacket($db))->packets($user),403,'disabled service');
 ok(empty(activateModule('modMaxorders',1,1)['errors']),'re-enable native lifecycle'); $conf->setValues($db);
 ok(rowsO('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'maxorders_revision')[0]->n===$before,'toggle preserves revisions'); exit;
}
if($mode==='off' || $mode==='on') { $result=$mode==='off'?unActivateModule('modMaxorders'):activateModule('modMaxorders',1,1); ok(empty($result['errors']),'browser toggle'); exit; }
if($mode==='verify') { ok(snapshotO()===file_get_contents(DOL_DATA_ROOT.'/orders-before'),'HTTP preserves native ERP and legal identity'); echo "PASS Orders final verification\n"; exit; }
ok(empty(activateModule('modMaxorders',1,1)['errors']),'actual module installation'); $conf->setValues($db); $user->getrights('',1);
ok(isModEnabled('maxorders'),'module enabled');
ok(count(rowsO("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='maxorders'"))===3,'three native permissions');
ok(count(rowsO("SELECT rowid FROM ".MAIN_DB_PREFIX."menu WHERE module='maxorders'"))===1,'native unified menu');
$c=new Societe($db); $c->name='Orders Synthetic Customer'; $c->client=1; $c->code_client='ORDERS-DEMO'; ok($c->create($user)>0,'native synthetic customer');
$other=new Societe($db); $other->name='Orders Other Customer'; $other->client=1; $other->code_client='ORDERS-OTHER'; ok($other->create($user)>0,'native other customer');
$native=[];
foreach([[$c->id,'PACK-100'],[$other->id,'PACK-100'],[$c->id,'WRONG-REF']] as [$soc,$ref]) {
 $o=new Commande($db); $o->socid=$soc; $o->date=dol_now(); $o->ref_client=$ref; ok($o->create($user)>0,'native draft fixture'); $native[]=$o->id;
}
$accounts=[]; $actors=[];
foreach(['denied'=>0,'reader'=>61014001,'intake'=>61014002,'reviewer'=>61014003,'no-native'=>61014003] as $role=>$permission) {
 $a=new User($db); $a->login='orders-'.$role; $a->lastname='Synthetic '.$role; $pass=bin2hex(random_bytes(24)); $a->pass=$pass;
 ok($a->create($user)>0,'create role '.$role); $a->clearrights();
 if($permission) ok($a->addrights($permission)>0,'grant MAX role');
 if($role!=='no-native') foreach([['societe','lire',null],['societe','client','voir'],['commande','lire',null]] as [$module,$perms,$sub]) {
  $r=rowsO("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$module."' AND perms='".$perms."'".($sub?" AND subperms='".$sub."'":" AND (subperms IS NULL OR subperms='')"));
  ok(count($r)===1,'native right lookup'); ok($a->addrights($r[0]->id)>0,'grant native read scope');
 }
 $a->getrights('',1); $actors[$role]=$a; $accounts[$role]=['login'=>$a->login,'password'=>$pass];
}
$before=snapshotO(); $service=new MaxOrdersPacket($db);
// Missing keys and literal user text must never share a sentinel identity.
$presence=$service->ingest($actors['intake'],$c->id,'PRESENCE-ONLY','PO','D-PRESENCE',1,'{"quantity":"1"}');
$collisionPresence=$service->ingest($actors['intake'],$c->id,'PRESENCE-ONLY','PO','D-PRESENCE',1,'{"quantity":"1","sku":"[missing]"}');
$pd=$service->detail($user,$presence['packet']);
ok($collisionPresence['status']==='collision' && count($pd['conflict'])===1 && $pd['conflict'][0]->old_value!==$pd['conflict'][0]->new_value,'literal text cannot hide missing-field collision');
$emptyPresence=$service->ingest($actors['intake'],$c->id,'EMPTY-PRESENCE','PO','D-EMPTY',1,'{"quantity":"1"}');
$service->ingest($actors['intake'],$c->id,'EMPTY-PRESENCE','PO','D-EMPTY',1,'{"quantity":"1","sku":""}');
ok(count($service->detail($user,$emptyPresence['packet'])['conflict'])===1,'absent and explicit empty remain distinct revision evidence');
$ingest=fn($kind,$doc,$rev,$facts)=>$service->ingest($actors['intake'],$c->id,'PACK-100',$kind,$doc,$rev,json_encode((object)$facts));
$r=$ingest('LL','LL-1',1,['quantity'=>'98','sku'=>'DEMO']); $id=$r['packet'];
ok($service->packet($user,$id)->status==='provisional','LL first provisional');
$r=$ingest('PO','PO-1',2,['quantity'=>'100','sku'=>'DEMO','packaging'=>'CASE']); ok($r['packet']===$id,'PO later same packet');
$old=$ingest('PO','PO-1',1,['quantity'=>'10','packaging'=>'OLD']); ok($old['status']==='archived','late old revision archived');
$d=$service->detail($user,$id); $n=count($d['revision']); $audit=count($d['audit']);
$replay=$ingest('PO','PO-1',2,['packaging'=>'CASE','sku'=>'DEMO','quantity'=>'100.000']);
ok($replay['status']==='duplicate' && count($service->detail($user,$id)['revision'])===$n && count($service->detail($user,$id)['audit'])===$audit,'canonical retry idempotent');
$collision=$ingest('PO','PO-1',2,['quantity'=>'999','sku'=>'DEMO','packaging'=>'CASE']); ok($collision['status']==='collision','same version collision preserved');
$collision=$ingest('PO','PO-1',2,['quantity'=>'999','sku'=>'DEMO','packaging'=>'CASE']);
ok(count($service->detail($user,$id)['conflict'])===1,'collision replay deduplicated');
$service->lockField($actors['reviewer'],$id,'quantity','100');
$ingest('PO','PO-1',3,['quantity'=>'120','sku'=>'','packaging'=>'']);
$ingest('PC','PC-1',1,['quantity'=>'99']);
$d=$service->detail($user,$id); $quantities=[]; foreach($d['current'] as $v) {
 if($v->field_name==='quantity') $quantities[$v->kind]=$v->field_value;
 if($v->kind==='PO' && $v->field_name==='packaging') ok($v->field_value==='CASE' && (int)$v->revision_no===2,'empty never erases previous provenance');
}
ok($quantities===['LL'=>'98','PO'=>'120','PC'=>'99'],'separate source quantities no priority');
ok($d['lock'][0]->field_value==='100','incoming source cannot overwrite manual lock');
$manual=array_values(array_filter($d['conflict'],fn($v)=>$v->reason==='manual_lock'));
ok(count($manual)===2,'PO and PC lock conflicts visible');
$service->resolve($actors['reviewer'],$id,$manual[0]->rowid,'keep_manual');
$service->resolve($actors['reviewer'],$id,$manual[0]->rowid,'keep_manual');
$service->resolve($actors['reviewer'],$id,$manual[1]->rowid,'accept_source');
$d=$service->detail($user,$id); ok($d['lock'][0]->field_value==='99','explicit human accepts source');
$resolved=array_values(array_filter($d['conflict'],fn($v)=>$v->status==='resolved'));
ok(count($resolved)===2 && (int)$resolved[0]->fk_user_resolver===(int)$actors['reviewer']->id,'resolution actor audited');
$unknown=$ingest('UNKNOWN','UNREADABLE',1,[]); ok($unknown['status']==='needs_review','unknown type no guessed classification');
$empty=$ingest('PO','PO-EMPTY',1,['quantity'=>'']); ok($empty['status']==='needs_review','empty known type needs review');
foreach(['reader','denied','reviewer','no-native'] as $role) deniesO(fn()=>$service->ingest($actors[$role],$c->id,'PACK-X','PO','D',1,'{}'),403,'intake role deny '.$role);
foreach(['reader','denied','intake','no-native'] as $role) deniesO(fn()=>$service->lockField($actors[$role],$id,'quantity','1'),403,'review role deny '.$role);
deniesO(fn()=>$service->packets($actors['no-native']),403,'native permission missing');
$external=clone $user; $external->socid=$c->id; ok(!MaxOrdersPacket::can($external,'read'),'external denied despite admin');
foreach(['[]','{"quantity":100}','{"quantity":"-1"}','{"quantity":"1e3"}','{"sku":"<script>"}','{"path":"../../etc/passwd"}',str_repeat('a',4097)] as $bad) deniesO(fn()=>MaxOrdersPacket::facts($bad),400,'typed metadata reject');
deniesO(fn()=>$service->link($actors['reviewer'],$id,$native[0],false),400,'explicit native link confirmation');
foreach([$native[1],$native[2],999999] as $bad) deniesO(fn()=>$service->link($actors['reviewer'],$id,$bad,true),400,'native order matching');
$service->link($actors['reviewer'],$id,$native[0],true); $service->link($actors['reviewer'],$id,$native[0],true);
ok((int)$service->packet($user,$id)->fk_order===$native[0],'metadata native order link only');
$originalEntity=$conf->entity; $conf->entity=2; $isolated=new MaxOrdersPacket($db);
deniesO(fn()=>$isolated->detail($user,$id),404,'entity isolation'); $conf->entity=$originalEntity;
ok(snapshotO()===$before,'intake preserves native legal finance products stock orders');
file_put_contents(DOL_DATA_ROOT.'/orders-before',$before);
file_put_contents(DOL_DATA_ROOT.'/orders-accounts.json',json_encode(['accounts'=>$accounts,'packet'=>$id,'customer'=>$c->id,'order'=>$native[0]])); chmod(DOL_DATA_ROOT.'/orders-accounts.json',0600);
echo "PASS Orders installed vertical slice\n";
