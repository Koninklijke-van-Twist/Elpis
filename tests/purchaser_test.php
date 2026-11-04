<?php
/**
 * Besteller: toewijzing (gekoppeld, verdeelde rest, geen) en samengevoegde PM+besteller-mail.
 * Run: php tests/purchaser_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-purchaser-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query): array {
    $filter = (string) ($query['$filter'] ?? '');
    switch ($entity) {
        case 'PurchaseOrders':
            return str_contains($filter, "'IO1'") ? [['No' => 'IO1', 'Purchaser_Code' => 'JVDE']] : [];
        case 'PostedPurchaseReceipt':
            return str_contains($filter, "'IO2'") ? [['Order_No' => 'IO2', 'Purchaser_Code' => 'RDB']] : [];
        case 'SalespersonPurchaserCard':
            return [['Code' => 'JVDE', 'Name' => 'Johan van der Ende', 'E_Mail' => ''], ['Code' => 'RDB', 'Name' => 'Ruud de Boer', 'E_Mail' => 'card@kvt.nl']];
        case 'AppUserSetup':
            if (str_contains($filter, 'Salespers_Purch_Code')) {
                return [['User_ID' => 'KVT\\JVDENDE', 'Salespers_Purch_Code' => 'JVDE', 'Email' => 'jvdende@kvt.nl'], ['User_ID' => 'KVT\\RDBOER', 'Salespers_Purch_Code' => 'RDB', 'Email' => 'rdboer@kvt.nl']];
            }
            return [['User_ID' => 'KVT\\RDBOER', 'Email' => 'rdboer@kvt.nl']];
    }

    return [];
};

// Verdeelde rest: regel zonder koppeling krijgt order IO2 uit de pool.
$lines = elpis_allocate_project_supply('PRJ1', [
    ['item_no' => 'B', 'job_task_no' => 'T1', 'quantity' => 2],
], [
    ['document_no' => 'IO2', 'line_no' => 10000, 'job_no' => 'PRJ1', 'job_task_no' => 'T1', 'item_no' => 'B', 'outstanding_qty' => 2],
]);
if (array_keys($lines[0]['alloc_purchase_docs'] ?? []) !== ['IO2']) {
    fail('verdeelde rest onthoudt de order: ' . json_encode($lines[0]));
}

$byProject = ['PRJ1' => [
    ['item_no' => 'A', 'purchase_order_no' => 'IO1'],
    $lines[0],
    ['item_no' => 'C'],
]];
elpis_reset_fetch_memo();
$out = elpis_attach_purchasers($byProject, elpis_fetch_purchasers('Koninklijke van Twist', elpis_collect_line_purchase_docs($byProject)));
$linked = $out['PRJ1'][0]['purchasers'] ?? [];
if (count($linked) !== 1 || $linked[0]['user_id'] !== 'KVT\\JVDENDE' || $linked[0]['email'] !== 'jvdende@kvt.nl'
    || elpis_purchaser_label($linked[0]) !== 'Johan van der Ende (JVDE)') {
    fail('gekoppelde regel -> PurchaseOrders.Purchaser_Code: ' . json_encode($linked));
}
$alloc = $out['PRJ1'][1]['purchasers'] ?? [];
if (count($alloc) !== 1 || $alloc[0]['code'] !== 'RDB' || elpis_purchaser_key($alloc[0]) !== 'KVT\\RDBOER') {
    fail('verdeelde rest -> besteller via ontvangst-fallback: ' . json_encode($alloc));
}
if (($out['PRJ1'][2]['purchasers'] ?? null) !== []) {
    fail('regel zonder order heeft geen besteller');
}

// Mail: RDBOER is PM van PRJ1 en besteller op een regel in PRJ2 -> één mail, geen dubbele regels.
$sent = [];
$GLOBALS['ELPIS_MAIL_SENDER'] = static function (string $to, string $subject, string $plain, string $html) use (&$sent): bool {
    $sent[] = compact('to', 'subject', 'plain', 'html');

    return true;
};
$rdb = ['code' => 'RDB', 'name' => 'Ruud de Boer', 'user_id' => 'KVT\\RDBOER', 'email' => 'rdboer@kvt.nl'];
$jvde = ['code' => 'JVDE', 'name' => 'Johan van der Ende', 'user_id' => 'KVT\\JVDENDE', 'email' => 'jvdende@kvt.nl'];
$late = ['quantity' => 1, 'purchase_order_no' => 'IO1', 'purchase_outstanding_qty' => 1, 'receipt_state' => 'late', 'expected_receipt_date' => '2026-10-01', 'work_order_no' => 'WO1'];
$bundle = ['Koninklijke van Twist' => [
    'projects' => [
        ['no' => 'PRJ1', 'description' => 'Eigen', 'project_manager' => 'KVT\\RDBOER'],
        ['no' => 'PRJ2', 'description' => 'Ander', 'project_manager' => 'KVT\\XPM'],
    ],
    'lines' => [
        'PRJ1' => [['item_no' => 'EIGEN1', 'project_no' => 'PRJ1', 'description' => 'Eigen regel', 'purchasers' => [$rdb]] + $late],
        'PRJ2' => [
            ['item_no' => 'BEST1', 'project_no' => 'PRJ2', 'description' => 'Besteld', 'purchasers' => [$rdb, $jvde]] + $late,
            ['item_no' => 'VREEMD', 'project_no' => 'PRJ2', 'description' => 'Niet van mij', 'purchasers' => []] + $late,
        ],
    ],
]];
elpis_reset_fetch_memo();
$result = elpis_dispatch_pm_digests($bundle, '2026-10-20');
$mine = array_values(array_filter($sent, static fn ($m) => str_contains($m['plain'], 'rdboer@kvt.nl')));
if (count($mine) !== 1) {
    fail('pm + besteller krijgt precies één mail: ' . count($mine) . ' ' . json_encode($result));
}
$plain = $mine[0]['plain'];
if (substr_count($plain, '| EIGEN1 |') !== 1 || substr_count($plain, '| BEST1 |') !== 1 || str_contains($plain, 'VREEMD')) {
    fail('mail bevat eigen projectregel en bestelde regel, elk één keer: ' . $plain);
}
$other = array_values(array_filter($sent, static fn ($m) => str_contains($m['plain'], 'jvdende@kvt.nl')));
if (count($other) !== 1 || !str_contains($other[0]['plain'], 'BEST1') || str_contains($other[0]['plain'], 'EIGEN1')) {
    fail('besteller zonder projecten krijgt alleen zijn regels');
}

echo "OK\n";
