<?php
// PHP development-server routing only; dispatch unchanged native PHP scripts.
if (PHP_SAPI !== 'cli-server' || empty(getenv('MAX_INPUT_NATIVE_RUN'))) { http_response_code(403); exit; }
$root=realpath(getenv('MAX_INPUT_NATIVE_ROOT'));
$_SERVER['QUERY_STRING']=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY)??'';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
// Native API PATH_INFO needs a router on PHP's Windows development server.
if (str_starts_with($path,'/api/index.php/')) {
 $_SERVER['SCRIPT_FILENAME']=$root.'/api/index.php';
 $_SERVER['SCRIPT_NAME']='/api/index.php';
 $_SERVER['PHP_SELF']=$path;
 $_SERVER['PATH_INFO']=substr($path,strlen('/api/index.php'));
 chdir($root.'/api'); require $root.'/api/index.php'; return true;
}
return false;
