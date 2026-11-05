<?php
/** Fixed CLI worker with private, cleaned workspace and bounded resources. GPL-3.0-or-later. */
require_once __DIR__.'/extraction.class.php';
class MaxOrdersRunner {
 public static function options(){return ['columns_json'=>function_exists('getDolGlobalString')?getDolGlobalString('MAXORDERS_V03_COLUMNS'):'','pdf_sandbox'=>function_exists('getDolGlobalInt') && getDolGlobalInt('MAXORDERS_PDF_SAFE_ENABLE')===1];}
 public static function blocked($name,$issue){return ['format'=>strtolower(pathinfo($name,PATHINFO_EXTENSION))==='pdf'?'pdf':'xlsx','status'=>'needs_review','fields'=>[],'lines'=>[],'missing'=>[],'issues'=>[$issue]];}
 public static function inspect($name,$bytes){
  MaxOrdersExtraction::validate($name,$bytes);
  $options=self::options();MaxOrdersTable::columns($options['columns_json']);
  $dir=sys_get_temp_dir().'/maxorders-worker-'.bin2hex(random_bytes(12));if(!mkdir($dir,0700))throw new RuntimeException('OrdersStorageError',500);
  $process=null;$pipes=[];$cleaned=false;
  $cleanup=function()use(&$process,&$pipes,$dir,&$cleaned){
   if($cleaned)return true;if(is_resource($process)){proc_terminate($process,9);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);$process=null;}
   $ok=true;foreach(scandir($dir)?:[] as $entry)if($entry!=='.'&&$entry!=='..'){$file=$dir.'/'.$entry;if(is_dir($file)&&!is_link($file))$ok=false;elseif(!unlink($file))$ok=false;}
   if(!rmdir($dir))$ok=false;$cleaned=$ok;return $ok;
  };
  register_shutdown_function(function()use($cleanup){$cleanup();});
  try{
   $input=$dir.'/input';if(file_put_contents($input,$bytes)!==strlen($bytes)||!chmod($input,0600))throw new RuntimeException('OrdersStorageError',500);
   $php=PHP_SAPI==='cli'?PHP_BINARY:PHP_BINDIR.'/php';if(!is_executable($php)||!function_exists('proc_open'))return self::blocked($name,'OrdersWorkerUnavailable');
   $env=['PATH'=>'/usr/bin:/bin','LANG'=>'C.UTF-8','TMPDIR'=>$dir];if(PHP_SAPI==='cli')foreach(['LD_LIBRARY_PATH','PHP_INI_SCAN_DIR'] as $key)if(getenv($key)!==false)$env[$key]=getenv($key);
   file_put_contents($dir.'/request',json_encode(['name'=>$name,'options'=>$options],JSON_THROW_ON_ERROR));chmod($dir.'/request',0600);
   $process=proc_open([$php,'-d','memory_limit=128M','-d','display_errors=0','-d','log_errors=0',__DIR__.'/../lib/analysis_worker.php',DOL_DOCUMENT_ROOT,$input,$dir.'/request'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
   if(!is_resource($process))return self::blocked($name,'OrdersWorkerUnavailable');
   fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
   $start=hrtime(true);$output='';$errorBytes=0;$exit=-1;$limited=false;
   while(true){
    $output.=stream_get_contents($pipes[1],65536);$errorBytes+=strlen(stream_get_contents($pipes[2],8192));$state=proc_get_status($process);
    if(strlen($output)>262144||$errorBytes>8192||hrtime(true)-$start>5000000000){$limited=true;proc_terminate($process,9);break;}
    if(!$state['running']){$exit=$state['exitcode'];$output.=stream_get_contents($pipes[1],262145-strlen($output));break;}usleep(10000);
   }
   foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);$process=null;
   if($limited||$exit!==0||strlen($output)>262144)return self::blocked($name,'OrdersWorkerLimit');
   try{$reply=json_decode($output,true,40,JSON_THROW_ON_ERROR);}catch(Throwable $e){return self::blocked($name,'OrdersWorkerFailed');}
   if(isset($reply['error']))throw new InvalidArgumentException(in_array($reply['error'],['OrdersFileInvalid','OrdersArchiveInvalid','OrdersTemplateInvalid'],true)?$reply['error']:'OrdersFileInvalid',400);
   if(!isset($reply['analysis']['format'],$reply['analysis']['fields'],$reply['analysis']['issues']))return self::blocked($name,'OrdersWorkerFailed');return $reply['analysis'];
  }finally{if(!$cleanup())throw new RuntimeException('OrdersStorageError',500);}
 }
}
