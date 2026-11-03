<?php
/** Real native collector; fixture only. Never expose as a web endpoint. GPL-3.0-or-later. */
if (PHP_SAPI !== 'cli') exit(2);
chdir('/opt/dolibarr/htdocs'); require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/emailcollector/class/emailcollector.class.php';
require_once DOL_DOCUMENT_ROOT.'/emailcollector/class/emailcollectoraction.class.php';
require_once DOL_DOCUMENT_ROOT.'/emailcollector/class/emailcollectorfilter.class.php';
function good($ok, $label) { if (!$ok) throw new RuntimeException('FAIL '.$label); }
good($dolibarr_main_db_host==='db' && $dolibarr_main_db_name==='max_demo' && DOL_VERSION==='24.0.1' && is_file(DOL_DATA_ROOT.'/install.lock'), 'disposable pinned install');
$user->fetch('', 'maxadmin'); good($user->admin, 'native administrator');
function scalarMail($sql) { global $db; $r=$db->query($sql); good((bool)$r,'fixture query'); return $db->fetch_row($r)[0]; }
function snapshotMail() {
 global $db; $out=[];
 foreach (['societe','commande','commandedet','expedition','facture','stock_mouvement','bank','paiement'] as $table) {
  $r=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY rowid'); good((bool)$r,'native snapshot');
  $out[$table]=[]; while($row=$db->fetch_array($r)) $out[$table][]=$row;
 }
 $r=$db->query("SELECT name,value,entity FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_INFO_SOCIETE_%' ORDER BY name,entity");
 while($row=$db->fetch_array($r)) $out['legal'][]=$row;
 return hash('sha256',json_encode($out));
}
$mode=$argv[1]??'';
if ($mode==='setup') {
 good(empty(activateModule('modAgenda',1,1)['errors']),'native agenda enabled');
 foreach(['MAIN_IMAP_USE_PHPIMAP'=>'1','MAIN_DISABLE_ALL_MAILS'=>'1','MAIN_USE_CONNECT_TIMEOUT'=>'3','MAIN_USE_RESPONSE_TIMEOUT'=>'3'] as $key=>$value) good(dolibarr_set_const($db,$key,$value,'chaine',0,'',1)>0,'native configuration');
 $conf->setValues($db); $user->getrights('',1);
 good(!function_exists('imap_open'),'baseline PHP extension absent');
 good(ini_get('sendmail_path')==='/bin/false','no-send PHP boundary');
 $state=json_decode(file_get_contents('/mail-state/state.json'),true);
 foreach($state['accounts'] as $alias=>$account) {
  $c=new EmailCollector($db); $c->ref='Fixture '.$alias; $c->label=$c->ref; $c->entity=1;
  $c->host='fakeimap'; $c->port=1143; $c->imap_encryption=''; $c->hostcharset='UTF-8'; $c->norsh=1; $c->acces_type=0;
  $c->login=$account['login']; $c->password=$account['password']; $c->source_directory='Orders'; $c->target_directory='';
  $c->maxemailpercollect=20; $c->status=1; $c->filters=[];
  $c->actions=[['type'=>'recordevent','actionparam'=>'','status'=>1,'position'=>10]];
  good($c->create($user)>0,'create active native collector');
 }
 $p=new User($db); $p->login='mail-reader'; $p->lastname='Synthetic reader'; $password=bin2hex(random_bytes(24)); $p->pass=$password;
 good($p->create($user)>0,'create native no-rights user'); $p->clearrights();
 file_put_contents(DOL_DATA_ROOT.'/mail-accounts.json',json_encode(['login'=>$p->login,'password'=>$password])); chmod(DOL_DATA_ROOT.'/mail-accounts.json',0600);
 file_put_contents(DOL_DATA_ROOT.'/mail-before',snapshotMail());
 echo "PASS native active collectors configured\n";
} elseif ($mode==='poll') {
 $alias=$argv[2]??'';
 $id=(int)scalarMail("SELECT rowid FROM ".MAIN_DB_PREFIX."emailcollector_emailcollector WHERE ref='Fixture ".$db->escape($alias)."'");
 $c=new EmailCollector($db); good($c->fetch($id)>0,'fetch collector');
 // Native diagnostics can contain raw mail. Never emit them, even on failure.
 ob_start(); try { $result=$c->doCollectOneCollector(0); } catch(Throwable $e) { $result=-99; } finally { ob_end_clean(); }
 $metadata=[];
 foreach(($GLOBALS['attachments']??[]) as $attachment) {
  if(is_object($attachment)) $metadata[]=['name'=>$attachment->getName(),'size'=>strlen($attachment->getContent()),'sha256'=>hash('sha256',$attachment->getContent()),'mime'=>$attachment->getMimeType()];
 }
 $events=(int)scalarMail("SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."actioncomm WHERE email_msgid LIKE '%@fixture.invalid%'");
 $attribution=(int)scalarMail("SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."actioncomm WHERE email_msgid LIKE '%@fixture.invalid%' AND email_from LIKE '%buyer@partner.invalid%'");
 echo json_encode(['result'=>$result,'events'=>$events,'sender_attribution'=>$attribution,'attachments'=>$metadata])."\n";
} elseif ($mode==='cron') {
 $c=new EmailCollector($db); ob_start(); try { $result=$c->doCollect(); } finally { ob_end_clean(); }
 good($result===0,'actual scheduled polling'); echo "PASS actual native cron polling\n";
} elseif ($mode==='verify') {
 good(snapshotMail()===file_get_contents(DOL_DATA_ROOT.'/mail-before'),'native business/legal preservation');
 good(getDolGlobalString('MAIN_DISABLE_ALL_MAILS')==='1' && ini_get('sendmail_path')==='/bin/false','no-send preserved');
 // recordevent is not a private mail-attachment repository. No guessed PO/LL/PC import.
 echo "PASS no order/stock/finance/legal effects and no-send\n";
} else exit(2);
