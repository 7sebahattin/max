<?php
// Capability-dependent real offline PDF tests. Missing sandbox is BLOCKED, never PASS.
if(PHP_SAPI!=='cli')exit(2);
define('DOL_DOCUMENT_ROOT',realpath($argv[1]??'/opt/dolibarr/htdocs'));
$module=realpath(__DIR__.'/../../modules/maxorders')?:DOL_DOCUMENT_ROOT.'/custom/maxorders';
require $module.'/class/runner.class.php';
function getDolGlobalInt($name){return $name==='MAXORDERS_PDF_SAFE_ENABLE'?1:0;}require __DIR__.'/fixtures.php';
require DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
$checks=[];
function pdfCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
function pdfBytes($text,$pages=1,$encrypted=false,$active=false){
 if($pages===1 && !$encrypted && strlen($text)<1000){
  $stream="BT /F1 10 Tf 50 760 Td ";
  foreach(explode("\n",$text) as $line)$stream.='('.str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line).") Tj 0 -15 Td ";
  $stream.='ET';
  $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
  if($active)$objects[0]='<< /Type /Catalog /Pages 2 0 R /OpenAction << /S /JavaScript /JS (synthetic-no-execution) >> >>';
  $pdf="%PDF-1.4\n";$offsets=[];foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
  $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";foreach($offsets as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
  return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
 }
 $p=new TCPDF();$p->setPrintHeader(false);$p->setPrintFooter(false);if($encrypted)$p->SetProtection(['copy'],'synthetic','synthetic-owner');
 for($i=0;$i<$pages;$i++){$p->AddPage();$p->SetFont('helvetica','',10);$p->MultiCell(0,5,$i===0?$text:'',0,'L');}
 return $p->Output('','S');
}
$text="MAX_ORDERS_PDF_V03\ndocument_kind: PO\norder_reference: PDF-100\ndocument_reference: PDF-SOURCE\ncustomer_reference: SYNTHETIC\nquantity: 12.5\ncurrency: EUR";
$before=glob(sys_get_temp_dir().'/maxorders-pdf-*');$bytes=pdfBytes($text);$a=MaxOrdersExtraction::inspect('text.pdf',$bytes,['pdf_sandbox'=>true]);
if(in_array('OrdersPdfSandboxBlocked',$a['issues'],true)){
 $result=['status'=>'BLOCKED','reason'=>'Reviewed Poppler / Bubblewrap / prlimit / unprivileged namespace capability unavailable','checks'=>[],'native_pdf_default'=>'BLOCKED'];
 if(isset($argv[2]))file_put_contents($argv[2],json_encode($result,JSON_PRETTY_PRINT));
 echo 'BLOCKED optional PDF sandbox capability; text cases NOT RUN'."\n";exit;
}
pdfCheck(($a['template']??'')==='text-pdf-v03' && $a['status']==='suggested','real Poppler text extraction inside network-denied sandbox');
pdfCheck(MaxOrdersExtraction::unique($a)['quantity']==='12.5' && $a['fields'][0]['source']['page']===1 && $a['fields'][0]['source']['line']===2 && $a['fields'][0]['source']['offset']>0 && $a['fields'][0]['raw']===' PO','deterministic text fields with page line and byte offset');
foreach(['active-script'=>pdfBytes($text,1,false,true),'scanned'=>ordersFixturePdf(true),'encrypted'=>pdfBytes($text,1,true),'malformed'=>"%PDF-1.7\ntruncated",'too-many-pages'=>pdfBytes($text,11),'unknown-layout'=>ordersFixturePdf(),'unknown-field'=>pdfBytes($text."\nunknown: no"),'too-much-text'=>pdfBytes($text."\ndescription: ".str_repeat('x',66000))] as $label=>$input){
 $a=MaxOrdersExtraction::inspect('unsafe.pdf',$input,['pdf_sandbox'=>true]);
 pdfCheck($a['status']==='needs_review' && !$a['fields'],'safe PDF review '.$label);
}
$a=MaxOrdersExtraction::inspect('duplicate.pdf',pdfBytes($text."\nquantity: 9"),['pdf_sandbox'=>true]);
pdfCheck(!isset(MaxOrdersExtraction::unique($a)['quantity']) && count(array_filter($a['fields'],fn($f)=>$f['state']==='ambiguous'))===2,'duplicate text fields never choose a quantity');
$worker=MaxOrdersRunner::inspect('worker.pdf',$bytes);
pdfCheck(($worker['template']??'')==='text-pdf-v03' && isset($worker['resource_limits']),'PDF opt-in through resource-bounded analysis worker');
pdfCheck(glob(sys_get_temp_dir().'/maxorders-pdf-*')===$before,'PDF temporary input cleanup');
pdfCheck(MaxOrdersExtraction::inspect('default.pdf',$bytes)['issues']===['OrdersPdfExtractionBlocked'],'PDF opt-in remains disabled by default');
$result=['status'=>'PASS','checks'=>$checks,'native_pdf_default'=>'BLOCKED','scope'=>'explicit MAX_ORDERS_PDF_V03 text layout only'];
if(isset($argv[2]))file_put_contents($argv[2],json_encode($result,JSON_PRETTY_PRINT));
echo 'PASS '.count($checks).' real offline PDF checks; native default remains BLOCKED'."\n";
