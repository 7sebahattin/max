<?php
require dirname(__DIR__).'/lib/bootstrap.php';
maxCoreRequire('admin'); maxCoreHeader($langs->trans('MAXAdministration'));
print '<main class="maxcore-shell"><h1>'.$langs->trans('MAXAdministration').'</h1><p>'.$langs->trans('MAXPrototypeNotice').'</p><p>MAX Core 0.1.0 · Dolibarr '.dol_escape_htmltag(DOL_VERSION).'</p></main>';
llxFooter(); $db->close();
