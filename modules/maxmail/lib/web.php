<?php
// Native session, explicit native CSRF token, no secondary authentication. GPL-3.0-or-later.
define('CSRFCHECK_WITH_TOKEN',1);
require dirname(__DIR__,3).'/main.inc.php';
require_once __DIR__.'/../class/intake.class.php';
$langs->load('maxmail@maxmail');
function mailEscape($value): string { return dol_escape_htmltag((string)$value); }
function mailLabel(string $key): string { global $langs; return mailEscape($langs->trans($key)); }
function mailError(Throwable $e): void
{
    $status=in_array($e->getCode(),[400,403,404,409],true)?$e->getCode():500;
    http_response_code($status); header('Cache-Control: no-store');
    $key=$status===500?'MailStorageError':$e->getMessage();
    if(basename($_SERVER['SCRIPT_NAME']??'')==='api.php') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$key]); }
    else print '<p role="alert">'.mailLabel($key).'</p>';
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')==='POST' && GETPOST('errorcode','alpha')==='InvalidToken') mailError(new RuntimeException('MailDenied',403));
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { http_response_code(405); exit; }
if(!empty($_FILES)) mailError(new InvalidArgumentException('MailInvalid',400));
$mail=new MaxMailIntake($db);
function mailHeader(string $title): void
{
    llxHeader('',$title,'','',0,0,[],['/maxmail/css/mail.css'],'','maxmail-page');
    print '<main class="maxmail-shell"><h1>'.mailEscape($title).'</h1><p>'.mailLabel('MailReviewOnly').'</p>';
}
function mailFooter(): void { global $db; print '<p>'.mailLabel('MailAttribution').'</p></main>'; llxFooter(); $db->close(); }
function mailForm(string $action,int $account=0,int $entry=0): void
{
    print '<form method="post"><input type="hidden" name="token" value="'.mailEscape(newToken()).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="account" value="'.$account.'"><input type="hidden" name="id" value="'.$entry.'">';
}
function mailField(string $name,string $type='text',string $value=''): void
{
    print '<label>'.mailLabel('MailField_'.$name).'<input name="'.$name.'" type="'.$type.'" maxlength="256" value="'.mailEscape($value).'" required'.($type==='password'?' autocomplete="new-password"':'').'></label>';
}
function mailSubmit(string $key): void { print '<button type="submit">'.mailLabel($key).'</button></form>'; }
function mailAction($service,$actor): array
{
    $action=GETPOST('action','aZ09'); $account=GETPOSTINT('account'); $id=GETPOSTINT('id');
    if($action==='poll') return ['account'=>$account,'result'=>$service->poll($actor,$account)];
    if($action==='retry') { $service->requestRetry($actor,$id); return ['id'=>$id]; }
    if($action==='acknowledge') {
        if(GETPOST('confirm','aZ09')!=='yes') throw new InvalidArgumentException('MailConfirmRequired',400);
        $service->acknowledge($actor,$id); return ['id'=>$id];
    }
    if($action==='account') {
        $new=$service->createAccount($actor,['label'=>GETPOST('label','none'),'host'=>GETPOST('host','none'),'port'=>GETPOSTINT('port'),
            'transport'=>GETPOST('transport','aZ09'),'username'=>GETPOST('username','none'),'password'=>GETPOST('password','none'),'folder'=>GETPOST('folder','none')]);
        return ['account'=>$new];
    }
    if($action==='grant') {
        $grants=[]; foreach(['view','poll','review','download'] as $right) $grants[$right]=GETPOST('scope_'.$right,'aZ09')==='yes';
        $service->grant($actor,$account,GETPOSTINT('target_user'),$grants); return ['account'=>$account];
    }
    if($action==='credential') { $service->configure($actor,$account,GETPOST('password','none'),null); return ['account'=>$account]; }
    if($action==='active') { $service->configure($actor,$account,null,GETPOST('active','aZ09')==='yes'); return ['account'=>$account]; }
    throw new InvalidArgumentException('MailInvalid',400);
}
