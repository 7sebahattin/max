<?php
// Dedicated synthetic installation only; no production input or native core edits.
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if(PHP_SAPI!=='cli'||$dolibarr_main_db_host!=='db'||$dolibarr_main_db_name!=='max_demo'||DOL_VERSION!=='24.0.1') exit(2);
$user->fetch('','maxcore-test'); $user->getrights('',1);
function brandingSnapshot() {
 global $db;
 $out=array();
 $r=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 while($v=$db->fetch_array($r)) $out['legal'][]=$v;
 foreach(array('facture','bank','bank_account','paiement') as $t) {
  $r=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$t.' ORDER BY rowid');
  $out[$t]=array(); while($v=$db->fetch_array($r)) $out[$t][]=$v;
 }
 return hash('sha256',json_encode($out));
}
$mode=$argv[1]??'';
if($mode==='prepare') {
 $variant=$argv[2]??'';
 if(!in_array($variant,array('a','b'),true)) exit(2);
 // Only this test fixture writes synthetic legal identity, before the brand tests.
 foreach(array('MAIN_INFO_SOCIETE_NOM'=>'Synthetic Legal '.strtoupper($variant),
  'MAIN_INFO_SOCIETE_IDPROF1'=>'TEST-TAX-'.strtoupper($variant),
  'MAIN_INFO_SOCIETE_ADDRESS'=>'Synthetic Address '.strtoupper($variant)) as $k=>$v)
  if(dolibarr_set_const($db,$k,$v,'chaine',0,'',1)<=0) throw new RuntimeException('Fixture constant failed');
 require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
 $bank=new Account($db); $bank->ref='BRAND-'.strtoupper($variant); $bank->label='Synthetic brand isolation fixture';
 $bank->country_id=1; $bank->currency_code='EUR'; $bank->type=1;
 if($bank->create($user)<=0) throw new RuntimeException('Bank fixture failed');
 // Deliberately fictional, never used for payments or any transport.
 $r=$db->query("UPDATE ".MAIN_DB_PREFIX."bank_account SET iban_prefix='TEST-IBAN-".strtoupper($variant)."' WHERE rowid=".(int)$bank->id);
 if(!$r) throw new RuntimeException('Synthetic IBAN fixture failed');
 $r=activateModule('modSociete',1,1);
 if(!empty($r['errors'])) throw new RuntimeException('Native commercial module failed');
 file_put_contents(DOL_DATA_ROOT.'/branding-state',brandingSnapshot());
} elseif($mode==='verify') {
 if(file_get_contents(DOL_DATA_ROOT.'/branding-state')!==brandingSnapshot()) throw new RuntimeException('Brand changed issuer/tax/bank/invoice/payment state');
 $r=$db->query("SELECT name FROM ".MAIN_DB_PREFIX."const WHERE name IN ('MAIN_APPLICATION_TITLE','MAIN_HTML_TITLE','MAIN_FAVICON_URL')");
 if(!$r||$db->num_rows($r)!==0) throw new RuntimeException('Rendering settings persisted outside MAX namespace');
 echo "PASS brand propagation preserves issuer/tax/IBAN/finance and native rendering constants\n";
} else exit(2);
