<?php
/**
 * PM-mail: reservering -> inkoopregel, voorraaddekking en uitsluitingen.
 * Run: php tests/pm_mail_lines_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-22';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-pmlines-' . getmypid();
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
        'job_task_no' => '100',
        'line_no' => 10000,
        'job_contract_entry_no' => 77,
        'item_no' => 'PK-33424107',
        'quantity' => 1,
        'qty_to_order' => 1,
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'expected_receipt_date' => '',
        'material_status' => 'I',
    ], $extra);
}

$demand = ['Entry_No' => 5, 'Positive' => false, 'Item_No' => 'PK-33424107', 'Quantity_Base' => -1,
    'Reservation_Status' => 'Reservation', 'Source_Type' => ELPI_JOB_PLANNING_SOURCE_TYPE,
    'Source_Subtype' => ELPI_JOB_PLANNING_SUBTYPE, 'Source_ID' => 'P', 'Source_Ref_No' => 77];
$purchaseSupply = ['Entry_No' => 5, 'Positive' => true, 'Item_No' => 'PK-33424107', 'Quantity_Base' => 1,
    'Reservation_Status' => 'Reservation', 'Source_Type' => ELPI_PURCHASE_LINE_SOURCE_TYPE,
    'Source_Subtype' => '1', 'Source_ID' => 'PO12602324', 'Source_Ref_No' => 20000];
$purchaseLine = ['Document_Type' => 'Order', 'Document_No' => 'PO12602324', 'Line_No' => 20000, 'Type' => 'Item',
    'No' => 'PK-33424107', 'Quantity' => 1, 'Outstanding_Quantity' => 1, 'Expected_Receipt_Date' => '2026-04-28'];

$out = elpis_apply_receipt_expectations(['P' => [line()]], [$demand, $purchaseSupply], [$purchaseLine], []);
$l = $out['P'][0];
if (($l['expected_receipt_date'] ?? '') !== '2026-04-28' || ($l['receipt_state'] ?? '') !== 'late') {
    fail('reservering op inkoopregel geeft PurchaseLines-datum en te laat: ' . json_encode($l));
}

$stockSupply = array_merge($purchaseSupply, ['Source_Type' => ELPI_ITEM_LEDGER_SOURCE_TYPE, 'Source_ID' => '', 'Source_Ref_No' => 0]);
$out = elpis_apply_receipt_expectations(['P' => [line()]], [$demand, $stockSupply], [], []);
if (($out['P'][0]['receipt_state'] ?? '') !== 'stock' || elpis_mail_line_matches_content($out['P'][0], 'both')) {
    fail('reservering uit voorraad is geen Datum ontbreekt: ' . json_encode($out['P'][0]));
}

$out = elpis_apply_receipt_expectations(['P' => [line()]], [], [], []);
if (($out['P'][0]['receipt_state'] ?? '') !== 'unconfirmed') {
    fail('zonder dekking blijft Datum ontbreekt');
}

if (!elpis_mail_line_excluded(line(['material_status' => 'V', 'inkoop_outstanding_qty' => 0.0]))) {
    fail('V met openstaand 0 hoort niet in de mail');
}
if (elpis_mail_line_excluded(line(['material_status' => 'V', 'inkoop_outstanding_qty' => 2.0]))) {
    fail('V met openstaand 2 blijft');
}
foreach (['M', 'G'] as $status) {
    if (!elpis_mail_line_excluded(line(['material_status' => $status]))) {
        fail("$status hoort niet in de mail");
    }
}
foreach (['Afgesloten', 'Gecontroleerd', 'Geannuleerd', 'Gefactureerd', ' afgesloten ', 'GECONTROLEERD',
    'Completed', 'Checked', 'Closed', 'Cancelled', 'Invoiced', ' closed ',
    'Uitgevoerd', ' uitgevoerd ', 'Executed', 'PERFORMED'] as $status) {
    if (!elpis_mail_line_excluded(line(['work_order_status' => $status]))) {
        fail("werkorder $status hoort niet in de mail");
    }
}
foreach (['Open', 'Gepland', 'Onderhanden', 'Ondertekend', 'Planned', ''] as $status) {
    if (elpis_mail_line_excluded(line(['work_order_status' => $status]))) {
        fail("werkorder $status blijft");
    }
}
if (!elpis_mail_line_excluded(line(['item_no' => 'uur']), ['UUR']) || elpis_mail_line_excluded(line(['item_no' => 'UUR']))) {
    fail('uitsluitlijst werkt alleen als hij gevuld is');
}
$n = elpis_refresh_receipt_state(line(['material_status' => 'N', 'expected_receipt_date' => '2026-01-01']));
if (elpis_line_is_late($n) || ($n['receipt_state'] ?? '') === 'late') {
    fail('N is nooit te laat');
}
if (elpis_material_status_label('M') !== 'Pick aangemaakt') {
    fail('M staat in de statuslijst');
}

// Ronde 3: openstaand zonder gekoppelde inkoopregel = LVS_Outstanding_Qty_Base (PRJ5260160 / WO2600161).
$v = line(['material_status' => 'V', 'work_order_no' => 'WO2600161', 'inkoop_outstanding_qty' => 0.0]);
if (elpis_mail_line_outstanding_qty($v) !== 0.0 || !elpis_mail_line_excluded($v)) {
    fail('V zonder inkoopregel met LVS openstaand 0 hoort niet in de mail');
}
if (!elpis_mail_line_excluded(line(['material_status' => 'V', 'LVS_Outstanding_Qty_Base' => '0']))) {
    fail('ruwe LVS_Outstanding_Qty_Base 0 telt ook');
}
if (elpis_mail_line_outstanding_qty(line(['inkoop_outstanding_qty' => 0.0, 'purchase_outstanding_qty' => 3.0])) !== 3.0) {
    fail('gekoppelde inkoopregel gaat voor LVS');
}
if (elpis_mail_line_excluded(line(['material_status' => 'V', 'inkoop_outstanding_qty' => 0.0, 'purchase_outstanding_qty' => 3.0]))) {
    fail('V met openstaande inkoopregel blijft');
}
if (elpis_mail_line_outstanding_qty(line()) !== null || elpis_mail_line_excluded(line(['material_status' => 'V']))) {
    fail('onbekend openstaand sluit niet uit');
}

// Ronde 3: meerdere reserveringen -> altijd de laatste PO-datum, ongeacht volgorde.
$mkSupply = static fn(string $po, int $ln) => array_merge($purchaseSupply, ['Source_ID' => $po, 'Source_Ref_No' => $ln]);
$mkPl = static fn(string $po, int $ln, string $d) => array_merge($purchaseLine, ['Document_No' => $po, 'Line_No' => $ln, 'Expected_Receipt_Date' => $d]);
$supplies = [$mkSupply('PO1', 10000), $mkSupply('PO2', 20000), $mkSupply('PO3', 30000)];
$pls = [$mkPl('PO1', 10000, '2026-11-05'), $mkPl('PO2', 20000, '2026-12-20'), $mkPl('PO3', 30000, '2026-11-30')];
foreach ([[0, 1, 2], [2, 1, 0], [1, 0, 2], [2, 0, 1]] as $order) {
    $res = [$demand];
    foreach ($order as $i) { $res[] = $supplies[$i]; }
    $plOrdered = array_reverse($pls);
    $out = elpis_apply_receipt_expectations(['P' => [line()]], $res, $order[0] === 0 ? $pls : $plOrdered, []);
    if (($out['P'][0]['expected_receipt_date'] ?? '') !== '2026-12-20') {
        fail('laatste PO-datum verwacht bij volgorde ' . implode(',', $order) . ': ' . json_encode($out['P'][0]['expected_receipt_date'] ?? null));
    }
}
// Gekoppelde inkoopregel met vroege datum + reservering met latere datum -> laatste.
$out = elpis_apply_receipt_expectations(
    ['P' => [line(['purchase_order_no' => 'PO1', 'purchase_order_line_no' => 10000, 'work_order_no' => 'WO1'])]],
    [$demand, $mkSupply('PO2', 20000)], $pls, []
);
if (($out['P'][0]['expected_receipt_date'] ?? '') !== '2026-12-20') {
    fail('PO-datum + latere reservering geeft laatste: ' . json_encode($out['P'][0]['expected_receipt_date'] ?? null));
}

// AppProjecten zonder Project_Manager-filter in de query; lege managers vallen client-side weg.
$projectsSeen = elpis_collect_projects_from_rows([
    ['No' => 'P1', 'Description' => 'A', 'Project_Manager' => 'KVT\\AVERHAGEN'],
    ['No' => 'P2', 'Description' => 'B', 'Project_Manager' => ''],
    ['No' => 'P3', 'Description' => 'C', 'Project_Manager' => 'fstal'],
]);
if (count($projectsSeen) !== 2 || $projectsSeen[0]['project_manager'] !== 'KVT\\AVERHAGEN' || $projectsSeen[1]['project_manager'] !== 'KVT\\FSTAL') {
    fail('projecten zonder manager vallen weg en KVT\\-prefix blijft: ' . json_encode($projectsSeen));
}
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entitySet, array $query): ?array {
    if ($entitySet === 'AppProjecten' && isset($query['$filter'])) {
        fail('AppProjecten-query hoort geen $filter te hebben');
    }
    return $entitySet === 'AppProjecten' ? [['No' => 'X1', 'Project_Manager' => 'KVT\\MHUBREGTSE'], ['No' => 'X2', 'Project_Manager' => '']] : null;
};
elpis_reset_fetch_memo();
if (elpis_fetch_project_managers('Hunter van Twist', 60) !== ['KVT\\MHUBREGTSE'] || count(elpis_fetch_projects_for_company('Hunter van Twist', 60)) !== 1) {
    fail('managers/projecten uit ongefilterde AppProjecten');
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);
elpis_reset_fetch_memo();

echo "OK\n";
