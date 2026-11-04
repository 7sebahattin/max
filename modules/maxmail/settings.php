<?php
require __DIR__.'/lib/web.php';
try {
    if(!isModEnabled('maxmail') || !MaxMailIntake::right($user,'admin')) throw new RuntimeException('MailDenied',403);
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') { mailAction($mail,$user); header('Location: '.dol_buildpath('/maxmail/settings.php',1)); exit; }
    $accounts=$mail->accounts($user);
} catch(Throwable $e) { mailError($e); }
mailHeader($langs->trans('MailSettings'));
print '<section data-maxmail="settings"><p>'.mailLabel('MailConfigBoundary').'</p><h2>'.mailLabel('MailAddAccount').'</h2>';
mailForm('account');
foreach(['label','host','username','folder'] as $field) mailField($field,'text',$field==='folder'?'INBOX':'');
mailField('port','number','993'); mailField('password','password');
print '<label>'.mailLabel('MailField_transport').'<select name="transport"><option value="ssl">TLS / SSL</option><option value="tls">STARTTLS</option>';
if(getDolGlobalInt('MAXMAIL_ALLOW_SYNTHETIC_IMAP')===1) print '<option value="synthetic">'.mailLabel('MailSynthetic').'</option>';
print '</select></label>'; mailSubmit('MailAddAccount');
foreach($accounts as $a) {
    print '<article><h2>'.mailEscape($a->label).'</h2>';
    mailForm('credential',(int)$a->rowid); mailField('password','password'); mailSubmit('MailRotateSecret');
    mailForm('active',(int)$a->rowid); print '<label class="maxmail-check"><input type="checkbox" name="active" value="yes"'.($a->active?' checked':'').'> '.mailLabel('MailActive').'</label>'; mailSubmit('MailSetActive');
    mailForm('grant',(int)$a->rowid); mailField('target_user','number');
    foreach(['view','poll','review','download'] as $right) print '<label class="maxmail-check"><input type="checkbox" name="scope_'.$right.'" value="yes"> '.mailLabel('MailPermission_'.$right).'</label>';
    mailSubmit('MailGrant'); print '</article>';
}
print '</section>'; mailFooter();
