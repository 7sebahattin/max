<?php
/** Mandatory business text policy. Technical/raw values never enter this class implicitly.
 * GPL-3.0-or-later. No DB, session, network, native object writes or historical migration.
 */
class MaxInput {
 const LOCALE='tr_TR';
 const MAX_BYTES=262144;
 const PROTECTED='~((?:(?<![a-z0-9+.-])(?:[a-z][a-z0-9+.-]{0,31}://|mailto:|www\.)[^\s<>"\x27]+)|(?:(?<![\p{L}\p{N}.!#$%&*+/=?^_`{|}\x7e-])[\p{L}\p{N}.!#$%&*+/=?^_`{|}\x7e-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,})|(?:[a-z]:[\\\\/][^\s<>"\x27]+)|(?:(?<!\S)/[^\s<>"\x27]+))~iu';
 private static function source($value,$limit) {
  if(!is_int($limit)||$limit<1||$limit>65535||!is_string($value)||strlen($value)>self::MAX_BYTES||!mb_check_encoding($value,'UTF-8'))throw new InvalidArgumentException('MAXInputInvalid',400);
  if(!class_exists('Normalizer')||!class_exists('Transliterator'))throw new RuntimeException('MAXInputUnavailable',503);
  return $value;
 }
 private static function upper($value) {
  static $upper=null;
  if($upper===null)$upper=Transliterator::create('tr-Upper');
  if(!$upper)throw new RuntimeException('MAXInputUnavailable',503);
  $value=Normalizer::normalize($value,Normalizer::FORM_C);
  if($value===false)throw new InvalidArgumentException('MAXInputInvalid',400);
  $value=$upper->transliterate($value);
  $value=Normalizer::normalize($value,Normalizer::FORM_C);
  if($value===false)throw new InvalidArgumentException('MAXInputInvalid',400);
  return $value;
 }
 /** plain_business_text; preserves technical URI/email/path spans inside prose. */
 public static function text($value,$limit=65535) {
  self::source($value,$limit);
  $parts=preg_split(self::PROTECTED,$value,-1,PREG_SPLIT_DELIM_CAPTURE);
  if($parts===false)throw new InvalidArgumentException('MAXInputInvalid',400);
  foreach($parts as $index=>&$part)if($index%2===0)$part=self::upper($part);unset($part);
  $result=implode('',$parts);
  if(mb_strlen($result,'UTF-8')>$limit||strlen($result)>self::MAX_BYTES)throw new InvalidArgumentException('MAXInputTooLong',400);
  return $result;
 }
 /** rich_business_text; normalization is NOT an HTML sanitizer. Native sanitizing remains required. */
 public static function html($value,$limit=65535) {
  self::source($value,$limit);
  if(preg_match('/<!DOCTYPE|<!ENTITY/i',$value))throw new InvalidArgumentException('MAXInputInvalid',400);
  if(!str_contains($value,'<'))return self::text($value,$limit);
  $previous=libxml_use_internal_errors(true);
  try {
   $doc=new DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;
   if(!$doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="max-input-root">'.$value.'</div></body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))throw new InvalidArgumentException('MAXInputInvalid',400);
   $root=$doc->getElementById('max-input-root');if(!$root)throw new InvalidArgumentException('MAXInputInvalid',400);
   $body=$doc->getElementsByTagName('body')->item(0);
   if(!$body||$root->parentNode!==$body)throw new InvalidArgumentException('MAXInputInvalid',400);
   foreach($body->childNodes as $node)if($node!==$root&&!($node->nodeType===XML_TEXT_NODE&&trim($node->nodeValue)===''))throw new InvalidArgumentException('MAXInputInvalid',400);
   if(preg_match('/<\/?(?:html|head|body)(?:\s|>)/i',$value))throw new InvalidArgumentException('MAXInputInvalid',400);
   $xpath=new DOMXPath($doc);
   foreach($xpath->query('.//text()[not(ancestor::script or ancestor::style or ancestor::code or ancestor::pre or ancestor::textarea)]',$root) as $node)$node->nodeValue=self::text($node->nodeValue,$limit);
   $result='';foreach($root->childNodes as $node)$result.=$doc->saveHTML($node);
   if(mb_strlen($result,'UTF-8')>$limit||strlen($result)>self::MAX_BYTES)throw new InvalidArgumentException('MAXInputTooLong',400);
   return $result;
  } finally {libxml_clear_errors();libxml_use_internal_errors($previous);}
 }
 public static function value($value,array $rule) {
  if($rule[0]==='text')return self::text($value,$rule[1]);
  if($rule[0]==='html')return self::html($value,$rule[1]);
  throw new InvalidArgumentException('MAXInputUnclassified',400);
 }
 /** Only registry-owned fields are transformed. Unknown/technical fields are byte-preserved. */
 public static function record($data,array $rules) {
  if(!is_array($data)&&!is_object($data))throw new InvalidArgumentException('MAXInputInvalid',400);
  $result=$data;
  if(is_object($result))$result=clone $result;
  foreach($rules as $field=>$rule){
   $has=is_array($data)?array_key_exists($field,$data):property_exists($data,$field);
   if(!$has)continue;
   $value=is_array($data)?$data[$field]:$data->$field;
   if($value===null)continue;
   $value=self::value($value,$rule);
   if(is_array($result))$result[$field]=$value;else$result->$field=$value;
  }
  return $result;
 }
 public static function registry(){static $map=null;if($map===null){$map=json_decode(file_get_contents(__DIR__.'/../input-fields.json'),true,24,JSON_THROW_ON_ERROR);}return $map;}
 public static function web($path,array $post){$map=self::registry();return self::record($post,$map['web'][$path]??[]);}
 public static function api($resource,$data){
  $rules=self::registry()['api'][$resource]??[];
  if(!$rules)return $data;
  $result=self::record($data,$rules);
  if(!in_array($resource,['orders','invoices','shipments'],true))return $result;
  foreach(['lines'] as $key){
   $rows=is_object($result)?($result->$key??null):($result[$key]??null);
   if($rows===null)continue;
   if(!is_array($rows)||count($rows)>1000)throw new InvalidArgumentException('MAXInputInvalid',400);
   foreach($rows as &$row)$row=self::record($row,['desc'=>['html',65535],'description'=>['html',65535],'label'=>['text',255]]);unset($row);
   if(is_object($result))$result->$key=$rows;else$result[$key]=$rows;
  }
  return $result;
 }
 /** Native import row copy; original raw source bytes/array are not changed. */
 public static function importRow($dataset,array $row,array $mapping){
  $rules=self::registry()['imports'][$dataset]??[];
  // CSV cells start at zero, XLSX cells at one; native mapping is one-based.
  // Preserve the driver's keys, metadata, null/blank semantics and opaque columns.
  $native=(bool)array_filter($row,'is_array');
  $positions=array_keys($row);
  if($native&&($positions!==range(0,count($row)-1)&&$positions!==range(1,count($row))))throw new InvalidArgumentException('MAXInputInvalid',400);
  foreach($mapping as $position=>$target){
   if(!isset($rules[$target]))continue;
   if($native){
    if(!is_int($position)||$position<1)throw new InvalidArgumentException('MAXInputInvalid',400);
    if(!isset($positions[$position-1]))continue;
    $position=$positions[$position-1];
   }
   if(!array_key_exists($position,$row))continue;
   if(!$native){$row[$position]=self::value($row[$position],$rules[$target]);continue;}
   $cell=$row[$position];
   if(!is_array($cell)||!array_key_exists('val',$cell)||!isset($cell['type'])||!in_array($cell['type'],[-1,0,1],true))throw new InvalidArgumentException('MAXInputInvalid',400);
   if($cell['type']===1)$row[$position]['val']=self::value($cell['val'],$rules[$target]);
   elseif($cell['val']!==''&&$cell['val']!==null)throw new InvalidArgumentException('MAXInputInvalid',400);
  }
  return $row;
 }
 /** Supported native pre-dispatch stream API, before Restler::handle parses the body. */
 public static function restler($restler,$resource,$method,$mime){
  if(!in_array(strtoupper($method),['POST','PUT','PATCH'],true)||!isset(self::registry()['api'][$resource]))return false;
  if(strtolower(trim(explode(';',$mime)[0]))!=='application/json')throw new InvalidArgumentException('MAXInputJsonRequired',415);
  $stream=$restler->getRequestStream();if(!is_resource($stream))throw new InvalidArgumentException('MAXInputInvalid',400);
  rewind($stream);$raw=stream_get_contents($stream,self::MAX_BYTES+1);
  if(!is_string($raw)||strlen($raw)>self::MAX_BYTES)throw new InvalidArgumentException('MAXInputTooLong',413);
  require_once __DIR__.'/input_json.class.php';
  $json=MaxInputJson::normalize($raw,$resource);
  if(strlen($json)>self::MAX_BYTES)throw new InvalidArgumentException('MAXInputTooLong',413);
  rewind($stream);if(!ftruncate($stream,0)||fwrite($stream,$json)!==strlen($json))throw new RuntimeException('MAXInputUnavailable',503);rewind($stream);
  return true;
 }
}
