<?php
/**
 * Levertijd (Ariadne): DateFormula-parsing, LVS_Critical_Lead_Time > AppItems.Lead_Time_Calculation,
 * LVS_Latest_Order_Date en de testregels uit project 153390 (zonder pick, zodat de inkoopregel telt).
 * Run: php tests/lead_time_test.php
 */
$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-06-01';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-lt-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
require dirname(__DIR__) . '/web/elpis_data.php';

$failures = 0;
function check(bool $ok, string $msg): void { global $failures; if (!$ok) { $failures++; fwrite(STDERR, "FAIL: $msg\n"); } }

$t = '2026-06-10';
foreach (['10D' => 10, '5D' => 5, '1W' => 7, '2W' => 14, '5W' => 35, '28D' => 28, '60D' => 60, '0D' => 0, '1M' => 30, 'CM' => 20, '<1W>' => 7, '1W+CM' => 20] as $f => $want) {
    check(elpis_date_formula_days($f, $t) === $want, "formule $f = $want, kreeg " . var_export(elpis_date_formula_days($f, $t), true));
}
foreach (['', '1' . "\x02", 'abc'] as $bad) {
    check(elpis_date_formula_days($bad, $t) === null, 'onleesbare formule → null');
}
check(elpis_line_lead_time_days(['critical_lead_time' => '7D', 'item_lead_time' => '2W'], $t) === 7, 'critical gaat voor item');
check(elpis_line_lead_time_days(['critical_lead_time' => '', 'item_lead_time' => '2W'], $t) === 14, 'fallback item');
check(elpis_line_lead_time_days(['critical_lead_time' => '', 'item_lead_time' => ''], $t) === null, 'onbekend → null');

$today = '2026-06-01';
// 153390 / 1643000 PI20002458: planning 20-06, PO 24-07 → Levering te laat
$s = elpis_line_status(['quantity' => 330, 'planning_date' => '2026-06-20', 'purchase_order_no' => 'PO12604384', 'purchase_outstanding_qty' => 330,
    'expected_receipt_date' => '2026-07-24', 'critical_lead_time' => '5D', 'latest_order_date' => '2026-06-15'], $today);
check($s['key'] === 'DELIVERY_LATE', '1643000 → DELIVERY_LATE, kreeg ' . $s['key']);
// 153390 / 1593000 PI20002065: planning 12-06, PO 12-06 → op tijd
$s = elpis_line_status(['quantity' => 1, 'planning_date' => '2026-06-12', 'purchase_order_no' => 'PO12603285', 'purchase_outstanding_qty' => 1,
    'expected_receipt_date' => '2026-06-12', 'critical_lead_time' => '7D', 'latest_order_date' => '2026-06-05'], $today);
check($s['key'] === 'ORDERED', '1593000 → ORDERED, kreeg ' . $s['key']);
// 4066164/10000 AO-11A1050S: geen PO, item-levertijd 1D, planning 31-12 → Te bestellen (op tijd)
$s = elpis_line_status(['quantity' => 8, 'planning_date' => '2026-12-31', 'item_lead_time' => '1D', 'latest_order_date' => '0001-01-01'], '2026-11-03');
check($s['key'] === 'TO_ORDER', 'AO-11A1050S → TO_ORDER, kreeg ' . $s['key']);
// Nog niet besteld: LVS_Latest_Order_Date verstreken → Te laat besteld (ook als levertijd het nog zou halen)
$s = elpis_line_status(['quantity' => 1, 'planning_date' => '2026-12-31', 'item_lead_time' => '1D', 'latest_order_date' => '2026-11-01'], '2026-11-03');
check($s['key'] === 'ORDER_TOO_LATE', 'laatste besteldatum voorbij → ORDER_TOO_LATE, kreeg ' . $s['key']);
// Zonder laatste besteldatum: vandaag + levertijd > planning
$s = elpis_line_status(['quantity' => 1, 'planning_date' => '2026-11-20', 'item_lead_time' => '5W'], '2026-11-03');
check($s['key'] === 'ORDER_TOO_LATE', 'levertijd haalt planning niet → ORDER_TOO_LATE, kreeg ' . $s['key']);

if ($failures) { exit(1); }
echo "OK lead_time_test\n";
