<?php
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/maxcore/lib/maxcore.lib.php';
function ok($v,$label) { if(!$v) throw new RuntimeException('FAIL '.$label); echo 'PASS '.$label."\n"; }
ok($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1','isolated pinned runtime');
$user->fetch('','maxcore-test');
ok($user->admin && file_exists(DOL_DATA_ROOT.'/install.lock'),'native admin and installer lock');
function legalSnapshot() {
 global $db;
 $rows=array();
 $r=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 while($v=$db->fetch_array($r)) $rows[]=array($v['name'],$v['value'],$v['entity']);
 foreach(array('societe','facture','bank_account','bank','paiement') as $table) {
  $q=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid');
  if(!$q) throw new RuntimeException('Snapshot table unavailable');
  $data=array(); while($v=$db->fetch_array($q)) $data[]=$v; $rows[$table]=$data;
 }
 return $rows;
}
dolibarr_set_const($db,'MAIN_INFO_SOCIETE_NOM','Synthetic Legal Entity','chaine',0,'',1);
$before=legalSnapshot();
$r=activateModule('modMaxcore',1,1); ok(empty($r['errors']),'actual module activation');
$conf->setValues($db); $user->getrights('',1);
ok(isModEnabled('maxcore'),'enabled module');
$r=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."menu WHERE module='maxcore'");
ok($r && $db->num_rows($r)===3,'three native menus');
$r=$db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='maxcore'");
ok($r && $db->num_rows($r)===3,'three native permissions');
maxCoreSave(array('MAXCORE_DISPLAY_NAME'=>'Synthetic Display','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'max-icon.svg','MAIN_INFO_SOCIETE_NOM'=>'Must not write'),$user);
$conf->setValues($db);
ok(maxCoreSettings()['MAXCORE_DISPLAY_NAME']==='SYNTHETİC DİSPLAY','brand persistence and Turkish uppercase policy');
ok($before===legalSnapshot(),'native legal and commercial records unchanged');
foreach(array('<script>','',str_repeat('X',81)) as $name) {
 try { maxCoreValidate(array('MAXCORE_DISPLAY_NAME'=>$name)); ok(false,'reject bad name'); }
 catch(InvalidArgumentException $e) { ok(true,'reject bad name'); }
}
try { maxCoreValidate(array('MAXCORE_DISPLAY_NAME'=>'Test','MAXCORE_LOGO'=>'https://example.invalid/a.svg','MAXCORE_FAVICON'=>'none')); ok(false,'reject remote asset'); }
catch(InvalidArgumentException $e) { ok(true,'reject remote asset'); }
$accounts=array();
foreach(array('denied'=>0,'reader'=>61012001,'editor'=>61012002,'manager'=>61012003) as $role=>$permission) {
 $person=new User($db); $person->login='maxcore-'.$role; $person->lastname='Synthetic '.$role;
 $pass=bin2hex(random_bytes(24)); $person->pass=$pass;
 ok($person->create($user)>0,'native account '.$role); $person->clearrights();
 if($permission) ok($person->addrights($permission)>0,'grant '.$role);
 $person->getrights('',1);
 ok(maxCoreCan($person,'configure')===in_array($role,array('editor','manager'),true),'configure scope '.$role);
 if($role==='reader') {
  try { maxCoreSave(array(),$person); ok(false,'reader save denial'); } catch(RuntimeException $e) { ok(true,'reader save denial'); }
 }
 $accounts[$role]=array('login'=>$person->login,'password'=>$pass);
}
$external=new User($db); $external->admin=1; $external->socid=999;
ok(!maxCoreCan($external,'read'),'reject external identity');
$r=unActivateModule('modMaxcore'); ok(empty($r['errors']),'actual deactivation');
$conf->setValues($db); ok(!isModEnabled('maxcore'),'disabled state');
$r=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."menu WHERE module='maxcore'"); ok($db->num_rows($r)===0,'menus removed');
ok(maxCoreSettings()['MAXCORE_DISPLAY_NAME']==='SYNTHETİC DİSPLAY','brand survives disable');
$r=activateModule('modMaxcore',1,1); ok(empty($r['errors']),'actual reactivation');
ok($before===legalSnapshot(),'toggle preserves native identity and business records');
file_put_contents(DOL_DATA_ROOT.'/maxcore-accounts.json',json_encode($accounts)); chmod(DOL_DATA_ROOT.'/maxcore-accounts.json',0600);
echo "MAX Core native lifecycle/permissions/brand isolation PASS\n";
