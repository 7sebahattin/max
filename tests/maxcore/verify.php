<?php
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
if($dolibarr_main_db_host!=='db' || $dolibarr_main_db_name!=='max_demo') exit(2);
$r=$db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MAIN_INFO_SOCIETE_NOM' AND entity=1");
if($db->fetch_object($r)->value!=='Synthetic Legal Entity') throw new RuntimeException('HTTP brand write altered issuer');
$r=$db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MAIN_FAVICON_URL'");
if($db->num_rows($r)!==0) throw new RuntimeException('Module persisted native favicon');
foreach(array('facture','bank','paiement') as $table) {
 $r=$db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.$table);
 if((int)$db->fetch_object($r)->n!==0) throw new RuntimeException('Unexpected business records');
}
echo "PASS HTTP updates/toggle preserve native issuer, favicon and finance records\n";
