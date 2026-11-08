<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
/** Original MAX prototype; retain upstream notices. Registry/licensing review before distribution. */
class modMaxcore extends DolibarrModules {
 public function __construct($db) {
  $this->db=$db; $this->numero=610120; $this->rights_class='maxcore';
  $this->family='interface'; $this->module_position='10'; $this->name='Maxcore';
  $this->description='ModuleMaxcoreDesc'; $this->editor_name='MAX'; $this->version='0.3.1';
  $this->const_name='MAIN_MODULE_MAXCORE'; $this->picto='fa-cubes';
  $this->module_parts=array('moduleforexternal'=>0,'hooks'=>array('all'),'triggers'=>1); $this->config_page_url=array('setup.php@maxcore');
  $this->langfiles=array('maxcore@maxcore'); $this->phpmin=array(8,1); $this->need_dolibarr_version=array(24,0);
  $this->dirs=array(); $this->depends=array(); $this->requiredby=array(); $this->conflictwith=array(); $this->const=array();
  $this->rights=array();
  foreach(array('read'=>'MAXRead','configure'=>'MAXConfigure','admin'=>'MAXAdmin') as $key=>$label) {
   $i=count($this->rights); $this->rights[$i]=array(61012001+$i,$label,0,0,$key);
  }
  $this->menu=array();
  foreach(array(array('top','','MAXMenu','/maxcore/index.php','read'),
   array('left','fk_mainmenu=maxcore','MAXSettings','/maxcore/admin/setup.php','configure'),
   array('left','fk_mainmenu=maxcore','MAXAdministration','/maxcore/admin/status.php','admin')) as $i=>$e) {
   $perms='$user->hasRight("maxcore", "'.$e[4].'") || $user->hasRight("maxcore", "admin")';
   if($e[4]==='read') $perms.=' || $user->hasRight("maxcore", "configure")';
   $this->menu[]=array('fk_menu'=>$e[1],'type'=>$e[0],'titre'=>$e[2],'mainmenu'=>'maxcore','leftmenu'=>'maxcore'.$e[4],
    'url'=>$e[3],'langs'=>'maxcore@maxcore','position'=>1000+$i,'enabled'=>'isModEnabled("maxcore")','perms'=>$perms,'target'=>'','user'=>0);
  }
 }
 public function init($options='') {
  $r=$this->db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id BETWEEN 61012001 AND 61012003 AND module <> 'maxcore'");
  if(!$r || $this->db->num_rows($r)) { $this->error='MAX Core permission ID conflict'; return -1; }
  $this->remove($options); return $this->_init(array(),$options);
 }
 public function remove($options='') { return $this->_remove(array(),$options); }
}
