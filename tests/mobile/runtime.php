<?php
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if(PHP_SAPI!=='cli'||$dolibarr_main_db_host!=='db'||$dolibarr_main_db_name!=='max_demo'||DOL_VERSION!=='24.0.1') exit(2);
$user->fetch('','maxadmin'); $user->getrights('',1);
function snapshotMobile() {
 global $db;
 $out=array();
 $r=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 while($v=$db->fetch_array($r)) $out['legal'][]=$v;
 foreach(array('societe','product','commande','expedition','facture','paiement','bank','bank_account','stock_mouvement') as $t) {
  $r=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$t.' ORDER BY rowid');
  if(!$r) throw new RuntimeException('Snapshot unavailable');
  $out[$t]=array(); while($v=$db->fetch_array($r)) $out[$t][]=$v;
 }
 return hash('sha256',json_encode($out));
}
$mode=$argv[1]??'';
if($mode==='prepare') {
 file_put_contents(DOL_DATA_ROOT.'/mobile-state',snapshotMobile());
 $accounts=array();
 foreach(array('reader','denied') as $role) {
  $p=new User($db); $p->login='mobile-'.$role; $p->lastname='Synthetic mobile '.$role;
  $pass=bin2hex(random_bytes(24)); $p->pass=$pass;
  if($p->create($user)<=0) throw new RuntimeException('Account creation failed');
  $p->clearrights();
  if($role==='reader') {
   $r=$db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE perms='lire' AND module IN ('societe','produit','commande','expedition','facture')");
   while($row=$db->fetch_object($r)) if($p->addrights($row->id)<=0) throw new RuntimeException('Read grant failed');
  }
  $accounts[$role]=array('login'=>$p->login,'password'=>$pass);
 }
 file_put_contents(DOL_DATA_ROOT.'/mobile-accounts.json',json_encode($accounts)); chmod(DOL_DATA_ROOT.'/mobile-accounts.json',0600);
} elseif($mode==='on'||$mode==='off') {
 $r=$mode==='on'?activateModule('modMaxmobile',1,1):unActivateModule('modMaxmobile');
 if(!empty($r['errors'])) throw new RuntimeException('Native module toggle failed');
} elseif($mode==='verify') {
 if(file_get_contents(DOL_DATA_ROOT.'/mobile-state')!==snapshotMobile()) throw new RuntimeException('UI changed native business/legal state');
 echo "PASS mobile UI preserves native legal/commercial records\n";
} else exit(2);
echo "PASS native mobile fixture ".$mode."\n";
