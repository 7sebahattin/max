<?php
/**
 * P2 local-only verification after the actual official Dolibarr installer ran.
 * Running this check must not mutate any customer or real application data.
 */
declare(strict_types=1);
$cfg = '/opt/dolibarr/htdocs/conf/conf.php';
if (!is_file($cfg)) {
    throw new RuntimeException('No installed configuration file.');
}
require $cfg;
require '/opt/dolibarr/htdocs/version.inc.php';
if (DOL_VERSION !== '24.0.1') {
    throw new RuntimeException('Unexpected Dolibarr version');
}
$db = new mysqli($dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, (int) $dolibarr_main_db_port);
$db->set_charset('utf8mb4');
$expected = [
    'llx_user', 'llx_const', 'llx_societe', 'llx_product',
    'llx_entrepot', 'llx_stock_mouvement', 'llx_commande',
    'llx_expedition', 'llx_facture', 'llx_paiement'
];
foreach ($expected as $table) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name=?');
    $stmt->bind_param('ss', $dolibarr_main_db_name, $table);
    $stmt->execute();
    $stmt->bind_result($exists);
    $stmt->fetch();
    $stmt->close();
    if ((int)$exists !== 1) {
        throw new RuntimeException("Dolibarr table missing: {$table}");
    }
}
$stmt = $db->prepare("SELECT COUNT(*) FROM llx_user WHERE login=? AND admin=1");
$name = 'maxadmin';
$stmt->bind_param('s', $name);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();
if ((int)$count !== 1) {
    throw new RuntimeException('Official installer did not create the synthetic admin');
}
$tables = $db->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE()")->fetch_assoc()['n'];
if ((int)$tables < 50) {
    throw new RuntimeException("Unexpectedly small installed schema: {$tables}");
}
if (!is_file('/var/lib/dolibarr/documents/install.lock')) {
    throw new RuntimeException('Installer lock was not created');
}
echo "P2 actual installer PASS: Dolibarr " . DOL_VERSION . "; {$tables} tables; synthetic admin; core commercial tables; install.lock.\n";
