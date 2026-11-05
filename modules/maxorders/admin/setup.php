<?php
require dirname(__DIR__).'/lib/bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once dirname(__DIR__).'/class/extraction.class.php';
if(empty($user->admin)) { http_response_code(403); accessforbidden('',0,0,1); }
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { http_response_code(405); exit; }
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
 try {
  if(GETPOST('action','aZ09')!=='template') throw new InvalidArgumentException('OrdersInvalid',400);
  $input=GETPOST('columns','none');
  if(strlen($input)>2048) throw new InvalidArgumentException('OrdersTemplateInvalid',400);
  $columns=MaxOrdersTable::columns($input);
  if(dolibarr_set_const($db,'MAXORDERS_V03_COLUMNS',json_encode($columns,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'chaine',0,'MAX Orders source headers',(int)$conf->entity)<=0) throw new RuntimeException('OrdersStorageError',500);
  if(dolibarr_set_const($db,'MAXORDERS_PDF_SAFE_ENABLE',GETPOST('safe_pdf','aZ09')==='yes'?'1':'0','yesno',0,'Offline sandbox only',(int)$conf->entity)<=0) throw new RuntimeException('OrdersStorageError',500);
  header('Location: '.dol_buildpath('/maxorders/admin/setup.php',1),true,303); exit;
 } catch(Throwable $e) { moError(new RuntimeException($e->getCode()===400?$e->getMessage():'OrdersStorageError',$e->getCode()===400?400:500)); }
}
moHeader($langs->trans('OrdersTemplateSettings'));
print '<section data-maxorders="template"><p>'.moLabel('OrdersTemplateHelp').'</p>';
moForm('template');
print '<label>'.moLabel('OrdersTemplateColumns').'<textarea name="columns" rows="12" maxlength="2048" required>'.moEsc(getDolGlobalString('MAXORDERS_V03_COLUMNS',json_encode(MaxOrdersTable::DEFAULT_COLUMNS,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))).'</textarea></label>';
print '<label class="maxorders-check"><input type="checkbox" name="safe_pdf" value="yes"'.(getDolGlobalInt('MAXORDERS_PDF_SAFE_ENABLE')===1?' checked':'').'> '.moLabel('OrdersPdfOptIn').'</label><p>'.moLabel('OrdersPdfSandboxBlocked').'</p>';
moButton('OrdersSave'); print '</section>'; moFooter();
