<?php
// Installed native fixture/transaction/privacy checks. Never execute through HTTP.
if(PHP_SAPI!=='cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxorders/class/document.class.php';
require __DIR__.'/fixtures.php';
$checks=[];
function intakeCheck($value,$label) { global $checks; if(!$value) throw new RuntimeException('FAIL '.$label); $checks[]=['check'=>$label,'status'=>'PASS']; echo 'PASS '.$label."\n"; }
function intakeRows($sql) { global $db; $r=$db->query($sql); if(!$r) throw new RuntimeException('FAIL fixture SQL'); $out=[]; while($v=$db->fetch_object($r)) $out[]=$v; return $out; }
function intakeDenial($callback,$code,$label) { try { $callback(); } catch(Throwable $e) { intakeCheck($e->getCode()===$code,$label); return; } throw new RuntimeException('FAIL missing denial '.$label); }
function intakeSnapshot() {
 global $db; $all=[];
 foreach(['societe','commande','commandedet','product','product_stock','product_lot','product_batch','stock_mouvement','expedition','expeditiondet','facture','facturedet','bank','bank_account','paiement','paiement_facture','payment_various'] as $table) {
  $all[$table]=intakeRows('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid');
 }
 $all['legal']=intakeRows("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 return hash('sha256',json_encode($all));
}
intakeCheck(DOL_VERSION==='24.0.1' && $dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && is_file(DOL_DATA_ROOT.'/install.lock'),'isolated pinned installed Dolibarr');
$user->fetch('','maxadmin');$user->getrights('',1);
$documents=new MaxOrdersDocument($db);$packets=new MaxOrdersPacket($db);
$mode=$argv[1]??'setup';
if($mode==='concurrent') {
 $bytes=file_get_contents(DOL_DATA_ROOT.'/orders-intake-inputs/concurrent.xlsx');
 $documents->receive($user,'concurrent.xlsx',$bytes); exit;
}
if($mode==='verify') {
 intakeCheck(intakeSnapshot()===file_get_contents(DOL_DATA_ROOT.'/orders-intake-before'),'customers stock orders shipments invoices bank legal unchanged after HTTP');
 $bytes=file_get_contents(DOL_DATA_ROOT.'/orders-intake-inputs/concurrent.xlsx');
 $rows=intakeRows("SELECT rowid FROM ".MAIN_DB_PREFIX."maxorders_upload WHERE entity=1 AND content_hash='".hash('sha256',$bytes)."'");
 intakeCheck(count($rows)===1,'concurrent uploads have one ledger row');
 $f=$documents->download($user,$rows[0]->rowid); intakeCheck(hash_file('sha256',$f['path'])===hash('sha256',$bytes),'concurrent private content remains intact');
 echo "PASS Orders 0.2 final native invariants\n"; exit;
}
$fixture=json_decode(file_get_contents(DOL_DATA_ROOT.'/orders-accounts.json'),true);
$actors=[]; foreach(['denied','reader','intake','reviewer','no-native'] as $role) { $a=new User($db);$a->fetch('','orders-'.$role);$a->getrights('',1);$actors[$role]=$a; }
$reviewer=new User($db); $reviewer->login='orders-document-review';$reviewer->lastname='Synthetic review';$password=bin2hex(random_bytes(24));$reviewer->pass=$password;
intakeCheck($reviewer->create($user)>0,'native combined intake/review actor');$reviewer->clearrights();
foreach([61014001,61014002,61014003] as $right) intakeCheck($reviewer->addrights($right)>0,'grant existing MAX right');
foreach([['societe','lire',null],['societe','client','voir'],['commande','lire',null]] as [$module,$right,$sub]) {
 $found=intakeRows("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$module."' AND perms='".$right."'".($sub?" AND subperms='".$sub."'":" AND (subperms IS NULL OR subperms='')"));
 intakeCheck(count($found)===1 && $reviewer->addrights($found[0]->id)>0,'native customer/order read scope');
}
$reviewer->getrights('',1);
$provisional=$packets->ingest($reviewer,$fixture['customer'],'UPLOAD-200','UNKNOWN','PROVISIONAL',1,'{}');$packet=$provisional['packet'];
$before=intakeSnapshot();file_put_contents(DOL_DATA_ROOT.'/orders-intake-before',$before);chmod(DOL_DATA_ROOT.'/orders-intake-before',0600);
$revisionCount=count(intakeRows('SELECT rowid FROM '.MAIN_DB_PREFIX.'maxorders_revision'));
$ll=ordersFixtureXlsx(ordersFixtureFields('LL',' upload-200 ','98'));
$first=$documents->receive($actors['intake'],'misleading-PO.xlsx',$ll);$id=$first['id'];
$record=$documents->get($user,$id);$analysis=json_decode($record->analysis_json,true);
intakeCheck($analysis['status']==='suggested' && MaxOrdersExtraction::unique($analysis)['document_kind']==='LL','real XLSX content overrides filename hints');
intakeCheck(count(intakeRows('SELECT rowid FROM '.MAIN_DB_PREFIX.'maxorders_revision'))===$revisionCount,'upload creates no packet revision');
$suggestions=$documents->suggestions($reviewer,$id);
intakeCheck(count($suggestions)===1 && (int)$suggestions[0]->rowid===$packet,'normalized exact scoped packet suggestion');
intakeDenial(fn()=>$documents->associate($reviewer,$id,$packet,$fixture['customer'],'UPLOAD-200','LL','LL-DEMO',1,'{"quantity":"98"}',false),400,'human confirmation required');
intakeDenial(fn()=>$documents->associate($reviewer,$id,$packet,$fixture['customer'],'WRONG','LL','LL-DEMO',1,'{"quantity":"98"}',true),400,'reviewed reference must match chosen packet');
$linked=$documents->associate($reviewer,$id,$packet,$fixture['customer'],'upload-200','LL','LL-DEMO',1,'{"quantity":"98"}',true);
intakeCheck($linked['packet']===$packet && $packets->packet($user,$packet)->status==='provisional','LL first human association leaves one provisional packet');
$detail=$packets->detail($user,$packet);$counts=[count($detail['revision']),count($detail['audit'])];
$again=$documents->receive($actors['intake'],'renamed.xlsx',$ll);
$documents->associate($reviewer,$id,$packet,$fixture['customer'],'UPLOAD-200','LL','LL-DEMO',1,'{"quantity":"98.000"}',true);
$detail=$packets->detail($user,$packet);
intakeCheck($again['id']===$id && $again['duplicate'] && [count($detail['revision']),count($detail['audit'])]===$counts,'same bytes and repeated association are idempotent');
$file=$documents->download($actors['reader'],$id);
intakeCheck(str_starts_with($file['path'],realpath(DOL_DATA_ROOT).'/maxorders-private/entity-1/') && !str_starts_with($file['path'],realpath(DOL_DOCUMENT_ROOT).'/') && (fileperms($file['path'])&0777)===0600,'private content path outside webroot and mode 0600');
$blobCount=count(glob(dirname($file['path']).'/*.blob'));
$documents->receive($actors['intake'],'third-name.xlsx',$ll);
intakeCheck(count(glob(dirname($file['path']).'/*.blob'))===$blobCount,'repeated upload does not duplicate private files');
$po=ordersFixtureXlsx(ordersFixtureFields('PO','UPLOAD-200','100'));
$poId=$documents->receive($reviewer,'po.xlsx',$po)['id'];
$documents->associate($reviewer,$poId,$packet,$fixture['customer'],'UPLOAD-200','PO','PO-DEMO',2,'{"quantity":"100"}',true);
$oldId=$documents->receive($reviewer,'old.xlsx',ordersFixtureXlsx(ordersFixtureFields('PO','UPLOAD-200','10')))['id'];
$old=$documents->associate($reviewer,$oldId,$packet,$fixture['customer'],'UPLOAD-200','PO','PO-DEMO',1,'{"quantity":"10"}',true);
intakeCheck($old['status']==='archived','late old file revision archived');
$packets->lockField($reviewer,$packet,'quantity','100');
$newId=$documents->receive($reviewer,'new.xlsx',ordersFixtureXlsx(ordersFixtureFields('PO','UPLOAD-200','120')))['id'];
$documents->associate($reviewer,$newId,$packet,$fixture['customer'],'UPLOAD-200','PO','PO-DEMO',3,'{"quantity":"120"}',true);
$collisionId=$documents->receive($reviewer,'collision.xlsx',ordersFixtureXlsx(ordersFixtureFields('PO','UPLOAD-200','999')))['id'];
$collision=$documents->associate($reviewer,$collisionId,$packet,$fixture['customer'],'UPLOAD-200','PO','PO-DEMO',3,'{"quantity":"999"}',true);
$detail=$packets->detail($user,$packet);$quantities=[];foreach($detail['current'] as $fact) if($fact->field_name==='quantity')$quantities[$fact->kind]=$fact->field_value;
intakeCheck($quantities===['LL'=>'98','PO'=>'120'] && $detail['lock'][0]->field_value==='100','independent quantities and manual lock preserved');
intakeCheck($collision['status']==='collision' && $documents->get($user,$newId)->content_hash!==$documents->get($user,$collisionId)->content_hash && count($detail['conflict'])===2,'different bytes retained and revision/lock conflicts reviewable');
intakeDenial(fn()=>$documents->associate($reviewer,$id,$packet,$fixture['customer'],'UPLOAD-200','LL','LL-DEMO',2,'{"quantity":"50"}',true),409,'association cannot silently change after review');
foreach(['denied','reader','reviewer','no-native'] as $role) intakeDenial(fn()=>$documents->receive($actors[$role],'denied.pdf',ordersFixturePdf()),403,'upload role denial '.$role);
foreach(['denied','reader','intake','reviewer','no-native'] as $role) intakeDenial(fn()=>$documents->associate($actors[$role],$id,$packet,$fixture['customer'],'UPLOAD-200','LL','LL-DEMO',1,'{}',true),403,'combined association role denial '.$role);
foreach(['denied','no-native'] as $role) { intakeDenial(fn()=>$documents->get($actors[$role],$id),403,'read role denial '.$role); intakeDenial(fn()=>$documents->download($actors[$role],$id),403,'download role denial '.$role); }
$entity=$conf->entity;$conf->entity=2;
try { intakeDenial(fn()=>(new MaxOrdersDocument($db))->get($user,$id),404,'entity scoped file denial');intakeDenial(fn()=>$documents->get($user,$id),403,'service cannot cross its captured entity'); }
finally {$conf->entity=$entity;}
$saved=$file['path'].'.saved';rename($file['path'],$saved);
try { symlink($saved,$file['path']); intakeDenial(fn()=>$documents->download($user,$id),404,'symlink content denied'); }
finally { if(is_link($file['path']))unlink($file['path']);rename($saved,$file['path']); }
intakeCheck(empty(unActivateModule('modMaxorders')['errors']),'disable module native lifecycle');$conf->setValues($db);
intakeDenial(fn()=>(new MaxOrdersDocument($db))->download($user,$id),403,'disabled module file service denied');
intakeCheck(empty(activateModule('modMaxorders',1,1)['errors']),'re-enable/upgrade native schema');$conf->setValues($db);$user->getrights('',1);
intakeCheck((new MaxOrdersDocument($db))->get($user,$id)->content_hash===$record->content_hash,'lifecycle preserves private content ledger');
$dir=DOL_DATA_ROOT.'/orders-intake-inputs';if(!is_dir($dir))mkdir($dir,0700);
$inputs=['browser.xlsx'=>ordersFixtureXlsx(ordersFixtureFields('PC','PACK-100','99')),'manual.pdf'=>ordersFixturePdf(),'unreadable.pdf'=>ordersFixturePdf(true),'concurrent.xlsx'=>ordersFixtureXlsx(ordersFixtureFields('PO','CONCURRENT-ONLY','7'))];
foreach($inputs as $name=>$bytes) {file_put_contents($dir.'/'.$name,$bytes);chmod($dir.'/'.$name,0600);}
$fixture['accounts']['document-review']=['login'=>$reviewer->login,'password'=>$password];
$fixture['uploaded']=$id;$fixture['uploaded_hash']=$record->content_hash;$fixture['upload_packet']=$packet;$fixture['cookie_name']='DOLINSTALLNOPING_'.getHashUniqueIdOfRegistration('sha256');
file_put_contents(DOL_DATA_ROOT.'/orders-intake-session.json',json_encode($fixture));chmod(DOL_DATA_ROOT.'/orders-intake-session.json',0600);
intakeCheck(intakeSnapshot()===$before,'native financial and commercial snapshot unchanged by intake');
file_put_contents(DOL_DATA_ROOT.'/orders-intake-native.json',json_encode(['passed'=>true,'checks'=>$checks,'pdf_extraction'=>'BLOCKED','native_before'=>$before,'native_after'=>intakeSnapshot(),'field_source_example'=>['field'=>'quantity','sheet'=>'Synthetic','cell'=>'B11','state'=>'suggested']],JSON_PRETTY_PRINT));
echo "PASS installed Orders 0.2 native intake\n";
