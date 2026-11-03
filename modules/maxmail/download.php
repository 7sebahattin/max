<?php
require __DIR__.'/lib/web.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET') throw new InvalidArgumentException('MailInvalid',400);
    $file=$mail->download($user,GETPOSTINT('id'),GETPOSTINT('file'));
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="maxmail-file-'.GETPOSTINT('file').'.bin"');
    header('Content-Length: '.$file['size']); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'");
    readfile($file['path']);
} catch(Throwable $e) { mailError($e); }
