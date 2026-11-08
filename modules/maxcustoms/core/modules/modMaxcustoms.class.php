<?php
// MAX Customs optional draft module. GPL-3.0-or-later; preserve Dolibarr notices.
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modMaxcustoms extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db=$db;
        $this->numero=610160;
        $this->rights_class='maxcustoms';
        $this->family='crm';
        $this->name='Maxcustoms';
        $this->description='CustomsDescription';
        $this->version='0.1.0';
        $this->editor_name='MAX';
        $this->const_name='MAIN_MODULE_MAXCUSTOMS';
        $this->picto='fa-file-alt';
        $this->module_parts=['moduleforexternal'=>0];
        $this->langfiles=['maxcustoms@maxcustoms'];
        $this->phpmin=[8,1];
        $this->need_dolibarr_version=[24,0];
        $this->dirs=['/maxcustoms'];
        $this->depends=['modMaxcore','modSociete','modCommande','modExpedition'];
        $this->requiredby=[];
        $this->conflictwith=[];
        $this->config_page_url=[];
        $this->const=[];
        $this->rights=[];
        foreach(['view','create','edit','status','match','contact','analysis','source','admin'] as $index=>$capability) {
            $this->rights[]=[61016001+$index,'CustomsPermission_'.$capability,0,0,$capability];
        }
        $this->menu=[[
            'fk_menu'=>'','type'=>'top','titre'=>'CustomsTitle','mainmenu'=>'maxcustoms','leftmenu'=>'',
            'url'=>'/maxcustoms/index.php','langs'=>'maxcustoms@maxcustoms','position'=>1120,
            'enabled'=>'isModEnabled("maxcustoms")',
            'perms'=>'$user->hasRight("maxcustoms","view") || $user->hasRight("maxcustoms","admin")',
            'target'=>'','user'=>0
        ]];
    }
    public function init($options='')
    {
        $check=$this->db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id BETWEEN 61016001 AND 61016009 AND module <> 'maxcustoms'");
        if(!$check || $this->db->num_rows($check)) { $this->error='MAX Customs permission allocation conflict'; return -1; }
        $schema=require __DIR__.'/../../lib/schema.php';
        $this->remove($options);
        return $this->_init($schema,$options);
    }
    public function remove($options='') { return $this->_remove([],$options); }
}
