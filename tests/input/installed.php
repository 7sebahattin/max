<?php
// Native integration fixtures only. CLI modes/paths/credentials are opaque.
// Lowercase test payloads intentionally exercise MaxInput at authoritative boundaries.
if(PHP_SAPI!=='cli')exit(2);
$run=realpath(getenv('MAX_INPUT_NATIVE_RUN'));
$root=realpath(getenv('MAX_INPUT_NATIVE_ROOT'));
if(!$run||!$root||!is_file($run.'/owner.json'))throw new RuntimeException('Missing owned runtime');
$owner=json_decode(file_get_contents($run.'/owner.json'),true);
if($owner['root']!==$root||$owner['database']!==getenv('MAX_INPUT_NATIVE_DB')||!preg_match('/^max_input_[a-f0-9]{10}$/D',$owner['database']))throw new RuntimeException('Ownership mismatch');
chdir($root);require 'master.inc.php';
if(DOL_VERSION!=='24.0.1'||$dolibarr_main_db_host!=='127.0.0.1'||$dolibarr_main_db_name!==$owner['database']||(int)$dolibarr_main_db_port!==$owner['port']||realpath(DOL_DATA_ROOT)!==realpath($run.'/documents')||!is_file(DOL_DATA_ROOT.'/install.lock'))throw new RuntimeException('Refusing unowned install');
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$checks=[];
function installedCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('ASSERT '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
function installedRows($sql){global $db;$q=$db->query($sql);if(!$q)throw new RuntimeException('Native query failed');$out=[];while($row=$db->fetch_array($q))$out[]=$row;return $out;}
function installedResult($result,$label){installedCheck($result>0,$label);return $result;}
function installedSnapshot($ids){
 $out=[];
 foreach(['product_stock','product_batch','stock_mouvement','commande','commandedet','expedition','expeditiondet','facture','facturedet','bank_account','bank','paiement','paiement_facture'] as $table)$out[$table]=installedRows('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid');
 foreach(['product','societe'] as $table)$out[$table]=installedRows('SELECT * FROM '.MAIN_DB_PREFIX.$table.' WHERE rowid='.(int)$ids[$table]);
 $out['protected_contact']=installedRows('SELECT * FROM '.MAIN_DB_PREFIX.'socpeople WHERE rowid='.(int)$ids['socpeople']);
 $out['other_entity_product']=installedRows('SELECT * FROM '.MAIN_DB_PREFIX.'product WHERE rowid='.(int)$ids['other_product']);
 $out['legal']=installedRows("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 return ['sha256'=>hash('sha256',json_encode($out)),'counts'=>array_map('count',$out)];
}
$user->fetch('','maxadmin');$user->getrights('',1);
if(($argv[1]??'')==='fixture'){
 foreach(['Societe','Product','Stock','Commande','Expedition','Facture','Banque','Api','Import','Maxcore','Maxmobile','Maxorders','Maxcustoms','Maxmail'] as $module)installedCheck(empty(activateModule('mod'.$module,1,1)['errors']),'activate '.$module);
 foreach(['MAIN_MONNAIE'=>'EUR','MAIN_INFO_SOCIETE_NOM'=>'Synthetic Legal Case Ltd','MAIN_INFO_SOCIETE_COUNTRY'=>'1:FR:France','MAIN_INFO_SOCIETE_IDPROF1'=>'CaseTax123','MAIN_DISABLE_ALL_MAILS'=>'1','MAIN_CHECK_DUPLICATE_COMPANY'=>'1'] as $key=>$value)installedResult(dolibarr_set_const($db,$key,$value,'chaine',0,'',1),'synthetic constant '.$key);
 $conf->setValues($db);$user->getrights('',1);
 $user->api_key=bin2hex(random_bytes(24));installedResult($user->update($user,1),'native admin API credential');
 $denied=new User($db);$denied->login='input-denied';$denied->lastname='Synthetic';$denied->pass=bin2hex(random_bytes(24));$deniedPass=$denied->pass;installedResult($denied->create($user),'native denied user');$denied->clearrights();$denied->fetch($denied->id);$denied->api_key=bin2hex(random_bytes(24));installedResult($denied->update($user,1),'native denied API credential');
 foreach(['societe/class/societe.class.php','product/class/product.class.php','product/stock/class/entrepot.class.php','product/stock/class/mouvementstock.class.php','commande/class/commande.class.php','compta/facture/class/facture.class.php','compta/bank/class/account.class.php'] as $file)require_once DOL_DOCUMENT_ROOT.'/'.$file;
 $soc=new Societe($db);$soc->name='Protected Synthetic Customer';$soc->client=1;$soc->code_client='Fixture-Case';$soc->country_id=1;installedResult($soc->create($user),'commercial customer fixture');
 require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
 $contact=new Contact($db);$contact->socid=$soc->id;$contact->lastname='PROTECTED CONTACT';$contact->firstname='FIXTURE';$contact->email='Fixture.Case@Example.invalid';installedResult($contact->create($user),'protected native contact fixture');
 $product=new Product($db);$product->ref='Fixture-SkuCase';$product->label='PROTECTED GOODS';$product->type=0;$product->status=1;$product->status_buy=1;installedResult($product->create($user),'commercial product fixture');
 $warehouse=new Entrepot($db);$warehouse->ref='Fixture-Warehouse';$warehouse->label='PROTECTED WAREHOUSE';$warehouse->statut=1;installedResult($warehouse->create($user),'commercial warehouse fixture');
 $stock=new MouvementStock($db);installedResult($stock->reception($user,$product->id,$warehouse->id,20,5,'Synthetic stock receipt'),'commercial stock fixture');
 $order=new Commande($db);$order->socid=$soc->id;$order->date=dol_now();installedResult($order->create($user),'commercial order fixture');installedResult($order->addline('PROTECTED GOODS',10,2,0,0,0,$product->id),'commercial order line');
 $invoice=new Facture($db);$invoice->socid=$soc->id;$invoice->date=dol_now();$invoice->type=0;installedResult($invoice->create($user),'draft invoice fixture');installedResult($invoice->addline('PROTECTED GOODS',10,2,0),'draft invoice line');
 $bank=new Account($db);$bank->ref='Input-Bank';$bank->label='SYNTHETIC NONBANK';$bank->country_id=1;$bank->currency_code='EUR';$bank->type=1;$bank->date_solde=dol_now();installedResult($bank->create($user),'fictional bank fixture');$bank->iban='TEST-IBAN-CaseOnly';installedResult($bank->update_bban($user),'fictional IBAN fixture');installedResult($bank->addline(dol_now(),'VIR','SYNTHETIC RECEIPT',40,'',0,$user),'fictional receipt fixture');
 $conf->entity=2;$other=new Product($db);$other->ref='Other-EntityCase';$other->label='OTHER ENTITY';$other->type=0;installedResult($other->create($user),'entity two native fixture');$conf->entity=1;
 require_once DOL_DOCUMENT_ROOT.'/blockedlog/lib/blockedlog.lib.php';
 $accounts=['admin'=>['login'=>'maxadmin','password'=>getenv('MAX_INPUT_ADMIN_PASS'),'key'=>$user->api_key],'denied'=>['login'=>'input-denied','password'=>$deniedPass,'key'=>$denied->api_key],'fixture_customer'=>$soc->id,'other_product'=>$other->id,'privacy'=>'DOLINSTALLNOPING_'.getHashUniqueIdOfRegistration('sha256')];
 file_put_contents($run.'/accounts.json',json_encode($accounts));
 $ids=['product'=>$product->id,'societe'=>$soc->id,'socpeople'=>$contact->id,'other_product'=>$other->id];file_put_contents($run.'/protected-ids.json',json_encode($ids));
 $snapshot=installedSnapshot($ids);installedCheck($snapshot['counts']['bank']>0&&$snapshot['counts']['facturedet']>0&&$snapshot['counts']['stock_mouvement']>0,'populated immutable boundary after commercial fixtures');
 installedCheck(!is_file($run.'/before.json'),'baseline written once');file_put_contents($run.'/before.json',json_encode($snapshot,JSON_PRETTY_PRINT));
 file_put_contents($run.'/fixture-checks.json',json_encode($checks,JSON_PRETTY_PRINT));exit;
}
if(($argv[1]??'')!=='verify')exit(2);
require_once DOL_DOCUMENT_ROOT.'/custom/maxcore/lib/input_hooks.lib.php';
// Same unmodified WAF helper the native web import page loads via main.inc.php.
// CLI's Windows filesystem PHP_SELF contains a drive colon; WAF expects a URL.
$_SERVER['PHP_SELF']='/imports/import.php';
require_once DOL_DOCUMENT_ROOT.'/waf.inc.php';
require_once DOL_DOCUMENT_ROOT.'/imports/class/import.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/modules/import/import_csv.modules.php';
$objimport=new Import($db);$objimport->load_arrays($user,'produit_1');
installedCheck(count($objimport->array_import_tables)===1,'native product import descriptor');
$csv=$run.'/documents/input-source.csv';file_put_contents($csv,"Import-SkuCase,çiçek straße,0\n");$sourceHash=hash_file('sha256',$csv);
$driver=new ImportCsv($db,'produit_1');installedCheck($driver->import_open_file($csv)>0,'native CSV source open');$row=$driver->import_read_record();$driver->import_close_file();
$map=[1=>'p.ref',2=>'p.label',3=>'p.fk_product_type'];$raw=$row;$nbok=0;
$params=['datatoimport'=>'produit_1','obj'=>$driver,'arrayrecord'=>$row,'array_match_file_to_database'=>$map,'objimport'=>$objimport,'fieldssource'=>array_values($map),'importid'=>'InputCase00001','updatekeys'=>[],'nbok'=>&$nbok];
$hookmanager->initHooks(['imports']);
$hookresult=$hookmanager->executeHooks('ImportInsert',$params);
if($hookresult!==1||$nbok!==1)file_put_contents($run.'/import-debug.json',json_encode(['result'=>$hookresult,'counter'=>$nbok,'errors'=>$driver->errors,'warnings'=>$driver->warnings,'db_error'=>$db->lasterror()],JSON_PRETTY_PRINT));
installedCheck($hookresult===1&&$nbok===1,'native HookManager importer delegates and increments once');
$imported=installedRows("SELECT ref,label FROM ".MAIN_DB_PREFIX."product WHERE ref='Import-SkuCase'");installedCheck(count($imported)===1&&$imported[0]['label']==='ÇİÇEK STRASSE'&&$imported[0]['ref']==='Import-SkuCase','native imported business uppercase and opaque ref exact');installedCheck($row===$raw&&hash_file('sha256',$csv)===$sourceHash,'raw import row and CSV bytes immutable');
$driver=new ImportCsv($db,'produit_1');$params['obj']=$driver;
installedCheck($hookmanager->executeHooks('ImportInsert',$params)<0,'native duplicate import rejected');
installedCheck(count(installedRows("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='Import-SkuCase'"))===1&&$nbok===1,'duplicate import rolls back without merge or counter increment');
$params['arrayrecord'][0]['val']='Overflow-Case';$params['arrayrecord'][1]['val']=str_repeat('ß',128);
installedCheck($hookmanager->executeHooks('ImportInsert',$params)<0,'import expansion overflow rejected before insert');
installedCheck(count(installedRows("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='Overflow-Case'"))===0,'import overflow leaves no row');
require_once DOL_DOCUMENT_ROOT.'/core/modules/import/import_xlsx.modules.php';
$xlsxDriver=new ImportXlsx($db,'produit_1');$book=new PhpOffice\PhpSpreadsheet\Spreadsheet();
$book->getActiveSheet()->fromArray([['Xlsx-SkuCase','şişli straße','0']]);$xlsx=$run.'/documents/input-source.xlsx';(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($xlsx);$book->disconnectWorksheets();
$xlsxHash=hash_file('sha256',$xlsx);installedCheck($xlsxDriver->import_open_file($xlsx)>0,'native XLSX source open');$xlsxRow=$xlsxDriver->import_read_record();$xlsxDriver->import_close_file();$xlsxRaw=$xlsxRow;$xlsxOk=0;
$params['obj']=$xlsxDriver;$params['arrayrecord']=$xlsxRow;$params['nbok']=&$xlsxOk;
installedCheck($hookmanager->executeHooks('ImportInsert',$params)===1&&$xlsxOk===1,'native XLSX HookManager import succeeds once');
$xlsxProduct=installedRows("SELECT ref,label FROM ".MAIN_DB_PREFIX."product WHERE ref='Xlsx-SkuCase'");installedCheck(count($xlsxProduct)===1&&$xlsxProduct[0]['label']==='ŞİŞLİ STRASSE'&&$xlsxProduct[0]['ref']==='Xlsx-SkuCase','native XLSX mapping preserves SKU and uppercases label');
installedCheck($xlsxRaw===$xlsxRow&&hash_file('sha256',$xlsx)===$xlsxHash,'native XLSX source bytes and typed row immutable');
// Match native import wizard's outer transaction/simulation contract as well.
$db->begin();$outerOk=0;$params['obj']=new ImportCsv($db,'produit_1');$params['arrayrecord']=$raw;$params['arrayrecord'][0]['val']='Outer-Rollback';$params['nbok']=&$outerOk;
installedCheck($hookmanager->executeHooks('ImportInsert',$params)===1&&$outerOk===1&&$db->transaction_opened===1,'native outer transaction remains owned by wizard');
$db->rollback();installedCheck(count(installedRows("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='Outer-Rollback'"))===0,'native simulation rollback removes normalized proposed row');
$db->begin();$params['obj']=new ImportCsv($db,'produit_1');$params['arrayrecord'][0]['val']='Batch-Rollback';
installedCheck($hookmanager->executeHooks('ImportInsert',$params)===1,'native batch pending successful row');
$params['obj']=new ImportCsv($db,'produit_1');$params['arrayrecord']=$raw;
installedCheck($hookmanager->executeHooks('ImportInsert',$params)<0,'native batch duplicate propagates error to wizard');
$db->rollback();installedCheck(count(installedRows("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='Batch-Rollback'"))===0,'native wizard error rollback removes earlier pending row');
require_once DOL_DOCUMENT_ROOT.'/custom/maxcore/lib/maxcore.lib.php';
maxCoreSave(['MAXCORE_DISPLAY_NAME'=>'şişli marka','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'max-icon.svg'],$user);
installedCheck(installedRows("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MAXCORE_DISPLAY_NAME' AND entity=1")[0]['value']==='ŞİŞLİ MARKA','native direct brand service normalized');
$conf->entity=2;maxCoreSave(['MAXCORE_DISPLAY_NAME'=>'ikinci marka','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'max-icon.svg'],$user);$conf->entity=1;
installedCheck(installedRows("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MAXCORE_DISPLAY_NAME' AND entity=1")[0]['value']==='ŞİŞLİ MARKA','brand entity one unchanged by entity two');
foreach(['Maxorders','Maxcustoms','Maxmail'] as $name){$class='mod'.$name;require_once DOL_DOCUMENT_ROOT.'/custom/'.strtolower($name).'/core/modules/'.$class.'.class.php';$m=new $class($db);installedCheck(in_array('modMaxcore',$m->depends,true),'Core dependency '.$name);}
installedCheck(empty(unActivateModule('modMaxcore',1)['errors']),'native Core deactivate');$conf->setValues($db);installedCheck(!isModEnabled('maxcore'),'native Core disabled flag');
try{maxCoreSave(['MAXCORE_DISPLAY_NAME'=>'denied','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'max-icon.svg'],$user);throw new LogicException('disabled Core write allowed');}catch(RuntimeException $e){installedCheck($e->getMessage()==='MAXDenied','disabled Core rejects brand service');}
installedCheck(empty(activateModule('modMaxcore',1,1)['errors']),'native Core reactivate');$conf->setValues($db);installedCheck(isModEnabled('maxcore'),'native Core restored');
$before=json_decode(file_get_contents($run.'/before.json'),true);$after=installedSnapshot(json_decode(file_get_contents($run.'/protected-ids.json'),true));file_put_contents($run.'/after.json',json_encode($after,JSON_PRETTY_PRINT));
installedCheck($before===$after,'commercial bank IBAN stock order invoice payment and legal snapshot unchanged');
file_put_contents($run.'/verify-checks.json',json_encode($checks,JSON_PRETTY_PRINT));
