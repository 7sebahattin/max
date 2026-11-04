<?php
// Native Dolibarr bootstrap and CSRF. GPL-3.0-or-later.
define('CSRFCHECK_WITH_TOKEN',1);
require dirname(__DIR__,3).'/main.inc.php';
require_once __DIR__.'/../class/declaration.class.php';
$langs->load('maxcustoms@maxcustoms');
function customsEscape($value): string { return dol_escape_htmltag((string)$value); }
function customsLabel(string $key): string { global $langs; return customsEscape($langs->trans($key)); }
function customsFailure(Throwable $error): void
{
    $code=in_array($error->getCode(),[400,403,404,409],true)?$error->getCode():500;
    http_response_code($code);
    header('Cache-Control: no-store');
    if(basename($_SERVER['SCRIPT_NAME']??'')==='api.php') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>$code===500?'CustomsStorageError':$error->getMessage()]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        print '<p role="alert">'.customsLabel($code===500?'CustomsStorageError':$error->getMessage()).'</p>';
    }
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'')==='POST' && GETPOST('errorcode','alpha')==='InvalidToken') customsFailure(new RuntimeException('CustomsDenied',403));
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { http_response_code(405); exit; }
$customs=new MaxCustomsDeclaration($db);
function customsInput(string $name,string $value='',string $type='text'): void
{
    print '<label>'.customsLabel('CustomsField_'.$name).'<input name="'.customsEscape($name).'" type="'.customsEscape($type).'" maxlength="2000" value="'.customsEscape($value).'"></label>';
}
function customsOpenForm(string $action,int $id=0,int $version=0,bool $upload=false): void
{
    print '<form method="post"'.($upload?' enctype="multipart/form-data"':'').'><input type="hidden" name="token" value="'.customsEscape(newToken()).'"><input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="version" value="'.$version.'">';
}
function customsSubmit(string $label): void { print '<button type="submit">'.customsLabel($label).'</button></form>'; }
function customsPage(string $title): void
{
    llxHeader('',$title,'','',0,0,[],['/maxcustoms/css/customs.css'],'','maxcustoms-page');
    print '<main class="maxcustoms-shell"><h1>'.customsEscape($title).'</h1><p>'.customsLabel('CustomsLocalOnly').'</p>';
}
function customsEnd(): void { global $db; print '<p>'.customsLabel('CustomsAttribution').'</p></main>'; llxFooter(); $db->close(); }
function customsFields(array $data,bool $reference=false): void
{
    if($reference) customsInput('ref');
    foreach(MaxCustomsDeclaration::FIELDS as $key=>$limit) {
        $value=(string)($data[$key]??'');
        if(in_array($key,['goods_summary','references'],true)) print '<label>'.customsLabel('CustomsField_'.$key).'<textarea name="'.$key.'" maxlength="'.$limit.'" rows="3">'.customsEscape($value).'</textarea></label>';
        elseif($key==='mode') {
            print '<label>'.customsLabel('CustomsField_mode').'<select name="mode">';
            foreach(['','road','sea','air','rail','other'] as $mode) print '<option value="'.$mode.'"'.($mode===$value?' selected':'').'>'.customsLabel('CustomsMode_'.$mode).'</option>';
            print '</select></label>';
        } else customsInput($key,$value,str_ends_with($key,'_date')?'date':'text');
    }
}
function customsPrivateInputs($actor,array $data): void
{
    foreach(MaxCustomsDeclaration::RESTRICTED as $key=>$column) if(MaxCustomsDeclaration::permits($actor,$key)) print '<label>'.customsLabel('CustomsRestricted_'.$key).'<textarea name="'.$key.'" maxlength="'.($key==='source'?4096:2000).'" rows="3">'.customsEscape($data[$key]??'').'</textarea></label>';
}
function customsAction($service,$actor,bool $api=false): int
{
    $id=GETPOSTINT('id'); $version=GETPOSTINT('version'); $action=GETPOST('action','aZ09');
    $public=[]; $private=[];
    if(in_array($action,['create','edit'],true)) {
        if($api) {
            $public=MaxCustomsDeclaration::object(GETPOST('fields','none'));
            $private=isset($_POST['restricted'])?MaxCustomsDeclaration::object(GETPOST('restricted','none')):[];
        } else {
            foreach(MaxCustomsDeclaration::FIELDS as $field=>$limit) if(isset($_POST[$field])) $public[$field]=$_POST[$field];
            foreach(MaxCustomsDeclaration::RESTRICTED as $field=>$column) if(isset($_POST[$field])) $private[$field]=$_POST[$field];
        }
        if($action==='create') return $service->create($actor,GETPOST('ref','none'),$public,$private);
        $service->edit($actor,$id,$version,$public,$private);
    } elseif($action==='match') {
        $shipmentIds=[];
        $raw=trim((string)GETPOST('shipments','none'));
        if($raw!=='') foreach(explode(',',$raw) as $value) {
            $value=trim($value);
            if(!preg_match('/^[1-9]\d{0,9}$/D',$value)) throw new InvalidArgumentException('CustomsInvalid',400);
            $shipmentIds[]=(int)$value;
        }
        $service->matchNative($actor,$id,$version,GETPOSTINT('customer'),GETPOSTINT('order'),$shipmentIds,GETPOST('order_reference','none'),GETPOST('confirm','aZ09')==='yes');
    } elseif($action==='status') $service->transition($actor,$id,$version,GETPOST('target','aZ09'),GETPOST('confirm','aZ09')==='yes');
    elseif($action==='acknowledge') $service->acknowledgeEvidence($actor,$id,$version,GETPOSTINT('evidence_id'),GETPOST('confirm','aZ09')==='yes');
    elseif($action==='evidence' && !$api) {
        $file=$_FILES['evidence']??null;
        if(!$file || count($_FILES)!==1 || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name'])>8192) throw new InvalidArgumentException('CustomsInvalidEvidence',400);
        $service->addEvidence($actor,$id,$version,$file['name'],file_get_contents($file['tmp_name']),$file['type']);
    } else throw new InvalidArgumentException('CustomsInvalid',400);
    return $id;
}
