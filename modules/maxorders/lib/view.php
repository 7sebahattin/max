<?php
// All content goes through HTML escaping; no raw metadata is logged.
function moEsc($v) { return dol_escape_htmltag((string)$v); }
function moValue($value) {
 global $langs;
 $key='OrdersValue_'.(string)$value;
 $translated=$langs->trans($key);
 return moEsc($translated===$key?$value:$translated);
}
function moLabel($key) { global $langs; return moEsc($langs->trans($key)); }
function moHeader($title) { llxHeader('',$title,'','',0,0,[],['/maxorders/css/orders.css'],'','maxorders-page'); print '<main class="maxorders-shell"><h1>'.moEsc($title).'</h1><p>'.moLabel('OrdersNoEffects').'</p>'; }
function moFooter() { global $db; print '<p>'.moLabel('OrdersAttribution').'</p></main>'; llxFooter(); $db->close(); }
function moInput($name,$label,$type='text',$value='') { print '<label>'.moLabel($label).'<input name="'.moEsc($name).'" type="'.moEsc($type).'" value="'.moEsc($value).'" required maxlength="80"></label>'; }
function moForm($action,$id=0) { print '<form method="post"><input type="hidden" name="token" value="'.moEsc(newToken()).'"><input type="hidden" name="action" value="'.moEsc($action).'"><input type="hidden" name="id" value="'.(int)$id.'">'; }
function moButton($label) { print '<button type="submit">'.moLabel($label).'</button></form>'; }
function moError($e) { $code=in_array($e->getCode(),[400,403,404,409],true)?$e->getCode():500; http_response_code($code); if(in_array($code,[403,404],true)) { accessforbidden('',0,0,1); } print '<p role="alert">'.moLabel($e->getMessage()).'</p>'; }
function moTable($rows,$columns,$label) {
 print '<h2>'.moLabel($label).'</h2><div class="maxorders-scroll" tabindex="0" role="region" aria-label="'.moLabel($label).'"><table><thead><tr>';
 foreach($columns as $column=>$label) print '<th scope="col">'.moLabel($label).'</th>';
 print '</tr></thead><tbody>';
 foreach($rows as $row) { print '<tr>'; foreach($columns as $column=>$label) print '<td>'.(in_array($column,['field_name','status','decision','event','comparison'],true)?moValue($row->$column??''):moEsc($row->$column??'')).'</td>'; print '</tr>'; }
 print '</tbody></table></div>';
}
