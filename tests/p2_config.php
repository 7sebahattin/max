<?php
/**
 * P2: create an isolated Dolibarr configuration for the disposable CI/local pilot.
 * This file is mounted into /checks and is not part of the upstream Dolibarr codebase.
 * No production credentials; only Compose's generated disposable DB values.
 */
declare(strict_types=1);
$config = '/opt/dolibarr/htdocs/conf/conf.php';
if (!is_file($config) || filesize($config) > 0) {
    fwrite(STDERR, "Refusing to replace an existing Dolibarr configuration. Reset disposable local data first.\n");
    exit(2);
}
$dbName = getenv('MARIADB_DATABASE');
$dbUser = getenv('MARIADB_USER');
$dbPass = getenv('MARIADB_PASSWORD');
if (!$dbName || !$dbUser || !$dbPass || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $dbName)) {
    fwrite(STDERR, "Missing or invalid disposable DB configuration.\n");
    exit(2);
}
$values = [
    'dolibarr_main_url_root' => 'http://127.0.0.1:8080',
    'dolibarr_main_document_root' => '/opt/dolibarr/htdocs',
    'dolibarr_main_url_root_alt' => '/custom',
    'dolibarr_main_document_root_alt' => '/opt/dolibarr/htdocs/custom',
    'dolibarr_main_data_root' => '/var/lib/dolibarr/documents',
    'dolibarr_main_db_host' => 'db',
    'dolibarr_main_db_port' => '3306',
    'dolibarr_main_db_name' => $dbName,
    'dolibarr_main_db_prefix' => 'llx_',
    'dolibarr_main_db_user' => $dbUser,
    'dolibarr_main_db_pass' => $dbPass,
    'dolibarr_main_db_type' => 'mysqli',
    'dolibarr_main_db_character_set' => 'utf8mb4',
    'dolibarr_main_db_collation' => 'utf8mb4_unicode_ci',
    'dolibarr_main_authentication' => 'dolibarr',
    'dolibarr_main_prod' => '0',
    'dolibarr_main_instance_unique_id' => bin2hex(random_bytes(24)),
    'dolibarr_main_distrib' => 'standard',
];
$body = "<?php\n// Generated only for the isolated MAX P2 local sandbox. DO NOT COMMIT.\n";
foreach ($values as $name => $value) {
    $body .= '$' . $name . ' = ' . var_export($value, true) . ";\n";
}
if (file_put_contents($config, $body, LOCK_EX) === false) {
    fwrite(STDERR, "Cannot write disposable configuration.\n");
    exit(2);
}
chown($config, 'www-data');
chmod($config, 0640);
echo "Isolated P2 Dolibarr configuration generated (no credentials shown).\n";
