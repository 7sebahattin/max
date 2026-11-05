<?php
/** Optional offline Poppler CLI adapter. No rendering/OCR/embedded actions. GPL-3.0-or-later. */
class MaxOrdersPdf {
 const MARKER='MAX_ORDERS_PDF_V03';
 private static function base($issue) { return ['format'=>'pdf','mime'=>'application/pdf','status'=>'needs_review','fields'=>[],'missing'=>MaxOrdersExtraction::FIELDS,'issues'=>[$issue]]; }
 private static function command($tool,$input,$args,$limit) {
  foreach(['/usr/bin/bwrap','/usr/bin/prlimit','/usr/bin/'.$tool] as $path) if(!is_executable($path)) return null;
  $cmd=['/usr/bin/bwrap','--unshare-user','--unshare-net','--unshare-pid','--unshare-ipc','--unshare-uts','--die-with-parent','--new-session','--cap-drop','ALL','--clearenv','--setenv','LANG','C','--setenv','HOME','/tmp'];
  foreach(['/usr/bin','/usr/lib','/usr/share/poppler','/usr/share/fonts','/lib','/lib64','/etc/fonts'] as $path) if(is_dir($path)) array_push($cmd,'--ro-bind',$path,$path);
  array_push($cmd,'--ro-bind',$input,'/input.pdf','--proc','/proc','--dev','/dev','--size','4194304','--tmpfs','/tmp','--chdir','/tmp','--','/usr/bin/prlimit','--as=268435456','--cpu=2','--fsize=131072','--nofile=64','--nproc=128','--core=0','--','/usr/bin/'.$tool,...$args);
  $process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['PATH'=>'/usr/bin:/bin','LANG'=>'C']);
  if(!is_resource($process)) return null;
  fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
  $out='';$errors=0;$start=hrtime(true);$exit=-1;$limited=false;
  try {
   while(true) {
    $out.=stream_get_contents($pipes[1],8192);$errors+=strlen(stream_get_contents($pipes[2],4096));$state=proc_get_status($process);
    if(strlen($out)>$limit || $errors>4096 || hrtime(true)-$start>2000000000) { $limited=true;proc_terminate($process,9);break; }
    if(!$state['running']) { $exit=$state['exitcode'];$out.=stream_get_contents($pipes[1],$limit+1-strlen($out));break; }
    usleep(10000);
   }
  } finally { foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process); }
  return !$limited && $exit===0 && strlen($out)<=$limit?$out:null;
 }
 public static function inspect($bytes) {
  $path=tempnam(sys_get_temp_dir(),'maxorders-pdf-');if(!$path)throw new RuntimeException('OrdersStorageError',500);
  try {
   if(!chmod($path,0600) || file_put_contents($path,$bytes)!==strlen($bytes))throw new RuntimeException('OrdersStorageError',500);
   $info=self::command('pdfinfo',$path,['/input.pdf'],8192);
   if($info===null)return self::base('OrdersPdfSandboxBlocked');
   if(!preg_match('/^Pages:\s+([0-9]+)\s*$/m',$info,$pages) || (int)$pages[1]<1 || (int)$pages[1]>10 || !preg_match('/^Encrypted:\s+no\s*$/m',$info))return self::base('OrdersPdfUnreadable');
   $text=self::command('pdftotext',$path,['-f','1','-l','10','-enc','UTF-8','-layout','/input.pdf','-'],65536);
   if($text===null || preg_match('//u',$text)!==1 || trim($text)==='')return self::base('OrdersPdfUnreadable');
   return self::text($text);
  } finally { if(is_file($path) && !unlink($path))throw new RuntimeException('OrdersStorageError',500); }
 }
 private static function text($text) {
  $result=self::base('OrdersLayoutReview');$pages=explode("\f",$text);$first=true;$offset=0;$seen=[];$fields=[];$issues=[];
  foreach($pages as $page=>$content) {
   foreach(explode("\n",$content) as $line=>$raw) {
    $start=$offset;$offset+=strlen($raw)+1;$value=trim($raw);if($value==='')continue;
    if($first) { if($value!==self::MARKER)return $result;$first=false;continue; }
    $colon=strpos($value,':');if($colon===false)return $result;
    $key=trim(substr($value,0,$colon));$value=trim(substr($value,$colon+1));
    if(!in_array($key,MaxOrdersExtraction::FIELDS,true))return $result;
    $valid=MaxOrdersExtraction::valid($key,$value);$truncated=mb_strlen($value)>255;
    $fields[]=['field'=>$key,'raw'=>mb_substr($value,0,255),'value'=>mb_substr($value,0,255),'truncated'=>$truncated,'state'=>$valid?'suggested':'uncertain','source'=>['page'=>$page+1,'line'=>$line+1,'offset'=>$start]];
    $seen[$key]=($seen[$key]??0)+1;if(!$valid)$issues[]='OrdersUncertainField';
   }
  }
  foreach($fields as &$field)if($seen[$field['field']]>1){$field['state']='ambiguous';$issues[]='OrdersAmbiguousField';}unset($field);
  foreach(['document_kind','order_reference','document_reference'] as $key)if(empty($seen[$key]))$issues[]='OrdersMissingField';
  if(empty($seen['customer_reference']) && empty($seen['customer_name']))$issues[]='OrdersMissingField';
  if(!$fields)return $result;
  return ['format'=>'pdf','mime'=>'application/pdf','template'=>'text-pdf-v03','status'=>$issues?'needs_review':'suggested','fields'=>$fields,'missing'=>array_values(array_diff(MaxOrdersExtraction::FIELDS,array_keys($seen))),'issues'=>array_values(array_unique($issues)),'limits'=>['pages'=>10,'text_bytes'=>65536,'command_wall_seconds'=>2,'address_space_bytes'=>268435456]];
 }
}
