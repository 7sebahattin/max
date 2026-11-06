<?php
/** Pure normalization + native-hook adapters + MAX service guards. No installed DB claim. */
if(PHP_SAPI!=='cli')exit(2);
require __DIR__.'/../../modules/maxcore/class/input.class.php';
require __DIR__.'/../../modules/maxcore/lib/input_hooks.lib.php';
require __DIR__.'/../../modules/maxcore/lib/maxcore.lib.php';
require __DIR__.'/../../modules/maxorders/class/packet.class.php';
require __DIR__.'/../../modules/maxcustoms/class/declaration.class.php';
require __DIR__.'/../../modules/maxmail/class/intake.class.php';
function isModEnabled($name){return true;}
function getDolGlobalString($name,$default=''){return $default;}
define('MAIN_DB_PREFIX','unit_');$dolibarr_main_db_name='synthetic_unit';
$conf=(object)['entity'=>1];$checks=[];
function checkInput($ok,$label){global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks[]=['check'=>$label,'status'=>'PASS'];echo 'PASS '.$label."\n";}
function rejectsInput($callback,$label){try{$callback();}catch(InvalidArgumentException $e){checkInput($e->getCode()===400,$label);return;}throw new RuntimeException('FAIL missing rejection '.$label);}
checkInput(MaxInput::text('istanbul ıslak İ I i ı çğöşü')==='İSTANBUL ISLAK İ I İ I ÇĞÖŞÜ','Turkish dotted/dotless I and alphabet');
checkInput(MaxInput::text("c\u{0327}ic\u{0327}ek")==='ÇİÇEK','decomposed Unicode normalized to NFC');
checkInput(MaxInput::text(str_repeat('a',65535),65535)===str_repeat('A',65535),'long non-address text does not cause unbounded protected-span backtracking');
checkInput(MaxInput::text('straße')==='STRASSE','Unicode expansion');
checkInput(MaxInput::text("  çiçek\nşişli  ")==="  ÇİÇEK\nŞİŞLİ  ",'whitespace and line breaks preserved');
$embedded='ad User.Name+tag@Example.com https://example.invalid/Case?Key=aB /srv/Case C:\\Private\\Case';
checkInput(MaxInput::text($embedded)==='AD User.Name+tag@Example.com https://example.invalid/Case?Key=aB /srv/Case C:\\Private\\Case','embedded email URI and paths remain exact');
checkInput(MaxInput::text(MaxInput::text('ıslak çiçek'))===MaxInput::text('ıslak çiçek'),'idempotence');
rejectsInput(fn()=>MaxInput::text('ß',1),'length checked after uppercase expansion');
rejectsInput(fn()=>MaxInput::text("\xff"),'malformed UTF8');
rejectsInput(fn()=>MaxInput::text(['name'=>'x']),'array cannot bypass text classification');
rejectsInput(fn()=>MaxInput::value('name',['unknown',10]),'unknown role rejected');
rejectsInput(fn()=>MaxInput::html('<!DOCTYPE x SYSTEM "https://example.invalid/no-call">x'),'HTML external definitions rejected');
rejectsInput(fn()=>MaxInput::html('</div><b>outside</b>'),'malformed rich text cannot silently drop content');
$rich='<p title="CaseAttr">çiçek <a href="https://example.invalid/Case?Sig=aB">istanbul</a><code>CaseSensitive()</code></p>';
$out=MaxInput::html($rich);
checkInput(str_contains($out,'ÇİÇEK')&&str_contains($out,'İSTANBUL')&&str_contains($out,'title="CaseAttr"')&&str_contains($out,'href="https://example.invalid/Case?Sig=aB"')&&str_contains($out,'<code>CaseSensitive()</code>'),'rich text changes only business text nodes');
$technical=['password'=>'aBc','token'=>'CaseToken','email'=>'Name@Example.com','url'=>'https://x.invalid/Case','ref'=>'Sku-aB','mode'=>'road','quantity'=>'1.250','payload'=>'{"Case":"aB"}'];
$data=['name'=>'istanbul']+$technical;$original=$data;
$out=MaxInput::web('societe/card.php',$data);
checkInput($out['name']==='İSTANBUL'&&array_diff_assoc($technical,$out)===[]&&$data===$original,'native POST changes only declared business fields without mutating original');
checkInput(MaxInput::web('index.php',$data)===$data,'login and unknown paths untouched');
checkInput(MaxInput::web('product/card.php',['label'=>'çiçek','ref'=>'Case-SKU'])===['label'=>'ÇİÇEK','ref'=>'Case-SKU'],'product label versus native identifier');
checkInput(MaxInput::web('custom/maxmail/settings.php',['label'=>'özel posta','username'=>'CaseUser','password'=>'aBc','folder'=>'CaseFolder'])===['label'=>'ÖZEL POSTA','username'=>'CaseUser','password'=>'aBc','folder'=>'CaseFolder'],'mail account label versus credentials and folder');
$body=json_decode('{"name":"istanbul","email":"Name@Example.com","empty":{},"lines":[{"desc":"çiçek","ref":"Case"}]}');
$converted=MaxInput::api('thirdparties',$body);
checkInput($converted->name==='İSTANBUL'&&$converted->email===$body->email&&is_object($converted->empty)&&$body->name==='istanbul'&&$converted->lines[0]->desc==='çiçek'&&$converted->lines[0]->ref==='Case','API normalizes declared fields and preserves unclassified nested source/types');
checkInput(MaxInput::api('orders',(object)['lines'=>[(object)['desc'=>'çiçek','ref'=>'Case']]])->lines[0]->desc==='ÇİÇEK','native commercial API line fields normalize through declared resource scope');
class StreamFixture {public $stream;public function __construct($bytes){$this->stream=fopen('php://temp','w+');fwrite($this->stream,$bytes);}public function getRequestStream(){rewind($this->stream);return $this->stream;}}
$rest=new StreamFixture('{"label":"çiçek","ref":"Case","apikey":"aBc"}');
checkInput(MaxInput::restler($rest,'products','POST','application/json; charset=utf-8'),'public pre-dispatch stream adapter');
$out=json_decode(stream_get_contents($rest->getRequestStream()),true);
checkInput($out===['label'=>'ÇİÇEK','ref'=>'Case','apikey'=>'aBc'],'API bypass receives server-side uppercase and exact technical values');
$wire='{ "label" : "çiçek", "amount": -0.00, "large": 9223372036854775808, "precise":0.123456789012345678901, "signed":"\\u0061Bc", "empty":{} }';
$restExact=new StreamFixture($wire);MaxInput::restler($restExact,'products','POST','application/json');$actual=stream_get_contents($restExact->getRequestStream());
checkInput($actual===str_replace('"çiçek"','"ÇİÇEK"',$wire),'API preserves every nonbusiness wire byte including exact financial numeric literals');
$dupe=new StreamFixture('{"label":"one","label":"two"}');rejectsInput(fn()=>MaxInput::restler($dupe,'products','POST','application/json'),'duplicate JSON keys rejected without ambiguous normalization');
checkInput(!MaxInput::restler($rest,'products','GET','text/plain'),'read requests never rewritten');
$row=['istanbul','Case-ID','Name@Example.com'];$mapped=MaxInput::importRow('societe_1',$row,[0=>'s.nom',1=>'s.code_client',2=>'s.email']);
checkInput($mapped===['İSTANBUL','Case-ID','Name@Example.com']&&$row[0]==='istanbul','import row copy changes only mapped business columns');
$nativeRow=[['val'=>'Case-Sku','type'=>1],['val'=>'çiçek straße','type'=>1],['val'=>'','type'=>-1],['val'=>null,'type'=>0],['val'=>'12.34000','type'=>1]];
$nativeMapped=MaxInput::importRow('produit_1',$nativeRow,[1=>'p.ref',2=>'p.label',3=>'p.description',4=>'p.note_public',5=>'p.price']);
$expectedNative=$nativeRow;$expectedNative[1]['val']='ÇİÇEK STRASSE';
checkInput($nativeMapped===$expectedNative&&$nativeRow[1]['val']==='çiçek straße','native one-based mapping normalizes zero-based typed cells without changing raw metadata or numbers');
$xlsxRow=array_combine(range(1,count($nativeRow)),$nativeRow);$xlsxExpected=$xlsxRow;$xlsxExpected[2]['val']='ÇİÇEK STRASSE';
checkInput(MaxInput::importRow('produit_1',$xlsxRow,[1=>'p.ref',2=>'p.label',3=>'p.description',4=>'p.note_public',5=>'p.price'])===$xlsxExpected,'native XLSX one-based cell keys preserve correct field alignment');
rejectsInput(fn()=>MaxInput::importRow('produit_1',[2=>['val'=>'çiçek','type'=>1]],[1=>'p.label']),'native sparse typed row cannot shift business classification');
rejectsInput(fn()=>MaxInput::importRow('produit_1',[['val'=>str_repeat('ß',128),'type'=>1]],[1=>'p.label']),'native typed import expansion overflow');
rejectsInput(fn()=>MaxInput::importRow('produit_1',[['val'=>'lowercase','type'=>-1]],[1=>'p.label']),'native typed import inconsistent null marker rejected');
define('DOL_DOCUMENT_ROOT',__DIR__.'/native-uninstalled');
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['SCRIPT_FILENAME']=DOL_DOCUMENT_ROOT.'/product/card.php';unset($_POST);MaxInputHooks::web();
checkInput(!isset($_POST),'native CSRF removal of POST stays removed without adapter error');
class TransactionFixture {public $begins=0;public $commits=0;public $rollbacks=0;public function begin(){$this->begins++;return true;}public function commit(){$this->commits++;return true;}public function rollback(){$this->rollbacks++;return true;}}
class DriverFixture {public $db;public function __construct(){$this->db=new TransactionFixture();}public $errors=[];public $warnings=[];public $calls=0;public $row;public function import_insert($row,...$args){$this->calls++;$this->row=$row;return 1;}}
$driver=new DriverFixture();$count=0;
$p=['datatoimport'=>'societe_1','obj'=>$driver,'arrayrecord'=>$row,'array_match_file_to_database'=>[0=>'s.nom',1=>'s.code_client',2=>'s.email'],'objimport'=>(object)[],'fieldssource'=>[1,2,3],'importid'=>'Case','updatekeys'=>[],'nbok'=>&$count];
checkInput(MaxInputHooks::import($p)===1&&$count===1&&$driver->calls===1&&$driver->row===$mapped,'supported import hook delegates exactly once and preserves native success counter');
$driver->warnings=['synthetic native warning'];try{MaxInputHooks::import($p);throw new RuntimeException('FAIL import warning accepted');}catch(RuntimeException $e){checkInput($driver->db->rollbacks===1&&$driver->db->commits===1,'import warnings roll back the wrapper row transaction');}
checkInput(MaxInputHooks::import(['datatoimport'=>'unknown'])===0,'unclassified import is not claimed supported');
$facts=MaxOrdersPacket::facts('{"variant":"çiçek","packaging":"kutu","sku":"CaseSKU","quantity":"1.250","currency":"eur"}');
checkInput($facts['variant']==='ÇİÇEK'&&$facts['packaging']==='KUTU'&&$facts['sku']==='CaseSKU'&&$facts['currency']==='EUR'&&$facts['quantity']==='1.25','Orders direct service and bridges normalize business values, not SKU/numbers');
rejectsInput(fn()=>MaxOrdersPacket::facts(json_encode(['variant'=>str_repeat('ß',41)])),'Orders validates canonical length');
$brand=maxCoreValidate(['MAXCORE_DISPLAY_NAME'=>'istanbul','MAXCORE_LOGO'=>'max.svg','MAXCORE_FAVICON'=>'none']);
checkInput($brand['MAXCORE_DISPLAY_NAME']==='İSTANBUL'&&$brand['MAXCORE_LOGO']==='max.svg','brand label normalization leaves asset selector intact');
class DeniedActor {public $id=9;public $socid=0;public function hasRight(...$args){return false;}}
class NoDatabaseWrites {public function query($sql){throw new RuntimeException('FAIL unauthorized DB access');}}
$denied=new DeniedActor();$db=new NoDatabaseWrites();
foreach(['customs'=>fn()=>(new MaxCustomsDeclaration($db))->create($denied,'Case',[]),'mail'=>fn()=>(new MaxMailIntake($db))->createAccount($denied,[]),'orders'=>fn()=>(new MaxOrdersPacket($db))->ingest($denied,1,'Case','PO','Case',1,'{}'),'core'=>fn()=>maxCoreSave([], $denied)] as $name=>$call){
 try{$call();throw new RuntimeException('FAIL guard absent');}catch(RuntimeException $e){checkInput(in_array($e->getMessage(),['CustomsDenied','MailDenied','OrdersDenied','MAXDenied'],true),'existing native permission guard precedes normalization/write '.$name);}
}
class AllowedActor {public $id=10;public $socid=0;public function hasRight(...$args){return true;}}
$allowed=new AllowedActor();
rejectsInput(fn()=>(new MaxCustomsDeclaration($db))->create($allowed,'Case',['goods_summary'=>str_repeat('ß',1001)]),'Customs direct service enforces uppercase length before persistence');
rejectsInput(fn()=>(new MaxMailIntake($db))->createAccount($allowed,['label'=>str_repeat('ß',41)]),'Mail direct service enforces label normalization before persistence');
$report=['passed'=>true,'scope'=>'pure_normalizer_and_adapter_unit','checks'=>$checks,'installed_native'=>'NOT RUN'];
if(isset($argv[1]))file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'PASS '.count($checks)." normalization and adapter unit checks\n";
