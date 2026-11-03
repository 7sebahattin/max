<?php
/** Product assets only. Native legal/company/bank fields are never written here. */
function maxCoreAssetDirectory() {
 global $conf;
 $dir=DOL_DATA_ROOT.'/maxcore/'.((int)$conf->entity).'/branding';
 if(is_link($dir)) throw new RuntimeException('MAXInvalidAsset');
 return $dir;
}
function maxCoreIsUploadedAsset($value) {
 return is_string($value) && preg_match('/^upload:([a-f0-9]{64})\.png$/D',$value)===1;
}
function maxCoreAssetPath($value) {
 if(!maxCoreIsUploadedAsset($value)) throw new InvalidArgumentException('MAXInvalidAsset');
 $path=maxCoreAssetDirectory().'/'.substr($value,7);
 if(is_link($path)||!is_file($path)) throw new InvalidArgumentException('MAXInvalidAsset');
 return $path;
}
function maxCoreAssetUrl($value,$kind='logo') {
 if($value==='none') return '';
 if(in_array($value,array('max.svg','max-icon.svg'),true)) return dol_buildpath('/maxcore/img/'.$value,1);
 if(maxCoreIsUploadedAsset($value)) return dol_buildpath('/maxcore/asset.php',1).'?kind='.($kind==='favicon'?'favicon':'logo').'&v='.substr($value,7,64);
 return '';
}
function maxCoreUpload($upload,$actor) {
 if(!isModEnabled('maxcore')||!maxCoreCan($actor,'configure')) throw new RuntimeException('MAXDenied');
 if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK
  ||!is_string($upload['name']??null)||basename($upload['name'])!==$upload['name']||strpos($upload['name'],chr(92))!==false
  ||!in_array(strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION)),array('png','jpg','jpeg'),true)
  ||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])
  ||($upload['size']??0)<1||$upload['size']>1048576||filesize($upload['tmp_name'])>1048576)
  throw new InvalidArgumentException('MAXInvalidUpload');
 $bytes=file_get_contents($upload['tmp_name']);
 $info=@getimagesizefromstring($bytes);
 $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
 if(!$info||!in_array($mime,array('image/png','image/jpeg'),true)
  ||!in_array($info[2],array(IMAGETYPE_PNG,IMAGETYPE_JPEG),true)
  ||$info[0]<1||$info[1]<1||$info[0]>2048||$info[1]>2048||$info[0]*$info[1]>4194304)
  throw new InvalidArgumentException('MAXInvalidUpload');
 $image=@imagecreatefromstring($bytes);
 if(!$image) throw new InvalidArgumentException('MAXInvalidUpload');
 // Decode/re-encode, discarding file name, metadata and appended active content.
 ob_start(); imagepng($image); $clean=ob_get_clean(); imagedestroy($image);
 $token='upload:'.hash('sha256',$clean).'.png';
 $dir=maxCoreAssetDirectory();
 if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir)) throw new RuntimeException('MAXSaveFailed');
 $path=$dir.'/'.substr($token,7);
 if(is_link($path)) throw new RuntimeException('MAXInvalidAsset');
 if(!is_file($path)) {
  $tmp=tempnam($dir,'asset-');
  if($tmp===false||file_put_contents($tmp,$clean,LOCK_EX)!==strlen($clean)) throw new RuntimeException('MAXSaveFailed');
  chmod($tmp,0600);
  if(!rename($tmp,$path)) { unlink($tmp); throw new RuntimeException('MAXSaveFailed'); }
 }
 return $token;
}
function maxCoreApplyBrand() {
 global $conf,$langs;
 if(!isModEnabled('maxcore')) return;
 $settings=maxCoreSettings();
 // Supported native rendering configuration; request-local, never persisted.
 $conf->global->MAIN_APPLICATION_TITLE=$settings['MAXCORE_DISPLAY_NAME'];
 $conf->global->MAIN_HTML_TITLE='showapp';
 $favicon=maxCoreAssetUrl($settings['MAXCORE_FAVICON'],'favicon');
 if($favicon!=='') $conf->global->MAIN_FAVICON_URL=$favicon;
 $langs->load('maxcore@maxcore');
 // Only our module's own menu key in the public native translator cache.
 $langs->tab_translate['MAXMenu']=$settings['MAXCORE_DISPLAY_NAME'];
}
function maxCoreBrandMarkup($login=false) {
 $s=maxCoreSettings(); $url=maxCoreAssetUrl($s['MAXCORE_LOGO']);
 $out='<div class="maxcore-global-brand'.($login?' maxcore-login-brand':'').'">';
 if($url!=='') $out.='<img src="'.dol_escape_htmltag($url).'" alt="" width="32" height="32">';
 $out.='<span>'.dol_escape_htmltag($s['MAXCORE_DISPLAY_NAME']).'</span></div>';
 return $out;
}
