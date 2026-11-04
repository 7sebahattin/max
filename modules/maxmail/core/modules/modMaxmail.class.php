<?php
// MAX Mail safe intake. GPL-3.0-or-later. No upstream modifications or sender.
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modMaxmail extends DolibarrModules
{
    public function __construct($db)
    {
        $this->db=$db; $this->numero=610150; $this->rights_class='maxmail';
        $this->name='Maxmail'; $this->family='interface'; $this->description='MailDescription';
        $this->editor_name='MAX'; $this->version='0.1.0'; $this->const_name='MAIN_MODULE_MAXMAIL'; $this->picto='email';
        $this->module_parts=['moduleforexternal'=>0]; $this->langfiles=['maxmail@maxmail'];
        $this->phpmin=[8,1]; $this->need_dolibarr_version=[24,0]; $this->dirs=['/maxmail'];
        $this->depends=[]; $this->requiredby=[]; $this->conflictwith=[]; $this->config_page_url=[]; $this->const=[];
        $this->rights=[];
        foreach(['view','poll','review','download','admin'] as $n=>$right) $this->rights[]=[61015001+$n,'MailPermission_'.$right,0,0,$right];
        $this->menu=[['fk_menu'=>'','type'=>'top','titre'=>'MailTitle','mainmenu'=>'maxmail','leftmenu'=>'','url'=>'/maxmail/index.php',
            'langs'=>'maxmail@maxmail','position'=>1110,'enabled'=>'isModEnabled("maxmail")',
            'perms'=>'$user->hasRight("maxmail","view") || $user->hasRight("maxmail","admin")','target'=>'','user'=>0]];
    }
    public function init($options='')
    {
        $r=$this->db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id BETWEEN 61015001 AND 61015005 AND module <> 'maxmail'");
        if(!$r || $this->db->num_rows($r)) { $this->error='MAX Mail permission allocation conflict'; return -1; }
        $schema=require __DIR__.'/../../lib/schema.php'; $this->remove($options); return $this->_init($schema,$options);
    }
    public function remove($options='') { return $this->_remove([],$options); }
}
