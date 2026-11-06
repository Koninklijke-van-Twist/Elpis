<?php
/**
 * Mail-toelichting: elke elpis_line_status-key (plus fallback) geeft altijd tekst.
 * Run: php tests/mail_reasons_always_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-05';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-mail-reasons-' . getmypid();
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function baseLine(array $extra = []): array
{
    return array_merge([
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
}

$fixtures = [
    'RETURN' => ['quantity' => 0],
    'CLOSED' => ['quantity' => 1, 'work_order_status' => 'Afgesloten', 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'PICKED' => ['quantity' => 1, 'completely_picked' => true, 'kvt_qty_picked' => 1, 'qty_posted' => 1],
    'LATE' => ['quantity' => 1, 'planning_date' => '2025-01-01', 'purchase_order_no' => '', 'purchase_outstanding_qty' => 0, 'free_stock' => 0],
    'DELIVERY_LATE' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2025-07-31'],
    'ORDER_TOO_LATE' => ['quantity' => 1, 'planning_date' => '2026-11-06', 'latest_order_date' => '2026-11-01', 'purchase_order_no' => '', 'free_stock' => 0],
    'LEAD_TIME_INVALID' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'purchase_expected_receipt_date' => '2026-10-01', 'order_date' => '2026-10-01', 'expected_receipt_date' => '2026-12-01', 'planning_date' => '2026-12-15'],
    'NO_DATE' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => ''],
    'TO_ORDER' => ['quantity' => 2, 'purchase_order_no' => '', 'free_stock' => 0],
    'INBOUND' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 0, 'completely_received' => true, 'qty_received' => 1, 'inbound_qty' => 1, 'free_stock' => 0],
    'PARTIAL_RECEIVED' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 0, 'completely_received' => true, 'qty_received' => 2, 'free_stock' => 0],
    'PARTIAL_STOCK' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => '', 'free_stock' => 5],
    'PARTIAL_ORDERED' => ['quantity' => 2, 'kvt_qty_picked' => 1, 'qty_posted' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'ORDERED' => ['quantity' => 1, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 1, 'expected_receipt_date' => '2026-12-01'],
    'RECEIVED_TO_PICK' => [
        'item_no' => 'PI00000001', 'quantity' => 0.25, 'purchase_order_no' => 'PO1', 'purchase_outstanding_qty' => 0,
        'completely_received' => true, 'qty_received' => 0.25, 'expected_receipt_date' => '2025-07-31', 'free_stock' => 0,
    ],
    'STOCK' => [
        'item_no' => 'PI00000001', 'quantity' => 0.25, 'purchase_order_no' => '', 'qty_received' => 0,
        'expected_receipt_date' => '2025-07-31', 'free_stock' => 1,
    ],
];

$table = [];
foreach ($fixtures as $want => $extra) {
    $row = baseLine($extra);
    $got = elpis_line_status($row)['key'];
    if ($got !== $want) {
        fail("fixture $want gaf status $got");
    }
    $reasons = elpis_mail_line_reasons($row);
    if ($reasons === [] || trim(elpis_mail_join_reasons($reasons)) === '') {
        fail("lege toelichting voor $want: " . json_encode($reasons));
    }
    $table[$want] = elpis_mail_join_reasons($reasons);
}

foreach (array_keys(ELPI_LINE_STATUSES) as $statusKey) {
    if (!isset($table[$statusKey])) {
        fail("geen fixture voor status $statusKey");
    }
}

if ($table['RECEIVED_TO_PICK'] !== 'Ontvangen, nog niet gepickt') {
    fail('RECEIVED_TO_PICK: ' . $table['RECEIVED_TO_PICK']);
}
if ($table['STOCK'] !== 'Op voorraad, nog niet gepickt') {
    fail('STOCK: ' . $table['STOCK']);
}
if ($table['ORDERED'] !== 'Besteld, verwacht 01-12-2026') {
    fail('ORDERED: ' . $table['ORDERED']);
}
if ($table['NO_DATE'] !== 'Verwachte ontvangstdatum ontbreekt') {
    fail('NO_DATE: ' . $table['NO_DATE']);
}
if (LOC('elpis.mail.reason.fallback', 'Demo') !== 'Status: Demo') {
    fail('fallback-loc');
}

echo "OK\n";
