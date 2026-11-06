<?php
// Included only by the owned CLI fixture. No committed commercial changes.
if(PHP_SAPI!=='cli'||!isset($run,$db,$hookmanager))exit(2);
// Keep row/mapping/driver locals separate from the caller's product regression.
function installedOrderImportChecks($run,$db,$user,$hookmanager){
// Resolve every registered profile through the actual enabled native descriptors.
foreach(MaxInput::registry()['imports'] as $code=>$fields){
 $descriptor=new Import($db);$descriptor->load_arrays($user,$code);
 installedCheck(count($descriptor->array_import_code)===1&&in_array($code,$descriptor->array_import_code,true),'native registry dataset '.$code);
 $nativeFields=reset($descriptor->array_import_fields);
 installedCheck(is_array($nativeFields)&&!array_diff_key($fields,$nativeFields),'native registry field aliases '.$code);
}
foreach(['csv','xlsx'] as $format){
 foreach(['commande_1','commande_lines_2'] as $code){
  $isLine=$code==='commande_lines_2';
  $orderRef=installedRows('SELECT ref FROM '.MAIN_DB_PREFIX.'commande ORDER BY rowid LIMIT 1')[0]['ref'];
  $reference='Input-OrderCase-'.$format;
  $values=$isLine?[$orderRef,'çiçek straße <a href="https://Example.invalid/Case">şişli</a>','2','0']:[$reference,'id:'.json_decode(file_get_contents($run.'/protected-ids.json'),true)['societe'],'2026-11-06','0','çiçek straße','şişli'];
  $map=$isLine?[1=>'cd.fk_commande',2=>'cd.description',3=>'cd.qty',4=>'cd.product_type']:[1=>'c.ref',2=>'c.fk_soc',3=>'c.date_commande',4=>'c.fk_statut',5=>'c.note_public',6=>'c.note_private'];
  $path=$run.'/documents/'.$code.'.'.$format;
  if($format==='csv'){$handle=fopen($path,'wb');fputcsv($handle,$values);fclose($handle);$driver=new ImportCsv($db,$code);}
  else{$book=new PhpOffice\PhpSpreadsheet\Spreadsheet();$book->getActiveSheet()->fromArray([$values]);(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);$book->disconnectWorksheets();$driver=new ImportXlsx($db,$code);}
  $hash=hash_file('sha256',$path);installedCheck($driver->import_open_file($path)>0,$code.' '.$format.' source open');$record=$driver->import_read_record();$driver->import_close_file();$raw=$record;
  $descriptor=new Import($db);$descriptor->load_arrays($user,$code);$ok=0;
  $parameters=['datatoimport'=>$code,'obj'=>$driver,'arrayrecord'=>$record,'array_match_file_to_database'=>$map,'objimport'=>$descriptor,'fieldssource'=>array_values($map),'importid'=>'OrderInputCase','updatekeys'=>[],'nbok'=>&$ok];
  $db->begin();
  try{
   installedCheck($hookmanager->executeHooks('ImportInsert',$parameters)===1&&$ok===1&&$db->transaction_opened===1,$code.' '.$format.' native insert within simulation');
   if($isLine){$rows=installedRows('SELECT description,qty FROM '.MAIN_DB_PREFIX.'commandedet WHERE import_key=\'OrderInputCase\'');installedCheck(count($rows)===1&&$rows[0]['description']==='ÇİÇEK STRASSE <a href="https://Example.invalid/Case">ŞİŞLİ</a>'&&(float)$rows[0]['qty']===2.0,$code.' '.$format.' uppercase prose exact URI and quantity');}
   else{$rows=installedRows("SELECT ref,note_public,note_private FROM ".MAIN_DB_PREFIX."commande WHERE ref='".$db->escape($reference)."'");installedCheck(count($rows)===1&&$rows[0]['ref']===$reference&&$rows[0]['note_public']==='ÇİÇEK STRASSE'&&$rows[0]['note_private']==='ŞİŞLİ',$code.' '.$format.' uppercase notes exact reference');}
   installedCheck($raw===$record&&$hash===hash_file('sha256',$path),$code.' '.$format.' immutable typed row and source');
  }finally{$db->rollback();}
  installedCheck(count(installedRows('SELECT rowid FROM '.MAIN_DB_PREFIX.($isLine?'commandedet':'commande')." WHERE import_key='OrderInputCase'"))===0,$code.' '.$format.' rollback removes all proposals');
 }
}
}
installedOrderImportChecks($run,$db,$user,$hookmanager);
