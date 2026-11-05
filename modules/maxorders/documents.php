<?php
// Only this endpoint accepts native PHP multipart uploads.
define('MAXORDERS_DOCUMENT_UPLOAD',1);
require __DIR__.'/lib/bootstrap.php';
require_once __DIR__.'/class/document.class.php';
$documents=new MaxOrdersDocument($db); $notice='';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','POST'],true)) { http_response_code(405); exit; }
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
 try {
  if(!MaxOrdersPacket::can($user,'intake')) throw new RuntimeException('OrdersDenied',403);
  if(GETPOST('action','aZ09')!=='upload' || array_keys($_FILES)!==['document_file']) throw new InvalidArgumentException('OrdersFileInvalid',400);
  $file=$_FILES['document_file'];
  if(!is_string($file['name']) || !is_string($file['tmp_name']) || $file['error']!==UPLOAD_ERR_OK || $file['size']>MaxOrdersExtraction::MAX_BYTES || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('OrdersFileInvalid',400);
  $bytes=file_get_contents($file['tmp_name'],false,null,0,MaxOrdersExtraction::MAX_BYTES+1);
  $result=$documents->receive($user,$file['name'],$bytes);
  header('Location: '.dol_buildpath('/maxorders/document.php',1).'?id='.$result['id']); exit;
 } catch(Throwable $e) {
  $code=in_array($e->getCode(),[400,403,503],true)?$e->getCode():500; http_response_code($code);
  if($code===403) accessforbidden('',0,0,1);
  $notice=$code===500?'OrdersStorageError':$e->getMessage();
 }
}
moHeader($langs->trans('OrdersDocuments'));
print '<section data-maxorders="documents"><p><a href="'.dol_buildpath('/maxorders/index.php',1).'">'.moLabel('OrdersInbox').'</a></p>';
if($notice) print '<p role="alert">'.moLabel($notice).'</p>';
print '<p>'.moLabel('OrdersUploadRule').'</p>';
if(MaxOrdersPacket::can($user,'intake')) {
 print '<form method="post" enctype="multipart/form-data" data-orders-upload><input type="hidden" name="token" value="'.moEsc(newToken()).'"><input type="hidden" name="action" value="upload"><label>'.moLabel('OrdersFile').'<input type="file" name="document_file" accept=".pdf,.xlsx" required></label>';
 moButton('OrdersUpload');
}
print '<ul>';
foreach($documents->listing($user) as $document) print '<li><a href="'.dol_buildpath('/maxorders/document.php',1).'?id='.(int)$document->rowid.'">'.moEsc($document->original_name).'</a> — '.moValue($document->analysis_status).' / '.moValue($document->link_status).'</li>';
print '</ul></section>'; moFooter();
