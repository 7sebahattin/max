<?php
// Native bootstrap for htdocs/custom/maxcore/lib. No secondary login or CSRF bypass.
define('CSRFCHECK_WITH_TOKEN',1);
require dirname(__DIR__,3).'/main.inc.php';
require_once __DIR__.'/maxcore.lib.php';
$langs->load('maxcore@maxcore');
