<?php
// Synthetic native outbound test through a capture-only sendmail replacement.
if (PHP_SAPI !== 'cli' || ini_get('sendmail_path') !== 'php /checks/p2_capture_mail.php') {
    throw new RuntimeException('Capture handler must be explicitly selected');
}
chdir('/opt/dolibarr/htdocs');
require 'master.inc.php';
if ($dolibarr_main_db_host !== 'db' || $dolibarr_main_db_name !== 'max_demo') {
    throw new RuntimeException('Refusing non-disposable pilot');
}
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
$conf->global->MAIN_DISABLE_ALL_MAILS = 0; // In-memory only, never persisted.
$conf->global->MAIN_MAIL_SENDMODE = 'mail';
$path = DOL_DATA_ROOT.'/p2-captured.eml';
if (file_exists($path)) { throw new RuntimeException('Capture must start empty'); }
$mail = new CMailFile('P2-CAPTURE-ONLY', 'recipient@example.invalid', 'sender@example.invalid', 'Synthetic outgoing capture body');
if (!$mail->sendfile() || !is_file($path)) {
    throw new RuntimeException('Native mail capture failed: '.$mail->error);
}
$raw = file_get_contents($path);
if (!str_contains($raw, 'recipient@example.invalid') || !str_contains($raw, 'P2-CAPTURE-ONLY')) {
    throw new RuntimeException('Unexpected captured MIME');
}
unlink($path);
echo "PASS native outgoing mail through capture-only local sendmail; no SMTP/network delivery\n";
