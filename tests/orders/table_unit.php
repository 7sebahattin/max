<?php
// Actual bundled reader, bounded worker and synthetic ZIPs. No database mock.
if(PHP_SAPI!=='cli') exit(2);
define('DOL_DOCUMENT_ROOT',realpath($argv[1]??'/opt/dolibarr/htdocs'));
$module=realpath(__DIR__.'/../../modules/maxorders')?:DOL_DOCUMENT_ROOT.'/custom/maxorders';
require $module.'/class/runner.class.php';require __DIR__.'/table_fixtures.php';
$checks=[];
function tableCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
function tableInspect($rows=null,$meta=[],$columns=null,$options=[],$extra=''){return MaxOrdersExtraction::inspect('table.xlsx',ordersTableFixture($rows,$meta,$columns,$extra),$options);}
$a=tableInspect();
tableCheck($a['template']==='table-v03' && count($a['lines'])===3,'new table distinguished from A/B');
tableCheck(array_column(array_column($a['lines'],'values'),'quantity')===['1000','500','800'],'three separate exact quantity proposals');
tableCheck($a['lines'][0]['values']['description']==='Strawberry' && !isset($a['lines'][0]['values']['product_reference']),'description is not native product code');
tableCheck($a['lines'][1]['cells']['unit_price']['raw']==='8.50' && $a['lines'][1]['values']['unit_price']==='8.5','raw price retained with deterministic decimal normalization');
tableCheck($a['lines'][2]['cells']['quantity']['source']===['sheet'=>'Synthetic','row'=>12,'column'=>'D','cell'=>'D12'],'exact sheet row column and cell provenance');
tableCheck($a['issues']===['OrdersMixedCurrencies'] && $a['status']==='needs_review' && !isset($a['total']),'currencies retained independently without totals');
$a=MaxOrdersExtraction::inspect('old.xlsx',ordersFixtureXlsx(ordersFixtureFields()));
tableCheck(count($a['fields'])===13 && !isset($a['template']) && $a['status']==='suggested','A/B compatibility unchanged');
$a=tableInspect([ordersTableRows()[0],[],ordersTableRows()[2]]);
tableCheck($a['lines'][1]['state']==='blank' && $a['lines'][2]['row']===12,'blank rows retained without shifting provenance');
$row=ordersTableRows()[0];$a=tableInspect([$row,$row]);
tableCheck(array_column($a['lines'],'state')===['duplicate','duplicate'],'both exact duplicate rows marked');
$changed=$row;$changed[3]='999';$a=tableInspect([$row,$changed]);
tableCheck(array_column($a['lines'],'state')===['conflict','conflict'] && $a['lines'][0]['values']['quantity']==='1000','contradictory row proposals not merged');
$invalidA=$row;$invalidB=$row;$invalidA[3]='1,000';$invalidB[3]='1.000,00';$a=tableInspect([$invalidA,$invalidB]);
tableCheck(array_column($a['lines'],'state')===['conflict','conflict'],'different uncertain raw values are conflicts, not duplicates');
foreach(['1,000','1.000,00','1e3','-1','NaN','1.2345'] as $number){$r=$row;$r[3]=$number;$a=tableInspect([$r]);tableCheck(!isset($a['lines'][0]['values']['quantity']) && $a['lines'][0]['cells']['quantity']['raw']===$number,'ambiguous quantity rejected '.$number);}
$r=$row;$r[3]=['number','001.230'];$a=tableInspect([$r]);tableCheck($a['lines'][0]['values']['quantity']==='1.23' && $a['lines'][0]['cells']['quantity']['raw']==='001.230','numeric XML precision retained without floats');
$r=$row;$r[3]=['formula','WEBSERVICE("https://example.invalid/no-network")'];$a=tableInspect([$r]);
tableCheck(!$a['fields'] && empty($a['lines']) && $a['issues']===['OrdersFormulaBlocked'],'formula and cached value never accepted');
$columns=['product_reference'=>'Code']+MaxOrdersTable::DEFAULT_COLUMNS+['packaging'=>'Container'];
$r=array_merge(['DEMO'],$row,['CARTON']);$a=tableInspect([$r],[],$columns,['columns_json'=>json_encode($columns)]);
tableCheck($a['lines'][0]['values']['product_reference']==='DEMO' && $a['lines'][0]['cells']['packaging']['source']['cell']==='I10','configured optional explicit code and packaging');
$a=tableInspect([$r],[],$columns);tableCheck(in_array('OrdersTableHeaderMismatch',$a['issues'],true) && !$a['lines'],'unconfigured layout fails closed');
foreach(['{}','[]','{"description":"Same","quantity":"Same","unit":"U","unit_price":"P","currency":"C"}','{"description":"P","quantity":"Q","unit":"U","unit_price":"P2","currency":"C","unsafe":"X"}'] as $config){try{MaxOrdersTable::columns($config);throw new RuntimeException('accepted mapping');}catch(InvalidArgumentException $e){tableCheck($e->getCode()===400,'invalid template mapping rejected');}}
$a=tableInspect(array_fill(0,51,$row));tableCheck($a['issues']===['OrdersTableRowLimit'] && !$a['lines'],'50 line cap enforced');
foreach(['<cols><col min="1" max="9" hidden="true"/></cols>','<cols><col min="1" max="9" width="0"/></cols>'] as $extra){$a=tableInspect([$row],[],null,[],$extra);tableCheck(!$a['fields'] && empty($a['lines']) && $a['issues']===['OrdersLayoutReview'],'hidden column rejected');}
$before=glob(sys_get_temp_dir().'/maxorders-worker-*');
$a=MaxOrdersRunner::inspect('table.xlsx',ordersTableFixture());
tableCheck(($a['template']??'')==='table-v03' && $a['resource_limits']['wall_seconds']===5,'real bounded CLI worker returns proposals');
tableCheck(glob(sys_get_temp_dir().'/maxorders-worker-*')===$before,'worker workspace cleanup after successful parse');
try{MaxOrdersRunner::inspect('table.xlsx','not ZIP bytes');throw new RuntimeException('accepted bytes');}catch(InvalidArgumentException $e){tableCheck($e->getCode()===400,'worker structural rejection preserved');}
tableCheck(glob(sys_get_temp_dir().'/maxorders-worker-*')===$before,'worker workspace cleanup after rejection');
$result=['passed'=>true,'checks'=>$checks,'pdf_extraction'=>'BLOCKED','parser'=>'bundled PhpSpreadsheet / table-v03'];
if(isset($argv[2]))file_put_contents($argv[2],json_encode($result,JSON_PRETTY_PRINT));
echo 'PASS '.count($checks).' Orders 0.3 real-file checks'."\n";
