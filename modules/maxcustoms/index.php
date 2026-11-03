<?php
require __DIR__.'/lib/http.php';
try {
    $rows=$customs->listing($user);
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $id=customsAction($customs,$user);
        header('Location: '.dol_buildpath('/maxcustoms/card.php',1).'?id='.$id); exit;
    }
} catch(Throwable $e) { customsFailure($e); }
customsPage($langs->trans('CustomsTitle'));
print '<section data-maxcustoms="list"><h2>'.customsLabel('CustomsDrafts').'</h2><ul>';
foreach($rows as $row) print '<li><a href="'.dol_buildpath('/maxcustoms/card.php',1).'?id='.(int)$row->rowid.'">'.customsEscape($row->ref).'</a> — '.customsLabel('CustomsStatus_'.$row->status).'</li>';
print '</ul></section>';
if(MaxCustomsDeclaration::permits($user,'create')) {
    print '<section data-maxcustoms="create"><h2>'.customsLabel('CustomsCreate').'</h2>';
    customsOpenForm('create'); customsFields([],true); customsPrivateInputs($user,[]); customsSubmit('CustomsCreate'); print '</section>';
}
customsEnd();
