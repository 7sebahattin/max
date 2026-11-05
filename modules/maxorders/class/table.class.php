<?php
/** Bounded, customer-neutral line proposals. GPL-3.0-or-later. */
class MaxOrdersTable {
 const MARKER='MAX_ORDERS_TABLE_V03';
 const MAX_LINES=50;
 const META=['document_kind','order_reference','document_reference','document_revision','customer_reference','customer_name','document_date'];
 const DEFAULT_COLUMNS=['description'=>'Product','variety'=>'Variety','size'=>'Size','quantity'=>'Quantity','unit'=>'Unit','unit_price'=>'Price','currency'=>'Currency'];
 public static function columns($json='') {
  try{$columns=$json===''?self::DEFAULT_COLUMNS:json_decode($json,true,8,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('OrdersTemplateInvalid',400);}
  if(!is_array($columns)||count($columns)<5||count($columns)>9||array_diff(['description','quantity','unit','unit_price','currency'],array_keys($columns)))throw new InvalidArgumentException('OrdersTemplateInvalid',400);
  $labels=[];foreach($columns as $field=>$label){
   if(!in_array($field,['description','variety','size','quantity','unit','unit_price','currency','product_reference','packaging'],true)||!is_string($label)||mb_strlen($label)>40||trim($label)===''||preg_match('/[\x00-\x1f\x7f<>]/u',$label)!==0)throw new InvalidArgumentException('OrdersTemplateInvalid',400);
   $key=self::normalized($label);if(isset($labels[$key]))throw new InvalidArgumentException('OrdersTemplateInvalid',400);$labels[$key]=true;
  }return $columns;
 }
 public static function normalized($text){return mb_strtoupper(preg_replace('/\s+/u',' ',trim($text)),'UTF-8');}
 public static function cell($sheet,$ref,$numbers,$field){
  $cell=$sheet->getCell($ref);$raw=$cell->getDataType()==='n'?($numbers[$ref]??''):$cell->getValue();
  $raw=is_string($raw)?$raw:'';$truncated=mb_strlen($raw)>255;$raw=mb_substr($raw,0,255);$value=trim($raw);
  $valid=!$truncated&&MaxOrdersExtraction::valid($field,$value);
  if($field==='document_revision')$valid=(bool)preg_match('/^[1-9][0-9]{0,5}$/D',$value);
  if($valid&&in_array($field,['quantity','unit_price'],true)){$p=explode('.',$value);$whole=ltrim($p[0],'0');$fraction=rtrim($p[1]??'','0');$value=($whole===''?'0':$whole).($fraction!==''?'.'.$fraction:'');}
  preg_match('/^([A-I])([0-9]+)$/D',$ref,$m);
  return ['field'=>$field,'raw'=>$raw,'value'=>$value,'state'=>$value===''?'missing':($valid?'suggested':'uncertain'),'truncated'=>$truncated,'source'=>['sheet'=>$sheet->getTitle(),'row'=>(int)$m[2],'column'=>$m[1],'cell'=>$ref]];
 }
 public static function parse($sheet,$raw,$options){
  $columns=self::columns($options['columns_json']??'');
  $result=['format'=>'xlsx','mime'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','template'=>'table-v03','template_sha256'=>hash('sha256',json_encode($columns,JSON_UNESCAPED_UNICODE)),'status'=>'needs_review','fields'=>[],'lines'=>[],'missing'=>[],'issues'=>[]];
  $last=$sheet->getHighestDataRow();if($last<10||$last>9+self::MAX_LINES){$result['issues']=['OrdersTableRowLimit'];return $result;}
  $seen=[];for($r=2;$r<=8;$r++){
   $field=$sheet->getCell('A'.$r)->getValue();if($field===null||$field==='')continue;
   if(!is_string($field)||!in_array($field,self::META,true)){$result['issues'][]='OrdersUnknownField';continue;}
   $item=self::cell($sheet,'B'.$r,$raw['numbers'],$field);$item['source']['label_cell']='A'.$r;
   if(isset($seen[$field])){foreach($result['fields'] as &$previous)if($previous['field']===$field)$previous['state']='ambiguous';unset($previous);$item['state']='ambiguous';$result['issues'][]='OrdersAmbiguousField';}
   $seen[$field]=true;$result['fields'][]=$item;if($item['state']!=='suggested')$result['issues'][]='OrdersUncertainField';
  }
  foreach(['document_kind','order_reference','document_reference','document_revision'] as $field)if(!isset($seen[$field]))$result['missing'][]=$field;
  if(!isset($seen['customer_reference'])&&!isset($seen['customer_name']))$result['missing'][]='customer_reference';
  if($result['missing'])$result['issues'][]='OrdersMissingField';
  $index=0;foreach($columns as $field=>$label)if($sheet->getCell(chr(65+$index++).'9')->getValue()!==$label){$result['issues'][]='OrdersTableHeaderMismatch';return $result;}
  foreach($sheet->getCoordinates() as $ref){
   preg_match('/^([A-I])([0-9]+)$/D',$ref,$m);$col=ord($m[1])-65;$row=(int)$m[2];$value=$sheet->getCell($ref)->getValue();
   if($value!==null&&$value!==''&&(($row<9&&$col>1)||($row===1&&$col>0)||($row>=9&&$col>=count($columns)))){$result['issues'][]='OrdersLayoutReview';return $result;}
  }
  $groups=[];$currencies=[];
  for($row=10;$row<=$last;$row++){
   $cells=[];$values=[];$issues=[];$col=0;$hasData=false;
   foreach($columns as $field=>$label){$cell=self::cell($sheet,chr(65+$col++).$row,$raw['numbers'],$field);$cells[$field]=$cell;if($cell['raw']!=='')$hasData=true;
    if($cell['state']==='suggested')$values[$field]=$cell['value'];elseif(in_array($field,['description','quantity','unit','unit_price','currency'],true))$issues[]='OrdersLineMissingOrInvalid';
    if($cell['raw']!==''&&$cell['state']!=='suggested')$issues[]='OrdersUncertainField';
   }
   $state=$hasData?($issues?'needs_review':'suggested'):'blank';if(!$hasData)$issues=['OrdersBlankSourceRow'];
   $identity=[];foreach(['product_reference','description','variety','size','unit'] as $field)$identity[$field]=self::normalized($values[$field]??'');
   $key=hash('sha256',json_encode($identity,JSON_UNESCAPED_UNICODE));
   $result['lines'][]=['row'=>$row,'sheet'=>$sheet->getTitle(),'key'=>$key,'state'=>$state,'review_status'=>'pending','cells'=>$cells,'values'=>$values,'issues'=>array_values(array_unique($issues))];
   if($hasData){$groups[$key][]=count($result['lines'])-1;if(isset($values['currency']))$currencies[$values['currency']]=true;}
  }
  foreach($groups as $indexes){if(count($indexes)<2)continue;$first=$result['lines'][$indexes[0]]['values'];$identical=true;foreach($indexes as $i)if($result['lines'][$i]['values']!==$first)$identical=false;
   foreach($indexes as $i){$result['lines'][$i]['state']=$identical?'duplicate':'conflict';$result['lines'][$i]['issues'][]=$identical?'OrdersDuplicateSourceRow':'OrdersConflictingSourceRows';}
  }
  foreach($result['lines'] as $line)foreach($line['issues'] as $issue)$result['issues'][]=$issue;
  if(count($currencies)>1)$result['issues'][]='OrdersMixedCurrencies';
  $result['issues']=array_values(array_unique($result['issues']));$result['status']=$result['issues']?'needs_review':'suggested';return $result;
 }
}
