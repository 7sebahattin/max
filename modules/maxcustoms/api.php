<?php
require __DIR__.'/lib/http.php';
try {
    if(!empty($_FILES)) throw new InvalidArgumentException('CustomsInvalid',400);
    $id=($_SERVER['REQUEST_METHOD']??'')==='POST'?customsAction($customs,$user,true):GETPOSTINT('id');
    $result=$customs->projection($user,$id);
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) { customsFailure($e); }
