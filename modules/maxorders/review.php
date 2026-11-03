<?php
require __DIR__.'/lib/bootstrap.php';
if(!MaxOrdersPacket::can($user,'review')) { http_response_code(403); accessforbidden('',0,0,1); }
$id=GETPOSTINT('id'); $notice='';
try {
 $packets->packet($user,$id);
 if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
  $action=GETPOST('action','aZ09');
  if($action==='lock') $packets->lockField($user,$id,GETPOST('field','aZ09'),GETPOST('value','none'),GETPOST('locked','aZ09')==='yes');
  elseif($action==='resolve') $packets->resolve($user,$id,GETPOSTINT('conflict'),GETPOST('decision','aZ09'));
  elseif($action==='link') $packets->link($user,$id,GETPOSTINT('order'),GETPOST('confirmed','aZ09')==='yes');
  else throw new InvalidArgumentException('OrdersInvalid',400);
  $notice='OrdersSaved';
 }
 $d=$packets->detail($user,$id);
} catch(Throwable $e) {
 $code=in_array($e->getCode(),[400,403,404,409],true)?$e->getCode():500; http_response_code($code);
 if(in_array($code,[403,404],true)) { accessforbidden('',0,0,1); } $notice=$e->getMessage(); $d=$packets->detail($user,$id);
}
moHeader($langs->trans('OrdersReview').' '.$d['packet']->match_ref);
print '<section data-maxorders="review"><p role="status">'.moLabel($notice).'</p><p><a href="'.dol_buildpath('/maxorders/packet.php',1).'?id='.$id.'">'.moLabel('OrdersPacket').'</a></p>';
moForm('lock',$id); print '<label>'.moLabel('OrdersField').'<select name="field">';
foreach(['quantity','unit_price','sku','variant','packaging','currency'] as $field) print '<option value="'.moEsc($field).'">'.moValue($field).'</option>';
print '</select></label>'; moInput('value','OrdersValue');
print '<label>'.moLabel('OrdersLocked').'<select name="locked"><option value="yes">'.moLabel('OrdersLock').'</option><option value="no">'.moLabel('OrdersUnlock').'</option></select></label>'; moButton('OrdersSaveLock');
foreach($d['conflict'] as $c) {
 if($c->status!=='open') continue;
 print '<article><h2>'.moLabel('OrdersConflict').' #'.(int)$c->rowid.'</h2><p>'.moValue($c->field_name).': '.moEsc($c->old_value).' → '.moEsc($c->new_value).' ('.moLabel('OrdersSource').' '.(int)$c->fk_revision.')</p>';
 if($c->reason==='revision_collision') print '<p>'.moLabel('OrdersCollisionPresenceHint').'</p>';
 moForm('resolve',$id); print '<input type="hidden" name="conflict" value="'.(int)$c->rowid.'"><label>'.moLabel('OrdersDecision').'<select name="decision">';
 foreach($c->reason==='revision_collision'?['acknowledge'=>'OrdersAcknowledge']:['keep_manual'=>'OrdersKeepManual','accept_source'=>'OrdersAcceptSource'] as $value=>$label) print '<option value="'.moEsc($value).'">'.moLabel($label).'</option>';
 print '</select></label>'; moButton('OrdersResolve'); print '</article>';
}
print '<h2>'.moLabel('OrdersNativeOrder').'</h2><p>'.moLabel('OrdersLinkRule').'</p>';
moForm('link',$id); moInput('order','OrdersNativeOrder','number');
print '<label class="maxorders-check"><input type="checkbox" name="confirmed" value="yes" required> '.moLabel('OrdersConfirmLink').'</label>'; moButton('OrdersLink');
print '</section>'; moFooter();
