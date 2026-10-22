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
    'Completed', 'Checked', 'Closed', 'Cancelled', 'Invoiced', ' closed '] as $status) {
    if (!elpis_mail_line_excluded(line(['work_order_status' => $status]))) {
        fail("werkorder $status hoort niet in de mail");
    }
}
foreach (['Open', 'Gepland', 'Uitgevoerd', 'Onderhanden', 'Ondertekend', 'Planned', ''] as $status) {
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

echo "OK\n";
