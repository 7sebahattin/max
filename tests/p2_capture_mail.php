<?php
// Replaces sendmail for this ONE CLI invocation. No network or forwarding code.
$raw = stream_get_contents(STDIN, 65537);
if (strlen($raw) > 65536 || !str_contains($raw, 'recipient@example.invalid') || !str_contains($raw, 'P2-CAPTURE-ONLY')) {
    exit(1);
}
$path = '/var/lib/dolibarr/documents/p2-captured.eml';
if (file_put_contents($path, $raw, LOCK_EX) === false) { exit(1); }
chmod($path, 0600);
