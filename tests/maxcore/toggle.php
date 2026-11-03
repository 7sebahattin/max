<?php
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if(PHP_SAPI!=='cli' || $dolibarr_main_db_host!=='db' || $dolibarr_main_db_name!=='max_demo') exit(2);
$user->fetch('','maxcore-test');
if(($argv[1]??'')==='off') $r=unActivateModule('modMaxcore');
elseif(($argv[1]??'')==='on') $r=activateModule('modMaxcore',1,1);
else exit(2);
if(!empty($r['errors'])) throw new RuntimeException('Toggle failed');
echo "Native test toggle complete\n";
