<?php
// Isolated preparation check only; no ERP objects, email, HKS or invoices created.
$required = ['mysqli', 'mbstring', 'gd', 'curl', 'calendar', 'simplexml', 'intl', 'zip', 'soap'];
foreach ($required as $name) {
    if (!extension_loaded($name)) {
        throw new RuntimeException("Missing PHP extension: ".$name);
    }
}
require '/opt/dolibarr/htdocs/version.inc.php';
if (DOL_VERSION !== '24.0.1') {
    throw new RuntimeException('Unexpected Dolibarr version');
}
$db = new mysqli('db', getenv('MARIADB_USER'), getenv('MARIADB_PASSWORD'), getenv('MARIADB_DATABASE'));
$db->query('CREATE TEMPORARY TABLE max_preflight (id INT PRIMARY KEY, label VARCHAR(40))');
$db->query("INSERT INTO max_preflight VALUES (1, 'Synthetic preparation check')");
if ($db->query('SELECT COUNT(*) AS n FROM max_preflight')->fetch_assoc()['n'] != 1) {
    throw new RuntimeException('SQL roundtrip failed');
}
$db->query('DROP TEMPORARY TABLE max_preflight');
printf("Dolibarr %s; PHP %s; SQL %s; extensions and temporary SQL roundtrip PASS\n", DOL_VERSION, PHP_VERSION, $db->server_info);
