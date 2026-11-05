<?php
// No native session, DB, private configuration or credentials are loaded here.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(count($argv)!==4)exit(2);
define('DOL_DOCUMENT_ROOT',$argv[1]);
require_once __DIR__.'/../class/extraction.class.php';
try{
 if(!function_exists('posix_setrlimit'))throw new RuntimeException('limits unavailable');
 foreach([[POSIX_RLIMIT_AS,402653184],[POSIX_RLIMIT_CPU,3],[POSIX_RLIMIT_FSIZE,3145728],[POSIX_RLIMIT_NOFILE,64]] as [$resource,$limit])if(!posix_setrlimit($resource,$limit,$limit))throw new RuntimeException('limits unavailable');
 $request=json_decode(file_get_contents($argv[3]),true,12,JSON_THROW_ON_ERROR);
 $bytes=file_get_contents($argv[2],false,null,0,MaxOrdersExtraction::MAX_BYTES+1);
 $analysis=MaxOrdersExtraction::inspect($request['name'],$bytes,$request['options']);
 $analysis['resource_limits']=['wall_seconds'=>5,'cpu_seconds'=>3,'php_heap_bytes'=>134217728,'address_space_bytes'=>402653184,'max_output_bytes'=>262144];
 echo json_encode(['analysis'=>$analysis],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){echo json_encode(['error'=>$e->getMessage()]);}catch(Throwable $e){exit(1);}
