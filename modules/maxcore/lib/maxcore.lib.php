<?php
function maxCoreCan($actor,$right) {
 if(!empty($actor->socid)) return false;
 return $actor->hasRight('maxcore','admin') || $actor->hasRight('maxcore',$right)
  || ($right==='read' && $actor->hasRight('maxcore','configure'));
}
function maxCoreRequire($right) {
 global $user;
 if(!isModEnabled('maxcore') || !maxCoreCan($user,$right)) { http_response_code(403); accessforbidden('', 0, 0, 1); }
}
function maxCoreSettings() {
 return array('MAXCORE_DISPLAY_NAME'=>getDolGlobalString('MAXCORE_DISPLAY_NAME','MAX'),
  'MAXCORE_LOGO'=>getDolGlobalString('MAXCORE_LOGO','max.svg'),
  'MAXCORE_FAVICON'=>getDolGlobalString('MAXCORE_FAVICON','max-icon.svg'));
}
function maxCoreValidate($input) {
 if(!isset($input['MAXCORE_DISPLAY_NAME']) || !is_string($input['MAXCORE_DISPLAY_NAME'])) throw new InvalidArgumentException('MAXInvalidBrand');
 $name=trim($input['MAXCORE_DISPLAY_NAME']);
 if($name==='' || mb_strlen($name,'UTF-8')>80 || preg_match('/[\\x00-\\x1f\\x7f<>]/u',$name)!==0)
  throw new InvalidArgumentException('MAXInvalidBrand');
 $out=array('MAXCORE_DISPLAY_NAME'=>$name);
 foreach(array('MAXCORE_LOGO','MAXCORE_FAVICON') as $key) {
  if(!isset($input[$key]) || !is_string($input[$key])) throw new InvalidArgumentException('MAXInvalidAsset');
  $value=$input[$key];
  if(!in_array($value,array('none','max.svg','max-icon.svg'),true)) throw new InvalidArgumentException('MAXInvalidAsset');
  $out[$key]=$value;
 }
 return $out;
}
function maxCoreSave($input,$actor) {
 global $db,$conf;
 if(!isModEnabled('maxcore') || !maxCoreCan($actor,'configure')) throw new RuntimeException('MAXDenied');
 $values=maxCoreValidate($input);
 require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
 $db->begin();
 foreach($values as $key=>$value) {
  if(dolibarr_set_const($db,$key,$value,'chaine',0,'',$conf->entity)<=0) {
   $db->rollback(); throw new RuntimeException('MAXSaveFailed');
  }
 }
 $db->commit();
}
function maxCoreHeader($title) {
 global $conf;
 $s=maxCoreSettings();
 if(in_array($s['MAXCORE_FAVICON'],array('max.svg','max-icon.svg'),true))
  $conf->global->MAIN_FAVICON_URL=dol_buildpath('/maxcore/img/'.$s['MAXCORE_FAVICON'],1);
 llxHeader('',$s['MAXCORE_DISPLAY_NAME'].' - '.$title,'','',0,0,array(),array('/maxcore/css/maxcore.css'),'','maxcore-page');
}
