<?php
// Native bootstrap for htdocs/custom/maxcore/lib. No secondary login or CSRF bypass.
define('CSRFCHECK_WITH_TOKEN',1);
require dirname(__DIR__,3).'/main.inc.php';
// Native 24.0.1 clears invalid-token POST data and exposes this error code.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && GETPOST('errorcode', 'alpha') === 'InvalidToken') {
    http_response_code(403); accessforbidden('', 0, 0, 1);
}
require_once __DIR__.'/maxcore.lib.php';
$langs->load('maxcore@maxcore');
