<?php
require __DIR__.'/lib/bootstrap.php';
$notice='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
 try {
  if(GETPOST('action','aZ09')!=='intake') throw new InvalidArgumentException('OrdersInvalid',400);
  $r=$packets->ingest($user,GETPOSTINT('customer'),GETPOST('reference','none'),GETPOST('kind','aZ09'),GETPOST('document','none'),GETPOSTINT('revision'),GETPOST('facts','none'));
  if($r['status']==='collision') { http_response_code(409); $notice='OrdersCollision'; }
  else { header('Location: '.dol_buildpath('/maxorders/packet.php',1).'?id='.$r['packet']); exit; }
 } catch(Throwable $e) { $code=in_array($e->getCode(),[400,403,409],true)?$e->getCode():500; http_response_code($code); if($code===403) accessforbidden('',0,0,1); $notice=$e->getMessage(); }
}
moHeader($langs->trans('OrdersTitle')); if($notice) print '<p role="alert">'.moLabel($notice).'</p>';
print '<section data-maxorders="inbox"><h2>'.moLabel('OrdersInbox').'</h2><ul>';
foreach($packets->packets($user) as $p) print '<li><a href="'.dol_buildpath('/maxorders/packet.php',1).'?id='.(int)$p->rowid.'">'.moEsc($p->match_ref).'</a> — '.moEsc($p->status).'</li>';
print '</ul></section>';
if(MaxOrdersPacket::can($user,'intake')) {
 print '<section data-maxorders="intake"><h2>'.moLabel('OrdersIntake').'</h2><p>'.moLabel('OrdersMetadataOnly').'</p>';
 moForm('intake'); moInput('customer','OrdersCustomer','number'); moInput('reference','OrdersReference');
 print '<label>'.moLabel('OrdersKind').'<select name="kind"><option>PO</option><option>LL</option><option>PC</option><option value="UNKNOWN">'.moLabel('OrdersUnknown').'</option></select></label>';
 moInput('document','OrdersDocument'); moInput('revision','OrdersRevision','number','1');
 print '<label>'.moLabel('OrdersFacts').'<textarea name="facts" required maxlength="4096" rows="5" placeholder="{&quot;quantity&quot;:&quot;100&quot;,&quot;sku&quot;:&quot;DEMO&quot;}"></textarea></label>';
 moButton('OrdersRecord'); print '</section>';
}
moFooter();
