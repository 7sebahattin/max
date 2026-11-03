<?php
require __DIR__.'/lib/web.php';
try {
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $result=mailAction($mail,$user);
        if(isset($result['result'])) {
            $poll=$result['result'];
            if(!empty($poll['transport_error']) || $poll['state']==='blocked') http_response_code($poll['state']==='retry_due'?503:409);
            elseif($poll['state']==='review_required') http_response_code(202);
        }
    } elseif(GETPOST('kind','alpha')==='entry') $result=$mail->detail($user,GETPOSTINT('id'));
    elseif(GETPOSTINT('account')) $result=['entries'=>$mail->entries($user,GETPOSTINT('account'))];
    else $result=['accounts'=>$mail->accounts($user)];
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_THROW_ON_ERROR);
} catch(Throwable $e) { mailError($e); }
