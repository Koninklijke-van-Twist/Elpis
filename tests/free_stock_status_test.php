<?php
/**
 * Vrije voorraad (Mímir-fixture, punt L) en statuskleuren (punt N).
 * Run: php tests/free_stock_status_test.php
 */
$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-03';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-fs-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
require dirname(__DIR__) . '/web/elpis_data.php';

$failures = 0;
function check(bool $ok, string $msg): void { global $failures; if (!$ok) { $failures++; fwrite(STDERR, "FAIL: $msg\n"); } }

// --- L: fixture uit Mímir (2026-11-03) ---
$fx = json_decode((string) file_get_contents(__DIR__ . '/fixtures/free_stock_mimir.json'), true);
$ao = $fx['AO-2-70000-22-13'];
$i = elpis_build_free_stock_index($ao['ile'], $ao['res'], $ao['pick']);
$r = elpis_line_free_stock($i, 'AO-2-70000-22-13', 'KVT', 'ANDER');
check(abs($r['open_no_job'] - 363) < 0.001, 'AO open 363, kreeg ' . $r['open_no_job']);
check(abs($r['reserved_elsewhere'] - 352) < 0.001, 'AO gereserveerd 352, kreeg ' . $r['reserved_elsewhere']);
check(abs($r['free'] - 11) < 0.001, 'AO vrij 11, kreeg ' . $r['free']);
$pi = $fx['PI20000902'];
$i = elpis_build_free_stock_index($pi['ile'], $pi['res'], $pi['pick']);
$r = elpis_line_free_stock($i, 'PI20000902', '', 'ANDER');
check(abs($r['free']) < 0.001, 'PI20000902 vrij voor anderen 0, kreeg ' . $r['free']);

// --- N: statussen/kleuren ---
$base = ['quantity' => 10, 'kvt_qty_picked' => 0, 'qty_posted' => 0, 'planning_date' => '2026-12-01'];
$cases = [
    'PICKED' => ['ok', ['kvt_qty_picked' => 10]],
    'ORDERED' => ['ok', ['purchase_order_no' => 'IO1', 'purchase_outstanding_qty' => 10, 'expected_receipt_date' => '2026-11-20']],
    'DELIVERY_LATE' => ['alert', ['purchase_order_no' => 'IO1', 'purchase_outstanding_qty' => 10, 'expected_receipt_date' => '2026-12-10']],
    'NO_DATE' => ['orange', ['purchase_order_no' => 'IO1', 'purchase_outstanding_qty' => 10]],
    'TO_ORDER' => ['orange', []],
    'ORDER_TOO_LATE' => ['alert', ['lead_time_days' => 60]],
    'LATE' => ['alert', ['planning_date' => '2026-10-01']],
    'STOCK' => ['orange', ['free_stock' => 10]],
    'PARTIAL_STOCK' => ['orange', ['kvt_qty_picked' => 4, 'free_stock' => 10]],
    'PARTIAL_ORDERED' => ['ok', ['kvt_qty_picked' => 4, 'purchase_order_no' => 'IO1', 'purchase_outstanding_qty' => 6, 'expected_receipt_date' => '2026-11-20']],
    'RETURN' => ['muted', ['quantity' => -1]],
];
foreach ($cases as $want => [$tone, $extra]) {
    $s = elpis_line_status(array_merge($base, $extra), '2026-11-03');
    check($s['key'] === $want, "$want verwacht, kreeg {$s['key']}");
    check(ELPI_LINE_STATUSES[$want]['tone'] === $tone, "$want toon $tone");
    check(elpis_status_is_problem($want) === in_array($tone, ['orange', 'alert'], true), "$want probleemvlag = oranje/rood");
}
// Onbekende levertijd → nooit rood
check(elpis_line_status($base, '2026-11-03')['key'] === 'TO_ORDER', 'onbekende levertijd → TO_ORDER');
foreach (ELPI_LINE_STATUSES as $k => $d) {
    check(LOC($d['label']) !== $d['label'], "LOC-label $k");
}
if ($failures) { exit(1); }
echo "OK free_stock_status_test\n";
