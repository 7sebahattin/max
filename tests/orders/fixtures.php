<?php
// Synthetic bytes only. No customer documents, credentials or remote resources.
function ordersFixtureFields($kind='PO',$reference='PACK-100',$quantity='100') {
 return [['document_kind',$kind],['order_reference',$reference],['document_reference',$kind.'-DEMO'],['document_date','2026-11-05'],['customer_reference','ORDERS-DEMO'],['customer_name','Orders Synthetic Customer'],['product_reference','DEMO'],['description','Synthetic product'],['quantity',$quantity],['unit','CASE'],['unit_price','2.50'],['currency','EUR'],['packaging','BOX']];
}
function ordersFixtureXlsx($fields,$extra=[],$mode='') {
 $xml=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');
 $cell=fn($ref,$v)=>'<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$xml($v).'</t></is></c>';
 $rows='<row r="1">'.$cell('A1','MAX_ORDERS_V02').'</row><row r="2">'.$cell('A2','field').$cell('B2','value').'</row>';
 foreach($fields as $i=>[$key,$value]) {
  $row=$i+3; $v=is_array($value)?($value[0]==='formula'?'<c r="B'.$row.'"><f>'.$xml($value[1]).'</f><v>999</v></c>':'<c r="B'.$row.'" t="n"><v>'.$xml($value[1]).'</v></c>'):$cell('B'.$row,$value);
  $rows.='<row r="'.$row.'"'.($mode==='hidden'?' hidden="1"':'').'>'.$cell('A'.$row,$key).$v.'</row>';
 }
 $files=[
  '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
  '_rels/.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
  'xl/workbook.xml'=>'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Synthetic" sheetId="1" r:id="rId1"/></sheets></workbook>',
  'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
  'xl/styles.xml'=>'<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
  'xl/worksheets/sheet1.xml'=>'<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:B'.(count($fields)+2).'"/><sheetData>'.$rows.'</sheetData></worksheet>'
 ];
 foreach($extra as $key=>$value) $files[$key]=$value;
 $path=tempnam(sys_get_temp_dir(),'orders-fixture-'); $zip=new ZipArchive();
 if($zip->open($path,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Fixture ZIP creation failed');
 try { foreach($files as $key=>$value) { if(!$zip->addFromString($key,$value)) throw new RuntimeException('Fixture entry failed'); $zip->setMtimeName($key,1700000000); } }
 finally { $zip->close(); }
 try { return file_get_contents($path); } finally { unlink($path); }
}
function ordersFixturePdf($scan=false) {
 // Valid one-page PDF, with an actual xref and literal Helvetica text layer.
 $stream=$scan?'q 10 0 0 10 50 700 cm /Im1 Do Q':"BT /F1 12 Tf 50 700 Td (Order reference: PACK-100) Tj ET";
 $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>','<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
 if($scan) {
  $objects[2]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>';
  $objects[3]="<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length 3 >>\nstream\n\xff\xff\xff\nendstream";
 }
 $pdf="%PDF-1.4\n"; $offsets=[0];
 foreach($objects as $i=>$object) { $offsets[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n".$object."\nendobj\n"; }
 $xref=strlen($pdf); $pdf.="xref\n0 6\n0000000000 65535 f \n";
 foreach(array_slice($offsets,1) as $offset) $pdf.=sprintf("%010d 00000 n \n",$offset);
 return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
