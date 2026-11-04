<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
/** Opt-in presentation adapter. Native endpoints/rights remain authoritative. */
class modMaxmobile extends DolibarrModules {
 public function __construct($db) {
  $this->db=$db; $this->numero=610130; $this->rights_class='maxmobile';
  $this->family='interface'; $this->name='Maxmobile'; $this->version='0.1.0';
  $this->description='ModuleMaxmobileDesc'; $this->editor_name='MAX';
  $this->const_name='MAIN_MODULE_MAXMOBILE'; $this->picto='fa-mobile-alt';
  $this->phpmin=array(8,1); $this->need_dolibarr_version=array(24,0);
  $this->module_parts=array('hooks'=>array('all'),'moduleforexternal'=>0);
  $this->langfiles=array('maxmobile@maxmobile');
  $this->dirs=array(); $this->depends=array(); $this->requiredby=array();
  $this->conflictwith=array(); $this->const=array(); $this->rights=array(); $this->menu=array();
 }
 public function init($options='') { return $this->_init(array(),$options); }
 public function remove($options='') { return $this->_remove(array(),$options); }
}
