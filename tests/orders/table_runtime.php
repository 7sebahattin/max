<?php
// Installed DB only. All fixtures are synthetic; original suites run first.
if(PHP_SAPI!=='cli')exit(2);
chdir('/opt/dolibarr/htdocs');require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxorders/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require __DIR__.'/table_fixtures.php';
$checks=[];
function tCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
function tRows($sql){global $db;$r=$db->query($sql);if(!$r)throw new RuntimeException('FAIL fixture SQL');$rows=[];while($v=$db->fetch_object($r))$rows[]=$v;return $rows;}
function tDeny($fn,$code,$label){try{$fn();}catch(Throwable $e){tCheck($e->getCode()===$code,$label);return;}throw new RuntimeException('FAIL missing denial '.$label);}
function tSnapshot(){
 $tables=['societe','commande','commandedet','product','product_stock','product_lot','product_batch','stock_mouvement','expedition','expeditiondet','facture','facturedet','bank','bank_account','paiement','paiement_facture','payment_various'];$all=[];
 foreach($tables as $table)$all[$table]=tRows('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid');
 $all['legal']=tRows("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 return hash('sha256',json_encode($all));
}
tCheck(DOL_VERSION==='24.0.1' && $dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && is_file(DOL_DATA_ROOT.'/install.lock'),'isolated pinned native table environment');
$user->fetch('','maxadmin');$user->getrights('',1);
$documents=new MaxOrdersDocument($db);$lines=new MaxOrdersLines($db);$packets=new MaxOrdersPacket($db);$mode=$argv[1]??'setup';
if($mode==='concurrent'){$documents->receive($user,'table-concurrent.xlsx',file_get_contents(DOL_DATA_ROOT.'/orders-table-inputs/concurrent.xlsx'));exit;}
if($mode==='verify'){
 tCheck(tSnapshot()===file_get_contents(DOL_DATA_ROOT.'/orders-table-before'),'native commercial financial legal snapshot unchanged after table HTTP');
 $sha=hash_file('sha256',DOL_DATA_ROOT.'/orders-table-inputs/concurrent.xlsx');
 $records=tRows("SELECT rowid FROM ".MAIN_DB_PREFIX."maxorders_upload WHERE entity=1 AND content_hash='".$sha."'");
 tCheck(count($records)===1 && count($lines->forUpload($user,$records[0]->rowid))===3,'concurrent workers create one upload and exactly three source rows');
 tCheck(!glob(sys_get_temp_dir().'/maxorders-worker-*'),'no leftover analysis workspace');
 $report=json_decode(file_get_contents(DOL_DATA_ROOT.'/orders-table-native.json'),true);$report['checks']=array_merge($report['checks'],$checks);$report['native_after']=tSnapshot();$report['final_verified']=true;
 file_put_contents(DOL_DATA_ROOT.'/orders-table-native.json',json_encode($report,JSON_PRETTY_PRINT));exit;
}
$fixture=json_decode(file_get_contents(DOL_DATA_ROOT.'/orders-intake-session.json'),true);
$reviewer=new User($db);$reviewer->fetch('','orders-document-review');$reviewer->getrights('',1);
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
$product=new Product($db);$product->ref='SYNTH-V03';$product->label='Strawberry';$product->type=0;
tCheck($product->create($user)>0,'native synthetic product fixture before immutable snapshot');
$before=tSnapshot();file_put_contents(DOL_DATA_ROOT.'/orders-table-before',$before);chmod(DOL_DATA_ROOT.'/orders-table-before',0600);
$packet=$packets->ingest($reviewer,$fixture['customer'],'TABLE-300','UNKNOWN','TABLE-PROVISIONAL',1,'{}')['packet'];
$packets->lockField($reviewer,$packet,'quantity','77');$original=$packets->detail($reviewer,$packet);
$meta=['order_reference'=>'TABLE-300'];$bytes=ordersTableFixture(null,$meta);
$id=$documents->receive($reviewer,'table.xlsx',$bytes)['id'];$record=$documents->get($reviewer,$id);$source=$lines->forUpload($reviewer,$id);
tCheck(count($source)===3 && $source[0]->content_hash===hash('sha256',$bytes) && $source[0]->declared_kind==='PO' && (int)$source[0]->declared_revision===2,'three persisted immutable source lines with file identity and revision');
tCheck(json_decode($source[1]->source_json,true)['cells']['quantity']['source']['cell']==='D11','source JSON preserves exact provenance');
tCheck($documents->receive($reviewer,'renamed.xlsx',$bytes)['id']===$id && count($lines->forUpload($reviewer,$id))===3,'replay creates no source rows');
$changedColumns=MaxOrdersTable::DEFAULT_COLUMNS;$changedColumns['description']='Item';
dolibarr_set_const($db,'MAXORDERS_V03_COLUMNS',json_encode($changedColumns),'chaine',0,'',1);$conf->setValues($db);
$documents->receive($reviewer,'reconfigured.xlsx',$bytes);
tCheck($documents->get($reviewer,$id)->analysis_json===$record->analysis_json && $lines->forUpload($reviewer,$id)[0]->source_json===$source[0]->source_json,'configuration changes cannot reinterpret existing file hash');
dolibarr_del_const($db,'MAXORDERS_V03_COLUMNS',1);$conf->setValues($db);
tCheck(count($documents->suggestions($reviewer,$id))===1,'table packet suggestion uses native customer scope and exact reference');
tDeny(fn()=>$documents->associate($reviewer,$id,$packet,$fixture['customer'],'TABLE-300','PO','TABLE-DEMO',2,'{}',false),400,'table association needs human confirmation');
tDeny(fn()=>$documents->associate($reviewer,$id,$packet,$fixture['customer'],'TABLE-300','PO','TABLE-DEMO',2,'{"quantity":"2300"}',true),400,'multiline cannot collapse into scalar quantity');
$linked=$documents->associate($reviewer,$id,$packet,$fixture['customer'],'TABLE-300','PO','TABLE-DEMO',2,'{}',true);
tCheck($linked['revision']===null && $linked['status']==='received' && $lines->forUpload($reviewer,$id)[0]->review_status==='linked','human association records context only, not commercial approval');
$documents->associate($reviewer,$id,$packet,$fixture['customer'],'TABLE-300','PO','TABLE-DEMO',2,'{}',true);
tCheck(count($lines->history($reviewer,$packet))===1,'association replay creates no source revision');
foreach([['PO',1,'10','archived'],['PO',3,'1200','received'],['PO',3,'999','collision'],['LL',1,'980','received'],['PC',1,'990','received']] as [$kind,$revision,$quantity,$status]){
 $upload=$documents->receive($reviewer,strtolower($kind).'-'.$quantity.'.xlsx',ordersTableFixture(ordersTableRows($quantity),array_replace($meta,['document_kind'=>$kind,'document_revision'=>(string)$revision])))['id'];
 $r=$documents->associate($reviewer,$upload,$packet,$fixture['customer'],'TABLE-300',$kind,'TABLE-DEMO',$revision,'{}',true);
 tCheck($r['status']===$status,'independent source revision '.$kind.' '.$revision.' '.$status);
}
$detail=$packets->detail($reviewer,$packet);
tCheck(json_encode($detail['current'])===json_encode($original['current']) && json_encode($detail['lock'])===json_encode($original['lock']) && count($detail['revision'])===count($original['revision']),'line intake leaves manual locks and scalar revisions unchanged');
$comparison=$lines->comparisons($reviewer,$packet);
tCheck(!$comparison['limited'] && count($comparison['rows'])===18 && count(array_filter($comparison['rows'],fn($r)=>$r->comparison==='OrdersSourceDifference'))===6,'PO LL PC conflicting quantities retained without selecting a winner');
tCheck($lines->catalog($reviewer,json_decode($record->analysis_json,true))===[],'descriptions do not become catalog code matches');
foreach(['denied','no-native','reader','intake','reviewer'] as $role){$actor=new User($db);$actor->fetch('','orders-'.$role);$actor->getrights('',1);tDeny(fn()=>$documents->associate($actor,$id,$packet,$fixture['customer'],'TABLE-300','PO','TABLE-DEMO',2,'{}',true),403,'native table association role '.$role);}
$mapping=['product_reference'=>'Code']+MaxOrdersTable::DEFAULT_COLUMNS;
$catalogAnalysis=MaxOrdersExtraction::inspect('catalog.xlsx',ordersTableFixture([array_merge(['SYNTH-V03'],ordersTableRows()[0])],[],$mapping),['columns_json'=>json_encode($mapping)]);
tCheck(count($lines->catalog($user,$catalogAnalysis))===1 && $lines->catalog($reviewer,$catalogAnalysis)===[],'explicit product code suggestion respects native product read role');
$entity=$conf->entity;$conf->entity=2;
try{tCheck((new MaxOrdersLines($db))->forUpload($user,$id)===[],'source lines entity scoped');tDeny(fn()=>$lines->forUpload($user,$id),403,'captured source service cannot change entity');}finally{$conf->entity=$entity;}
$download=$documents->download($reviewer,$id);tCheck(hash_file('sha256',$download['path'])===hash('sha256',$bytes),'private original unchanged after association');
$dir=DOL_DATA_ROOT.'/orders-table-inputs';if(!is_dir($dir))mkdir($dir,0700);
foreach(['browser.xlsx'=>ordersTableFixture(null,array_replace($meta,['document_reference'=>'BROWSER-TABLE','document_revision'=>'4'])),'concurrent.xlsx'=>ordersTableFixture(null,['order_reference'=>'TABLE-CONCURRENT'])] as $name=>$content){file_put_contents($dir.'/'.$name,$content);chmod($dir.'/'.$name,0600);}
$fixture['table_packet']=$packet;$fixture['table_upload']=$id;$fixture['table_hash']=hash('sha256',$bytes);
file_put_contents(DOL_DATA_ROOT.'/orders-table-session.json',json_encode($fixture));chmod(DOL_DATA_ROOT.'/orders-table-session.json',0600);
tCheck(tSnapshot()===$before,'table intake preserves all native commercial financial and legal records');
file_put_contents(DOL_DATA_ROOT.'/orders-table-native.json',json_encode(['passed'=>true,'checks'=>$checks,'native_before'=>$before,'native_after'=>tSnapshot(),'final_verified'=>false],JSON_PRETTY_PRINT));
