<?php
// Real XLSX reader, synthetic source only. No database/provider.
if(PHP_SAPI!=='cli')exit(2);
define('DOL_DOCUMENT_ROOT',realpath($argv[1]));
require __DIR__.'/../../modules/maxorders/class/extraction.class.php';
require __DIR__.'/../orders/fixtures.php';
$checks=[];
function sourceCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
$fields=ordersFixtureFields();$fields[5][1]='istanbul ihracat';$fields[6][1]='CaseSKU';$fields[7][1]='çiçek';$fields[9][1]='koli';$fields[12][1]='özel kutu';
$bytes=ordersFixtureXlsx($fields);$sha=hash('sha256',$bytes);$a=MaxOrdersExtraction::inspect('synthetic.xlsx',$bytes);$v=MaxOrdersExtraction::unique($a);
sourceCheck($a['status']==='suggested'&&$v['customer_name']==='İSTANBUL İHRACAT'&&$v['description']==='ÇİÇEK'&&$v['packaging']==='ÖZEL KUTU'&&$v['unit']==='KOLİ','file/import proposals uppercase deterministically');
sourceCheck($a['fields'][5]['raw']==='istanbul ihracat'&&$a['fields'][7]['raw']==='çiçek'&&hash('sha256',$bytes)===$sha,'raw values and original source hash unchanged');
sourceCheck($v['product_reference']==='CaseSKU'&&$v['document_reference']==='PO-DEMO'&&$v['quantity']==='100'&&$v['unit_price']==='2.50','source identifiers quantities and decimal literals exact');
$fields[7][1]=str_repeat('ß',200);$a=MaxOrdersExtraction::inspect('expansion.xlsx',ordersFixtureXlsx($fields));
sourceCheck($a['status']==='needs_review'&&$a['fields'][7]['state']==='uncertain'&&!$a['fields'][7]['truncated']&&$a['fields'][7]['raw']===$fields[7][1]&&!isset(MaxOrdersExtraction::unique($a)['description']),'expansion overflow is uncertain without falsely claiming raw truncation');
$pdf=ordersFixturePdf();$before=hash('sha256',$pdf);$a=MaxOrdersExtraction::inspect('synthetic.pdf',$pdf);
sourceCheck($a['issues']===['OrdersPdfExtractionBlocked']&&hash('sha256',$pdf)===$before,'PDF source remains private manual-review evidence without conversion');
if(isset($argv[2]))file_put_contents($argv[2],json_encode(['passed'=>true,'scope'=>'real_file_parser_only','checks'=>$checks,'installed_native'=>'NOT RUN'],JSON_PRETTY_PRINT));
echo 'PASS '.count($checks)." source preservation checks\n";
