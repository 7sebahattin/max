<?php
// MAX Orders — GPL-3.0-or-later. External module; no Dolibarr core changes.
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modMaxorders extends DolibarrModules {
 public function __construct($db) {
  $this->db=$db; $this->numero=610140; $this->rights_class='maxorders'; $this->family='crm';
  $this->module_position='20'; $this->name='Maxorders'; $this->description='OrdersDescription';
  $this->editor_name='MAX'; $this->version='0.1.0'; $this->const_name='MAIN_MODULE_MAXORDERS'; $this->picto='order';
  $this->module_parts=['moduleforexternal'=>0]; $this->config_page_url=[]; $this->langfiles=['maxorders@maxorders'];
  $this->phpmin=[8,1]; $this->need_dolibarr_version=[24,0]; $this->dirs=[];
  $this->depends=['modSociete','modCommande']; $this->requiredby=[]; $this->conflictwith=[]; $this->const=[];
  $this->rights=[];
  foreach(['read'=>'OrdersRead','intake'=>'OrdersIntake','review'=>'OrdersReview'] as $key=>$label) {
   $i=count($this->rights); $this->rights[]= [61014001+$i,$label,0,0,$key];
  }
  $this->menu=[['fk_menu'=>'','type'=>'top','titre'=>'OrdersTitle','mainmenu'=>'maxorders','leftmenu'=>'',
   'url'=>'/maxorders/index.php','langs'=>'maxorders@maxorders','position'=>1100,'enabled'=>'isModEnabled("maxorders")',
   'perms'=>'$user->hasRight("maxorders","read") || $user->hasRight("maxorders","intake") || $user->hasRight("maxorders","review")','target'=>'','user'=>0]];
 }
 public function init($options='') {
  $r=$this->db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id BETWEEN 61014001 AND 61014003 AND module <> 'maxorders'");
  if(!$r || $this->db->num_rows($r)) { $this->error='MAX Orders permission ID conflict'; return -1; }
  $schema=require __DIR__.'/../../lib/schema.php';
  $this->remove($options); return $this->_init($schema,$options);
 }
 public function remove($options='') { return $this->_remove([],$options); }
}
