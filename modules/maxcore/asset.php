<?php
// Public, non-sensitive current product artwork for the login/favicon surfaces.
// No arbitrary file/entity selector, business data, session or mutation API.
require dirname(__DIR__,2).'/master.inc.php';
require_once __DIR__.'/lib/maxcore.lib.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET') { http_response_code(405); header('Allow: GET'); exit; }
if(!isModEnabled('maxcore')) { http_response_code(404); exit; }
$kind=$_GET['kind']??'';
if(!is_string($kind)||!in_array($kind,array('logo','favicon'),true)) { http_response_code(404); exit; }
$value=maxCoreSettings()[$kind==='logo'?'MAXCORE_LOGO':'MAXCORE_FAVICON'];
try { $path=maxCoreAssetPath($value); }
catch(Exception $e) { http_response_code(404); exit; }
header('Content-Type: image/png'); header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; sandbox');
header('Cache-Control: no-store'); // Current pointer may change; never retain another customer's asset.
readfile($path);
