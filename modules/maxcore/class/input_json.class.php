<?php
/** Replace only classified JSON string values. Preserve all other wire bytes,
 * including numeric literals, opaque strings, keys and empty-object types.
 * JSON syntax/types are validated by native json_decode + MaxInput before use.
 * GPL-3.0-or-later.
 */
class MaxInputJson {
 private $tokens;private $position=0;private $changes=[];private $normalized;private $rules;private $lines=false;
 public static function normalize($raw,$resource) {
  try{$decoded=json_decode($raw,false,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('MAXInputInvalid',400);}
  if(!is_object($decoded))throw new InvalidArgumentException('MAXInputInvalid',400);
  $parser=new self();$parser->lines=in_array($resource,['orders','invoices','shipments'],true);$parser->normalized=MaxInput::api($resource,$decoded);$parser->rules=MaxInput::registry()['api'][$resource]??[];
  preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\[\],:]|[^\s{}\[\],:]+/s',$raw,$tokens,PREG_OFFSET_CAPTURE);
  $parser->tokens=$tokens[0];if(count($parser->tokens)>65536)throw new InvalidArgumentException('MAXInputTooLong',413);
  $parser->value([]);
  if($parser->position!==count($parser->tokens))throw new InvalidArgumentException('MAXInputInvalid',400);
  foreach(array_reverse($parser->changes) as [$offset,$length,$replacement])$raw=substr_replace($raw,$replacement,$offset,$length);
  return $raw;
 }
 private function take($expected=null) {
  if(!isset($this->tokens[$this->position]))throw new InvalidArgumentException('MAXInputInvalid',400);
  $token=$this->tokens[$this->position++];if($expected!==null&&$token[0]!==$expected)throw new InvalidArgumentException('MAXInputInvalid',400);return $token;
 }
 private function peek(){return $this->tokens[$this->position][0]??null;}
 private function classified($path) {
  return (count($path)===1&&isset($this->rules[$path[0]]))||($this->lines&&count($path)===3&&$path[0]==='lines'&&is_int($path[1])&&in_array($path[2],['desc','description','label'],true));
 }
 private function value($path) {
  [$token,$offset]=$this->take();
  if($token==='{'){
   $seen=[];
   if($this->peek()!=='}')do{
    [$key]=$this->take();$key=json_decode($key,true,2,JSON_THROW_ON_ERROR);
    if(!is_string($key)||isset($seen[$key]))throw new InvalidArgumentException('MAXInputInvalid',400);$seen[$key]=true;
    $this->take(':');$this->value(array_merge($path,[$key]));
    if($this->peek()!==',')break;$this->take(',');
   }while(true);
   $this->take('}');return;
  }
  if($token==='['){$index=0;if($this->peek()!==']')do{$this->value(array_merge($path,[$index++]));if($this->peek()!==',')break;$this->take(',');}while(true);$this->take(']');return;}
  if($token[0]==='"'&&$this->classified($path)){
   $value=$this->normalized;foreach($path as $key)$value=is_array($value)?$value[$key]:$value->$key;
   $this->changes[]=[$offset,strlen($token),json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
  }
 }
}
