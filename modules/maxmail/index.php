<?php
require __DIR__.'/lib/web.php';
try {
    $accounts=$mail->accounts($user); $account=GETPOSTINT('account');
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
        $result=mailAction($mail,$user); header('Location: '.dol_buildpath('/maxmail/index.php',1).'?account='.(int)($result['account']??0)); exit;
    }
    $entries=$account?$mail->entries($user,$account):[];
    $capabilities=$account?$mail->capabilities($user,$account):[];
} catch(Throwable $e) { mailError($e); }
mailHeader($langs->trans('MailTitle'));
print '<section data-maxmail="accounts"><h2>'.mailLabel('MailAccounts').'</h2><ul>';
foreach($accounts as $a) print '<li><a href="'.dol_buildpath('/maxmail/index.php',1).'?account='.(int)$a->rowid.'">'.mailEscape($a->label).'</a> — '.mailLabel('MailState_'.$a->state).($a->active?'':' · '.mailLabel('MailDisabled')).'</li>';
print '</ul>';
if(MaxMailIntake::right($user,'admin')) print '<p><a href="'.dol_buildpath('/maxmail/settings.php',1).'">'.mailLabel('MailSettings').'</a></p>';
print '</section>';
if($account) {
    print '<section data-maxmail="inbox"><h2>'.mailLabel('MailInbox').'</h2>';
    if($capabilities['poll']) { mailForm('poll',$account); mailSubmit('MailPoll'); }
    print '<ul>';
    foreach($entries as $e) print '<li><a href="'.dol_buildpath('/maxmail/message.php',1).'?id='.(int)$e->rowid.'">'.mailEscape($e->subject?:$langs->trans('MailUntitled')).'</a> — '.mailLabel('MailState_'.$e->state).' '.mailLabel('MailReason_'.$e->error_code).'</li>';
    print '</ul></section>';
}
mailFooter();
