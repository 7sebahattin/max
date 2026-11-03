<?php
require __DIR__.'/lib/bootstrap.php';
maxCoreRequire('read');
$s=maxCoreSettings(); maxCoreHeader($langs->trans('MAXHome'));
print '<main class="maxcore-shell">';
if(in_array($s['MAXCORE_LOGO'],array('max.svg','max-icon.svg'),true))
 print '<img class="maxcore-logo" alt="" src="'.dol_escape_htmltag(dol_buildpath('/maxcore/img/'.$s['MAXCORE_LOGO'],1)).'">';
print '<h1>'.dol_escape_htmltag($s['MAXCORE_DISPLAY_NAME']).'</h1>';
print '<p>'.dol_escape_htmltag($langs->trans('MAXEdition')).' 0.1.0 · Dolibarr '.dol_escape_htmltag(DOL_VERSION).'</p>';
print '<p>'.$langs->trans('MAXLegalSeparation').'</p>';
if(maxCoreCan($user,'configure')) print '<p><a class="butAction" href="'.dol_buildpath('/maxcore/admin/setup.php',1).'">'.$langs->trans('MAXSettings').'</a></p>';
print '<h2>'.$langs->trans('MAXOptionalModules').'</h2><ul class="maxcore-slots">';
foreach(array('Orders','Mail','Customs','HKS','Reports') as $slot) print '<li>'.$langs->trans('MAXSlot'.$slot).' — '.$langs->trans('MAXPlanned').'</li>';
print '</ul><p class="opacitymedium">'.$langs->trans('MAXAttribution').'</p></main>';
llxFooter(); $db->close();
