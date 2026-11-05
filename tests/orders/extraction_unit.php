<?php
// Real PHP and bundled Dolibarr reader; no DB mocks, providers or binary fixtures.
if(PHP_SAPI!=='cli') exit(2);
define('DOL_DOCUMENT_ROOT',realpath($argv[1]??'/opt/dolibarr/htdocs'));
$module=realpath(__DIR__.'/../../modules/maxorders')?:DOL_DOCUMENT_ROOT.'/custom/maxorders';
require $module.'/class/extraction.class.php';
require __DIR__.'/fixtures.php';
$report=[];
function checkExtraction($ok,$label) { global $report; if(!$ok) throw new RuntimeException('FAIL '.$label); $report[]=['check'=>$label,'status'=>'PASS']; echo 'PASS '.$label."\n"; }
function rejectsExtraction($name,$bytes,$label) { try { MaxOrdersExtraction::inspect($name,$bytes); } catch(InvalidArgumentException $e) { checkExtraction($e->getCode()===400,$label); return; } throw new RuntimeException('FAIL accepted '.$label); }
$valid=ordersFixtureXlsx(ordersFixtureFields()); $a=MaxOrdersExtraction::inspect('unrelated-name.xlsx',$valid);
checkExtraction($a['status']==='suggested' && count($a['fields'])===13 && !$a['missing'],'13 deterministic XLSX proposals');
$v=MaxOrdersExtraction::unique($a);
checkExtraction($v['document_kind']==='PO' && $v['quantity']==='100' && $a['fields'][8]['source']===['sheet'=>'Synthetic','cell'=>'B11','label_cell'=>'A11'],'content type and exact cell provenance');
$numeric=ordersFixtureFields(); $numeric[8][1]=['number','1.230'];
$a=MaxOrdersExtraction::inspect('numeric.xlsx',ordersFixtureXlsx($numeric));
checkExtraction(MaxOrdersExtraction::unique($a)['quantity']==='1.230','raw numeric literal without float/locale conversion');
$formula=ordersFixtureFields();$formula[8][1]=['formula','1+1'];
$a=MaxOrdersExtraction::inspect('formula.xlsx',ordersFixtureXlsx($formula));
checkExtraction($a['status']==='needs_review' && !$a['fields'] && $a['issues']===['OrdersFormulaBlocked'],'formulas and cached result never extracted or evaluated');
$a=MaxOrdersExtraction::inspect('conflict.xlsx',ordersFixtureXlsx(array_merge(ordersFixtureFields(),[['quantity','98']])));
checkExtraction($a['status']==='needs_review' && !isset(MaxOrdersExtraction::unique($a)['quantity']) && count(array_filter($a['fields'],fn($f)=>$f['state']==='ambiguous'))===2,'conflicting quantities retain both source cells');
$bad=ordersFixtureFields();$bad[3][1]='2026-02-30';$bad[8][1]='1,000';
$a=MaxOrdersExtraction::inspect('uncertain.xlsx',ordersFixtureXlsx($bad));
checkExtraction($a['status']==='needs_review' && !isset(MaxOrdersExtraction::unique($a)['quantity']) && !isset(MaxOrdersExtraction::unique($a)['document_date']),'invalid dates and ambiguous decimal formats');
$a=MaxOrdersExtraction::inspect('hidden.xlsx',ordersFixtureXlsx(ordersFixtureFields(),[],'hidden'));
checkExtraction($a['status']==='needs_review' && !$a['fields'],'hidden rows not silently trusted');
$a=MaxOrdersExtraction::inspect('unknown.xlsx',ordersFixtureXlsx([['order_reference','PACK-100']]));
checkExtraction($a['status']==='needs_review' && in_array('document_kind',$a['missing']),'missing fields visible without filename classification');
require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
$pdf=new TCPDF();$pdf->setPrintHeader(false);$pdf->setPrintFooter(false);
$pdf->SetProtection(['copy'],'synthetic-read','synthetic-owner');$pdf->AddPage();$pdf->SetFont('helvetica','',10);$pdf->Write(0,'Synthetic protected document');
$encrypted=$pdf->Output('','S');
foreach(['text'=>ordersFixturePdf(),'image-only'=>ordersFixturePdf(true),'damaged'=>"%PDF-1.7\ntruncated",'encrypted'=>$encrypted] as $label=>$bytes) {
 $a=MaxOrdersExtraction::inspect('PO-'.$label.'.pdf',$bytes);
 checkExtraction($a['status']==='needs_review' && !$a['fields'] && $a['issues']===['OrdersPdfExtractionBlocked'],'PDF '.$label.' quarantined without guessed extraction');
}
rejectsExtraction('../bad.xlsx',$valid,'filename traversal');
rejectsExtraction('bad\\name.xlsx',$valid,'backslash path');
rejectsExtraction("bad\r\nname.xlsx",$valid,'header/control characters');
rejectsExtraction('script.php',$valid,'extension mismatch');
rejectsExtraction('fake.xlsx',ordersFixturePdf(),'MIME and magic mismatch');
rejectsExtraction('fake.pdf','not a PDF document','PDF invalid signature');
rejectsExtraction('large.pdf',"%PDF-1.7\n".str_repeat('x',2097152),'size cap');
rejectsExtraction('traversal.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['../outside.xml'=>'<x/>']),'ZIP traversal structure');
rejectsExtraction('macro.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['xl/vbaProject.bin'=>'synthetic']),'macro content rejected');
rejectsExtraction('bomb.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['docProps/core.xml'=>'<x>'.str_repeat('a',200000).'</x>']),'ZIP compression ratio bomb');
rejectsExtraction('big-entry.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['docProps/core.xml'=>'<x>'.str_repeat('a',1048577).'</x>']),'ZIP entry size bomb');
rejectsExtraction('entity.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['docProps/core.xml'=>'<!DOCTYPE x [<!ENTITY data SYSTEM "file:///etc/passwd">]><x>&data;</x>']),'XML entity/DTD rejected before native reader');
rejectsExtraction('external.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['_rels/.rels'=>'<Relationships><Relationship TargetMode="External" Target="https://example.invalid/no-call"/></Relationships>']),'external relationships cannot be loaded');
rejectsExtraction('bad-xml.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['xl/workbook.xml'=>'<broken>']),'malformed XML rejected');
$many=array_fill(0,128,['quantity','1']); rejectsExtraction('big-sheet.xlsx',ordersFixtureXlsx($many),'worksheet row/cell bounds');
rejectsExtraction('negative-column.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['xl/worksheets/sheet1.xml'=>'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="-99999999" max="2"/></cols></worksheet>']),'negative column allocation bounds');
rejectsExtraction('merged-range.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['xl/worksheets/sheet1.xml'=>'<worksheet><mergeCells><mergeCell ref="A1:XFD1048576"/></mergeCells></worksheet>']),'unbounded merged ranges rejected');
rejectsExtraction('duplicate-case.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['XL/WORKBOOK.XML'=>'<workbook/>']),'case-colliding ZIP part rejected');
rejectsExtraction('duplicate-sheets.xlsx',ordersFixtureXlsx(ordersFixtureFields(),['xl/workbook.xml'=>'<workbook><sheets><sheet/><sheet/></sheets></workbook>']),'worksheet reference count bounded before native load');
// Compatibility with the actual bundled native XLSX writer, not only our fixture.
require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $sheet=$book->getActiveSheet(); $sheet->setTitle('Native');
$sheet->setCellValueExplicit('A1','MAX_ORDERS_V02','s');$sheet->setCellValueExplicit('A2','field','s');$sheet->setCellValueExplicit('B2','value','s');
foreach(ordersFixtureFields() as $i=>[$key,$value]) { $sheet->setCellValueExplicit('A'.($i+3),$key,'s');$sheet->setCellValueExplicit('B'.($i+3),$value,'s'); }
$path=tempnam(sys_get_temp_dir(),'orders-native-writer-');
try { (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path); $a=MaxOrdersExtraction::inspect('native.xlsx',file_get_contents($path)); checkExtraction($a['status']==='suggested' && count($a['fields'])===13,'bundled native XLSX writer interoperability'); }
finally { $book->disconnectWorksheets(); unlink($path); }
if(isset($argv[2])) file_put_contents($argv[2],json_encode(['passed'=>true,'checks'=>$report,'pdf_extraction'=>'BLOCKED','parser'=>'bundled PhpSpreadsheet / bounded template'],JSON_PRETTY_PRINT));
echo 'PASS '.count($report).' real-file extraction/security checks; PDF extraction BLOCKED'."\n";
