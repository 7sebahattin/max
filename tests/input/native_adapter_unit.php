<?php
// Actual pinned Restler public stream + Dolibarr HookManager. No installed DB/session.
if(PHP_SAPI!=='cli')exit(2);
define('DOL_DOCUMENT_ROOT',realpath($argv[1]));
define('DOL_URL_ROOT','');
require __DIR__.'/../../modules/maxcore/class/input.class.php';
require __DIR__.'/../../modules/maxcore/lib/input_hooks.lib.php';
require DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/AutoLoader.php';
spl_autoload_register(Luracast\Restler\AutoLoader::instance());
$checks=[];
function nativeInputCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
$temp=sys_get_temp_dir().'/max-input-library-'.bin2hex(random_bytes(8));mkdir($temp,0700);
try{
 Luracast\Restler\Defaults::$cacheDirectory=$temp;
 $restler=new Luracast\Restler\Restler();
 nativeInputCheck($restler->requestMethod===null,'native hook action method starts unset');
 $_SERVER['REQUEST_METHOD']='POST';$_SERVER['CONTENT_TYPE']='application/json';$_GET=['label'=>'şişli','api_key'=>'CaseKey'];
 $stream=$restler->getRequestStream();fwrite($stream,'{"label":"çiçek","ref":"CaseRef","email":"User@Example.com","options":{}}');
 nativeInputCheck(MaxInputHooks::api(['moduleobject'=>'products'],(object)['r'=>$restler],null),'pre-dispatch adapter uses native method resolver before initialization');
 nativeInputCheck($_GET['label']==='ŞİŞLİ'&&$_GET['api_key']==='CaseKey','API query fallback normalized without changing token');
 $restler->requestMethod='POST';$restler->requestFormat=new Luracast\Restler\Format\JsonFormat();
 $data=$restler->getRequestData(false);
 nativeInputCheck($data['label']==='ÇİÇEK'&&$data['ref']==='CaseRef'&&$data['email']==='User@Example.com','pinned native JSON decoder sees uppercase and exact technical identifiers');
 $raw=stream_get_contents($restler->getRequestStream());nativeInputCheck(str_contains($raw,'"options":{}'),'empty JSON object type preserved in transport');
 $source=file_get_contents(DOL_DOCUMENT_ROOT.'/api/index.php');
 $contactSchema=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_socpeople.sql');$contactBounds=true;
 foreach(['firstname','lastname'] as $field){
  $contactBounds=$contactBounds&&(bool)preg_match('/\b'.$field.'\s+varchar\(50\)/i',$contactSchema);
  $contactBounds=$contactBounds&&MaxInput::registry()['web']['contact/card.php'][$field][1]===50&&MaxInput::registry()['api']['contacts'][$field][1]===50&&MaxInput::registry()['imports']['societe_2']['s.'.$field][1]===50;
 }
 nativeInputCheck($contactBounds,'contact web API and import length rules match actual pinned SQL varchar columns');
 $companySchema=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_societe.sql');
 nativeInputCheck((bool)preg_match('/\btown\s+varchar\(50\)/i',$companySchema)&&MaxInput::registry()['web']['societe/card.php']['town'][1]===50&&MaxInput::registry()['api']['thirdparties']['town'][1]===50&&MaxInput::registry()['imports']['societe_1']['s.town'][1]===50,'company town web API import bounds match pinned SQL');
 $invoiceLineSchema=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_facturedet.sql');
 nativeInputCheck((bool)preg_match('/\blabel\s+varchar\(255\)/i',$invoiceLineSchema)&&MaxInput::registry()['imports']['facture_3']['fd.label']===['text',255],'invoice import line label length matches pinned native SQL');
 $warehouseSchema=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_entrepot.sql');$warehouseBounds=true;
 foreach(['lieu'=>64,'town'=>50] as $field=>$limit)$warehouseBounds=$warehouseBounds&&(bool)preg_match('/\b'.$field.'\s+varchar\('.$limit.'\)/i',$warehouseSchema)&&MaxInput::registry()['web']['product/stock/card.php'][$field]===['text',$limit]&&MaxInput::registry()['api']['warehouses'][$field]===['text',$limit];
 nativeInputCheck($warehouseBounds,'warehouse location and town web API bounds match pinned SQL');
 nativeInputCheck(strpos($source,"executeHooks('beforeApiCall'")<strpos($source,'$api->r->handle()'),'supported hook precedes native request parsing');
 $import=file_get_contents(DOL_DOCUMENT_ROOT.'/imports/import.php');
 nativeInputCheck(str_contains($import,"'obj'                          => &\$obj")&&str_contains($import,"'nbok'                         => &\$nbok")&&str_contains($import,'if (empty($reshook))'),'native import hook retains object/counter references and replacement contract');
 require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
 $_SERVER['SCRIPT_FILENAME']=DOL_DOCUMENT_ROOT.'/societe/card.php';$_GET=['name'=>'istanbul','token'=>'CaseToken'];$_POST=['name'=>'şişli'];$_REQUEST=$_GET+$_POST;
 MaxInputHooks::web();
 nativeInputCheck(GETPOST('name','none',0,null,null,1,1)==='İSTANBUL'&&GETPOST('name','none',2,null,null,1,1)==='ŞİŞLİ'&&$_GET['token']==='CaseToken','actual native GETPOST query precedence cannot bypass casing');
 unset($restler);
}finally{
 foreach(scandir($temp) as $file)if($file!=='.'&&$file!=='..'){if(is_dir($temp.'/'.$file))throw new RuntimeException('FAIL unexpected cache directory');unlink($temp.'/'.$file);}
 if(!rmdir($temp))throw new RuntimeException('FAIL native library fixture cleanup');
}
nativeInputCheck(!is_dir($temp),'native library temporary cache removed');
if(isset($argv[2]))file_put_contents($argv[2],json_encode(['passed'=>true,'scope'=>'pinned_native_library_only','checks'=>$checks,'installed_native'=>'NOT RUN'],JSON_PRETTY_PRINT));
echo 'PASS '.count($checks)." native library adapter checks\n";
