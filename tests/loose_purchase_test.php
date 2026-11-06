<?php
/**
 * Projectloze inkoop en besteldatum van de inkoopregel.
 * Run: php tests/loose_purchase_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-06';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-loose-' . getmypid();
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function purchase(array $extra): array
{
    return array_merge([
        'document_no' => 'PO1',
        'line_no' => 10000,
        'item_no' => 'PK-1',
        'type' => 'Artikel',
        'job_no' => '',
        'job_task_no' => '',
        'quantity' => 1.0,
        'outstanding_qty' => 1.0,
        'expected_receipt_date' => '2026-05-27',
        'order_date' => '2026-05-27',
        'uom_code' => 'PCS',
    ], $extra);
}

$boundary = elpis_line_status(elpis_loose_purchase_status_line(purchase([
    'document_no' => 'PO12602946',
    'item_no' => 'PI20001871',
    'order_date' => '2026-05-27',
    'expected_receipt_date' => '2026-05-27',
])));
if ($boundary['key'] !== 'LEAD_TIME_INVALID') {
    fail('regeldatum gelijk aan verwachte ontvangst is ongeldig: ' . $boundary['key']);
}
$headerOnly = elpis_line_status(elpis_loose_purchase_status_line(purchase([
    'document_no' => 'PO12602946',
    'order_date' => '2026-05-26',
    'expected_receipt_date' => '2026-05-27',
])));
if ($headerOnly['key'] === 'LEAD_TIME_INVALID') {
    fail('kopdatum 26 mei mag de regel van 27 mei niet ongeldig maken');
}

$later = elpis_line_status(elpis_loose_purchase_status_line(purchase([
    'document_no' => 'PO12600022',
    'quantity' => 2,
    'outstanding_qty' => 1,
    'order_date' => '2026-01-07',
    'expected_receipt_date' => '2026-02-04',
])));
if ($later['key'] === 'LEAD_TIME_INVALID') {
    fail('verwachte ontvangst na de regelbesteldatum is geen levertijd ongeldig');
}
$unknown = elpis_line_status(elpis_loose_purchase_status_line(purchase([
    'document_no' => 'PO12604903',
    'quantity' => 6,
    'outstanding_qty' => 4,
    'order_date' => '0001-01-01',
    'expected_receipt_date' => '0001-01-01',
])));
if ($unknown['key'] === 'LEAD_TIME_INVALID') {
    fail('minimumdatum is onbekend');
}
$before = elpis_line_status(elpis_loose_purchase_status_line(purchase([
    'document_no' => '4500115246',
    'order_date' => '2024-08-22',
    'expected_receipt_date' => '2024-08-21',
    'job_task_no' => '0',
])));
if ($before['key'] !== 'LEAD_TIME_INVALID') {
    fail('verwachte ontvangst vóór besteldatum is ongeldig: ' . $before['key']);
}

$lines = [
    purchase(['document_no' => 'PO12602946', 'job_no' => 'PRJ2606494', 'job_task_no' => 'WO2607810']),
    purchase(['document_no' => '4500115246', 'job_task_no' => '0', 'order_date' => '2024-08-22', 'expected_receipt_date' => '2024-08-21']),
    purchase(['document_no' => 'PO12600350', 'item_no' => 'PK-2131A008', 'uom_code' => 'SU010', 'quantity' => 1, 'outstanding_qty' => 1]),
    purchase(['document_no' => 'PO12600350', 'item_no' => 'PK-2131A008', 'uom_code' => 'SU010', 'quantity' => 1, 'outstanding_qty' => 1]),
    purchase(['document_no' => 'PO-REV', 'item_no' => 'PK-REV']),
    purchase(['document_no' => 'PO-STOCK', 'item_no' => 'AO-11A373']),
    purchase(['document_no' => 'PO-SVC', 'item_no' => 'ST-TASK']),
    purchase(['document_no' => 'PO-OPEN', 'item_no' => 'PK-OPEN', 'header_status' => 'Open']),
];
$selected = elpis_select_loose_purchase_lines('Koninklijke van Twist', $lines, ['PO-REV|10000' => true], ['ST-TASK' => true]);
$docs = array_map(static fn (array $line): string => $line['document_no'], $selected);
sort($docs);
if ($docs !== ['4500115246', 'PO-OPEN', 'PO-STOCK', 'PO12600350']) {
    fail('selectie projectloos: ' . json_encode($docs));
}
$otherCompany = elpis_select_loose_purchase_lines('Hunter van Twist', [
    purchase(['document_no' => 'PO12600350', 'item_no' => 'PK-2131A008']),
], [], []);
if (count($otherCompany) !== 1 || count($selected) < 1) {
    fail('hetzelfde document in een ander bedrijf blijft een eigen regel');
}
$su = null;
foreach ($selected as $line) {
    if ($line['document_no'] === 'PO12600350') {
        $su = $line;
    }
}
if ($su === null || (float) $su['quantity'] !== 1.0 || (float) $su['outstanding_qty'] !== 1.0) {
    fail('regeleenheid SU010 blijft 1, niet de basiseenheid');
}

$projectLine = [
    'item_no' => 'PI20001871',
    'quantity' => 1,
    'purchase_order_no' => 'PO12602946',
    'purchase_order_line_no' => 10000,
    'work_order_no' => 'WO2607810',
    'expected_receipt_date' => '2026-05-27',
    'planning_date' => '2026-07-29',
];
$purchaseRow = purchase([
    'document_no' => 'PO12602946',
    'item_no' => 'PI20001871',
    'order_date' => '2026-05-27',
    'expected_receipt_date' => '2026-05-27',
    'job_no' => 'PRJ2606494',
]);
$final = elpis_finalize_line($projectLine, 'PRJ2606494', ['PO12602946|10000' => $purchaseRow], [], [], [], [], [], [], []);
if (($final['order_date'] ?? '') !== '2026-05-27' || elpis_line_status($final)['key'] !== 'LEAD_TIME_INVALID') {
    fail('projectregel krijgt de regelbesteldatum: ' . json_encode([$final['order_date'] ?? null, elpis_line_status($final)['key']]));
}

$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity): array {
    if ($entity === 'AppPurchaseOrderSubform' && $company === 'Koninklijke van Twist') {
        return [[
            'Document_Type' => 'Order',
            'Document_No' => 'PO12602946',
            'Line_No' => 10000,
            'Order_Date' => '2026-05-27',
        ]];
    }

    return [];
};
elpis_reset_fetch_memo();
$attached = elpis_attach_purchase_order_dates('Koninklijke van Twist', [[
    'document_no' => 'PO12602946',
    'line_no' => 10000,
    'order_date' => '',
]], 60);
if (($attached[0]['order_date'] ?? '') !== '2026-05-27') {
    fail('subform vult alleen de regelbesteldatum: ' . json_encode($attached));
}

echo "OK\n";
