<?php
/** Native Dolibarr pilot. Disposable Compose database only; no core modifications. */
declare(strict_types=1);
require '/opt/dolibarr/htdocs/conf/conf.php';
if (PHP_SAPI !== 'cli' || $dolibarr_main_db_host !== 'db' || $dolibarr_main_db_name !== 'max_demo'
    || !is_file('/var/lib/dolibarr/documents/install.lock')) {
    throw new RuntimeException('Refusing non-disposable or unlocked pilot');
}
chdir('/opt/dolibarr/htdocs');
require 'master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
foreach (['societe/class/societe.class.php', 'contact/class/contact.class.php', 'product/class/product.class.php',
    'product/stock/class/entrepot.class.php', 'product/stock/class/mouvementstock.class.php',
    'commande/class/commande.class.php', 'expedition/class/expedition.class.php',
    'compta/facture/class/facture.class.php', 'compta/bank/class/account.class.php'] as $file) {
    require_once DOL_DOCUMENT_ROOT.'/'.$file;
}
function checkPilot(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label."\n";
}
function nativeResult($result, $object, string $label): int {
    checkPilot($result > 0, $label.($result <= 0 ? ': '.$object->error.' '.implode(';', $object->errors ?? []) : ''));
    return (int) $result;
}
function scalarPilot(string $sql) {
    global $db;
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException($db->lasterror()); }
    return $db->fetch_row($r)[0];
}
function stockPilot(int $product, int $warehouse): float {
    return (float) scalarPilot('SELECT COALESCE(SUM(reel),0) FROM '.MAIN_DB_PREFIX.'product_stock WHERE fk_product='.$product.' AND fk_entrepot='.$warehouse);
}
$user->fetch('', 'maxadmin');
checkPilot($user->id > 0 && $user->admin == 1, 'installed demo administrator');
foreach (['modSociete','modFournisseur','modProduct','modStock','modProductBatch','modCommande','modExpedition','modFacture','modBanque','modMultiCurrency','modExport','modEmailCollector'] as $module) {
    $result = activateModule($module, 1, 1);
    checkPilot(empty($result['errors']), 'native module '.$module);
}
foreach (['MAIN_MONNAIE'=>'EUR', 'MAIN_INFO_SOCIETE_NOM'=>'MAX Synthetic Pilot', 'MAIN_INFO_SOCIETE_COUNTRY'=>'1:FR:France',
    'STOCK_CALCULATE_ON_SHIPMENT'=>'1','STOCK_CALCULATE_ON_VALIDATE_ORDER'=>'0','STOCK_CALCULATE_ON_BILL'=>'0',
    'MAIN_DISABLE_ALL_MAILS'=>'1'] as $key=>$value) {
    checkPilot(dolibarr_set_const($db, $key, $value, 'chaine', 0, '', 1) > 0, 'configure '.$key);
}
$conf->setValues($db);
$user->getrights('', 1);
$mysoc->name = 'MAX Synthetic Pilot'; $mysoc->country_id = 1; $mysoc->country_code = 'FR';
$mysoc->currency_code = 'EUR';
$customer = new Societe($db); $customer->name = 'Synthetic Customer'; $customer->client = 1;
$customer->code_client = 'P2-CUSTOMER'; $customer->country_id = 1;
$cid = nativeResult($customer->create($user), $customer, 'create native customer');
$supplier = new Societe($db); $supplier->name = 'Synthetic Supplier'; $supplier->fournisseur = 1;
$supplier->code_fournisseur = 'P2-SUPPLIER'; $supplier->country_id = 1;
$sid = nativeResult($supplier->create($user), $supplier, 'create native supplier');
$contact = new Contact($db); $contact->socid = $cid; $contact->lastname = 'Synthetic'; $contact->firstname = 'Contact';
$contact->email = 'contact@example.invalid';
$contactid = nativeResult($contact->create($user), $contact, 'create native contact');
$read = new Contact($db); nativeResult($read->fetch($contactid), $read, 'reload contact');
checkPilot((int)$read->socid === $cid, 'contact belongs to customer');
$reader = new User($db); $reader->login = 'p2-no-rights'; $reader->lastname = 'Synthetic reader';
$reader->pass = bin2hex(random_bytes(24));
$readerid = nativeResult($reader->create($user), $reader, 'create unprivileged user');
$reader->clearrights(); $reader->getrights('', 1);
checkPilot(!$reader->hasRight('commande', 'creer'), 'unprivileged role cannot create/validate order');
$warehouses = [];
foreach (['P2-IN','P2-OUT'] as $ref) {
    $w = new Entrepot($db); $w->ref = $ref; $w->label = $ref; $w->statut = 1;
    $warehouses[] = nativeResult($w->create($user), $w, 'create warehouse '.$ref);
}
$products = [];
foreach (['P2-SKU', 'P2-CARTON', 'P2-LOT'] as $ref) {
    $p = new Product($db); $p->ref = $ref; $p->label = $ref; $p->type = 0;
    $p->status = 1; $p->status_buy = 1; $p->tobatch = ($ref === 'P2-LOT' ? 1 : 0);
    $p->stockable_product = 1;
    nativeResult($p->create($user), $p, 'create product '.$ref);
    $products[$ref] = $p;
}
$sku = $products['P2-SKU']->id; $carton = $products['P2-CARTON']->id; $lot = $products['P2-LOT']->id;
$m = new MouvementStock($db);
nativeResult($m->reception($user, $sku, $warehouses[0], 20, 5, 'P2 synthetic receipt'), $m, 'receive 20 units');
nativeResult($m->livraison($user, $sku, $warehouses[0], 5, 5, 'P2 transfer out'), $m, 'transfer debit');
nativeResult($m->reception($user, $sku, $warehouses[1], 5, 5, 'P2 transfer in'), $m, 'transfer credit');
checkPilot(stockPilot($sku,$warehouses[0]) === 15.0 && stockPilot($sku,$warehouses[1]) === 5.0, 'warehouse transfer conserves 20 units');
nativeResult($m->livraison($user, $sku, $warehouses[0], 1, 5, 'P2 count adjustment'), $m, 'inventory count adjustment');
nativeResult($m->reception($user, $carton, $warehouses[0], 50, 1, 'P2 packaging receipt'), $m, 'carton represented as stock product');
nativeResult($m->reception($user, $lot, $warehouses[0], 8, 5, 'P2 batch receipt', '', '', 'P2-BATCH'), $m, 'receive tracked batch');
nativeResult($m->livraison($user, $lot, $warehouses[0], 3, 5, 'P2 batch transfer', '', '', '', 'P2-BATCH'), $m, 'batch transfer debit');
nativeResult($m->reception($user, $lot, $warehouses[1], 3, 5, 'P2 batch transfer', '', '', 'P2-BATCH'), $m, 'batch transfer credit');
checkPilot(stockPilot($lot,$warehouses[0]) === 5.0 && stockPilot($lot,$warehouses[1]) === 3.0, 'batch transfer balances');
$order = new Commande($db); $order->socid = $cid; $order->date = dol_now();
$oid = nativeResult($order->create($user), $order, 'create native sales order');
$line = nativeResult($order->addline('Synthetic goods', 10, 10, 0, 0, 0, $sku), $order, 'order goods line');
nativeResult($order->addline('Synthetic service', 3, 2, 0, 0, 0, 0, 0, 0, 0, 'HT', 0, '', '', 1), $order, 'order second line');
checkPilot($order->valid($reader) < 0, 'native order validation denies unprivileged actor');
nativeResult($order->valid($user), $order, 'validate synthetic order');
$order->fetch($oid);
checkPilot(count($order->lines) === 2, 'persisted multi-line order');
$shipment = new Expedition($db); $shipment->socid = $cid; $shipment->origin = 'commande'; $shipment->origin_id = $oid;
$shipment->date_shipping = dol_now();
nativeResult($shipment->addline($warehouses[0], $line, 4), $shipment, 'partial shipment four of ten');
$eid = nativeResult($shipment->create($user), $shipment, 'persist native shipment');
nativeResult($shipment->valid($user), $shipment, 'validate partial shipment');
$order->loadExpeditions(1);
checkPilot((float)$order->expeditions[$line] === 4.0 && 10 - $order->expeditions[$line] === 6.0, 'six order units remain unshipped');
checkPilot(stockPilot($sku,$warehouses[0]) === 10.0, 'shipment removes exactly four units after count adjustment');
checkPilot(stockPilot($carton,$warehouses[0]) === 50.0, 'no implicit carton consumption configured');
checkPilot((float)scalarPilot('SELECT SUM(value) FROM '.MAIN_DB_PREFIX.'stock_mouvement WHERE fk_product='.$sku) === 15.0, 'stock movements reconcile with two warehouses');
$invoice = new Facture($db); $invoice->socid = $cid; $invoice->date = dol_now(); $invoice->type = 0;
$iid = nativeResult($invoice->create($user), $invoice, 'create draft-only invoice');
nativeResult($invoice->addline('Synthetic goods', 10, 4, 0, 0, 0, $sku), $invoice, 'draft invoice line');
$invoice->fetch($iid);
checkPilot((int)$invoice->status === 0 && abs($invoice->total_ht - 40) < 0.001, 'draft invoice persists total 40 without issuance');
checkPilot(abs($invoice->multicurrency_total_ht - 40) < 0.001, 'base currency invoice totals');
$bank = new Account($db); $bank->ref = 'P2-BANK'; $bank->label = 'Synthetic ledger only';
$bank->country_id = 1; $bank->currency_code = 'EUR'; $bank->type = 1; $bank->date_solde = dol_now();
$bid = nativeResult($bank->create($user), $bank, 'create fictional bank account');
$bankline = nativeResult($bank->addline(dol_now(), 'VIR', 'Synthetic receipt - not invoice settlement', 40, '', 0, $user), $bank, 'fictional bank receipt');
checkPilot((float)scalarPilot('SELECT amount FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.$bankline) === 40.0, 'fictional receipt persisted separately from unissued invoice');
$bankEntry = new AccountLine($db); $bankEntry->fetch($bankline); $bankEntry->num_releve = 'P2-SYNTHETIC-STATEMENT';
nativeResult($bankEntry->update_conciliation($user, 0, 1), $bankEntry, 'reconcile fictional bank entry');
checkPilot((int)scalarPilot('SELECT rappro FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.$bankline) === 1, 'fictional statement reconciliation persisted');
$currency = new MultiCurrency($db); $currency->code = 'USD'; $currency->name = 'Synthetic USD';
nativeResult($currency->create($user), $currency, 'configure native foreign currency');
nativeResult($currency->addRate(1.2), $currency, 'fixed synthetic FX rate without provider');
$foreign = new Facture($db); $foreign->socid = $cid; $foreign->date = dol_now(); $foreign->type = 0;
$foreign->multicurrency_code = 'USD'; $foreign->multicurrency_tx = 1.2;
nativeResult($foreign->create($user), $foreign, 'create foreign currency draft');
nativeResult($foreign->addline('Synthetic USD goods', 10, 2, 0), $foreign, 'foreign draft line');
$foreign->fetch($foreign->id);
checkPilot($foreign->multicurrency_code === 'USD' && abs($foreign->multicurrency_total_ht - 24) < 0.001, 'native fixed FX produces USD 24 from EUR 20');
checkPilot((int)scalarPilot('SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'paiement_facture WHERE fk_facture='.$iid) === 0, 'draft invoice has no settlement');
$langs->setDefaultLang('tr_TR'); $langs->loadLangs(['main','bills','orders']);
checkPilot($langs->trans('Invoice') !== 'Invoice', 'native Turkish invoice translation');
$langs->setDefaultLang('en_US');
foreach ([[$invoice,'crabe'],[$shipment,'rouget']] as [$object,$model]) {
    nativeResult($object->generateDocument($model, $langs), $object, 'generate native '.$model.' PDF');
}
require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_csvutf8.modules.php';
$export = new ExportCsvUtf8($db); $path = DOL_DATA_ROOT.'/p2-native.csv';
$export->open_file($path,$langs);
$export->write_record(['ref'=>'ref','qty'=>'qty'], (object)['ref'=>'P2-SKU','qty'=>4], $langs, ['ref'=>'Text','qty'=>'Numeric']);
$export->close_file();
checkPilot(str_contains(file_get_contents($path), 'P2-SKU'), 'native CSV writer persists fixture');
require_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_excel2007.modules.php';
$xlsx = new ExportExcel2007($db);
$xlsxPath = DOL_DATA_ROOT.'/p2-native.xlsx';
$xlsx->open_file($xlsxPath,$langs);
$xlsx->write_record(['ref'=>'ref','qty'=>'qty'], (object)['ref'=>'P2-SKU','qty'=>4], $langs, ['ref'=>'Text','qty'=>'Numeric']);
$xlsx->close_file();
$zip = new ZipArchive();
checkPilot($zip->open($xlsxPath) === true && $zip->locateName('xl/workbook.xml') !== false, 'native genuine XLSX export');
$zip->close();
require_once DOL_DOCUMENT_ROOT.'/emailcollector/class/emailcollector.class.php';
$collectors = [];
foreach (['P2-MAIL-A','P2-MAIL-B'] as $ref) {
    $collector = new EmailCollector($db); $collector->ref = $ref; $collector->label = $ref;
    $collector->host = 'mail.example.invalid'; $collector->port = 993; $collector->login = 'synthetic@example.invalid';
    $collector->password = ''; $collector->status = 0;
    $mailid = nativeResult($collector->create($user), $collector, 'create inactive native collector '.$ref);
    $copy = new EmailCollector($db); nativeResult($copy->fetch($mailid), $copy, 'reload inactive collector');
    checkPilot((int)$copy->status === 0, 'collector remains disabled; no IMAP connection');
    $collectors[] = $mailid;
}
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
$mail = new CMailFile('P2 synthetic message', 'recipient@example.invalid', 'sender@example.invalid', 'Synthetic only');
checkPilot(!$mail->sendfile() && str_contains($mail->error, 'MAIN_DISABLE_ALL_MAILS'), 'native outgoing mail gate prevents transport');
echo 'P2 OBJECT IDS '.json_encode(['customer'=>$cid,'supplier'=>$sid,'contact'=>$contactid,'reader'=>$readerid,'warehouses'=>$warehouses,'products'=>[$sku,$carton,$lot],'order'=>$oid,'shipment'=>$eid,'draft_invoice'=>$iid,'bank'=>$bid])."\n";
echo "P2 native business pilot PASS; UI/mail transport and advanced currency remain separately assessed.\n";
