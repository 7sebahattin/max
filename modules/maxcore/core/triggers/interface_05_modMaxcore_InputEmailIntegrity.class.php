<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once dirname(__DIR__,2).'/lib/email_integrity.lib.php';
class InterfaceInputEmailIntegrity extends DolibarrTriggers {
 public function __construct($db){$this->db=$db;$this->name='InputEmailIntegrity';$this->family='interface';$this->description='Preserve opaque email case in authorized native CRM requests';$this->version=self::VERSIONS['prod'];$this->picto='generic';}
 public function runTrigger($action,$object,$user,$langs,$conf){
  if(!isModEnabled('maxcore'))return 0;
  try{$result=MaxInputEmail::apply($action,$object,$user);}catch(Throwable $e){$result=-1;}
  if($result<0){$this->error='MAXInputEmailIntegrity';$this->errors=[$this->error];}
  return $result;
 }
}
