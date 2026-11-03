<?php
require __DIR__.'/lib/http.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET') throw new InvalidArgumentException('CustomsInvalid',400);
    $id=GETPOSTINT('id'); $csv=$customs->export($user,$id);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="maxcustoms-'.$id.'.csv"');
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); echo $csv;
} catch(Throwable $e) { customsFailure($e); }
