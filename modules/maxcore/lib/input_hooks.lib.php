<?php
/** Supported native hook adapters; no authentication replacement or DB statements. */
require_once dirname(__DIR__).'/class/input.class.php';
class MaxInputHooks {
 private static $importBatchRejected=false;
 public static function importFailure($step) {if($step==5)self::$importBatchRejected=true;}
 public static function importBatchRejected() {return self::$importBatchRejected;}
 /** Opaque native session tokens; no casing, decoding or token replacement. */
 public static function requireImportToken($step,$provided,$expected) {
  // Match the native wizard's numeric step comparison, including e.g. "5.0".
  if($step!=5)return;
  if(!is_string($provided)||!is_string($expected)||$provided===''||$expected===''||!hash_equals($expected,$provided))throw new InvalidArgumentException('MAXInputCsrf',403);
 }
 private static function importRequestToken($step) {
  self::requireImportToken($step,GETPOST('token','alpha'),currentToken());
 }
 public static function path($file,$root) {
  $root=rtrim(str_replace('\\','/',$root),'/').'/';$file=str_replace('\\','/',$file);
  return str_starts_with($file,$root)?substr($file,strlen($root)):'';
 }
 public static function web() {
  if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true))return;
  $path=self::path($_SERVER['SCRIPT_FILENAME']??'',DOL_DOCUMENT_ROOT);
  if($path==='imports/import.php')self::importRequestToken(GETPOST('step','alphanohtml'));
  // Native security handling may deliberately unset POST after rejecting CSRF.
  if(isset($_POST))$_POST=MaxInput::web($path,$_POST);
  // Native GETPOST defaults to query-before-body, so normalize both sources.
  if(isset($_GET))$_GET=MaxInput::web($path,$_GET);
  if(isset($_REQUEST))$_REQUEST=MaxInput::web($path,$_REQUEST);
 }
 public static function api($parameters,$api,$method) {
  $resource=$parameters['moduleobject']??'';
  // Restler requestMethod is not initialized until handle(); use its public resolver.
  $method=class_exists('Luracast\\Restler\\Util')?\Luracast\Restler\Util::getRequestMethod():($_SERVER['REQUEST_METHOD']??$method??'GET');
  if(in_array(strtoupper($method),['POST','PUT','PATCH'],true)&&isset(MaxInput::registry()['api'][$resource]))$_GET=(array)MaxInput::api($resource,$_GET);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>MaxInput::MAX_BYTES&&isset(MaxInput::registry()['api'][$resource]))throw new InvalidArgumentException('MAXInputTooLong',413);
  return MaxInput::restler($api->r,$resource,$method,$_SERVER['CONTENT_TYPE']??'');
 }
 /** Known native datasets delegate exactly once to the native importer. */
 public static function import($parameters) {
  // The early session hook is not the authoritative write guard (first-login
  // requests can follow a different native login branch). Guard the row hook too.
  if(isset($parameters['step']))self::importRequestToken($parameters['step']);
  $dataset=$parameters['datatoimport']??'';
  if(!isset(MaxInput::registry()['imports'][$dataset]))return 0;
  foreach(['obj','arrayrecord','array_match_file_to_database','objimport','fieldssource','importid','updatekeys','nbok'] as $key)if(!array_key_exists($key,$parameters))throw new InvalidArgumentException('MAXInputInvalid',400);
  $driver=$parameters['obj'];
  $row=MaxInput::importRow($dataset,$parameters['arrayrecord'],$parameters['array_match_file_to_database']);
  if(!isset($driver->db)||!is_callable([$driver->db,'begin'])||!is_callable([$driver->db,'rollback'])||!is_callable([$driver->db,'commit']))throw new RuntimeException('MAXInputImportReview',400);
  if(!$driver->db->begin())throw new RuntimeException('MAXInputImportReview',400);
  try {
  $result=$driver->import_insert($row,$parameters['array_match_file_to_database'],$parameters['objimport'],count($parameters['fieldssource']),$parameters['importid'],$parameters['updatekeys']);
  if($result<0||!empty($driver->errors)||!empty($driver->warnings))throw new RuntimeException('MAXInputImportReview',400);
  if(!$driver->db->commit())throw new RuntimeException('MAXInputImportReview',400);
  $parameters['nbok']++;
  return 1;
  }catch(Throwable $e){$driver->db->rollback();throw $e;}
 }
 public static function header() {
  static $printed=false;
  if($printed)return '';$printed=true;
  $path=self::path($_SERVER['SCRIPT_FILENAME']??'',DOL_DOCUMENT_ROOT);
  $fields=MaxInput::registry()['web'][$path]??[];
  // MAX form services enforce their own authoritative fields as well.
  if($path==='custom/maxorders/index.php'||$path==='custom/maxorders/review.php')$fields=[];
  $config=json_encode(['locale'=>'tr-TR','fields'=>$fields,'importWizard'=>$path==='imports/import.php','formLayout'=>in_array($path,['societe/card.php','product/card.php'],true),'facts'=>in_array($path,['custom/maxorders/index.php','custom/maxorders/review.php','custom/maxorders/document.php'],true)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
  return '<link rel="stylesheet" href="'.dol_buildpath('/maxcore/css/input.css',1).'"><script id="max-input-config" type="application/json">'.$config.'</script><script defer src="'.dol_buildpath('/maxcore/js/input.js',1).'"></script>';
 }
}
