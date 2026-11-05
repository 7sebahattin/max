<?php
/** Bounded proposals, never commercial truth. GPL-3.0-or-later. */
require_once __DIR__.'/table.class.php';
class MaxOrdersExtraction {
 const MAX_BYTES=2097152;
 const FIELDS=['document_kind','order_reference','document_reference','document_date','customer_reference','customer_name','product_reference','description','quantity','unit','unit_price','currency','packaging'];

 public static function validate($name,$bytes) {
  if(!is_string($name) || preg_match('//u',$name)!==1 || $name!==basename($name) || preg_match('/[\\\\\/\x00-\x1f\x7f:]/u',$name) || mb_strlen($name)>120 || !is_string($bytes) || strlen($bytes)<8 || strlen($bytes)>self::MAX_BYTES) throw new InvalidArgumentException('OrdersFileInvalid',400);
  $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if($extension==='pdf' && $mime==='application/pdf' && preg_match('/^%PDF-(?:1\.[0-7]|2\.0)[\r\n]/D',$bytes)) return [$extension,$mime];
  if($extension==='xlsx' && in_array($mime,['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],true) && substr($bytes,0,4)==="PK\x03\x04") return [$extension,$mime];
  throw new InvalidArgumentException('OrdersFileInvalid',400);
 }
 public static function inspect($name,$bytes,$options=[]) {
  [$extension,$mime]=self::validate($name,$bytes);
  if($extension==='pdf' && $mime==='application/pdf' && preg_match('/^%PDF-(?:1\.[0-7]|2\.0)[\r\n]/D',$bytes)) {
   if(!empty($options['pdf_sandbox'])) { require_once __DIR__.'/pdf.class.php'; return MaxOrdersPdf::inspect($bytes); }
   // No safe general text extractor is bundled. Never feed hostile PDF objects
   // to the upstream merge parser, render them, or guess from filename/bytes.
   return ['format'=>'pdf','mime'=>$mime,'status'=>'needs_review','fields'=>[],
    'missing'=>self::FIELDS,'issues'=>['OrdersPdfExtractionBlocked']];
  }
  if($extension!=='xlsx' || !in_array($mime,['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],true) || substr($bytes,0,4)!=="PK\x03\x04") throw new InvalidArgumentException('OrdersFileInvalid',400);
  if(!class_exists('ZipArchive') || !class_exists('DOMDocument')) throw new RuntimeException('OrdersParserBlocked',503);
  $temp=tempnam(sys_get_temp_dir(),'maxorders-');
  if(!$temp) throw new RuntimeException('OrdersStorageError',500);
  try {
   if(defined('DOL_DOCUMENT_ROOT') && str_starts_with(realpath($temp),rtrim(realpath(DOL_DOCUMENT_ROOT)?:DOL_DOCUMENT_ROOT,'/').'/')) throw new RuntimeException('OrdersParserBlocked',503);
   chmod($temp,0600);
   if(file_put_contents($temp,$bytes)!==strlen($bytes)) throw new RuntimeException('OrdersStorageError',500);
   $raw=self::preflight($temp);
   $result=['format'=>'xlsx','mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','status'=>'needs_review','fields'=>[],'missing'=>self::FIELDS,'issues'=>[]];
   if($raw['formulas']) { $result['issues']=['OrdersFormulaBlocked']; return $result; }
   $loader=DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
   if(!is_file($loader)) { $result['issues']=['OrdersParserBlocked']; return $result; }
   require_once $loader;
   require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
   $reader=new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
   $reader->setReadDataOnly(true);
   try { $book=$reader->load($temp); } catch(Throwable $e) { $result['issues']=['OrdersWorkbookUnreadable']; return $result; }
   try {
    if($book->getSheetCount()!==1 || $raw['hidden']) { $result['issues']=['OrdersLayoutReview']; return $result; }
    $sheet=$book->getSheet(0);
    if($sheet->getCell('A1')->getValue()===MaxOrdersTable::MARKER) return MaxOrdersTable::parse($sheet,$raw,$options);
    foreach($sheet->getCoordinates() as $ref) if(!preg_match('/^[AB][0-9]+$/D',$ref) && $sheet->getCell($ref)->getValue()!==null && $sheet->getCell($ref)->getValue()!=='') { $result['issues']=['OrdersLayoutReview']; return $result; }
    if($sheet->getCell('A1')->getValue()!=='MAX_ORDERS_V02' || $sheet->getCell('A2')->getValue()!=='field' || $sheet->getCell('B2')->getValue()!=='value') { $result['issues']=['OrdersLayoutReview']; return $result; }
    $seen=[];
    for($row=3;$row<=128;$row++) {
     $key=$sheet->getCell('A'.$row)->getValue(); $cell=$sheet->getCell('B'.$row);
     if(($key===null || $key==='') && ($cell->getValue()===null || $cell->getValue()==='')) continue;
     if(!is_string($key) || !in_array($key,self::FIELDS,true)) { $result['issues'][]='OrdersUnknownField'; continue; }
     // Raw XML numeric literals avoid formatting, locale, float rounding and
     // cached formula results. No getCalculatedValue()/formatted data is used.
     $value=$cell->getDataType()==='n'?($raw['numbers']['B'.$row]??''):$cell->getValue();
     if(!is_string($value)) $value='';
     $truncated=mb_strlen($value)>255; $rawValue=mb_substr($value,0,255);
     $value=trim($rawValue); $valid=!$truncated && self::valid($key,$value);
     $result['fields'][]=['field'=>$key,'value'=>$value,'raw'=>$rawValue,'truncated'=>$truncated,'state'=>$valid?'suggested':'uncertain',
      'source'=>['sheet'=>$sheet->getTitle(),'cell'=>'B'.$row,'label_cell'=>'A'.$row]];
     $seen[$key]=($seen[$key]??0)+1;
     if(!$valid) $result['issues'][]='OrdersUncertainField';
    }
    foreach($result['fields'] as &$field) if($seen[$field['field']]>1) { $field['state']='ambiguous'; $result['issues'][]='OrdersAmbiguousField'; }
    unset($field);
    $result['missing']=array_values(array_diff(self::FIELDS,array_keys($seen)));
    foreach(['document_kind','order_reference','document_reference'] as $required) if(empty($seen[$required])) $result['issues'][]='OrdersMissingField';
    if(empty($seen['customer_reference']) && empty($seen['customer_name'])) $result['issues'][]='OrdersMissingField';
    $result['issues']=array_values(array_unique($result['issues']));
    $result['status']=$result['issues']?'needs_review':'suggested';
    return $result;
   } finally { $book->disconnectWorksheets(); }
  } finally { if(is_file($temp) && !unlink($temp)) throw new RuntimeException('OrdersStorageError',500); }
 }

 public static function valid($key,$value) {
  if($value==='' || mb_strlen($value)>($key==='description'?255:80) || preg_match('/[\x00-\x1f\x7f<>]/u',$value)!==0) return false;
  if($key==='document_kind') return in_array($value,['PO','LL','PC'],true);
  if($key==='quantity') return (bool)preg_match('/^\d{1,9}(\.\d{1,3})?$/D',$value);
  if($key==='unit_price') return (bool)preg_match('/^\d{1,9}(\.\d{1,4})?$/D',$value);
  if($key==='currency') return (bool)preg_match('/^[A-Z]{3}$/D',$value);
  if($key==='document_date') { $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value); return $date && $date->format('Y-m-d')===$value; }
  return true;
 }

 /** Reject before invoking the bundled reader. Never extract ZIP paths. */
 private static function preflight($path) {
  $zip=new ZipArchive(); if($zip->open($path,ZipArchive::RDONLY)!==true) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
  try {
   if($zip->numFiles>64) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
   $seen=[]; $total=0; $nodes=0; $numbers=[]; $formulas=false; $hidden=false;
   for($i=0;$i<$zip->numFiles;$i++) {
    $stat=$zip->statIndex($i); if(!$stat) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $name=$stat['name'];
    if(isset($seen[strtolower($name)]) || !preg_match('~^(?:\[Content_Types\]\.xml|_rels/\.rels|docProps/(?:app|core)\.xml|xl/(?:workbook\.xml|_rels/workbook\.xml\.rels|sharedStrings\.xml|styles\.xml|theme/theme1\.xml|worksheets/(?:sheet1\.xml|_rels/sheet1\.xml\.rels)))$~D',$name)) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $seen[strtolower($name)]=true; $total+=$stat['size'];
    if($stat['size']>1048576 || $total>8388608 || $stat['size']>max(1,$stat['comp_size'])*50 || !in_array($stat['comp_method'],[0,8],true) || !empty($stat['encryption_method'])) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $attributes=0; $opsys=0; $zip->getExternalAttributesIndex($i,$opsys,$attributes);
    if(($attributes>>16 & 0170000)===0120000) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $xml=$zip->getFromIndex($i);
    if(!is_string($xml) || strlen($xml)!==$stat['size'] || sprintf('%u',crc32($xml))!==sprintf('%u',$stat['crc']) || str_contains($xml,"\0") || preg_match('/<!DOCTYPE|<!ENTITY|encoding\s*=\s*[\x22\x27](?!utf-8[\x22\x27])/i',$xml)) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $previous=libxml_use_internal_errors(true);
    try { $dom=new DOMDocument(); $loaded=$dom->loadXML($xml,LIBXML_NONET); } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    if(!$loaded || !$dom->documentElement) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    if($name==='xl/workbook.xml' && $dom->getElementsByTagNameNS('*','sheet')->length!==1) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    $relationships=$dom->getElementsByTagNameNS('*','Relationship'); $relationshipIds=[];
    if($relationships->length>16) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    foreach($relationships as $relation) {
     $relationId=$relation->getAttribute('Id');
     if(!preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,63}$/D',$relationId) || isset($relationshipIds[$relationId])) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     $relationshipIds[$relationId]=true;
    }
    $nodes+=$dom->getElementsByTagName('*')->length;
    if($nodes>20000) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
    foreach($dom->getElementsByTagName('*') as $node) {
     if(in_array($node->localName,['definedName','mergeCell','dataValidation','conditionalFormatting','hyperlink','oleObject','drawing','legacyDrawing','pivotTablePart','externalReference'],true)) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     if($node->hasAttribute('count') && (!ctype_digit($node->getAttribute('count')) || (int)$node->getAttribute('count')>20000)) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     if($node->localName==='Relationship' && ($node->getAttribute('TargetMode')==='External' || preg_match('~(?:^[\\\\/]|\.\.|:|\\\\)~',$node->getAttribute('Target')))) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     if($node->localName==='Override' && preg_match('/macro|vba|activex|oleObject/i',$node->getAttribute('ContentType'))) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     if($node->localName==='protection' && in_array($node->getAttribute('hidden'),['1','true'],true)) $hidden=true;
     if($node->localName==='sheet' && $node->getAttribute('state')!=='' && $node->getAttribute('state')!=='visible') $hidden=true;
    }
    if($name==='xl/worksheets/sheet1.xml') {
     $cells=$dom->getElementsByTagNameNS('*','c'); if($cells->length>1152) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     $cellNames=[];
     foreach($cells as $cell) {
      $ref=$cell->getAttribute('r');
      if(!preg_match('/^[A-I]([1-9][0-9]{0,2})$/D',$ref,$m) || (int)$m[1]>128 || isset($cellNames[$ref])) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
      $cellNames[$ref]=true;
      foreach($cell->getElementsByTagNameNS('*','v') as $v) $numbers[$ref]=$v->textContent;
      if($cell->getElementsByTagNameNS('*','f')->length) $formulas=true;
     }
     foreach($dom->getElementsByTagNameNS('*','row') as $row) { if(!preg_match('/^[1-9][0-9]{0,2}$/D',$row->getAttribute('r')) || (int)$row->getAttribute('r')>128) throw new InvalidArgumentException('OrdersArchiveInvalid',400); if(in_array($row->getAttribute('hidden'),['1','true'],true) || $row->getAttribute('ht')==='0') $hidden=true; }
     foreach($dom->getElementsByTagNameNS('*','dimension') as $dim) if(!preg_match('/^A1(?::[A-I]([1-9][0-9]{0,2}))?$/D',$dim->getAttribute('ref'),$m) || (isset($m[1]) && (int)$m[1]>128)) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
     foreach($dom->getElementsByTagNameNS('*','col') as $col) { if(!preg_match('/^[1-9]$/D',$col->getAttribute('min')) || !preg_match('/^[1-9]$/D',$col->getAttribute('max')) || (int)$col->getAttribute('min')>(int)$col->getAttribute('max')) throw new InvalidArgumentException('OrdersArchiveInvalid',400); if(in_array($col->getAttribute('hidden'),['1','true'],true) || $col->getAttribute('width')==='0') $hidden=true; }
    }
   }
   foreach(['[content_types].xml','_rels/.rels','xl/workbook.xml','xl/_rels/workbook.xml.rels','xl/worksheets/sheet1.xml'] as $required) if(!isset($seen[$required])) throw new InvalidArgumentException('OrdersArchiveInvalid',400);
   return ['numbers'=>$numbers,'formulas'=>$formulas,'hidden'=>$hidden];
  } finally { $zip->close(); }
 }

 public static function unique($analysis) {
  $values=[];
  foreach($analysis['fields'] as $field) if($field['state']==='suggested') $values[$field['field']]=$field['value'];
  return $values;
 }
}
