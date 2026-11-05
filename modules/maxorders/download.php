<?php
// GET only; native session and the same scoped service authorize every download.
if(($_SERVER['REQUEST_METHOD']??'')!=='GET') { http_response_code(405); exit; }
define('NOREDIRECTBYMAINTOLOGIN',1);
http_response_code(403); // Native early unauthenticated exit retains denial.
require __DIR__.'/lib/bootstrap.php';
require_once __DIR__.'/class/document.class.php';
try {
 $file=(new MaxOrdersDocument($db))->download($user,GETPOSTINT('id'));
 http_response_code(200);
 header('Content-Type: application/octet-stream');
 header('Content-Disposition: attachment; filename="maxorders-document-'.GETPOSTINT('id').'.'.$file['format'].'"');
 header('Content-Length: '.$file['size']); header('Cache-Control: private, no-store');
 header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'");
 readfile($file['path']);
} catch(Throwable $e) {
 $code=in_array($e->getCode(),[403,404],true)?$e->getCode():500;
 moError(new RuntimeException($code===404?'OrdersNotFound':($code===403?'OrdersDenied':'OrdersStorageError'),$code));
}
