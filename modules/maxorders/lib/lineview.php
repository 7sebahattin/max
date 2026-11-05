<?php
// Escaped proposals and immutable provenance, never an order editor. GPL-3.0-or-later.
function moSourceLines($lines,$hash) {
 print '<section data-orders-source-lines><h2>'.moLabel('OrdersSourceLines').'</h2><p>'.moLabel('OrdersFileHash').': '.moEsc($hash).'</p><p>'.moLabel('OrdersNoLineAggregation').'</p>';
 foreach($lines as $line) {
  print '<article><h3>'.moEsc($line->source_sheet).'!'.(int)$line->source_row.' — '.moValue($line->proposal_state).' / '.moValue($line->review_status).'</h3>';
  $source=json_decode($line->source_json,true,40,JSON_THROW_ON_ERROR);
  foreach($source['issues'] as $issue) print '<p role="note">'.moLabel($issue).'</p>';
  $rows=[]; foreach($source['cells'] as $cell) $rows[]=(object)['name'=>moSourceLabel($cell['field']),'raw'=>$cell['raw'],'value'=>$cell['value'],'status'=>$cell['state'],'position'=>$cell['source']['sheet'].'!'.$cell['source']['cell']];
  moTable($rows,['name'=>'OrdersField','raw'=>'OrdersRaw','value'=>'OrdersValue','status'=>'OrdersStatus','position'=>'OrdersPosition'],'OrdersLineCells');
  print '</article>';
 }
 print '</section>';
}
function moSourceLabel($field) { global $langs; return $langs->trans('OrdersExtract_'.$field); }
