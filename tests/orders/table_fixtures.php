<?php
require_once __DIR__.'/fixtures.php';
function ordersTableRows($quantity='1000') {
 return [['Strawberry','Felicity','Standard',$quantity,'PCS','0.25','USD'],['Pomegranate','Hicaz','9','500','BOX','8.50','EUR'],['Lemon','Interdonato','12','800','BOX','7.20','EUR']];
}
function ordersTableFixture($rows=null,$meta=[],$columns=null,$sheetExtra='') {
 $rows=$rows??ordersTableRows(); $columns=$columns??MaxOrdersTable::DEFAULT_COLUMNS;
 $meta=array_replace(['document_kind'=>'PO','order_reference'=>'PACK-100','document_reference'=>'TABLE-DEMO','document_revision'=>'2','customer_reference'=>'ORDERS-DEMO','customer_name'=>'Orders Synthetic Customer','document_date'=>'2026-11-05'],$meta);
 $escape=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');
 $cell=function($ref,$value)use($escape){
  if(is_array($value)) return '<c r="'.$ref.'"><'.($value[0]==='formula'?'f':'v').'>'.$escape($value[1]).'</'.($value[0]==='formula'?'f':'v').'>'.($value[0]==='formula'?'<v>999</v>':'').'</c>';
  return '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$escape($value).'</t></is></c>';
 };
 $xml='<row r="1">'.$cell('A1',MaxOrdersTable::MARKER).'</row>'; $row=2;
 foreach($meta as $key=>$value){$xml.='<row r="'.$row.'">'.$cell('A'.$row,$key).$cell('B'.$row,$value).'</row>';$row++;}
 $xml.='<row r="9">';$col=0;foreach($columns as $label)$xml.=$cell(chr(65+$col++).'9',$label);$xml.='</row>';
 foreach($rows as $index=>$values){$row=$index+10;$xml.='<row r="'.$row.'">';foreach($values as $col=>$value)$xml.=$cell(chr(65+$col).$row,$value);$xml.='</row>';}
 $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.chr(64+count($columns)).max(10,count($rows)+9).'"/>'.$sheetExtra.'<sheetData>'.$xml.'</sheetData></worksheet>';
 return ordersFixtureXlsx([],['xl/worksheets/sheet1.xml'=>$sheet]);
}
