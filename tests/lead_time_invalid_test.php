<?php
/**
 * Levertijd ongeldig: verwachte ontvangst op of vóór besteldatum, alleen bij open inkoop.
 * Run: php tests/lead_time_invalid_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-06';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-lead-' . getmypid();
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function line(array $extra = []): array
{
    return array_merge([
        'quantity' => 1,
        'qty_received' => 0,
        'purchase_order_no' => 'PO1',
        'purchase_outstanding_qty' => 1,
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'free_stock' => 0,
        'expected_receipt_date' => '2026-11-20',
        'order_date' => '2026-11-01',
        'planning_date' => '2026-12-01',
    ], $extra);
}

if (elpis_line_status(line(['expected_receipt_date' => '2026-11-01', 'order_date' => '2026-11-01']))['key'] !== 'LEAD_TIME_INVALID') {
    fail('gelijk aan besteldatum is ongeldig');
}
if (elpis_line_status(line(['expected_receipt_date' => '2026-10-31', 'order_date' => '2026-11-01']))['key'] !== 'LEAD_TIME_INVALID') {
    fail('vóór besteldatum is ongeldig');
}
if (elpis_line_status(line(['expected_receipt_date' => '2026-11-02', 'order_date' => '2026-11-01']))['key'] === 'LEAD_TIME_INVALID') {
    fail('na besteldatum is geen levertijd ongeldig');
}
foreach ([
    ['expected_receipt_date' => '', 'order_date' => '2026-11-01'],
    ['expected_receipt_date' => '0001-01-01', 'order_date' => '2026-11-01'],
    ['expected_receipt_date' => '2026-10-01', 'order_date' => ''],
    ['expected_receipt_date' => '2026-10-01', 'order_date' => '0001-01-01'],
] as $extra) {
    if (elpis_line_status(line($extra))['key'] === 'LEAD_TIME_INVALID') {
        fail('lege of minimumdatum is onbekend: ' . json_encode($extra));
    }
}
if (elpis_line_status(line([
    'expected_receipt_date' => '2026-10-01',
    'order_date' => '',
    'latest_order_date' => '2026-11-15',
]))['key'] === 'LEAD_TIME_INVALID') {
    fail('uiterste besteldatum is geen besteldatum');
}

$partial = line([
    'quantity' => 4,
    'qty_received' => 2,
    'purchase_outstanding_qty' => 2,
    'expected_receipt_date' => '2026-10-01',
    'order_date' => '2026-10-15',
]);
if (elpis_line_status($partial)['key'] !== 'LEAD_TIME_INVALID') {
    fail('deels ontvangen met open rest en ongeldige datum: ' . elpis_line_status($partial)['key']);
}

$received = line([
    'qty_received' => 1,
    'purchase_outstanding_qty' => 0,
    'completely_received' => true,
    'expected_receipt_date' => '2026-10-01',
    'order_date' => '2026-10-15',
    'free_stock' => 0,
]);
if (elpis_line_status($received)['key'] !== 'RECEIVED_TO_PICK') {
    fail('volledig ontvangen blijft ontvangen, nog picken: ' . elpis_line_status($received)['key']);
}
$onlyBuyer = ['mail_as_purchaser' => true, 'mail_as_manager' => false];
$purchaser = ['content' => 'purchaser'];
$problems = ['content' => 'problems'];
if (!elpis_mail_line_included($partial + $onlyBuyer, $purchaser) || !elpis_mail_line_included($partial + $onlyBuyer, $problems)) {
    fail('open ongeldige levertijd hoort in beide presets');
}
if (elpis_mail_line_included($received + $onlyBuyer, $purchaser) || elpis_mail_line_included($received + $onlyBuyer, $problems)) {
    fail('ontvangen regel wordt geen nieuw bestellerprobleem door de besteldatum');
}
if (!elpis_status_is_problem('LEAD_TIME_INVALID') || !in_array('LEAD_TIME_INVALID', elpis_mail_po_statuses(), true)
    || !in_array('LEAD_TIME_INVALID', ELPIS_MAIL_PURCHASER_STATUSES, true)) {
    fail('status zit in beide presets');
}
if (LOC('elpis.status.lead_time_invalid') !== 'Levertijd ongeldig') {
    fail('Nederlandse statusnaam');
}

echo "OK\n";
