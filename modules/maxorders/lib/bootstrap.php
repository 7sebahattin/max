<?php
// Native session, roles and CSRF. GPL-3.0-or-later.
define('CSRFCHECK_WITH_TOKEN',1);
require dirname(__DIR__,3).'/main.inc.php';
if (($_SERVER['REQUEST_METHOD']??'')==='POST' && GETPOST('errorcode','alpha')==='InvalidToken') { http_response_code(403); accessforbidden('',0,0,1); }
require_once __DIR__.'/../class/packet.class.php';
require_once __DIR__.'/view.php';
$langs->load('maxorders@maxorders');
$packets=new MaxOrdersPacket($db);
if(!isModEnabled('maxorders') || !MaxOrdersPacket::can($user,'read')) { http_response_code(403); accessforbidden('',0,0,1); }
if(!empty($_FILES) && !defined('MAXORDERS_DOCUMENT_UPLOAD')) { http_response_code(400); exit('OrdersInvalid'); }
