<?php
require dirname(__DIR__).'/lib/bootstrap.php';
maxCoreRequire('configure');
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
 try {
  maxCoreSave(array('MAXCORE_DISPLAY_NAME'=>GETPOST('MAXCORE_DISPLAY_NAME','restricthtml'),
   'MAXCORE_LOGO'=>GETPOST('MAXCORE_LOGO','alpha'),'MAXCORE_FAVICON'=>GETPOST('MAXCORE_FAVICON','alpha')),$user);
  setEventMessages($langs->trans('MAXSaved'),null,'mesgs');
  header('Location: '.dol_buildpath('/maxcore/admin/setup.php',1),true,303); exit;
 } catch(InvalidArgumentException $e) { http_response_code(400); setEventMessages($langs->trans($e->getMessage()),null,'errors');
 } catch(RuntimeException $e) { http_response_code(500); setEventMessages($langs->trans('MAXSaveFailed'),null,'errors'); }
}
$s=maxCoreSettings(); maxCoreHeader($langs->trans('MAXSettings'));
print '<main class="maxcore-shell"><h1>'.$langs->trans('MAXSettings').'</h1><p>'.$langs->trans('MAXLegalSeparation').'</p>';
print '<form method="post" action="'.dol_buildpath('/maxcore/admin/setup.php',1).'"><input type="hidden" name="token" value="'.newToken().'">';
print '<label for="MAXCORE_DISPLAY_NAME">'.$langs->trans('MAXDisplayName').'</label><input id="MAXCORE_DISPLAY_NAME" name="MAXCORE_DISPLAY_NAME" maxlength="80" required value="'.dol_escape_htmltag($s['MAXCORE_DISPLAY_NAME']).'">';
foreach(array('MAXCORE_LOGO'=>'MAXLogo','MAXCORE_FAVICON'=>'MAXFavicon') as $key=>$label) {
 print '<label for="'.$key.'">'.$langs->trans($label).'</label><select id="'.$key.'" name="'.$key.'">';
 foreach(array('none','max.svg','max-icon.svg') as $asset)
  print '<option value="'.$asset.'"'.($s[$key]===$asset?' selected':'').'>'.dol_escape_htmltag($asset).'</option>';
 print '</select>';
}
print '<p>'.$langs->trans('MAXAssetsHelp').'</p><button class="button" type="submit">'.$langs->trans('Save').'</button></form></main>';
llxFooter(); $db->close();
