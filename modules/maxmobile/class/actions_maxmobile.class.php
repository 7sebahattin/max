<?php
class ActionsMaxmobile {
 public $resprints='';
 public function __construct($db) {}
 private function applies() {
  global $user;
  if(empty($user->id) || !empty($user->socid) || !isModEnabled('maxmobile')) return false;
  $path=substr($_SERVER['PHP_SELF']??'',strlen(DOL_URL_ROOT));
  return in_array($path,array('/societe/list.php','/product/list.php','/commande/list.php','/expedition/list.php','/compta/facture/list.php'),true);
 }
 public function llxHeader($parameters,&$object,&$action,$hookmanager) {
  if($this->applies()) $parameters['morecssonbody'].=' maxmobile-page';
  return 0;
 }
 public function addHtmlHeader($parameters,&$object,&$action,$hookmanager) {
  $this->resprints='';
  if($this->applies()) {
   $this->resprints='<link rel="stylesheet" href="'.dol_buildpath('/maxmobile/css/mobile.css',1).'">'
    .'<script nonce="'.getNonce().'" defer src="'.dol_buildpath('/maxmobile/js/mobile.js',1).'"></script>';
  }
  return 0;
 }
}
