<?php
require __DIR__.'/lib/http.php';
try {
    $id=GETPOSTINT('id'); $d=$customs->projection($user,$id);
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        customsAction($customs,$user);
        header('Location: '.dol_buildpath('/maxcustoms/card.php',1).'?id='.$id); exit;
    }
} catch(Throwable $e) { customsFailure($e); }
customsPage($langs->trans('CustomsDeclaration').' '.$d['ref']);
print '<section data-maxcustoms="detail"><p>'.customsLabel('CustomsStatus_'.$d['status']).'</p><dl>';
foreach($d['fields'] as $field=>$value) print '<dt>'.customsLabel('CustomsField_'.$field).'</dt><dd>'.customsEscape($value).'</dd>';
print '<dt>'.customsLabel('CustomsField_customer').'</dt><dd>'.(int)$d['customer_id'].'</dd><dt>'.customsLabel('CustomsField_order').'</dt><dd>'.(int)$d['order_id'].'</dd><dt>'.customsLabel('CustomsField_shipments').'</dt><dd>'.customsEscape(implode(', ',$d['shipments'])).'</dd></dl>';
foreach($d['restricted']??[] as $key=>$value) print '<h2>'.customsLabel('CustomsRestricted_'.$key).'</h2><pre>'.customsEscape($value).'</pre>';
print '<p><a href="'.dol_buildpath('/maxcustoms/export.php',1).'?id='.$id.'">'.customsLabel('CustomsExport').'</a></p></section>';
if($d['status']==='draft' && MaxCustomsDeclaration::permits($user,'edit')) {
    print '<section data-maxcustoms="edit"><h2>'.customsLabel('CustomsEdit').'</h2>';
    customsOpenForm('edit',$id,$d['version']); customsFields($d['fields']); customsPrivateInputs($user,$d['restricted']??[]); customsSubmit('CustomsSave'); print '</section>';
}
if($d['status']==='draft' && MaxCustomsDeclaration::permits($user,'match')) {
    print '<section data-maxcustoms="match"><h2>'.customsLabel('CustomsMatch').'</h2><p>'.customsLabel('CustomsMatchRule').'</p>';
    customsOpenForm('match',$id,$d['version']);
    customsInput('customer',(string)$d['customer_id'],'number'); customsInput('order',(string)$d['order_id'],'number'); customsInput('order_reference',(string)$d['order_reference']); customsInput('shipments',implode(',',$d['shipments']));
    print '<label class="maxcustoms-check"><input type="checkbox" name="confirm" value="yes" required> '.customsLabel('CustomsConfirm').'</label>';
    customsSubmit('CustomsMatch'); print '</section>';
}
if(MaxCustomsDeclaration::permits($user,'status')) {
    print '<section data-maxcustoms="status"><h2>'.customsLabel('CustomsChangeStatus').'</h2>';
    customsOpenForm('status',$id,$d['version']); print '<label>'.customsLabel('CustomsTarget').'<select name="target">';
    foreach(['draft','reviewed','approved','closed','cancelled'] as $state) print '<option value="'.$state.'">'.customsLabel('CustomsStatus_'.$state).'</option>';
    print '</select></label><label class="maxcustoms-check"><input type="checkbox" name="confirm" value="yes" required> '.customsLabel('CustomsConfirm').'</label>';
    customsSubmit('CustomsChangeStatus'); print '</section>';
}
if(isset($d['evidence'])) {
    print '<section data-maxcustoms="evidence"><h2>'.customsLabel('CustomsEvidence').'</h2><p>'.customsLabel('CustomsEvidenceRule').'</p><ul>';
    foreach($d['evidence'] as $e) {
        print '<li><a href="'.dol_buildpath('/maxcustoms/download.php',1).'?id='.$id.'&evidence_id='.(int)$e->rowid.'">'.customsEscape($e->display_name).'</a> — '.customsLabel('CustomsEvidence_'.$e->status).'</li>';
        if($d['status']==='draft' && $e->status==='needs_review' && MaxCustomsDeclaration::permits($user,'status')) {
            customsOpenForm('acknowledge',$id,$d['version']); print '<input type="hidden" name="evidence_id" value="'.(int)$e->rowid.'"><label class="maxcustoms-check"><input type="checkbox" name="confirm" value="yes" required> '.customsLabel('CustomsOpaqueAck').'</label>'; customsSubmit('CustomsAcknowledge');
        }
    }
    print '</ul>';
    if($d['status']==='draft' && MaxCustomsDeclaration::permits($user,'edit')) {
        customsOpenForm('evidence',$id,$d['version'],true); print '<label>'.customsLabel('CustomsEvidence').'<input name="evidence" type="file" accept=".txt,text/plain" required></label>'; customsSubmit('CustomsAddEvidence');
    }
    print '</section>';
}
print '<h2>'.customsLabel('CustomsHistory').'</h2><div class="maxcustoms-scroll" role="region" aria-label="'.customsLabel('CustomsHistory').'" tabindex="0"><table><thead><tr><th>'.customsLabel('CustomsEvent').'</th><th>'.customsLabel('CustomsActor').'</th><th>'.customsLabel('CustomsTime').'</th></tr></thead><tbody>';
foreach($d['history'] as $h) print '<tr><td>'.customsLabel('CustomsEvent_'.$h['event']).' '.customsEscape($h['from']).' → '.customsEscape($h['to']).'</td><td>'.(int)$h['actor'].'</td><td>'.customsEscape($h['at']).'</td></tr>';
print '</tbody></table></div>'; customsEnd();
