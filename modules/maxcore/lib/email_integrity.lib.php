<?php
/** Request-scoped opaque email preservation through native CRM triggers/ORM. */
class MaxInputEmail {
 private static $request=null;
 public static function remember($resource,$operation,$identity,$email){
  self::$request=null;
  if(!in_array($resource,['thirdparties','contacts'],true)||!in_array($operation,['create','update'],true))return;
  if(!is_string($email))throw new InvalidArgumentException('MAXInputEmailIntegrity',400);
  self::$request=['resource'=>$resource,'operation'=>$operation,'id'=>(int)$identity,'email'=>trim($email)];
 }
 public static function web($path){
  self::$request=null;
  $resource=['societe/card.php'=>'thirdparties','contact/card.php'=>'contacts'][$path]??null;
  $action=GETPOST('action','aZ09');
  if(!$resource||!in_array($action,['add','update'],true))return;
  $id=$resource==='thirdparties'?GETPOSTINT('socid'):(GETPOSTINT('contactid')?:GETPOSTINT('id'));
  // Match the native full-form email sanitization/GETPOST precedence.
  self::remember($resource,$action==='add'?'create':'update',$id,(string)GETPOST('email','email'));
 }
 public static function api($resource,$method,$url,$body,$query){
  self::$request=null;
  if(!in_array($resource,['thirdparties','contacts'],true))return;
  $path=parse_url($url,PHP_URL_PATH);
  if(!preg_match('~/(thirdparties|contacts)(?:/([1-9][0-9]*))?/?$~D',(string)$path,$match)||$match[1]!==$resource)return;
  $id=(int)($match[2]??0);$operation=$method==='POST'&&!$id?'create':($method==='PUT'&&$id?'update':null);
  if(!$operation)return;
  if(array_key_exists('email',$body)&&array_key_exists('email',$query)&&$body['email']!==$query['email'])throw new InvalidArgumentException('MAXInputEmailAmbiguous',400);
  if(array_key_exists('email',$body)||array_key_exists('email',$query))$email=$query['email']??$body['email']??'';
  elseif($operation==='update'){
   // A partial PUT must preserve the existing native value too. This private
   // read does not grant access: only the subsequently authorized native event
   // may write the same object; no data is returned or logged here.
   global $db,$conf;
   $file=$resource==='thirdparties'?'/societe/class/societe.class.php':'/contact/class/contact.class.php';require_once DOL_DOCUMENT_ROOT.$file;
   $object=$resource==='thirdparties'?new Societe($db):new Contact($db);
   if($object->fetch($id)<=0||(int)$object->entity!==(int)$conf->entity)return;
   $email=(string)$object->email;
  }else $email='';
  self::remember($resource,$operation,$id,$email===null?'':$email);
 }
 public static function apply($event,$object,$user){
  $events=['COMPANY_CREATE'=>['thirdparties','create','societe'],'COMPANY_MODIFY'=>['thirdparties','update','societe'],'CONTACT_CREATE'=>['contacts','create','socpeople'],'CONTACT_MODIFY'=>['contacts','update','socpeople']];
  $rule=$events[$event]??null;$request=self::$request;
  if(!$rule||!$request||$request['resource']!==$rule[0]||$request['operation']!==$rule[1])return 0;
  if(isset($request['bound_id'])&&(int)$object->id!==$request['bound_id'])return 0;
  global $conf;
  if((int)$object->id<=0||$object->table_element!==$rule[2]||(int)$object->entity!==(int)$conf->entity||($request['operation']==='update'&&(int)$object->id!==$request['id']))return -1;
  $email=$request['email'];
  self::$request['bound_id']=(int)$object->id;
  // Restore case only, never bypass native validation or reinterpret a value.
  if(dol_strtolower($email)!==(string)$object->email)return -1;
  if($email===(string)$object->email)return 0;
  // Supported native ORM, in the existing outer transaction, no recursion or
  // unrelated audit-field update. No custom SQL and no second CRM store.
  return $object->setValueFrom('email',$email,'',(int)$object->id,'text','rowid',$user,'','')>0?0:-1;
 }
}
