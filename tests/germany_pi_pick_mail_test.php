<?php
/**
 * Germany PI-termijnartikelen: uitsluiting pick-statussen + toelichting RECEIVED_TO_PICK/STOCK.
 * Run: php tests/germany_pi_pick_mail_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-05';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-germany-pi-' . getmypid();
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function piLine(array $extra = []): array
{
    return array_merge([
        'item_no' => 'PI00000001',
        'description' => 'Rehlko Genset 1',
        'quantity' => 0.25,
        'qty_posted' => 0,
        'kvt_qty_picked' => 0,
        'completely_picked' => false,
        'purchase_order_no' => 'PO1',
        'purchase_outstanding_qty' => 0,
        'completely_received' => true,
        'qty_received' => 0.25,
        'expected_receipt_date' => '2025-07-31',
        'planning_date' => '',
        'reserved_qty' => 0,
        'reserved_purchase_qty' => 0,
        'free_stock' => 0,
        'inbound_qty' => 0,
    ], $extra);
}

$config = elpis_mail_config();
$pick = $config['excluded_pick_statuses'] ?? [];
if (($pick['KVT Germany GmbH']['item_prefixes'][0] ?? '') !== 'PI') {
    fail('default excluded_pick_statuses mist PI voor KVT Germany GmbH: ' . json_encode($pick));
}

$line = piLine();
$status = elpis_line_status($line);
if ($status['key'] !== 'RECEIVED_TO_PICK') {
    fail('volledig ontvangen PI zonder pick = RECEIVED_TO_PICK, kreeg ' . $status['key']);
}
if (!elpis_status_is_problem($status['key'])) {
    fail('RECEIVED_TO_PICK hoort een probleemstatus te zijn');
}

$reasons = elpis_mail_line_reasons($line);
if ($reasons !== ['Ontvangen, nog niet gepickt']) {
    fail('toelichting RECEIVED_TO_PICK: ' . json_encode($reasons));
}

$stock = piLine([
    'purchase_order_no' => '',
    'completely_received' => false,
    'qty_received' => 0,
    'free_stock' => 1,
]);
if (elpis_line_status($stock)['key'] !== 'STOCK') {
    fail('vrijestock-dekking = STOCK: ' . elpis_line_status($stock)['key']);
}
if (elpis_mail_line_reasons($stock) !== ['Op voorraad, nog niet gepickt']) {
    fail('toelichting STOCK: ' . json_encode(elpis_mail_line_reasons($stock)));
}

$rules = $config['excluded_pick_statuses'];
if (!elpis_mail_line_excluded($line, [], 'KVT Germany GmbH', $rules)) {
    fail('Germany + PI + RECEIVED_TO_PICK hoort uitgesloten');
}
if (elpis_mail_line_excluded($line, [], 'Koninklijke van Twist', $rules)) {
    fail('zelfde regel bij KVT NL blijft in de mail');
}
$pk = piLine(['item_no' => 'PK-123']);
if (elpis_mail_line_excluded($pk, [], 'KVT Germany GmbH', $rules)) {
    fail('niet-PI bij Germany blijft');
}

$late = piLine([
    'completely_received' => false,
    'qty_received' => 0,
    'purchase_outstanding_qty' => 0.25,
    'expected_receipt_date' => '2025-07-31',
]);
$lateKey = elpis_line_status($late)['key'];
if (!in_array($lateKey, ['DELIVERY_LATE', 'LATE'], true)) {
    fail('open PO met verstreken datum blijft te-laat-status: ' . $lateKey);
}
if (elpis_mail_line_excluded($late, [], 'KVT Germany GmbH', $rules)) {
    fail('Te laat PI bij Germany mag niet uitgesloten worden');
}

$jk = ['code' => 'JK', 'name' => 'John Klomp', 'user_id' => 'KVT\\JKLOMP', 'email' => 'jklomp@kvt.nl'];
$projects = [['no' => 'PRJ2500003', 'description' => 'G', 'project_manager' => 'KVT\\DTYLUTKI']];
$lines = ['PRJ2500003' => [
    array_merge($line, ['purchasers' => [$jk]]),
    array_merge($late, ['purchasers' => [$jk]]),
]];
$kept = elpis_digest_rows_for_manager($projects, $lines, 'JKLOMP', ['WO'], 'KVT Germany GmbH', $rules);
$items = array_map(static fn ($r) => ($r['item_no'] ?? '') . ':' . elpis_line_status($r)['key'], $kept);
if (count($kept) !== 1 || elpis_line_status($kept[0])['key'] !== $lateKey) {
    fail('digest houdt alleen te-laat-regel: ' . json_encode($items));
}
$keptPm = elpis_digest_rows_for_manager($projects, $lines, 'DTYLUTKI', ['WO'], 'KVT Germany GmbH', $rules);
if (count($keptPm) !== 1) {
    fail('PM ziet dezelfde uitsluiting: ' . count($keptPm));
}

// Expliciet lege config schakelt defaults uit.
file_put_contents(elpis_mail_config_path(), json_encode([
    'test_mode' => true,
    'test_recipient' => 'tfalken@kvt.nl',
    'excluded_item_nos' => [],
    'excluded_pick_statuses' => [],
], JSON_PRETTY_PRINT));
$empty = elpis_mail_config();
if (($empty['excluded_pick_statuses'] ?? null) !== []) {
    fail('expliciet lege excluded_pick_statuses blijft leeg');
}
if (elpis_mail_line_excluded($line, [], 'KVT Germany GmbH', $empty['excluded_pick_statuses'])) {
    fail('zonder regels geen uitsluiting');
}


// Elke bekende status (en onbekende key) geeft altijd een niet-lege toelichting.
$fixtures = [
    'RETURN' => ['quantity' => 0],
    'CLOSED' => ['quantity' => 1, 'work_order_status' => 'Afgesloten', 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'PICKED' => ['quantity' => 1, 'completely_picked' => true, 'kvt_qty_picked' => 1, 'qty_posted' => 1],
    'LATE' => ['quantity' => 1, 'planning_date' => '2025-01-01', 'purchase_order_no' => '', 'purchase_outstanding_qty' => 0, 'free_stock' => 0],
    'DELIVERY_LATE' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2025-07-31'],
    'ORDER_TOO_LATE' => ['quantity' => 1, 'planning_date' => '2026-11-06', 'latest_order_date' => '2026-11-01', 'purchase_order_no' => '', 'free_stock' => 0],
    'NO_DATE' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => ''],
    'TO_ORDER' => ['quantity' => 2, 'purchase_order_no' => '', 'free_stock' => 0],
    'INBOUND' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 0, 'completely_received' => true, 'qty_received' => 1, 'inbound_qty' => 1, 'free_stock' => 0],
    'PARTIAL_RECEIVED' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 0, 'completely_received' => true, 'qty_received' => 2, 'free_stock' => 0],
    'PARTIAL_STOCK' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => '', 'free_stock' => 5],
    'PARTIAL_ORDERED' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'ORDERED' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'RECEIVED_TO_PICK' => piLine(),
    'STOCK' => piLine(['purchase_order_no' => '', 'completely_received' => false, 'qty_received' => 0, 'free_stock' => 1]),
];
$table = [];
foreach ($fixtures as $want => $extra) {
    $row = array_merge([
        'item_no' => 'X1',
        'quantity' => 1,
        'qty_posted' => 0,
        'kvt_qty_picked' => 0,
        'reserved_qty' => 0,
        'reserved_purchase_qty' => 0,
        'free_stock' => 0,
        'inbound_qty' => 0,
        'expected_receipt_date' => '',
        'planning_date' => '',
    ], $extra);
    $got = elpis_line_status($row)['key'];
    if ($got !== $want) {
        fail("fixture $want gaf status $got");
    }
    $reasons = elpis_mail_line_reasons($row);
    if ($reasons === [] || trim(elpis_mail_join_reasons($reasons)) === '') {
        fail("lege toelichting voor $want");
    }
    $table[$want] = elpis_mail_join_reasons($reasons);
}
$unknown = elpis_mail_line_reasons(['quantity' => 1, 'free_stock' => 1]); // STOCK in practice
// Force fallback path via reflection-achtige stub: onbekende key door lege status array is last resort
// Controleer fallback-LOC direct.
if (LOC('elpis.mail.reason.fallback', 'Demo') !== 'Status: Demo') {
    fail('fallback-loc');
}
foreach (array_keys(ELPI_LINE_STATUSES) as $statusKey) {
    if (!isset($table[$statusKey])) {
        fail("geen fixture voor status $statusKey");
    }
}
file_put_contents(sys_get_temp_dir() . '/elpis-mail-reason-table.json', json_encode($table, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "OK\n";
