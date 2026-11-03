<?php
require_once dirname(__DIR__).'/lib/maxcore.lib.php';
class ActionsMaxcore {
 public $resprints='';
 public function __construct($db) {}
 // Native menus resolve their labels before llxHeader; apply our own key earlier.
 public function updateSession($parameters,&$object,&$action,$hookmanager) {
  maxCoreApplyBrand(); return 0;
 }
 public function afterLogin($parameters,&$object,&$action,$hookmanager) {
  maxCoreApplyBrand(); return 0;
 }
 public function llxHeader($parameters,&$object,&$action,$hookmanager) {
  maxCoreApplyBrand(); return 0;
 }
 public function getLoginPageOptions($parameters,&$object,&$action,$hookmanager) {
  maxCoreApplyBrand(); $this->resprints=''; return 0;
 }
 public function addHtmlHeader($parameters,&$object,&$action,$hookmanager) {
  $this->resprints=isModEnabled('maxcore')?'<link rel="stylesheet" href="'.dol_buildpath('/maxcore/css/branding.css',1).'">':'';
  return 0;
 }
 public function printTopRightMenu($parameters,&$object,&$action,$hookmanager) {
  $this->resprints=isModEnabled('maxcore')?maxCoreBrandMarkup():'';
  return 0; // Append only; preserve native menus, user controls and notices.
 }
 public function getLoginPageExtraContent($parameters,&$object,&$action,$hookmanager) {
  $this->resprints=isModEnabled('maxcore')?maxCoreBrandMarkup(true):'';
  return 0; // Append product brand; never replace native login/legal logo/notices.
 }
}
