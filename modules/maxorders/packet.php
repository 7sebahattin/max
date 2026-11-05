<?php
require __DIR__.'/lib/bootstrap.php';
require_once __DIR__.'/class/lines.class.php';
$id=GETPOSTINT('id');
try { $d=$packets->detail($user,$id); } catch(Throwable $e) { moError($e); exit; }
moHeader($langs->trans('OrdersPacket').' '.$d['packet']->match_ref);
print '<section data-maxorders="packet"><p>'.moLabel('OrdersStatus').': '.moEsc($d['packet']->status).'</p>';
if($d['packet']->fk_order) print '<p>'.moLabel('OrdersNativeOrder').': <a href="'.DOL_URL_ROOT.'/commande/card.php?id='.(int)$d['packet']->fk_order.'">'.(int)$d['packet']->fk_order.'</a></p>';
if(MaxOrdersPacket::can($user,'review')) print '<p><a class="butAction" href="'.dol_buildpath('/maxorders/review.php',1).'?id='.$id.'">'.moLabel('OrdersReview').'</a></p>';
moTable($d['current'],['kind'=>'OrdersKind','field_name'=>'OrdersField','field_value'=>'OrdersValue','revision_no'=>'OrdersRevision','fk_revision'=>'OrdersSource'],'OrdersSourceFacts');
moTable($d['revision'],['rowid'=>'OrdersSource','kind'=>'OrdersKind','document_ref'=>'OrdersDocument','revision_no'=>'OrdersRevision','status'=>'OrdersStatus'],'OrdersVersions');
moTable($d['fact'],['fk_revision'=>'OrdersSource','field_name'=>'OrdersField','field_value'=>'OrdersValue'],'OrdersImmutableFacts');
moTable($d['lock'],['field_name'=>'OrdersField','field_value'=>'OrdersValue','is_locked'=>'OrdersLocked','fk_user_author'=>'OrdersActor'],'OrdersLocks');
moTable($d['conflict'],['rowid'=>'OrdersID','field_name'=>'OrdersField','old_value'=>'OrdersOld','new_value'=>'OrdersNew','fk_revision'=>'OrdersSource','status'=>'OrdersStatus','decision'=>'OrdersDecision','fk_user_resolver'=>'OrdersActor'],'OrdersConflicts');
moTable($d['audit'],['event'=>'OrdersEvent','field_name'=>'OrdersField','fk_revision'=>'OrdersSource','fk_user_author'=>'OrdersActor','datec'=>'OrdersDate'],'OrdersAudit');
$sourceLines=new MaxOrdersLines($db);
$history=$sourceLines->history($user,$id);
moTable($history,['kind'=>'OrdersKind','document_ref'=>'OrdersDocument','revision_no'=>'OrdersRevision','status'=>'OrdersStatus','content_hash'=>'OrdersFileHash','fk_user_reviewer'=>'OrdersActor','date_reviewed'=>'OrdersDate'],'OrdersSourceHistory');
foreach($history as $source) print '<p><a href="'.dol_buildpath('/maxorders/document.php',1).'?id='.(int)$source->fk_upload.'">'.moEsc($source->original_name).'</a></p>';
$comparison=$sourceLines->comparisons($user,$id);
if($comparison['limited']) print '<p role="note">'.moLabel('OrdersHistoryLimit').'</p>';
moTable($comparison['rows'],['kind'=>'OrdersKind','revision_no'=>'OrdersRevision','status'=>'OrdersStatus','description'=>'OrdersExtract_description','quantity'=>'OrdersExtract_quantity','unit_price'=>'OrdersExtract_unit_price','currency'=>'OrdersExtract_currency','comparison'=>'OrdersComparison','source'=>'OrdersPosition'],'OrdersLineComparison');
print '</section>'; moFooter();
