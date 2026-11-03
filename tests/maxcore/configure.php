<?php
// Independent fresh sandbox; uses only official installer and main composition.
if(PHP_SAPI!=='cli' || getenv('MARIADB_DATABASE')!=='max_demo') exit(2);
$base='/opt/dolibarr/htdocs'; $path=$base.'/conf/conf.php';
$current=file_get_contents($path);
if($current!=='' && $current!==file_get_contents($path.'.example')) throw new RuntimeException('Fresh config required');
$sql=new mysqli('db',getenv('MARIADB_USER'),getenv('MARIADB_PASSWORD'),'max_demo');
if((int)$sql->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetch_row()[0]!==0)
 throw new RuntimeException('Empty database required');
$settings=array('url_root'=>'http://127.0.0.1:8080','document_root'=>$base,'url_root_alt'=>'/custom','document_root_alt'=>$base.'/custom',
 'data_root'=>'/var/lib/dolibarr/documents','db_host'=>'db','db_port'=>'3306','db_name'=>'max_demo','db_prefix'=>'llx_',
 'db_user'=>getenv('MARIADB_USER'),'db_pass'=>getenv('MARIADB_PASSWORD'),'db_type'=>'mysqli','db_character_set'=>'utf8mb4',
 'db_collation'=>'utf8mb4_unicode_ci','authentication'=>'dolibarr','prod'=>'0','instance_unique_id'=>bin2hex(random_bytes(24)));
$out="<?php\n";
foreach($settings as $key=>$value) $out.='$dolibarr_main_'.$key.' = '.var_export($value,true).";\n";
file_put_contents($path,$out,LOCK_EX); chown($path,'www-data'); chmod($path,0640);
echo "Fresh independent module sandbox configured\n";
