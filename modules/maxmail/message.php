<?php
require __DIR__.'/lib/web.php';
try {
    $id=GETPOSTINT('id'); $d=$mail->detail($user,$id); $cap=$mail->capabilities($user,$d['account']);
    if(($_SERVER['REQUEST_METHOD']??'')==='POST') { mailAction($mail,$user); header('Location: '.dol_buildpath('/maxmail/message.php',1).'?id='.$id); exit; }
} catch(Throwable $e) { mailError($e); }
mailHeader($langs->trans('MailMessage'));
print '<section data-maxmail="message"><h2>'.mailEscape($d['subject']).'</h2><p>'.mailLabel('MailHeaderUntrusted').'</p><dl>';
foreach(['sender','message_id','uid','uidvalidity','attempts'] as $key) print '<dt>'.mailLabel('MailField_'.$key).'</dt><dd>'.mailEscape($d[$key]).'</dd>';
print '<dt>'.mailLabel('MailState').'</dt><dd>'.mailLabel('MailState_'.$d['state']).'</dd><dd>'.mailLabel('MailReason_'.$d['error_code']).'</dd></dl>';
if($d['remote_deleted']) print '<p>'.mailLabel('MailTombstone').'</p>';
if($cap['review'] && in_array($d['state'],['failed','retry_due'],true)) { mailForm('retry',$d['account'],$id); mailSubmit('MailQueueRetry'); }
if($cap['review'] && $d['state']==='review_required' && !$d['reviewed_by']) {
    mailForm('acknowledge',$d['account'],$id); print '<label class="maxmail-check"><input name="confirm" type="checkbox" value="yes" required> '.mailLabel('MailAcknowledgeOnly').'</label>'; mailSubmit('MailAcknowledge');
}
if($d['reviewed_by']) print '<p>'.mailLabel('MailReviewedBy').' '.(int)$d['reviewed_by'].' '.mailEscape($d['reviewed_at']).'</p>';
print '<h2>'.mailLabel('MailPrivateFiles').'</h2><ul>';
foreach($d['files'] as $f) {
    print '<li>'.mailEscape($f->display_name).' — '.(int)$f->byte_size.' '.mailLabel('MailBytes').' · '.mailLabel('MailFile_'.$f->state).' '.mailLabel('MailReason_'.$f->reason);
    if($cap['download'] && ($f->state==='validated' || $cap['review'])) print ' <a href="'.dol_buildpath('/maxmail/download.php',1).'?id='.$id.'&file='.(int)$f->rowid.'">'.mailLabel('MailDownload').'</a>';
    print '</li>';
}
print '</ul><p>'.mailLabel('MailPdfBlocked').'</p></section><h2>'.mailLabel('MailHistory').'</h2><div class="maxmail-scroll" tabindex="0" role="region" aria-label="'.mailLabel('MailHistory').'"><table><thead><tr><th>'.mailLabel('MailEvent').'</th><th>'.mailLabel('MailActor').'</th><th>'.mailLabel('MailTime').'</th></tr></thead><tbody>';
foreach($d['events'] as $event) print '<tr><td>'.mailLabel('MailEvent_'.$event->event).' '.mailLabel('MailReason_'.$event->code).'</td><td>'.(int)$event->fk_user.'</td><td>'.mailEscape($event->datec).'</td></tr>';
print '</tbody></table></div>'; mailFooter();
