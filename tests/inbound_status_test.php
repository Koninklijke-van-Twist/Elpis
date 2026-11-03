<?php
/**
 * R: status 'In behandeling magazijn' (BinContent KVT/INBOUND) + verdeling per artikel.
 * Run: php tests/inbound_status_test.php
 */
$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-03';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-inb-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
require dirname(__DIR__) . '/web/elpis_data.php';

$failures = 0;
function check(bool $ok, string $msg): void { global $failures; if (!$ok) { $failures++; fwrite(STDERR, "FAIL: $msg\n"); } }

$keys = array_keys(ELPI_LINE_STATUSES);
check(array_search('INBOUND', $keys, true) < array_search('STOCK', $keys, true), 'INBOUND staat voor Op voorraad');
check(elpis_status_is_problem('INBOUND') && ELPI_LINE_STATUSES['INBOUND']['tone'] === 'orange', 'INBOUND is oranje probleemstatus');

// 159927 / 4078477 / 290000 PI20001812: Q 9, PO12602815/10000 volledig ontvangen, INBOUND 9.
$with = ['item_no' => '4078477', 'quantity' => 9, 'planning_date' => '2026-12-01', 'purchase_order_no' => 'PO12602815',
    'qty_received' => 9, 'completely_received' => true, 'purchase_outstanding_qty' => 0];
// 12602143 / 4085912 / 2823000 PI20000035: ontvangen, geen INBOUND.
$without = ['item_no' => '4085912', 'quantity' => 2, 'planning_date' => '2026-12-01', 'purchase_order_no' => 'PO1',
    'qty_received' => 2, 'completely_received' => true, 'purchase_outstanding_qty' => 0];
$out = elpis_apply_inbound_bins(['159927' => [$with], '12602143' => [$without]], ['4078477' => 9.0]);
check(elpis_line_status($out['159927'][0])['key'] === 'INBOUND', 'met INBOUND → In behandeling magazijn, kreeg ' . elpis_line_status($out['159927'][0])['key']);
check(elpis_line_status($out['12602143'][0])['key'] !== 'INBOUND', 'zonder INBOUND geen In behandeling magazijn');
check(elpis_line_is_problem($out['159927'][0]), 'INBOUND telt als probleemregel');

// Gepicked → nooit INBOUND.
$picked = $with + ['completely_picked' => true];
$p = elpis_apply_inbound_bins(['x' => [$picked]], ['4078477' => 9.0]);
check(elpis_line_status($p['x'][0])['key'] === 'PICKED', 'gepicked blijft gepicked');

// Niets ontvangen → geen toewijzing.
$open = ['item_no' => '4078477', 'quantity' => 9, 'purchase_order_no' => 'PO2', 'qty_received' => 0, 'purchase_outstanding_qty' => 9, 'expected_receipt_date' => '2026-11-20', 'planning_date' => '2026-12-01'];
$o = elpis_apply_inbound_bins(['x' => [$open]], ['4078477' => 9.0]);
check((float) $o['x'][0]['inbound_qty'] === 0.0, 'niet ontvangen → geen INBOUND');

// Per artikel verdelen: nooit meer dan het tekort per regel, pool raakt op.
$a = ['item_no' => 'A', 'quantity' => 5, 'purchase_order_no' => 'P', 'qty_received' => 5, 'completely_received' => true];
$d = elpis_apply_inbound_bins(['1' => [$a], '2' => [$a]], ['A' => 7.0]);
check((float) $d['1'][0]['inbound_qty'] === 5.0 && (float) $d['2'][0]['inbound_qty'] === 2.0, 'verdeling 5 + 2 van 7');

if ($failures > 0) { exit(1); }
echo "OK\n";
