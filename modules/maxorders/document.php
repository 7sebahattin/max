<?php
require __DIR__.'/lib/bootstrap.php';
require_once __DIR__.'/class/document.class.php';
$documents=new MaxOrdersDocument($db); $id=GETPOSTINT('id'); $notice='';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { http_response_code(405); exit; }
try {
 $document=$documents->get($user,$id);
 if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
  if(GETPOST('action','aZ09')!=='associate') throw new InvalidArgumentException('OrdersInvalid',400);
  $documents->associate($user,$id,GETPOSTINT('packet'),GETPOSTINT('customer'),GETPOST('reference','none'),GETPOST('kind','aZ09'),GETPOST('document','none'),GETPOSTINT('revision'),GETPOST('facts','none'),GETPOST('confirmed','aZ09')==='yes');
  $document=$documents->get($user,$id); $notice='OrdersSaved';
 }
} catch(Throwable $e) {
 $code=in_array($e->getCode(),[400,403,404,409],true)?$e->getCode():500;
 if($code===500) $e=new RuntimeException('OrdersStorageError',500);
 moError($e); if(in_array($code,[403,404,500],true)) exit;
 $document=$documents->get($user,$id);
}
$analysis=json_decode($document->analysis_json,true,32,JSON_THROW_ON_ERROR);
$values=MaxOrdersExtraction::unique($analysis); $facts=[];
foreach(['quantity'=>'quantity','unit_price'=>'unit_price','product_reference'=>'sku','packaging'=>'packaging','currency'=>'currency'] as $source=>$target) if(isset($values[$source])) $facts[$target]=$values[$source];
moHeader($langs->trans('OrdersDocumentReview'));
print '<section data-maxorders="document"><p><a href="'.dol_buildpath('/maxorders/documents.php',1).'">'.moLabel('OrdersDocuments').'</a></p>';
print '<p role="status">'.moLabel($notice).'</p><h2>'.moEsc($document->original_name).'</h2><p>'.moLabel('OrdersAnalysis').': '.moValue($document->analysis_status).'</p><p>'.moLabel('OrdersAssociation').': '.moValue($document->link_status).'</p>';
print '<p><a data-orders-download href="'.dol_buildpath('/maxorders/download.php',1).'?id='.$id.'">'.moLabel('OrdersDownload').'</a></p><p>'.moLabel('OrdersProposalOnly').'</p>';
foreach($analysis['issues'] as $issue) print '<p role="note">'.moLabel($issue).'</p>';
if($analysis['missing']) { print '<p>'.moLabel('OrdersMissing').': '; foreach($analysis['missing'] as $key) print moLabel('OrdersExtract_'.$key).' '; print '</p>'; }
$rows=[];
foreach($analysis['fields'] as $field) $rows[]=(object)['name'=>$langs->trans('OrdersExtract_'.$field['field']),'raw'=>$field['raw'],'value'=>$field['value'],'status'=>$field['state'],'position'=>$field['source']['sheet'].'!'.$field['source']['cell'].($field['truncated']?' …':'')];
moTable($rows,['name'=>'OrdersField','raw'=>'OrdersRaw','value'=>'OrdersValue','status'=>'OrdersStatus','position'=>'OrdersPosition'],'OrdersExtracted');
if($document->fk_packet) {
 print '<p><a href="'.dol_buildpath('/maxorders/packet.php',1).'?id='.(int)$document->fk_packet.'">'.moLabel('OrdersPacket').' #'.(int)$document->fk_packet.'</a> — '.moValue($document->link_status).'</p>';
} elseif(MaxOrdersPacket::can($user,'review') && MaxOrdersPacket::can($user,'intake')) {
 print '<h2>'.moLabel('OrdersMatches').'</h2><p>'.moLabel('OrdersHumanAssociation').'</p><ul>';
 foreach($documents->suggestions($user,$id) as $packet) print '<li>#'.(int)$packet->rowid.' — '.moEsc($packet->match_ref).' ('.moLabel('OrdersCustomer').' '.(int)$packet->fk_soc.')</li>';
 print '</ul>'; moForm('associate',$id);
 moInput('packet','OrdersPacketID','number'); moInput('customer','OrdersCustomer','number');
 moInput('reference','OrdersReference','text',$values['order_reference']??'');
 print '<label>'.moLabel('OrdersKind').'<select name="kind">'; foreach(['UNKNOWN','PO','LL','PC'] as $kind) print '<option'.(($values['document_kind']??'UNKNOWN')===$kind?' selected':'').'>'.$kind.'</option>'; print '</select></label>';
 moInput('document','OrdersDocument','text',$values['document_reference']??''); moInput('revision','OrdersRevision','number','1');
 print '<label>'.moLabel('OrdersFacts').'<textarea name="facts" maxlength="4096" rows="5" required>'.moEsc(json_encode((object)$facts,JSON_UNESCAPED_UNICODE)).'</textarea></label><label class="maxorders-check"><input type="checkbox" name="confirmed" value="yes" required> '.moLabel('OrdersConfirmDocument').'</label>';
 moButton('OrdersAssociate');
}
print '</section>'; moFooter();
