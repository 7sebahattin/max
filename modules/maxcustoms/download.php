<?php
require __DIR__.'/lib/http.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET') throw new InvalidArgumentException('CustomsInvalid',400);
    $file=$customs->download($user,GETPOSTINT('id'),GETPOSTINT('evidence_id'));
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="evidence-'.GETPOSTINT('evidence_id').'.txt"');
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'");
    readfile($file['path']);
} catch(Throwable $e) { customsFailure($e); }
