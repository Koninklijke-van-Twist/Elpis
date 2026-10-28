<?php
/**
 * Verwachte ontvangst, voorraad en scoped OData.
 * Run: php tests/expected_receipt_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-receipt-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function filter_ors_cross_fields(string $filter): bool
{
    if (!preg_match_all(
        "/([A-Za-z_][A-Za-z0-9_]*)\\s+eq\\s+(?:'(?:[^']|'')*'|-?\\d+|true|false)\\s+or\\s+([A-Za-z_][A-Za-z0-9_]*)\\s+eq\\s+/",
        $filter,
        $matches,
        PREG_SET_ORDER
    )) {
        return false;
    }

    foreach ($matches as $match) {
        if ($match[1] !== $match[2]) {
            return true;
        }
    }

    return false;
}

function planningLine(array $overrides = []): array
{
    return array_merge([
        'job_task_no' => '200-000-050',
        'line_no' => 40000,
        'item_no' => 'PI20002946',
        'description' => 'Artikel',
        'quantity' => 1,
        'qty_to_order' => 0,
        'qty_ordered' => 1,
        'qty_open' => 1,
        'qty_received' => 0,
        'purchase_order_no' => 'PO12606033',
        'purchase_order_line_no' => 10000,
        'work_order_no' => 'WO2610118',
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'expected_receipt_date' => '',
        'planning_date' => '2026-10-06',
        'material_status' => 'I',
        'receipt_state' => '',
        'received_date' => '',
    ], $overrides);
}

if (elpis_reservation_status_filter() !== "Reservation_Status eq 'Reservation'") {
    fail('reserveringsfilter moet Reservation gebruiken');
}
if (str_contains(elpis_reservation_status_filter(), 'Reservering')) {
    fail('Reservering is geen geldige filteroptie');
}
if (ELPI_PURCHASE_ORDER_LINE_ENTITY !== 'PurchaseLines' || ELPI_ITEM_LIST_ENTITY !== 'AppItems') {
    fail('PurchaseOrderLines en ItemList bestaan niet');
}
if (ELPI_NIGHTLY_MAX_AGE !== 14400 || ELPI_CACHE_TTL !== 86400) {
    fail('nightly max_age is 4 uur, UI-cache 24 uur');
}

$select = elpis_werkorders_line_select_fields();
foreach (['KVT_Expected_Receipt_Date', 'Planning_Date', 'Planned_Delivery_Date', 'LVS_Work_Order_No', 'LVS_Purchase_Order_No', 'LVS_Purchase_Order_Line_No', 'KVT_Status_Material', 'KVT_Qty_Picked', 'KVT_Completely_Picked'] as $field) {
    if (!str_contains($select, $field)) {
        fail('WerkordersLines-select mist ' . $field);
    }
}
if (!str_contains(elpis_item_inventory_select_fields(), 'InventoryField') || str_contains(elpis_item_inventory_select_fields(), 'Inventory,')) {
    fail('voorraad komt van AppItems.InventoryField');
}
if (!str_contains(elpis_purchase_documents_filter(['PO12606033']), "Document_Type eq 'Order'") || !str_contains(elpis_purchase_documents_filter(['PO12606033']), "Document_No eq 'PO12606033'")) {
    fail('inkoopregels filteren op Document_Type Order en documentnummer');
}
if (str_contains(elpis_purchase_documents_filter(['PO1']), 'Outstanding_Quantity') || filter_ors_cross_fields(elpis_purchase_documents_filter(['PO1', 'PO2']))) {
    fail('inkoopfilter mag niet bedrijfswijd of over velden OR-en');
}
$jobPurchaseFilter = elpis_purchase_jobs_filter(['153390']);
if (!str_contains($jobPurchaseFilter, "Document_Type eq 'Order'") || !str_contains($jobPurchaseFilter, "Job_No eq '153390'") || str_contains($jobPurchaseFilter, 'Job_Task_No') || str_contains($jobPurchaseFilter, 'Job_Line_Type') || str_contains($jobPurchaseFilter, 'Outstanding_Quantity') || filter_ors_cross_fields($jobPurchaseFilter)) {
    fail('inkoopregels per project filteren niet op taak of regeltype: ' . $jobPurchaseFilter);
}

$stockFilter = elpis_item_stock_reservation_filter(['PI20002946']);
foreach (["Reservation_Status eq 'Reservation'", 'Positive eq false', 'Source_Type eq 1003', 'Source_Type eq 901', 'Source_Type eq 37', "Item_No eq 'PI20002946'"] as $part) {
    if (!str_contains($stockFilter, $part)) {
        fail('voorraadreservering mist ' . $part . ': ' . $stockFilter);
    }
}
if (str_contains($stockFilter, 'Positive eq true') || str_contains($stockFilter, 'Source_Type eq 32') || filter_ors_cross_fields($stockFilter)) {
    fail('voorraadreservering heeft het oude aanbodfilter: ' . $stockFilter);
}

$GLOBALS['ELPIS_STOCK_RESERVATION_SOURCE_TYPES'] = [39, 32];
$extended = elpis_item_stock_reservation_filter(['A']);
if (!str_contains($extended, 'Source_Type eq 1003') || !str_contains($extended, 'Source_Type eq 901') || !str_contains($extended, 'Source_Type eq 37') || !str_contains($extended, 'Source_Type eq 39') || str_contains($extended, 'Source_Type eq 32') || filter_ors_cross_fields($extended)) {
    fail('extra brontype blijft op Source_Type, 32 blijft weg: ' . $extended);
}
unset($GLOBALS['ELPIS_STOCK_RESERVATION_SOURCE_TYPES']);

$ledger = elpis_item_ledger_filter(['PRJ2606522']);
if (!str_contains($ledger, "Entry_Type eq 'Purchase'") || !str_contains($ledger, "Document_Type eq 'Purchase Receipt'") || !str_contains($ledger, "Job_No eq 'PRJ2606522'") || filter_ors_cross_fields($ledger)) {
    fail('ontvangstboekingen zijn niet op het project beperkt: ' . $ledger);
}
if (!str_contains(elpis_item_ledger_select_fields(), 'Quantity')) {
    fail('ontvangstposten moeten Quantity ophalen');
}
$purchaseSelect = array_values(array_filter(array_map('trim', explode(',', elpis_purchase_line_select_fields()))));
$purchaseSelectAllowed = ['Document_Type', 'Document_No', 'Line_No', 'Buy_from_Vendor_No', 'Type', 'No', 'Description', 'Location_Code', 'Quantity', 'Job_No', 'Job_Task_No', 'Job_Line_Type', 'Expected_Receipt_Date', 'LVS_Ex_Factory_Date', 'LVS_Date_on_Board', 'Outstanding_Quantity', 'Order_No', 'Unit_of_Measure_Code'];
foreach ($purchaseSelect as $purchaseField) {
    if (!in_array($purchaseField, $purchaseSelectAllowed, true)) {
        fail('PurchaseLines-select bevat een niet bewezen veld: ' . $purchaseField);
    }
}
foreach (['Document_No', 'Line_No', 'Type', 'No', 'Job_No', 'Job_Task_No', 'Job_Line_Type', 'Quantity', 'Outstanding_Quantity', 'Expected_Receipt_Date'] as $purchaseField) {
    if (!in_array($purchaseField, $purchaseSelect, true)) {
        fail('PurchaseLines-select mist ' . $purchaseField);
    }
}
foreach (['Promised_Receipt_Date', 'Planned_Receipt_Date', 'Quantity_Received', 'LVS_Completely_Received'] as $purchaseField) {
    if (in_array($purchaseField, $purchaseSelect, true) || str_contains(elpis_purchase_line_select_fields(), $purchaseField)) {
        fail('PurchaseLines-select mag ' . $purchaseField . ' niet opvragen');
    }
}
$inkoopSelect = array_values(array_filter(array_map('trim', explode(',', elpis_inkoop_planning_select_fields()))));
foreach (['LVS_Completely_Received', 'LVS_Outstanding_Qty_Base', 'LVS_Purchase_Order_No', 'LVS_Purchase_Order_Line_No', 'KVT_Expected_Receipt_Date', 'Planned_Delivery_Date', 'Promised_Delivery_Date', 'Requested_Delivery_Date'] as $inkoopField) {
    if (!in_array($inkoopField, $inkoopSelect, true)) {
        fail('inkoopselect mist ' . $inkoopField);
    }
}
foreach (['KVT_Qty_Picked', 'KVT_Completely_Picked', 'LVS_Quantity_Order_UoM'] as $inkoopField) {
    if (in_array($inkoopField, $inkoopSelect, true)) {
        fail('inkoopselect mag ' . $inkoopField . ' niet opvragen');
    }
}
$reservationSelect = array_values(array_filter(array_map('trim', explode(',', elpis_reservation_select_fields()))));
if (!in_array('Quantity_Base', $reservationSelect, true) || in_array('Quantity', $reservationSelect, true)) {
    fail('reserveringen moeten Quantity_Base gebruiken: ' . elpis_reservation_select_fields());
}
$workOrderSelect = array_values(array_filter(array_map('trim', explode(',', elpis_work_order_select_fields()))));
if (!in_array('Task_Description', $workOrderSelect, true) || in_array('Description', $workOrderSelect, true)) {
    fail('werkorders moeten Task_Description gebruiken: ' . elpis_work_order_select_fields());
}
$ignoredPurchaseFlag = elpis_normalize_purchase_order_line([
    'Document_No' => 'PO12609901',
    'Line_No' => 10000,
    'No' => 'PI24003010',
    'Quantity' => 2,
    'Outstanding_Quantity' => 2,
    'LVS_Completely_Received' => true,
]);
if (array_key_exists('completely_received', $ignoredPurchaseFlag)) {
    fail('PurchaseLines.LVS_Completely_Received mag de regel niet sluiten');
}

$jobFilters = elpis_job_scoped_filters(array_map(static fn (int $i): string => 'P' . $i, range(1, 13)));
if (count($jobFilters) !== 2 || str_contains($jobFilters[0], 'Type eq') || filter_ors_cross_fields($jobFilters[0])) {
    fail('planningsregels blijven in chunks van 12 op Job_No, zonder taalafhankelijk Type-filter');
}

// Taalonafhankelijke optiewaarden (BC-gebruiker NL of EN).
foreach (['Artikel', 'Item', 'item', 'ITEM', '1'] as $type) {
    if (!elpis_row_is_item_type(['Type' => $type])) {
        fail('Type ' . $type . ' moet als artikelregel tellen');
    }
}
foreach (['Resource', 'Resource', 'Middel', 'G/L Account', 'Grootboekrekening', 'Text'] as $type) {
    if (elpis_row_is_item_type(['Type' => $type])) {
        fail('Type ' . $type . ' is geen artikelregel');
    }
}
$lines = [];
elpis_collect_planning_line_row(['Job_No' => 'P1', 'Type' => 'Resource', 'No' => 'R1', 'Description' => 'Uren'], $lines);
if ($lines !== []) {
    fail('resource-regels vallen client-side weg');
}
foreach (['Reservation', 'Reservering', 'reservation', 'Reservierung'] as $status) {
    if (!elpis_reservation_status_matches($status)) {
        fail('reserveringsstatus ' . $status . ' moet matchen');
    }
}
if (elpis_reservation_status_matches('Surplus')) {
    fail('Surplus is geen reservering');
}
foreach (['Uitgevoerd', 'COMPLETED', 'Completed', '05 Completed', 'Closed', 'Afgesloten', 'Abgeschlossen'] as $status) {
    if (!elpis_line_work_order_closed(['work_order_status' => $status])) {
        fail('werkorderstatus ' . $status . ' moet gesloten zijn');
    }
}
foreach (['02 IN PROGRESS', 'In Progress', 'Onderhanden', 'Open', ''] as $status) {
    if (elpis_line_work_order_closed(['work_order_status' => $status])) {
        fail('werkorderstatus ' . $status . ' is niet gesloten');
    }
}
if (!elpis_option_matches('02 IN PROGRESS', ['In Progress']) || !elpis_option_matches('in_progress', ['IN PROGRESS'])) {
    fail('nummer-prefix en underscores horen genegeerd te worden');
}

$normalized = elpis_normalize_planning_line_row([
    'Job_No' => 'PRJ2606522',
    'Job_Task_No' => '200-000-050',
    'Line_No' => 40000,
    'No' => 'PI20002946',
    'LVS_Work_Order_No' => 'WO2610118',
    'LVS_Purchase_Order_No' => 'PO12606033',
    'LVS_Purchase_Order_Line_No' => 10000,
    'Planning_Date' => '0001-01-01',
    'Planned_Delivery_Date' => '2026-10-06',
    'KVT_Expected_Receipt_Date' => '0001-01-01',
    'Quantity' => 2,
]);
if (($normalized['work_order_no'] ?? '') !== 'WO2610118' || ($normalized['job_task_no'] ?? '') === 'WO2610118') {
    fail('werkorder is LVS_Work_Order_No, niet de taak: ' . json_encode($normalized));
}
if (($normalized['planning_date'] ?? '') !== '2026-10-06' || ($normalized['planning_date_field'] ?? '') !== 'Planned_Delivery_Date') {
    fail('lege Planning_Date valt terug op Planned_Delivery_Date');
}
if (($normalized['expected_receipt_date'] ?? '') !== '' || (int) ($normalized['purchase_order_line_no'] ?? 0) !== 10000) {
    fail('0001-01-01 is leeg en het inkoopregelnummer blijft behouden');
}

$withPlanning = elpis_normalize_planning_line_row([
    'No' => 'PI20002946',
    'Planning_Date' => '2026-11-01',
    'Planned_Delivery_Date' => '2026-11-02',
]);
if (($withPlanning['planning_date'] ?? '') !== '2026-11-01' || ($withPlanning['planning_date_field'] ?? '') !== 'Planning_Date') {
    fail('Planning_Date gaat voor op Planned_Delivery_Date');
}

$purchase = [[
    'document_no' => 'PO12606033',
    'line_no' => 10000,
    'item_no' => 'PI20002946',
    'expected_receipt_date' => '2026-10-08',
    'promised_receipt_date' => '2026-10-01',
    'planned_receipt_date' => '2026-09-01',
    'completely_received' => false,
]];
$applied = elpis_apply_receipt_expectations([
    'PRJ2606522' => [planningLine()],
], [], $purchase, ['PI20002946' => 12]);
$pi = $applied['PRJ2606522'][0];
if (($pi['expected_receipt_date'] ?? '') !== '2026-10-08' || ($pi['receipt_state'] ?? '') !== 'late') {
    fail('PI20002946 moet 2026-10-08 van PurchaseLines halen en op 2026-10-20 te laat zijn: ' . json_encode($pi));
}
if (!str_starts_with(elpis_line_expected_receipt_display($pi), 'Te laat')) {
    fail('verstreken datum toont Te laat');
}
if ((elpis_line_tooltips($pi)['expected_receipt'] ?? '') !== '(PurchaseLines -> Expected_Receipt_Date)') {
    fail('tooltip noemt de PurchaseLines-bron: ' . json_encode(elpis_line_tooltips($pi)));
}
if ((elpis_line_tooltips($pi)['workorder'] ?? '') !== '(AppWerkorders -> No)' || !str_contains((string) (elpis_line_tooltips($pi)['stock'] ?? ''), 'InventoryField') || !str_contains((string) (elpis_line_tooltips($pi)['stock'] ?? ''), 'Quantity_Base')) {
    fail('tooltips noemen AppWerkorders, InventoryField en Quantity_Base');
}
if (abs((float) ($pi['free_stock'] ?? -1) - 12) > 0.00001) {
    fail('zonder reservering is vrije voorraad de InventoryField');
}

$GLOBALS['ELPIS_TODAY'] = '2026-10-07';
$onTime = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine()]], [], $purchase, []);
if (($onTime['PRJ2606522'][0]['receipt_state'] ?? '') !== 'date' || !str_ends_with(elpis_line_expected_receipt_display($onTime['PRJ2606522'][0]), '08-10-2026')) {
    fail('vóór de datum blijft de verwachte ontvangstdatum zichtbaar');
}
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$staleOnTime = $onTime['PRJ2606522'][0];
if (!str_starts_with(elpis_line_expected_receipt_display($staleOnTime), 'Te laat') || elpis_line_row_tone($staleOnTime) !== 'alert') {
    fail('een gecachte regel met verstreken datum wordt alsnog te laat');
}

$promisedOnly = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine()]], [], [[
    'document_no' => 'PO12606033',
    'line_no' => 10000,
    'expected_receipt_date' => '',
    'promised_receipt_date' => '2026-10-21',
    'planned_receipt_date' => '2026-10-01',
    'quantity' => 4,
    'outstanding_qty' => 1,
    'completely_received' => false,
]], []);
if (($promisedOnly['PRJ2606522'][0]['expected_receipt_date'] ?? 'x') !== '' || str_contains((string) ($promisedOnly['PRJ2606522'][0]['expected_receipt_source'] ?? ''), 'Promised_Receipt_Date') || str_contains((string) ($promisedOnly['PRJ2606522'][0]['expected_receipt_source'] ?? ''), 'Planned_Receipt_Date')) {
    fail('Promised_Receipt_Date en Planned_Receipt_Date bestaan niet op PurchaseLines: ' . json_encode($promisedOnly['PRJ2606522'][0]));
}
if (abs((float) ($promisedOnly['PRJ2606522'][0]['qty_received'] ?? -1) - 3) > 0.00001 || abs((float) ($promisedOnly['PRJ2606522'][0]['qty_open'] ?? -1) - 1) > 0.00001 || abs((float) ($promisedOnly['PRJ2606522'][0]['qty_ordered'] ?? -1) - 4) > 0.00001) {
    fail('ontvangen is Quantity min Outstanding_Quantity: ' . json_encode($promisedOnly['PRJ2606522'][0]));
}

$kvtWins = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine(['expected_receipt_date' => '2026-12-01'])]], [], $purchase, []);
if (($kvtWins['PRJ2606522'][0]['expected_receipt_date'] ?? '') !== '2026-12-01') {
    fail('KVT_Expected_Receipt_Date gaat voor op de inkoopregel');
}

$received = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine([
    'completely_received' => true,
])]], [], [[
    'document_no' => 'PO12606033',
    'line_no' => 10000,
    'expected_receipt_date' => '2026-10-08',
    'LVS_Completely_Received' => true,
    'Outstanding_Quantity' => 2,
    'Quantity' => 2,
]], [], [
    'receipts' => [[
        'Job_No' => 'PRJ2606522',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20002946',
        'Posting_Date' => '2026-10-09',
    ]],
]);
$receivedLine = $received['PRJ2606522'][0];
if (($receivedLine['receipt_state'] ?? '') !== 'received' || elpis_line_expected_receipt_display($receivedLine) !== 'Reeds ontvangen (09-10-2026)') {
    fail('volledig ontvangen toont de boekingsdatum: ' . elpis_line_expected_receipt_display($receivedLine));
}
if (elpis_line_row_tone($receivedLine) === 'alert') {
    fail('een ontvangen regel is niet rood');
}

$siblings = elpis_apply_receipt_expectations([
    'PRJ2606522' => [
        planningLine(['remaining_qty' => 1, 'expected_receipt_date' => '2026-10-01']),
        planningLine([
            'line_no' => 50000,
            'purchase_order_line_no' => 20000,
            'remaining_qty' => 1,
            'expected_receipt_date' => '2026-10-01',
        ]),
    ],
], [], [], [], [
    'receipts' => [[
        'Job_No' => 'PRJ2606522',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20002946',
        'Posting_Date' => '2026-10-09',
    ]],
]);
foreach ($siblings['PRJ2606522'] as $sibling) {
    if (($sibling['receipt_state'] ?? '') === 'received') {
        fail('een inkoopontvangst sluit niet elke regel met hetzelfde artikel: ' . json_encode($sibling));
    }
}

$closed = elpis_apply_receipt_expectations(['P' => [planningLine([
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'expected_receipt_date' => '2026-10-01',
    'remaining_qty' => 0,
    'material_status' => 'I',
])]], [], [], []);
$closedLine = $closed['P'][0];
if (($closedLine['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($closedLine) || elpis_mail_line_matches_content($closedLine, 'both')) {
    fail('Remaining_Qty 0 is afgesloten en hoort niet in de mail: ' . json_encode($closedLine));
}

$kvtCompletely = elpis_apply_receipt_expectations(['P' => [planningLine([
    'expected_receipt_date' => '2026-10-01',
    'item_no' => 'PI20001186',
    'purchase_order_no' => 'PO12601846',
    'purchase_order_line_no' => 10000,
    'work_order_no' => '4066161',
    'completely_received' => true,
])]], [], [[
    'document_no' => 'PO12601846',
    'line_no' => 10000,
    'item_no' => 'PI20001186',
    'expected_receipt_date' => '2026-09-01',
    'LVS_Completely_Received' => true,
    'Outstanding_Quantity' => 2,
    'Quantity' => 2,
]], []);
$kvtCompletelyLine = $kvtCompletely['P'][0];
if (($kvtCompletelyLine['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($kvtCompletelyLine) || str_starts_with(elpis_line_expected_receipt_display($kvtCompletelyLine), 'Te laat') || elpis_mail_line_matches_content($kvtCompletelyLine, 'both') || empty($kvtCompletelyLine['completely_received'])) {
    fail('LVS_Completely_Received van de planningsregel telt ook met een KVT-datum: ' . json_encode($kvtCompletelyLine));
}
if ((elpis_line_tooltips($kvtCompletelyLine)['expected_receipt'] ?? '') !== '(AppProjectInkoopPlanningsRegel -> LVS_Completely_Received)') {
    fail('tooltip noemt LVS_Completely_Received van de planningsregel: ' . json_encode(elpis_line_tooltips($kvtCompletelyLine)));
}
if (($kvtCompletelyLine['expected_receipt_date'] ?? '') !== '2026-10-01') {
    fail('de KVT-datum blijft de verwachte datum');
}

$fullyReceived = [
    ['item_no' => 'PI20001186', 'purchase_order_no' => 'PO12601846', 'purchase_order_line_no' => 10000, 'work_order_no' => '4066161', 'quantity' => 2],
    ['item_no' => 'PI20001189', 'purchase_order_no' => 'PO12601846', 'purchase_order_line_no' => 20000, 'work_order_no' => '4066161', 'quantity' => 1],
    ['item_no' => 'PI20001190', 'purchase_order_no' => 'PO12601846', 'purchase_order_line_no' => 30000, 'work_order_no' => '4066161', 'quantity' => 4],
    ['item_no' => 'PI20001191', 'purchase_order_no' => 'PO12601846', 'purchase_order_line_no' => 40000, 'work_order_no' => '4066161', 'quantity' => 1],
    ['item_no' => 'PI20000169', 'purchase_order_no' => 'PO12600251', 'purchase_order_line_no' => 10000, 'work_order_no' => '153390', 'quantity' => 3],
    ['item_no' => 'PI20000111', 'purchase_order_no' => 'PO12600562', 'purchase_order_line_no' => 10000, 'work_order_no' => '153390', 'quantity' => 1],
    ['item_no' => 'PI20000124', 'purchase_order_no' => 'PO12600306', 'purchase_order_line_no' => 10000, 'work_order_no' => '160224', 'quantity' => 6],
    ['item_no' => 'PI20000135', 'purchase_order_no' => 'PO12600565', 'purchase_order_line_no' => 10000, 'work_order_no' => '160224', 'quantity' => 2],
    ['item_no' => 'PI20002516', 'purchase_order_no' => 'PO12604455', 'purchase_order_line_no' => 10000, 'work_order_no' => '160224', 'quantity' => 1],
];
$stillOpen = [
    ['item_no' => 'PI24003010', 'purchase_order_no' => 'PO12609901', 'purchase_order_line_no' => 10000, 'work_order_no' => '153390', 'quantity' => 2, 'outstanding_qty' => 2],
    ['item_no' => 'PI28127010', 'purchase_order_no' => 'PO12609902', 'purchase_order_line_no' => 10000, 'work_order_no' => '160224', 'quantity' => 1, 'outstanding_qty' => 1],
    ['item_no' => 'PI27751010', 'purchase_order_no' => 'PO12609903', 'purchase_order_line_no' => 10000, 'work_order_no' => '160224', 'quantity' => 5, 'outstanding_qty' => 0.5],
];
$sampleLines = [];
$samplePurchase = [];
foreach ($fullyReceived as $spec) {
    $sampleLines[] = planningLine([
        'item_no' => $spec['item_no'],
        'purchase_order_no' => $spec['purchase_order_no'],
        'purchase_order_line_no' => $spec['purchase_order_line_no'],
        'work_order_no' => $spec['work_order_no'],
        'quantity' => $spec['quantity'],
        'qty_ordered' => $spec['quantity'],
        'qty_open' => $spec['quantity'],
        'qty_received' => 0,
        'expected_receipt_date' => '2026-10-01',
        'material_status' => 'I',
    ]);
    $samplePurchase[] = [
        'document_no' => $spec['purchase_order_no'],
        'line_no' => $spec['purchase_order_line_no'],
        'item_no' => $spec['item_no'],
        'completely_received' => false,
        'outstanding_qty' => 0,
    ];
}
foreach ($stillOpen as $spec) {
    $sampleLines[] = planningLine([
        'item_no' => $spec['item_no'],
        'purchase_order_no' => $spec['purchase_order_no'],
        'purchase_order_line_no' => $spec['purchase_order_line_no'],
        'work_order_no' => $spec['work_order_no'],
        'quantity' => $spec['quantity'],
        'qty_ordered' => $spec['quantity'],
        'qty_open' => $spec['outstanding_qty'],
        'qty_received' => $spec['quantity'] - $spec['outstanding_qty'],
        'expected_receipt_date' => '2026-10-01',
        'material_status' => 'I',
    ]);
    $samplePurchase[] = [
        'document_no' => $spec['purchase_order_no'],
        'line_no' => $spec['purchase_order_line_no'],
        'item_no' => $spec['item_no'],
        'completely_received' => false,
        'outstanding_qty' => $spec['outstanding_qty'],
    ];
}
$sample = elpis_apply_receipt_expectations(['WO153390' => $sampleLines], [], $samplePurchase, []);
foreach ($sample['WO153390'] as $sampleLine) {
    $itemNo = (string) ($sampleLine['item_no'] ?? '');
    $open = in_array($itemNo, ['PI24003010', 'PI28127010', 'PI27751010'], true);
    if ($open) {
        if (($sampleLine['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($sampleLine), 'Te laat') || !elpis_mail_line_matches_content($sampleLine, 'both')) {
            fail($itemNo . ' hoort te laat te blijven: ' . json_encode($sampleLine));
        }
        continue;
    }
    if (($sampleLine['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($sampleLine) || str_starts_with(elpis_line_expected_receipt_display($sampleLine), 'Te laat') || elpis_mail_line_matches_content($sampleLine, 'late') || elpis_mail_line_matches_content($sampleLine, 'both')) {
        fail($itemNo . ' is volledig ontvangen (Outstanding_Quantity 0) en hoort niet te laat of in de mail: ' . json_encode($sampleLine));
    }
    if ((elpis_line_tooltips($sampleLine)['expected_receipt'] ?? '') !== '(PurchaseLines -> Outstanding_Quantity)') {
        fail($itemNo . ' tooltip moet Outstanding_Quantity noemen: ' . json_encode(elpis_line_tooltips($sampleLine)));
    }
    if (($sampleLine['material_status'] ?? '') !== 'I') {
        fail('materiaalstatus blijft onaangeroerd');
    }
}

$linkedByItem = elpis_apply_receipt_expectations(['P' => [planningLine([
    'item_no' => 'PI20000169',
    'purchase_order_no' => 'PO12600251',
    'purchase_order_line_no' => 0,
    'work_order_no' => '153390',
    'expected_receipt_date' => '2026-09-15',
])]], [], [[
    'document_no' => 'PO12600251',
    'line_no' => 10000,
    'item_no' => 'PI20000169',
    'completely_received' => false,
    'outstanding_qty' => 0,
]], []);
if (($linkedByItem['P'][0]['receipt_state'] ?? '') !== 'received') {
    fail('zonder regelnummer koppelt een uniek artikel op de inkooporder: ' . json_encode($linkedByItem['P'][0]));
}

$misnumbered = elpis_apply_receipt_expectations(['P' => [planningLine([
    'item_no' => 'PI20002516',
    'purchase_order_no' => 'PO12604455',
    'purchase_order_line_no' => 10000,
    'work_order_no' => '160224',
    'expected_receipt_date' => '2026-09-15',
])]], [], [
    [
        'document_no' => 'PO12604455',
        'line_no' => 10000,
        'item_no' => 'PI-ANDER',
        'completely_received' => false,
        'outstanding_qty' => 8,
    ],
    [
        'document_no' => 'PO12604455',
        'line_no' => 20000,
        'item_no' => 'PI20002516',
        'completely_received' => false,
        'outstanding_qty' => 0,
    ],
], []);
if (($misnumbered['P'][0]['receipt_state'] ?? '') !== 'received') {
    fail('een regelnummer van een ander artikel valt terug op het unieke artikel: ' . json_encode($misnumbered['P'][0]));
}

$ambiguous = elpis_apply_receipt_expectations(['P' => [planningLine([
    'item_no' => 'PI20000111',
    'purchase_order_no' => 'PO12600562',
    'purchase_order_line_no' => 0,
    'expected_receipt_date' => '2026-10-01',
])]], [], [
    [
        'document_no' => 'PO12600562',
        'line_no' => 10000,
        'item_no' => 'PI20000111',
        'completely_received' => false,
        'outstanding_qty' => 0,
    ],
    [
        'document_no' => 'PO12600562',
        'line_no' => 20000,
        'item_no' => 'PI20000111',
        'completely_received' => false,
        'outstanding_qty' => 2,
    ],
], []);
if (($ambiguous['P'][0]['receipt_state'] ?? '') === 'received') {
    fail('twee inkoopregels voor hetzelfde artikel zonder regelnummer sluiten de regel niet');
}

$statusT = elpis_apply_receipt_expectations(['P' => [planningLine([
    'item_no' => 'PI20001190',
    'purchase_order_no' => 'PO12601846',
    'purchase_order_line_no' => 30000,
    'expected_receipt_date' => '2026-10-01',
    'material_status' => 'T',
])]], [], [[
    'document_no' => 'PO12601846',
    'line_no' => 30000,
    'item_no' => 'PI20001190',
    'completely_received' => false,
    'outstanding_qty' => 0,
]], []);
$statusTLine = $statusT['P'][0];
if (($statusTLine['material_status'] ?? '') !== 'T' || elpis_line_filter_status($statusTLine) !== 'T' || elpis_line_row_tone($statusTLine) !== 'alert' || str_starts_with(elpis_line_expected_receipt_display($statusTLine), 'Te laat')) {
    fail('materiaalstatus T blijft T; de ontvangstkolom is niet Te laat: ' . json_encode($statusTLine));
}

$withoutWoLines = [
    '153390' => [
        planningLine([
            'job_task_no' => '100',
            'line_no' => 40000,
            'item_no' => 'PI20001429',
            'description' => 'Spoedlevering',
            'work_order_no' => '',
            'purchase_order_no' => '',
            'purchase_order_line_no' => 0,
            'quantity' => 1,
            'qty_ordered' => 0,
            'qty_open' => 1,
            'qty_received' => 0,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'O',
            'remaining_qty' => 1,
        ]),
        planningLine([
            'job_task_no' => '100',
            'line_no' => 50000,
            'item_no' => 'PI20001429',
            'description' => 'Spoedlevering',
            'work_order_no' => '4066161',
            'purchase_order_no' => 'PO12609999',
            'purchase_order_line_no' => 10000,
            'quantity' => 1,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'G',
            'remaining_qty' => 1,
        ]),
        planningLine([
            'job_task_no' => '200',
            'line_no' => 60000,
            'item_no' => 'PI20000438',
            'description' => 'M24 bouten/moeren/sluitring',
            'work_order_no' => '',
            'purchase_order_no' => '',
            'purchase_order_line_no' => 0,
            'quantity' => 1,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'O',
            'remaining_qty' => 1,
        ]),
        planningLine([
            'job_task_no' => '300',
            'line_no' => 70000,
            'item_no' => 'PI20009901',
            'description' => 'Nog open',
            'work_order_no' => '',
            'purchase_order_no' => '',
            'purchase_order_line_no' => 0,
            'quantity' => 1,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'O',
            'remaining_qty' => 1,
        ]),
        planningLine([
            'job_task_no' => '400',
            'line_no' => 80000,
            'item_no' => 'PI20009902',
            'description' => 'Status T',
            'work_order_no' => '',
            'purchase_order_no' => 'PO12600679',
            'purchase_order_line_no' => 10000,
            'quantity' => 1,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'T',
            'remaining_qty' => 1,
        ]),
    ],
];
$withoutWoInkoop = [
    [
        'Job_No' => '153390',
        'Job_Task_No' => '900',
        'Line_No' => 10000,
        'No' => 'PI20001429',
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12602236',
        'LVS_Purchase_Order_Line_No' => 20000,
        'LVS_Quantity_Order_UoM' => 1,
        'LVS_Outstanding_Qty_Base' => 0,
        'LVS_Completely_Received' => false,
    ],
    [
        'Job_No' => '153390',
        'Job_Task_No' => '901',
        'Line_No' => 10000,
        'No' => 'PI20000438',
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12600678',
        'LVS_Purchase_Order_Line_No' => 10000,
        'LVS_Quantity_Order_UoM' => 1,
        'LVS_Outstanding_Qty_Base' => 0,
        'LVS_Completely_Received' => false,
    ],
    [
        'Job_No' => '153390',
        'Job_Task_No' => '902',
        'Line_No' => 10000,
        'No' => 'PI20009901',
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12609904',
        'LVS_Purchase_Order_Line_No' => 10000,
        'LVS_Quantity_Order_UoM' => 1,
        'LVS_Outstanding_Qty_Base' => 1,
        'LVS_Completely_Received' => false,
    ],
];
$withoutWoPurchase = [
    ['document_no' => 'PO12602236', 'line_no' => 10000, 'item_no' => 'PI-ANDER', 'outstanding_qty' => 4, 'completely_received' => false],
    ['document_no' => 'PO12602236', 'line_no' => 20000, 'item_no' => 'PI20001429', 'outstanding_qty' => 0, 'completely_received' => false],
    ['document_no' => 'PO12600678', 'line_no' => 10000, 'item_no' => 'PI20000438', 'outstanding_qty' => 0, 'completely_received' => false],
    ['document_no' => 'PO12609904', 'line_no' => 10000, 'item_no' => 'PI20009901', 'outstanding_qty' => 1, 'completely_received' => false],
    ['document_no' => 'PO12600679', 'line_no' => 10000, 'item_no' => 'PI20009902', 'outstanding_qty' => 0, 'completely_received' => false],
    ['document_no' => 'PO12609999', 'line_no' => 10000, 'item_no' => 'PI20001429', 'outstanding_qty' => 1, 'completely_received' => false],
];
$withoutWo = elpis_apply_receipt_expectations(
    elpis_attach_inkoop_planning_rows($withoutWoLines, $withoutWoInkoop),
    [],
    $withoutWoPurchase,
    []
);
$spoed = $withoutWo['153390'][0];
$picked = $withoutWo['153390'][1];
$bolts = $withoutWo['153390'][2];
$partialNoWo = $withoutWo['153390'][3];
$statusTNoWo = $withoutWo['153390'][4];
if (($spoed['work_order_no'] ?? '') !== '' || ($spoed['purchase_order_no'] ?? '') !== 'PO12602236' || (int) ($spoed['purchase_order_line_no'] ?? 0) !== 20000) {
    fail('regel zonder werkorder koppelt op document en regelnummer: ' . json_encode($spoed));
}
if (($spoed['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($spoed) || str_starts_with(elpis_line_expected_receipt_display($spoed), 'Te laat') || elpis_line_row_tone($spoed) === 'alert' || ($spoed['material_status'] ?? '') !== 'O' || elpis_mail_line_matches_content($spoed, 'both')) {
    fail('PI20001429 zonder werkorder is volledig ontvangen en niet te laat: ' . json_encode($spoed));
}
if ((elpis_line_tooltips($spoed)['expected_receipt'] ?? '') !== '(PurchaseLines -> Outstanding_Quantity)') {
    fail('tooltip van een regel zonder werkorder noemt Outstanding_Quantity: ' . json_encode(elpis_line_tooltips($spoed)));
}
if (($bolts['work_order_no'] ?? '') !== '' || ($bolts['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($bolts) || ($bolts['purchase_order_no'] ?? '') !== 'PO12600678' || (int) ($bolts['purchase_order_line_no'] ?? 0) !== 10000 || elpis_mail_line_matches_content($bolts, 'late')) {
    fail('PI20000438 zonder werkorder is volledig ontvangen: ' . json_encode($bolts));
}
if (($picked['work_order_no'] ?? '') !== '4066161' || ($picked['purchase_order_no'] ?? '') !== 'PO12609999' || ($picked['receipt_state'] ?? '') !== 'received' || ($picked['material_status'] ?? '') !== 'G') {
    fail('dezelfde artikelregel op de werkorder blijft Gepicked: ' . json_encode($picked));
}
if (($partialNoWo['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($partialNoWo), 'Te laat') || ($partialNoWo['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($partialNoWo, 'both')) {
    fail('deels ontvangen zonder werkorder blijft te laat: ' . json_encode($partialNoWo));
}
if (($statusTNoWo['work_order_no'] ?? '') !== '' || ($statusTNoWo['material_status'] ?? '') !== 'T' || elpis_line_filter_status($statusTNoWo) !== 'T' || elpis_line_row_tone($statusTNoWo) !== 'alert' || str_starts_with(elpis_line_expected_receipt_display($statusTNoWo), 'Te laat')) {
    fail('materiaalstatus T zonder werkorder blijft rood: ' . json_encode($statusTNoWo));
}
$ambiguousNoWo = elpis_apply_receipt_expectations(elpis_attach_inkoop_planning_rows([
    '153390' => [
        planningLine([
            'line_no' => 10000,
            'item_no' => 'PI20001429',
            'work_order_no' => '',
            'purchase_order_no' => '',
            'purchase_order_line_no' => 0,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'O',
            'remaining_qty' => 1,
        ]),
        planningLine([
            'line_no' => 20000,
            'job_task_no' => '200-000-060',
            'item_no' => 'PI20001429',
            'work_order_no' => '',
            'purchase_order_no' => '',
            'purchase_order_line_no' => 0,
            'expected_receipt_date' => '2026-09-01',
            'material_status' => 'O',
            'remaining_qty' => 1,
        ]),
    ],
], [[
    'Job_No' => '153390',
    'Job_Task_No' => '900',
    'Line_No' => 30000,
    'No' => 'PI20001429',
    'LVS_Work_Order_No' => '',
    'LVS_Purchase_Order_No' => 'PO12602236',
    'LVS_Purchase_Order_Line_No' => 20000,
    'LVS_Outstanding_Qty_Base' => 0,
]]), [], [], []);
foreach ($ambiguousNoWo['153390'] as $ambiguousLine) {
    if (($ambiguousLine['receipt_state'] ?? '') === 'received' || ($ambiguousLine['purchase_order_no'] ?? '') !== '') {
        fail('twee regels zonder werkorder voor hetzelfde artikel koppelen niet aan één inkoopregel: ' . json_encode($ambiguousLine));
    }
}

// WerkordersLines-select heeft geen Outstanding_Quantity. Een ontbrekend of 0
// LVS_Outstanding_Qty_Base vult Ontvangen niet; zonder inkoopregel blijft de regel open.
$shownFullRows = [
    [
        'No' => 'PI20001429',
        'Description' => 'Spoedlevering',
        'Quantity' => 1,
        'Remaining_Qty' => 1,
        'LVS_Purchase_Order_No' => 'PO12602236',
        'LVS_Purchase_Order_Line_No' => 20000,
    ],
    [
        'No' => 'PI20000438',
        'Description' => 'M24 bouten',
        'Quantity' => 112,
        'Remaining_Qty' => 112,
        'LVS_Purchase_Order_No' => 'PO12600678',
        'LVS_Purchase_Order_Line_No' => 10000,
        'LVS_Outstanding_Qty_Base' => 0,
    ],
];
foreach ($shownFullRows as $shownRow) {
    $shownLine = elpis_refresh_receipt_state(elpis_normalize_planning_line_row(array_merge([
        'Job_No' => '153390',
        'Job_Task_No' => '100',
        'Line_No' => 40000,
        'LVS_Work_Order_No' => '',
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => 'O',
        'LVS_Completely_Received' => false,
    ], $shownRow)));
    $shownPayload = elpis_line_public_payload($shownLine, '153390');
    $ordered = elpis_line_shown_ordered_qty($shownLine);
    $received = elpis_line_shown_received_qty($shownLine);
    if (abs($ordered - (float) $shownRow['Quantity']) > 0.00001 || $received > 0.00001 || $shownPayload['qty_ordered'] !== $ordered || $shownPayload['qty_received'] !== $received) {
        fail($shownRow['No'] . ' mag Ontvangen niet uit besteld of werkorder-outstanding halen: ' . json_encode($shownPayload));
    }
    if (array_key_exists('inkoop_outstanding_qty', $shownLine) || elpis_line_inkoop_outstanding_closed($shownLine) || elpis_line_purchase_outstanding_closed($shownLine)) {
        fail($shownRow['No'] . ' heeft geen inkoopregel-outstanding: ' . json_encode($shownLine));
    }
    if (elpis_line_is_received($shownLine) || !elpis_line_is_late($shownLine) || ($shownLine['receipt_state'] ?? '') !== 'late' || !str_starts_with(($shownPayload['expected_receipt'] ?? ''), 'Te laat') || ($shownPayload['receipt_state'] ?? '') !== 'late' || ($shownLine['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($shownLine, 'both')) {
        fail($shownRow['No'] . ' zonder ontvangst of inkoopregel blijft te laat: ' . json_encode($shownLine));
    }
}

$shownPartial = elpis_refresh_receipt_state(array_merge(elpis_normalize_planning_line_row([
    'Job_No' => '153390',
    'Job_Task_No' => '200',
    'Line_No' => 50000,
    'No' => 'PI20000438',
    'Description' => 'M24 bouten',
    'Quantity' => 112,
    'Remaining_Qty' => 40,
    'LVS_Work_Order_No' => '',
    'LVS_Purchase_Order_No' => 'PO12600678',
    'LVS_Purchase_Order_Line_No' => 10000,
    'KVT_Expected_Receipt_Date' => '2026-09-01',
    'KVT_Status_Material' => 'O',
]), elpis_line_quantities(112, 'PO12600678', 112, 40)));
if (elpis_line_shown_received_qty($shownPartial) + 0.00001 >= elpis_line_shown_ordered_qty($shownPartial) || ($shownPartial['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($shownPartial), 'Te laat') || !elpis_line_is_late($shownPartial) || ($shownPartial['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($shownPartial, 'both')) {
    fail('deels ontvangen met verlopen datum blijft te laat: ' . json_encode($shownPartial));
}

$shownStatusT = elpis_refresh_receipt_state(array_merge(elpis_normalize_planning_line_row([
    'Job_No' => '153390',
    'Job_Task_No' => '300',
    'Line_No' => 60000,
    'No' => 'PI20009902',
    'Description' => 'Status T',
    'Quantity' => 4,
    'Remaining_Qty' => 3,
    'LVS_Work_Order_No' => '',
    'LVS_Purchase_Order_No' => 'PO12600679',
    'LVS_Purchase_Order_Line_No' => 10000,
    'KVT_Expected_Receipt_Date' => '2026-09-01',
    'KVT_Status_Material' => 'T',
]), elpis_line_quantities(4, 'PO12600679', 4, 3)));
if (($shownStatusT['material_status'] ?? '') !== 'T' || ($shownStatusT['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($shownStatusT), 'Te laat') || elpis_line_row_tone($shownStatusT) !== 'alert' || !elpis_line_is_late($shownStatusT)) {
    fail('materiaalstatus T met open hoeveelheid blijft te laat en rood: ' . json_encode($shownStatusT));
}

$shownStatusTFull = elpis_refresh_receipt_state(elpis_normalize_planning_line_row([
    'Job_No' => '153390',
    'Job_Task_No' => '400',
    'Line_No' => 70000,
    'No' => 'PI20009903',
    'Description' => 'Status T ontvangen',
    'Quantity' => 2,
    'Remaining_Qty' => 2,
    'LVS_Work_Order_No' => '',
    'LVS_Purchase_Order_No' => 'PO12600680',
    'LVS_Purchase_Order_Line_No' => 10000,
    'KVT_Expected_Receipt_Date' => '2026-09-01',
    'KVT_Status_Material' => 'T',
]));
if (($shownStatusTFull['material_status'] ?? '') !== 'T' || ($shownStatusTFull['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($shownStatusTFull), 'Te laat') || elpis_line_row_tone($shownStatusTFull) !== 'alert' || !elpis_line_is_late($shownStatusTFull) || elpis_line_shown_received_qty($shownStatusTFull) > 0.00001) {
    fail('materiaalstatus T zonder echte ontvangst blijft te laat en rood: ' . json_encode($shownStatusTFull));
}

// Project 153390. De planningsregel heeft LVS_Purchase_Order_No en Line_No al.
// PI20000438 heeft Quantity 112; de inkoopregel Quantity 1.
$openPurchaseCases = [
    ['item_no' => 'PI24003010', 'description' => 'PI24003010', 'work_order_no' => '4066162', 'job_task_no' => '100', 'line_no' => 8000, 'purchase_order_no' => '4500124003', 'purchase_order_line_no' => 10000, 'quantity' => 2.0, 'material_status' => 'X', 'expect_received' => false, 'ordered' => 2.0, 'open' => 2.0, 'received' => 0.0],
    ['item_no' => 'PI28127010', 'description' => 'PI28127010', 'work_order_no' => '4066162', 'job_task_no' => '100', 'line_no' => 13000, 'purchase_order_no' => '4500128127', 'purchase_order_line_no' => 10000, 'quantity' => 1.0, 'material_status' => 'X', 'expect_received' => false, 'ordered' => 1.0, 'open' => 1.0, 'received' => 0.0],
    ['item_no' => 'PI27751010', 'description' => 'PI27751010', 'work_order_no' => '4066164', 'job_task_no' => '100', 'line_no' => 14000, 'purchase_order_no' => '4500127751', 'purchase_order_line_no' => 10000, 'quantity' => 1.0, 'material_status' => 'X', 'expect_received' => false, 'ordered' => 1.0, 'open' => 1.0, 'received' => 0.0],
    ['item_no' => 'PI20001429', 'description' => 'Spoedlevering', 'work_order_no' => '', 'job_task_no' => '4066161', 'line_no' => 2525000, 'purchase_order_no' => 'PO12602236', 'purchase_order_line_no' => 20000, 'quantity' => 1.0, 'material_status' => 'O', 'expect_received' => true, 'ordered' => 1.0, 'open' => 0.0, 'received' => 1.0, 'inkoop_task' => '4066161', 'inkoop_line' => 20000, 'purchase_line_no' => 20000],
    ['item_no' => 'PI20000438', 'description' => 'M24 bouten', 'work_order_no' => '', 'job_task_no' => '4066162', 'line_no' => 383000, 'purchase_order_no' => 'PO12600678', 'purchase_order_line_no' => 10000, 'quantity' => 112.0, 'material_status' => 'O', 'expect_received' => true, 'ordered' => 1.0, 'open' => 0.0, 'received' => 1.0, 'inkoop_task' => '4066162', 'inkoop_line' => 10000, 'purchase_line_no' => 10000, 'order_uom' => 112.0],
];
$openPurchaseLines = [];
$openPurchaseInkoop = [];
$openPurchaseOrders = [];
$openPurchaseByItem = [];
foreach ($openPurchaseCases as $case) {
    $openPurchaseByItem[$case['item_no']] = $case;
    $openPurchaseLines[] = elpis_normalize_planning_line_row([
        'Job_No' => '153390',
        'Job_Task_No' => $case['job_task_no'],
        'Line_No' => $case['line_no'],
        'No' => $case['item_no'],
        'Description' => $case['description'],
        'Quantity' => $case['quantity'],
        'Remaining_Qty' => $case['quantity'],
        'LVS_Work_Order_No' => $case['work_order_no'],
        'LVS_Purchase_Order_No' => $case['purchase_order_no'],
        'LVS_Purchase_Order_Line_No' => $case['purchase_order_line_no'],
        'LVS_Completely_Received' => false,
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => $case['material_status'],
    ]);
    $normalizedOpen = $openPurchaseLines[array_key_last($openPurchaseLines)];
    if (elpis_line_shown_received_qty($normalizedOpen) > 0.00001 || abs(elpis_line_shown_ordered_qty($normalizedOpen) - $case['quantity']) > 0.00001) {
        fail($case['item_no'] . ' leidt Ontvangen af uit de werkorderregel: ' . json_encode($normalizedOpen));
    }
    $purchaseLineNo = (int) ($case['purchase_line_no'] ?? $case['purchase_order_line_no']);
    $openPurchaseInkoop[] = [
        'Job_No' => '153390',
        'Job_Task_No' => (string) ($case['inkoop_task'] ?? $case['job_task_no']),
        'Line_No' => (int) ($case['inkoop_line'] ?? $case['line_no']),
        'No' => $case['item_no'],
        'LVS_Work_Order_No' => $case['work_order_no'],
        'LVS_Purchase_Order_No' => $case['purchase_order_no'],
        'LVS_Purchase_Order_Line_No' => $case['expect_received'] ? 99999 : $purchaseLineNo,
        'LVS_Quantity_Order_UoM' => (float) ($case['order_uom'] ?? $case['quantity']),
        'LVS_Outstanding_Qty_Base' => (float) ($case['order_uom'] ?? $case['quantity']),
        'LVS_Completely_Received' => false,
        'KVT_Status_Material' => $case['material_status'],
    ];
    $openPurchaseOrders[] = [
        'Document_No' => $case['purchase_order_no'],
        'Line_No' => $purchaseLineNo,
        'No' => $case['item_no'],
        'Quantity' => $case['expect_received'] ? $case['ordered'] : $case['quantity'],
        'Outstanding_Quantity' => $case['expect_received'] ? 0 : $case['quantity'],
        'LVS_Completely_Received' => false,
    ];
    if ($case['expect_received'] && (int) $case['purchase_order_line_no'] !== $purchaseLineNo) {
        $openPurchaseOrders[] = [
            'Document_No' => $case['purchase_order_no'],
            'Line_No' => $case['purchase_order_line_no'],
            'No' => $case['item_no'],
            'Quantity' => $case['quantity'],
            'Outstanding_Quantity' => $case['quantity'],
            'LVS_Completely_Received' => false,
        ];
    }
}
$openPurchase = elpis_apply_receipt_expectations(
    elpis_attach_inkoop_planning_rows(['153390' => $openPurchaseLines], $openPurchaseInkoop),
    [],
    $openPurchaseOrders,
    []
);
if (count($openPurchase['153390'] ?? []) !== count($openPurchaseCases)) {
    fail('de ontvangstgevallen horen samen in project 153390: ' . json_encode(array_column($openPurchase['153390'] ?? [], 'item_no')));
}
foreach ($openPurchase['153390'] as $openLine) {
    $itemNo = (string) ($openLine['item_no'] ?? '');
    $case = $openPurchaseByItem[$itemNo] ?? null;
    if (!is_array($case)) {
        fail('onverwachte regel in de ontvangstgevallen: ' . json_encode($openLine));
    }
    $payload = elpis_line_public_payload($openLine, '153390');
    $ordered = elpis_line_shown_ordered_qty($openLine);
    $received = elpis_line_shown_received_qty($openLine);
    $openQty = (float) ($openLine['qty_open'] ?? -1);
    if (($openLine['work_order_no'] ?? '') !== $case['work_order_no'] || ($openLine['material_status'] ?? '') !== $case['material_status'] || ($openLine['description'] ?? '') !== $case['description'] || abs($ordered - $case['ordered']) > 0.00001 || abs($received - $case['received']) > 0.00001 || abs($openQty - $case['open']) > 0.00001 || ($payload['qty_ordered'] ?? null) !== $ordered || ($payload['qty_received'] ?? null) !== $received) {
        fail($itemNo . ' wijkt af van het testgeval: ' . json_encode($payload));
    }
    if ($case['expect_received']) {
        $expectedPurchaseLine = (int) ($case['purchase_line_no'] ?? 0);
        if ((int) ($openLine['purchase_order_line_no'] ?? 0) !== $expectedPurchaseLine || ($openLine['purchase_order_no'] ?? '') !== $case['purchase_order_no'] || !elpis_line_is_received($openLine) || elpis_line_is_late($openLine) || ($openLine['receipt_state'] ?? '') !== 'received' || str_starts_with(($payload['expected_receipt'] ?? ''), 'Te laat') || str_starts_with(elpis_line_expected_receipt_display($openLine), 'Te laat') || elpis_line_row_tone($openLine) === 'alert' || elpis_mail_line_matches_content($openLine, 'both') || (elpis_line_tooltips($openLine)['expected_receipt'] ?? '') !== '(PurchaseLines -> Outstanding_Quantity)') {
            fail($itemNo . ' zonder werkorder hoort de inkoopregel ' . $case['purchase_order_no'] . '/' . $expectedPurchaseLine . ' te volgen: ' . json_encode($openLine));
        }
        continue;
    }
    if ($received > 0.00001 || abs($ordered - $received) <= 0.00001 || ($payload['qty_received'] ?? -1) > 0.00001) {
        fail($itemNo . ' toont ontvangen = besteld terwijl Outstanding_Quantity open is: ' . json_encode($payload));
    }
    if (elpis_line_is_received($openLine) || ($openLine['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($openLine), 'Te laat') || !str_starts_with(($payload['expected_receipt'] ?? ''), 'Te laat') || !empty($openLine['completely_received']) || elpis_line_row_tone($openLine) !== 'alert' || !elpis_mail_line_matches_content($openLine, 'both')) {
        fail($itemNo . ' is in BC niet ontvangen en hoort te laat te blijven: ' . json_encode($openLine));
    }
}
$openPartial = elpis_apply_receipt_expectations(['PRJ4066162' => [elpis_normalize_planning_line_row([
    'Job_No' => 'PRJ4066162',
    'Job_Task_No' => '100',
    'Line_No' => 15000,
    'No' => 'PI24003010',
    'Quantity' => 2,
    'Remaining_Qty' => 2,
    'LVS_Work_Order_No' => '4066162',
    'LVS_Purchase_Order_No' => '4500124003',
    'LVS_Purchase_Order_Line_No' => 20000,
    'LVS_Outstanding_Qty_Base' => 0,
    'LVS_Completely_Received' => false,
    'KVT_Expected_Receipt_Date' => '2026-09-01',
    'KVT_Status_Material' => 'X',
])]], [], [[
    'document_no' => '4500124003',
    'line_no' => 20000,
    'item_no' => 'PI24003010',
    'outstanding_qty' => 1,
    'completely_received' => false,
]], []);
$openPartialLine = $openPartial['PRJ4066162'][0];
if (abs(elpis_line_shown_received_qty($openPartialLine) - 1) > 0.00001 || abs(elpis_line_shown_ordered_qty($openPartialLine) - 2) > 0.00001 || ($openPartialLine['receipt_state'] ?? '') !== 'late' || !str_starts_with(elpis_line_expected_receipt_display($openPartialLine), 'Te laat') || elpis_line_is_received($openPartialLine)) {
    fail('gedeeltelijk ontvangen met verlopen datum blijft te laat: ' . json_encode($openPartialLine));
}

$ledgerFull = elpis_apply_receipt_expectations(['P' => [planningLine([
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'quantity' => 4,
    'expected_receipt_date' => '2026-10-01',
    'item_no' => 'PI20001186',
])]], [], [], [], [
    'receipts' => [
        [
            'Job_No' => 'P',
            'Job_Task_No' => '200-000-050',
            'Item_No' => 'PI20001186',
            'Posting_Date' => '2026-10-09',
            'Quantity' => 1,
        ],
        [
            'Job_No' => 'P',
            'Job_Task_No' => '200-000-050',
            'Item_No' => 'PI20001186',
            'Posting_Date' => '2026-10-11',
            'Quantity' => 3,
        ],
    ],
]);
$ledgerFullLine = $ledgerFull['P'][0];
if (($ledgerFullLine['receipt_state'] ?? '') !== 'received' || elpis_line_expected_receipt_display($ledgerFullLine) !== 'Reeds ontvangen (11-10-2026)' || elpis_line_is_late($ledgerFullLine) || elpis_mail_line_matches_content($ledgerFullLine, 'both')) {
    fail('ontvangstposten die de hoeveelheid dekken zijn ontvangen: ' . json_encode($ledgerFullLine));
}

$ledgerPartial = elpis_apply_receipt_expectations(['P' => [planningLine([
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'quantity' => 4,
    'expected_receipt_date' => '2026-10-01',
    'item_no' => 'PI20001189',
    'material_status' => 'I',
])]], [], [], [], [
    'receipts' => [[
        'Job_No' => 'P',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20001189',
        'Posting_Date' => '2026-10-09',
        'Quantity' => 3,
    ]],
]);
$ledgerPartialLine = $ledgerPartial['P'][0];
if (($ledgerPartialLine['receipt_state'] ?? '') === 'received' || !str_starts_with(elpis_line_expected_receipt_display($ledgerPartialLine), 'Te laat') || ($ledgerPartialLine['material_status'] ?? '') !== 'I') {
    fail('een gedeeltelijke ontvangst blijft te laat: ' . json_encode($ledgerPartialLine));
}

$ledgerSiblings = elpis_apply_receipt_expectations(['P' => [
    planningLine([
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'quantity' => 1,
        'line_no' => 10000,
        'expected_receipt_date' => '2026-10-01',
        'item_no' => 'PI20001191',
    ]),
    planningLine([
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'quantity' => 1,
        'line_no' => 20000,
        'expected_receipt_date' => '2026-10-01',
        'item_no' => 'PI20001191',
    ]),
]], [], [], [], [
    'receipts' => [[
        'Job_No' => 'P',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20001191',
        'Posting_Date' => '2026-10-09',
        'Quantity' => 1,
    ]],
]);
foreach ($ledgerSiblings['P'] as $ledgerSibling) {
    if (($ledgerSibling['receipt_state'] ?? '') === 'received') {
        fail('één ontvangsthoeveelheid sluit niet elke regel met hetzelfde artikel: ' . json_encode($ledgerSibling));
    }
}
$ledgerBoth = elpis_apply_receipt_expectations(['P' => [
    planningLine([
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'quantity' => 1,
        'line_no' => 10000,
        'expected_receipt_date' => '2026-10-01',
        'item_no' => 'PI20001191',
    ]),
    planningLine([
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'quantity' => 2,
        'line_no' => 20000,
        'expected_receipt_date' => '2026-10-01',
        'item_no' => 'PI20001191',
    ]),
]], [], [], [], [
    'receipts' => [[
        'Job_No' => 'P',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20001191',
        'Posting_Date' => '2026-10-12',
        'Quantity' => 3,
    ]],
]);
foreach ($ledgerBoth['P'] as $ledgerBothLine) {
    if (($ledgerBothLine['receipt_state'] ?? '') !== 'received' || elpis_mail_line_matches_content($ledgerBothLine, 'both')) {
        fail('de som van de ontvangst dekt beide regels: ' . json_encode($ledgerBothLine));
    }
}

$unconfirmed = elpis_apply_receipt_expectations(['P' => [planningLine([
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'expected_receipt_date' => '',
])]], [], [], []);
if (($unconfirmed['P'][0]['receipt_state'] ?? '') !== 'unconfirmed' || elpis_line_expected_receipt_display($unconfirmed['P'][0]) !== 'Datum ontbreekt') {
    fail('zonder datum blijft de regel onbevestigd');
}

$own = [
    'entry_no' => 1,
    'positive' => false,
    'item_no' => 'PI20002946',
    'quantity_base' => -4,
    'status' => 'Reservation',
    'source_type' => 1003,
    'source_id' => 'PRJ2606522',
];
$other = $own;
$other['entry_no'] = 2;
$other['source_id'] = 'PRJ-ANDER';
$other['quantity_base'] = -3;
$stockLines = elpis_apply_receipt_expectations([
    'PRJ2606522' => [planningLine(['purchase_order_no' => '', 'purchase_order_line_no' => 0, 'expected_receipt_date' => '2026-11-01'])],
], [$own, $other], [], ['PI20002946' => 10]);
if (abs((float) ($stockLines['PRJ2606522'][0]['free_stock'] ?? 0) - 7) > 0.00001) {
    fail('alleen reserveringen van andere projecten gaan van de voorraad af: ' . json_encode($stockLines['PRJ2606522'][0]));
}
$assembly = $other;
$assembly['entry_no'] = 3;
$assembly['source_type'] = 901;
$assembly['source_id'] = 'ASS2601';
$assembly['quantity_base'] = 2;
$sales = $other;
$sales['entry_no'] = 4;
$sales['source_type'] = 37;
$sales['source_id'] = 'SO1';
$sales['quantity_base'] = -1;
$ledgerReservation = $other;
$ledgerReservation['entry_no'] = 5;
$ledgerReservation['source_type'] = 32;
$ledgerReservation['source_id'] = 'PRJ-ANDER';
$ledgerReservation['quantity_base'] = -9;
$assemblyOnOwnProject = $assembly;
$assemblyOnOwnProject['entry_no'] = 6;
$assemblyOnOwnProject['source_id'] = 'PRJ2606522';
$assemblyOnOwnProject['quantity_base'] = -1;
$withConfirmed = elpis_apply_receipt_expectations([
    'PRJ2606522' => [planningLine(['purchase_order_no' => '', 'purchase_order_line_no' => 0, 'expected_receipt_date' => '2026-11-01'])],
], [$own, $other, $assembly, $sales, $ledgerReservation, $assemblyOnOwnProject], [], ['PI20002946' => 10]);
if (abs((float) ($withConfirmed['PRJ2606522'][0]['free_stock'] ?? 0) - 3) > 0.00001) {
    fail('901 en 37 gaan eraf, 32 en eigen 1003 niet: ' . json_encode($withConfirmed['PRJ2606522'][0]));
}
$ignored = $other;
$ignored['status'] = 'Tracking';
$ignored['quantity_base'] = -9;
$notTracking = elpis_apply_receipt_expectations([
    'PRJ2606522' => [planningLine(['expected_receipt_date' => '2026-11-01'])],
], [$ignored], [], ['PI20002946' => 10]);
if (abs((float) ($notTracking['PRJ2606522'][0]['free_stock'] ?? 0) - 10) > 0.00001) {
    fail('Tracking telt niet mee in de voorraad');
}

if (elpis_line_filter_status($pi) !== ELPI_STATUS_LATE || elpis_line_row_tone($pi) !== 'alert' || elpis_status_tone('I') !== 'orange') {
    fail('Te laat is een eigen rode status, inkooporder blijft oranje');
}

$summary = elpis_project_execution_summary([
    planningLine(['expected_receipt_date' => '2026-11-02', 'receipt_state' => 'date']),
    planningLine(['expected_receipt_date' => '2026-10-01', 'receipt_state' => 'late']),
]);
if (($summary['state'] ?? '') !== 'date' || ($summary['date'] ?? '') !== '2026-11-02') {
    fail('uitvoering plannen vanaf neemt de laatste open datum: ' . json_encode($summary));
}

$calls = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query, int $ttl) use (&$calls): array {
    $calls[] = ['entity' => $entity, 'query' => $query, 'ttl' => $ttl];
    if ($entity === ELPI_ITEM_LIST_ENTITY) {
        return [['No' => 'PI20002946', 'InventoryField' => 5]];
    }
    if ($entity === ELPI_WORK_ORDER_ENTITY) {
        return [['No' => 'WO2610118', 'Task_Description' => 'Werkorder']];
    }

    return [];
};
elpis_reset_fetch_memo();
$context = elpis_load_receipt_context('Koninklijke van Twist', [
    'PRJ2606522' => [planningLine(['expected_receipt_date' => '2026-11-01'])],
], ELPI_CACHE_TTL);
$entities = array_column($calls, 'entity');
foreach ([ELPI_RESERVATION_ENTRY_ENTITY, ELPI_PURCHASE_ORDER_LINE_ENTITY, ELPI_ITEM_LIST_ENTITY, ELPI_ITEM_LEDGER_ENTITY, ELPI_WORK_ORDER_ENTITY] as $entity) {
    if (!in_array($entity, $entities, true)) {
        fail('context mist ' . $entity . ': ' . json_encode($entities));
    }
}
foreach ($calls as $call) {
    $filter = (string) ($call['query']['$filter'] ?? '');
    if ($call['ttl'] !== ELPI_CACHE_TTL) {
        fail('UI-query moet 24 uur max_age houden');
    }
    if (!isset($call['query']['$select']) || filter_ors_cross_fields($filter)) {
        fail('query mist select of OR-t over velden: ' . json_encode($call));
    }
    if ($call['entity'] === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        $byDocument = str_contains($filter, "Document_No eq 'PO12606033'");
        $byJob = str_contains($filter, "Job_No eq 'PRJ2606522'") && !str_contains($filter, 'Job_Task_No') && !str_contains($filter, 'Job_Line_Type');
        if ((!$byDocument && !$byJob) || str_contains($filter, 'Job_Task_No') || str_contains($filter, 'Job_Line_Type') || str_contains($filter, 'Outstanding_Quantity')) {
            fail('inkoop is niet tot document of project beperkt, zonder taakfilter: ' . $filter);
        }
    }
    if ($call['entity'] === ELPI_ITEM_LIST_ENTITY && !str_contains($filter, "No eq 'PI20002946'")) {
        fail('voorraad is niet tot het artikel beperkt: ' . $filter);
    }
}
$sawPurchaseDocument = false;
$sawPurchaseJob = false;
foreach ($calls as $call) {
    if (($call['entity'] ?? '') !== ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        continue;
    }
    $filter = (string) ($call['query']['$filter'] ?? '');
    if (str_contains($filter, "Document_No eq 'PO12606033'")) {
        $sawPurchaseDocument = true;
    }
    if (str_contains($filter, "Job_No eq 'PRJ2606522'") && !str_contains($filter, 'Job_Task_No')) {
        $sawPurchaseJob = true;
    }
}
if (!$sawPurchaseDocument || !$sawPurchaseJob) {
    fail('inkoopregels worden per document en per project opgehaald: ' . json_encode($calls));
}
if (elpis_fetch_reservation_entries('Koninklijke van Twist') !== [] || elpis_fetch_open_purchase_order_lines('Koninklijke van Twist') !== []) {
    fail('ongefilterde fetches mogen geen rijen teruggeven');
}
$before = count($calls);
elpis_load_receipt_context('Koninklijke van Twist', [
    'PRJ2606522' => [planningLine(['expected_receipt_date' => '2026-11-01'])],
], ELPI_CACHE_TTL);
if (count($calls) !== $before) {
    fail('dezelfde context moet de memo gebruiken');
}

$projectNos = [];
for ($i = 1; $i <= 13; $i++) {
    $projectNos[] = 'PRJ' . $i;
}
$seen = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query) use (&$seen): array {
    $seen[] = $entity . ' ' . (string) ($query['$filter'] ?? '');

    return [];
};
elpis_reset_fetch_memo();
elpis_fetch_planning_lines_by_projects('Koninklijke van Twist', $projectNos, ELPI_CACHE_TTL);
$planningCalls = array_values(array_filter($seen, static fn (string $row): bool => str_starts_with($row, ELPI_PLANNING_LINE_ENTITY)));
if (count($planningCalls) !== 2) {
    fail('13 projecten geven twee planningschunks: ' . json_encode($planningCalls));
}
$stockItems = [];
for ($i = 1; $i <= 13; $i++) {
    $stockItems[] = 'ITEM' . $i;
}
$stockCalls = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query) use (&$stockCalls): array {
    $stockCalls[] = (string) ($query['$filter'] ?? '');

    return [];
};
elpis_reset_fetch_memo();
elpis_fetch_item_stock_reservations('Koninklijke van Twist', $stockItems, ELPI_CACHE_TTL);
if (count($stockCalls) !== 2 || !str_contains($stockCalls[0], 'Source_Type eq 1003') || !str_contains($stockCalls[0], "Item_No eq 'ITEM1'") || !str_contains($stockCalls[1], 'Item_No eq ') || str_contains($stockCalls[0] . $stockCalls[1], 'Source_Type eq 32')) {
    fail('voorraadreserveringen chunked per 12 artikelen: ' . json_encode($stockCalls));
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);

$cacheDir = sys_get_temp_dir() . '/elpis-project-cache-' . getmypid();
if (!is_dir(dirname(elpis_project_cache_path('K', 'P')))) {
    fail('projectcachemap wordt niet aangemaakt');
}
elpis_write_project_cache('Koninklijke van Twist', 'PRJ2606522', [$pi]);
$cached = elpis_read_project_cache('Koninklijke van Twist', 'PRJ2606522');
if (!is_array($cached) || ($cached['lines'][0]['item_no'] ?? '') !== 'PI20002946') {
    fail('projectcache bewaart de regels');
}
if ((int) ($cached['expires_at'] ?? 0) - (int) ($cached['cached_at'] ?? 0) !== ELPI_CACHE_TTL) {
    fail('projectcache geldt minstens een dag');
}
if ((int) ($cached['version'] ?? 0) !== ELPI_PROJECT_CACHE_VERSION) {
    fail('projectcache noteert de versie');
}
$stalePath = elpis_project_cache_path('Koninklijke van Twist', 'PRJ-OUD');
file_put_contents($stalePath, json_encode([
    'company' => 'Koninklijke van Twist',
    'project_no' => 'PRJ-OUD',
    'cached_at' => time(),
    'expires_at' => time() + 3600,
    'lines' => [$pi],
]));
if (elpis_read_project_cache('Koninklijke van Twist', 'PRJ-OUD') !== null) {
    fail('projectcache zonder versie wordt genegeerd');
}
if (ELPI_PROJECT_CACHE_VERSION < 15) {
    fail('projectcacheversie moet minstens 11 zijn (oude caches met gepickte regels of stille lege inkoop na Mímir-timeout verversen)');
}
$previousVersionPath = elpis_project_cache_path('Koninklijke van Twist', '153390');
file_put_contents($previousVersionPath, json_encode([
    'version' => ELPI_PROJECT_CACHE_VERSION - 1,
    'company' => 'Koninklijke van Twist',
    'project_no' => '153390',
    'cached_at' => time(),
    'expires_at' => time() + 3600,
    'lines' => [array_merge($shownStatusTFull, [
        'qty_ordered' => 2,
        'qty_received' => 2,
        'receipt_state' => 'received',
    ])],
]));
if (elpis_read_project_cache('Koninklijke van Twist', '153390') !== null) {
    fail('projectcache van de vorige versie wordt bij verversen niet hergebruikt');
}
$recomputed = array_merge($shownStatusTFull, [
    'qty_ordered' => 2,
    'qty_received' => 2,
    'qty_open' => 0,
    'receipt_state' => 'received',
]);
elpis_write_project_cache('Koninklijke van Twist', '153390', [$recomputed]);
$reread = elpis_read_project_cache('Koninklijke van Twist', '153390');
if (($reread['lines'][0]['receipt_state'] ?? '') === 'received' || !elpis_line_is_late($reread['lines'][0]) || !str_starts_with(elpis_line_expected_receipt_display($reread['lines'][0]), 'Te laat') || ($reread['lines'][0]['material_status'] ?? '') !== 'T' || elpis_line_row_tone($reread['lines'][0]) !== 'alert') {
    fail('ontvangen >= besteld zonder inkoopregel of ontvangstpost blijft te laat: ' . json_encode($reread['lines'][0] ?? null));
}

$index = (string) file_get_contents(dirname(__DIR__) . '/web/index.php');
$progress = (string) file_get_contents(dirname(__DIR__) . '/web/elpis_progress.php');
$projectLines = (string) file_get_contents(dirname(__DIR__) . '/web/project_lines.php');
if (str_contains($index, 'elpis_load_shown_lines') || str_contains($progress, 'elpis_load_shown_lines')) {
    fail('de projectlijst laadt de regels niet meer bij het openen');
}
if (!str_contains($projectLines, 'elpis_load_project_modal_lines') || !str_contains($index, 'project_lines.php') || !str_contains($index, 'data-elpis-refresh')) {
    fail('regels laden in de modal, met verversen');
}
if (!str_contains($index, 'elpis_nightly_needs_run') || !str_contains($index, 'elpis.company.choose')) {
    fail('eerst nightly-check en bedrijfskeuze');
}

// BC-structuur project 153390. PurchaseLines.Job_Task_No is het werkordernummer
// en Job_Line_Type is leeg. De planningsregel toont geen werkorder, maar heeft
// wel LVS_Purchase_Order_No + LVS_Purchase_Order_Line_No. Ontvangstposten
// PWR2611991 en PWR2606258 dekken niet de plannings-Quantity 112.
$bcPlanning = [
    elpis_normalize_planning_line_row([
        'Job_No' => '153390',
        'Job_Task_No' => '4066161',
        'Line_No' => 2525000,
        'No' => 'PI20001429',
        'Description' => 'Spoedlevering',
        'Quantity' => 1,
        'Remaining_Qty' => 1,
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12602236',
        'LVS_Purchase_Order_Line_No' => 20000,
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => 'O',
    ]),
    elpis_normalize_planning_line_row([
        'Job_No' => '153390',
        'Job_Task_No' => '4066161',
        'Line_No' => 2585000,
        'No' => 'PI20001429',
        'Description' => 'Spoedlevering',
        'Quantity' => 1,
        'Remaining_Qty' => 0,
        'LVS_Work_Order_No' => '4066161',
        'LVS_Purchase_Order_No' => '',
        'LVS_Purchase_Order_Line_No' => 0,
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => 'G',
    ]),
    elpis_normalize_planning_line_row([
        'Job_No' => '153390',
        'Job_Task_No' => '4066162',
        'Line_No' => 383000,
        'No' => 'PI20000438',
        'Description' => 'M24 bouten',
        'Quantity' => 112,
        'Remaining_Qty' => 112,
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12600678',
        'LVS_Purchase_Order_Line_No' => 10000,
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => 'O',
    ]),
];
$bcInkoop = [
    [
        'Job_No' => '153390',
        'Job_Task_No' => '4066161',
        'Line_No' => 20000,
        'No' => 'PI20001429',
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12602236',
        'LVS_Purchase_Order_Line_No' => 30000,
        'LVS_Quantity_Order_UoM' => 1,
        'LVS_Outstanding_Qty_Base' => 1,
    ],
    [
        'Job_No' => '153390',
        'Job_Task_No' => '4066162',
        'Line_No' => 10000,
        'No' => 'PI20000438',
        'LVS_Work_Order_No' => '',
        'LVS_Purchase_Order_No' => 'PO12600678',
        'LVS_Purchase_Order_Line_No' => 20000,
        'LVS_Quantity_Order_UoM' => 112,
        'LVS_Outstanding_Qty_Base' => 112,
    ],
];
$bcPurchase = [
    [
        'Document_No' => 'PO12602236',
        'Line_No' => 20000,
        'No' => 'PI20001429',
        'Document_Type' => 'Order',
        'Job_No' => '153390',
        'Job_Task_No' => '4066161',
        'Job_Line_Type' => '',
        'Quantity' => 1,
        'Outstanding_Quantity' => 0,
        'LVS_Completely_Received' => false,
    ],
    [
        'Document_No' => 'PO12602236',
        'Line_No' => 30000,
        'No' => 'PI20001429',
        'Document_Type' => 'Order',
        'Job_No' => '153390',
        'Job_Task_No' => '',
        'Job_Line_Type' => '',
        'Quantity' => 1,
        'Outstanding_Quantity' => 1,
        'LVS_Completely_Received' => false,
    ],
    [
        'Document_No' => 'PO12600678',
        'Line_No' => 10000,
        'No' => 'PI20000438',
        'Document_Type' => 'Order',
        'Job_No' => '153390',
        'Job_Task_No' => '4066162',
        'Job_Line_Type' => '',
        'Quantity' => 1,
        'Outstanding_Quantity' => 0,
        'LVS_Completely_Received' => false,
    ],
    [
        'Document_No' => 'PO12600678',
        'Line_No' => 20000,
        'No' => 'PI20000438',
        'Document_Type' => 'Order',
        'Job_No' => '153390',
        'Job_Task_No' => '4066162',
        'Job_Line_Type' => 'Budget',
        'Quantity' => 112,
        'Outstanding_Quantity' => 112,
        'LVS_Completely_Received' => false,
    ],
];
$bcNormalizedPurchase = elpis_normalize_purchase_order_line($bcPurchase[0]);
if (($bcNormalizedPurchase['job_no'] ?? '') !== '153390' || ($bcNormalizedPurchase['job_task_no'] ?? '') !== '4066161' || ($bcNormalizedPurchase['job_line_type'] ?? 'x') !== '' || (int) ($bcNormalizedPurchase['line_no'] ?? 0) !== 20000) {
    fail('PurchaseLines met lege Job_Line_Type en taak = werkorder blijven behouden: ' . json_encode($bcNormalizedPurchase));
}
$bcLines = elpis_attach_inkoop_planning_rows(['153390' => $bcPlanning], $bcInkoop);
$bcPurchaseFilters = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query) use (&$bcPurchaseFilters, $bcPurchase): array {
    if ($entity === ELPI_ITEM_LEDGER_ENTITY) {
        return [
            ['Job_No' => '153390', 'Job_Task_No' => '4066161', 'Item_No' => 'PI20001429', 'Posting_Date' => '2026-09-15', 'Quantity' => 1],
            ['Job_No' => '153390', 'Job_Task_No' => '4066162', 'Item_No' => 'PI20000438', 'Posting_Date' => '2026-09-15', 'Quantity' => 1],
        ];
    }
    if ($entity === ELPI_WORK_ORDER_ENTITY) {
        return [['No' => '4066161', 'Task_Description' => 'Werkorder']];
    }
    if ($entity !== ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        return [];
    }
    $filter = (string) ($query['$filter'] ?? '');
    $bcPurchaseFilters[] = $filter . ' select=' . (string) ($query['$select'] ?? '');
    if (str_contains($filter, 'Job_Task_No') || str_contains($filter, 'Job_Line_Type')) {
        return [];
    }
    if (str_contains($filter, "Job_No eq '153390'")) {
        return $bcPurchase;
    }

    return [];
};
elpis_reset_fetch_memo();
$bcResult = elpis_enrich_planning_lines_with_receipts('Koninklijke van Twist', $bcLines);
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);
elpis_reset_fetch_memo();
$sawBcJob = false;
$sawBcDocument = false;
foreach ($bcPurchaseFilters as $filter) {
    if (str_contains($filter, 'Job_Task_No eq') || str_contains($filter, 'Job_Line_Type eq')) {
        fail('PurchaseLines-filter gebruikt de taak of het regeltype: ' . $filter);
    }
    if (str_contains($filter, "Job_No eq '153390'") && str_contains($filter, "Document_Type eq 'Order'") && str_contains($filter, 'Job_Line_Type')) {
        $sawBcJob = true;
    }
    if (str_contains($filter, "Document_No eq 'PO12602236'") && str_contains($filter, "Document_No eq 'PO12600678'")) {
        $sawBcDocument = true;
    }
}
if (!$sawBcJob || !$sawBcDocument) {
    fail('PO12602236 en PO12600678 worden per document en per project opgehaald: ' . json_encode($bcPurchaseFilters));
}
$bcByLine = [];
foreach ($bcResult['153390'] ?? [] as $bcLine) {
    $bcByLine[(int) ($bcLine['line_no'] ?? 0)] = $bcLine;
}
$bcSpoed = $bcByLine[2525000] ?? null;
$bcWorkOrder = $bcByLine[2585000] ?? null;
$bcBolts = $bcByLine[383000] ?? null;
if (!is_array($bcSpoed) || !is_array($bcWorkOrder) || !is_array($bcBolts)) {
    fail('project 153390 mist een planningsregel: ' . json_encode(array_keys($bcByLine)));
}
$bcSpoedPayload = elpis_line_public_payload($bcSpoed, '153390');
if (($bcSpoed['work_order_no'] ?? 'x') !== '' || ($bcSpoed['purchase_order_no'] ?? '') !== 'PO12602236' || (int) ($bcSpoed['purchase_order_line_no'] ?? 0) !== 20000 || abs(elpis_line_shown_ordered_qty($bcSpoed) - 1) > 0.00001 || abs((float) ($bcSpoed['qty_open'] ?? -1)) > 0.00001 || abs(elpis_line_shown_received_qty($bcSpoed) - 1) > 0.00001 || !elpis_line_is_received($bcSpoed) || elpis_line_is_late($bcSpoed) || ($bcSpoed['receipt_state'] ?? '') !== 'received' || str_starts_with(($bcSpoedPayload['expected_receipt'] ?? ''), 'Te laat') || ($bcSpoed['material_status'] ?? '') !== 'O') {
    fail('PI20001429 volgt PO12602236/20000 als 1/0/1: ' . json_encode($bcSpoedPayload));
}
$bcBoltsPayload = elpis_line_public_payload($bcBolts, '153390');
if (($bcBolts['work_order_no'] ?? 'x') !== '' || ($bcBolts['purchase_order_no'] ?? '') !== 'PO12600678' || (int) ($bcBolts['purchase_order_line_no'] ?? 0) !== 10000 || abs(elpis_line_shown_ordered_qty($bcBolts) - 1) > 0.00001 || abs((float) ($bcBolts['qty_open'] ?? -1)) > 0.00001 || abs(elpis_line_shown_received_qty($bcBolts) - 1) > 0.00001 || abs((float) ($bcBolts['quantity'] ?? 0) - 112) > 0.00001 || !elpis_line_is_received($bcBolts) || elpis_line_is_late($bcBolts) || ($bcBolts['receipt_state'] ?? '') !== 'received' || str_starts_with(($bcBoltsPayload['expected_receipt'] ?? ''), 'Te laat') || ($bcBoltsPayload['qty_ordered'] ?? null) !== 1.0) {
    fail('PI20000438 toont de inkoopregel 1/0/1 en niet de planningsregel 112: ' . json_encode($bcBoltsPayload));
}
$bcWorkOrderPayload = elpis_line_public_payload($bcWorkOrder, '153390');
if (($bcWorkOrder['work_order_no'] ?? '') !== '4066161' || ($bcWorkOrder['purchase_order_no'] ?? 'x') !== '' || ($bcWorkOrder['material_status'] ?? '') !== 'G' || ($bcWorkOrder['receipt_state'] ?? '') !== 'received' || abs((float) ($bcWorkOrder['qty_to_order'] ?? -1)) > 0.00001 || ($bcWorkOrderPayload['qty_to_order_display'] ?? 'x') !== '0' || abs((float) ($bcWorkOrderPayload['qty_ordered'] ?? -1)) > 0.00001) {
    fail('de gepickte werkorderregel 4066161/2585000 blijft zichtbaar maar Te bestellen is 0: ' . json_encode($bcWorkOrderPayload));
}
$pickedOnly = elpis_apply_receipt_expectations(['153390' => [planningLine([
    'line_no' => 2585000,
    'item_no' => 'PI20001429',
    'work_order_no' => '4066161',
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'quantity' => 1,
    'qty_to_order' => 1,
    'qty_ordered' => 0,
    'qty_received' => 0,
    'material_status' => 'O',
    'completely_picked' => true,
])]], [], [], []);
if (abs((float) ($pickedOnly['153390'][0]['qty_to_order'] ?? -1)) > 0.00001) {
    fail('KVT_Completely_Picked zet Te bestellen op 0: ' . json_encode($pickedOnly['153390'][0]));
}
$stillToOrder = elpis_apply_receipt_expectations(['153390' => [planningLine([
    'line_no' => 2700000,
    'item_no' => 'PI20009999',
    'work_order_no' => '4066161',
    'purchase_order_no' => '',
    'purchase_order_line_no' => 0,
    'quantity' => 1,
    'qty_to_order' => 1,
    'material_status' => 'O',
    'completely_picked' => false,
])]], [], [], []);
if (abs((float) ($stillToOrder['153390'][0]['qty_to_order'] ?? 0) - 1) > 0.00001) {
    fail('een niet gepickte werkorderregel zonder dekking blijft Te bestellen: ' . json_encode($stillToOrder['153390'][0]));
}

$debugPlanning = [[
    'Job_No' => '153390',
    'Job_Task_No' => '4066162',
    'Line_No' => 383000,
    'Type' => 'Artikel',
    'No' => 'PI20000438',
    'Description' => 'M24 bouten',
    'Quantity' => 112,
    'Remaining_Qty' => 112,
    'LVS_Work_Order_No' => '',
    'LVS_Purchase_Order_No' => 'PO12600678',
    'LVS_Purchase_Order_Line_No' => 10000,
    'KVT_Expected_Receipt_Date' => '2026-09-01',
    'KVT_Status_Material' => 'O',
]];
$debugPurchase = [[
    'Document_No' => 'PO12600678',
    'Line_No' => 10000,
    'No' => 'PI20000438',
    'Document_Type' => 'Order',
    'Job_No' => '153390',
    'Job_Task_No' => '4066162',
    'Job_Line_Type' => '',
    'Quantity' => 1,
    'Outstanding_Quantity' => 0,
    'LVS_Completely_Received' => false,
]];
$debugPurchaseRows = $debugPurchase;
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity) use ($debugPlanning, &$debugPurchaseRows): array {
    if ($entity === ELPI_PLANNING_LINE_ENTITY) {
        return $debugPlanning;
    }
    if ($entity === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        return $debugPurchaseRows;
    }

    return [];
};
elpis_reset_fetch_memo();
$debugHit = elpis_debug_project_line('Koninklijke van Twist', '153390', 'PI20000438', true);
$debugLine = $debugHit['lines'][0] ?? null;
$debugFinalize = null;
$debugNormalize = null;
foreach ($debugHit['steps'] as $debugStep) {
    if (($debugStep['step'] ?? '') === 'finalize') {
        $debugFinalize = $debugStep;
    }
    if (($debugStep['step'] ?? '') === 'normalize') {
        $debugNormalize = $debugStep;
    }
}
if (!is_array($debugLine) || abs((float) ($debugLine['qty_ordered'] ?? -1) - 1) > 0.00001 || abs((float) ($debugLine['qty_open'] ?? -1)) > 0.00001 || abs((float) ($debugLine['qty_received'] ?? -1) - 1) > 0.00001 || (int) ($debugLine['purchase_order_line_no'] ?? 0) !== 10000 || !is_array($debugNormalize) || abs((float) ($debugNormalize['qty_ordered'] ?? -1) - 112) > 0.00001 || !is_array($debugFinalize) || (string) ($debugFinalize['qty_source'] ?? '') !== 'PurchaseLines.Quantity en Outstanding_Quantity' || (int) ($debugFinalize['purchase']['line_no'] ?? 0) !== 10000 || (string) ($debugFinalize['purchase']['job_task_no'] ?? '') !== '4066162' || (string) ($debugFinalize['purchase']['job_line_type'] ?? 'x') !== '') {
    fail('diagnose toont de inkoopregel als bron nadat normalize 112 zette: ' . json_encode($debugHit));
}
if (($debugHit['cache_before']['project']['version_checked'] ?? false) !== true || ($debugHit['cache_before']['mimir']['version_checked'] ?? true) !== false || ($debugHit['cache_before']['odata_file']['version_checked'] ?? true) !== false || ($debugHit['cache_before']['http']['version_checked'] ?? true) !== false || ($debugHit['cache_after']['project']['file_version'] ?? 0) !== ELPI_PROJECT_CACHE_VERSION) {
    fail('alleen de projectcache controleert de versie: ' . json_encode($debugHit['cache_before']));
}
$refreshStep = null;
foreach ($debugHit['steps'] as $debugStep) {
    if (($debugStep['step'] ?? '') === 'project-cache') {
        $refreshStep = $debugStep;
    }
}
if (!is_array($refreshStep) || !empty($refreshStep['hit']) || ($refreshStep['skipped_because'] ?? '') !== 'refresh') {
    fail('verversen leest de projectcache niet: ' . json_encode($refreshStep));
}
$debugPurchaseRows = [];
elpis_reset_fetch_memo();
$debugMiss = elpis_debug_project_line('Koninklijke van Twist', '153390', 'PI20000438', true);
$debugMissLine = $debugMiss['lines'][0] ?? null;
$purchaseFetches = array_values(array_filter($debugMiss['fetches'], static function (array $fetch): bool {
    return ($fetch['entity'] ?? '') === ELPI_PURCHASE_ORDER_LINE_ENTITY;
}));
if (!is_array($debugMissLine) || abs((float) ($debugMissLine['qty_ordered'] ?? -1) - 112) > 0.00001 || abs((float) ($debugMissLine['qty_open'] ?? -1) - 112) > 0.00001 || abs((float) ($debugMissLine['qty_received'] ?? -1)) > 0.00001 || $purchaseFetches === [] || (int) ($purchaseFetches[0]['rows'] ?? -1) !== 0 || !str_contains((string) ($debugMiss['conclusion'] ?? ''), 'WerkordersLines')) {
    fail('zonder PurchaseLines-rijen blijft 112/112/0 terwijl finalize wel loopt: ' . json_encode($debugMiss));
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);
elpis_reset_fetch_memo();
$GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity): array {
    if ($entity === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        throw new RuntimeException('HTTP 400 Could not find a property named Promised_Receipt_Date on type NAV.PurchaseLines');
    }

    return [];
};
$failedPurchaseRows = elpis_try_fetch_rows('Koninklijke van Twist', ELPI_PURCHASE_ORDER_LINE_ENTITY, [
    '$select' => elpis_purchase_line_select_fields(),
    '$filter' => "Document_Type eq 'Order' and Document_No eq 'PO12600678'",
]);
$purchaseFailure = $GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'][0] ?? null;
if ($failedPurchaseRows !== [] || !is_array($purchaseFailure) || !str_contains((string) ($purchaseFailure['message'] ?? ''), 'Promised_Receipt_Date') || !str_contains((string) ($purchaseFailure['select'] ?? ''), 'Outstanding_Quantity')) {
    fail('een PurchaseLines-fout moet gelogd worden: ' . json_encode($purchaseFailure));
}
$GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (): array {
    throw new RuntimeException('HTTP 400 andere entiteit');
};
elpis_try_fetch_rows('Koninklijke van Twist', ELPI_PLANNING_LINE_ENTITY, ['$select' => 'No']);
if (($GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] ?? []) !== []) {
    fail('een fout op een andere entiteit mag de PurchaseLines-log niet vullen');
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);
elpis_reset_fetch_memo();
$debugPage = (string) file_get_contents(dirname(__DIR__) . '/web/line_debug.php');
if (!str_contains($debugPage, 'logincheck.php') || !str_contains($debugPage, 'debug_line') || !str_contains($debugPage, 'Cache-Control: no-store')) {
    fail('line_debug.php moet achter logincheck staan');
}

// Mímir-timeout op ReservationEntries: fout i.p.v. stil "geen inkoop", en geen projectcache.
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query): array {
    if ($entity === ELPI_PLANNING_LINE_ENTITY) {
        return [['Job_No' => 'PRJ2699001', 'Job_Task_No' => '1000', 'Line_No' => 10000, 'Job_Contract_Entry_No' => 7, 'Type' => 'Artikel', 'No' => 'PK-422/8', 'Description' => 'Filter', 'Quantity' => 2, 'Remaining_Qty' => 2]];
    }
    if ($entity === ELPI_RESERVATION_ENTRY_ENTITY) {
        throw new RuntimeException('Mímir cURL error: Connection timed out');
    }

    return [];
};
elpis_reset_fetch_memo();
$threw = false;
try {
    elpis_load_project_modal_lines('Koninklijke van Twist', 'PRJ2699001', true);
} catch (RuntimeException $e) {
    $threw = true;
}
if (!$threw) {
    fail('mislukte ReservationEntries-query moet een fout geven');
}
if (elpis_read_project_cache('Koninklijke van Twist', 'PRJ2699001') !== null) {
    fail('onvolledige uitkomst mag niet in de projectcache');
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER']);


// Reservering op inkoopregel (Source_Type 39): gereserveerde hoeveelheid telt als Besteld/Openstaand.
$GLOBALS['ELPIS_TODAY'] = '2026-10-28';
$resRow = static fn(int $entry, bool $pos, int $type, string $id, int $ref, float $qty, string $item = 'PK-422/8'): array => [
    'Entry_No' => $entry, 'Positive' => $pos, 'Item_No' => $item, 'Quantity_Base' => $pos ? $qty : -$qty, 'Quantity' => $pos ? $qty : -$qty,
    'Reservation_Status' => 'Reservation', 'Source_Type' => $type, 'Source_Subtype' => $type === 1003 ? '2' : '1',
    'Source_ID' => $id, 'Source_Ref_No' => $ref, 'Expected_Receipt_Date' => '2026-09-24',
];
$resLine = planningLine([
    'item_no' => 'PK-422/8', 'quantity' => 2, 'qty_to_order' => 2, 'qty_ordered' => 0, 'qty_open' => 0,
    'purchase_order_no' => '', 'purchase_order_line_no' => 0, 'job_contract_entry_no' => 96419, 'material_status' => 'I',
]);
$resPurchase = [['Document_No' => 'PO12605553', 'Line_No' => 10000, 'No' => 'PK-422/8', 'Type' => 'Item', 'Quantity' => 5,
    'Outstanding_Quantity' => 5, 'Expected_Receipt_Date' => '2026-09-24']];
$resApplied = elpis_apply_receipt_expectations(['PRJ2608099' => [$resLine]], [
    $resRow(2890377, false, 1003, 'PRJ2608099', 96419, 2),
    $resRow(2890377, true, 39, 'PO12605553', 10000, 2),
], $resPurchase, [])['PRJ2608099'][0];
if (abs($resApplied['qty_ordered'] - 2) > 0.001 || abs($resApplied['qty_open'] - 2) > 0.001 || abs($resApplied['qty_to_order']) > 0.001) {
    fail('reservering op PO: Besteld/Openstaand 2, Te bestellen 0: ' . json_encode($resApplied));
}
if (elpis_line_expected_receipt_display($resApplied) !== 'Te laat – PO12605553, 24-09-2026') {
    fail('te laat met PO en datum: ' . elpis_line_expected_receipt_display($resApplied));
}
// Twee reserveringen op twee PO's: opgeteld, beide getoond.
$resLine2 = array_merge($resLine, ['quantity' => 3, 'qty_to_order' => 3]);
$two = elpis_apply_receipt_expectations(['PRJ2608099' => [$resLine2]], [
    $resRow(1, false, 1003, 'PRJ2608099', 96419, 2), $resRow(1, true, 39, 'PO12605553', 10000, 2),
    $resRow(2, false, 1003, 'PRJ2608099', 96419, 1), $resRow(2, true, 39, 'PO12605554', 10000, 1),
], array_merge($resPurchase, [array_merge($resPurchase[0], ['Document_No' => 'PO12605554'])]), [])['PRJ2608099'][0];
if (abs($two['qty_ordered'] - 3) > 0.001 || abs($two['qty_open'] - 3) > 0.001 || abs($two['qty_to_order']) > 0.001) {
    fail('twee reserveringen opgeteld: ' . json_encode($two));
}
if (elpis_line_expected_receipt_display($two) !== 'Te laat – PO12605553, 24-09-2026; PO12605554, 24-09-2026') {
    fail('twee PO\'s getoond: ' . elpis_line_expected_receipt_display($two));
}
// Direct gekoppeld (LVS_Purchase_Order_No) én gereserveerd: niet dubbel tellen.
$direct = elpis_apply_receipt_expectations(['PRJ2608099' => [array_merge($resLine, [
    'purchase_order_no' => 'PO12605553', 'purchase_order_line_no' => 10000, 'qty_to_order' => 0, 'qty_ordered' => 2, 'qty_open' => 2,
])]], [
    $resRow(2890377, false, 1003, 'PRJ2608099', 96419, 2), $resRow(2890377, true, 39, 'PO12605553', 10000, 2),
], $resPurchase, [])['PRJ2608099'][0];
if (abs($direct['qty_ordered'] - 5) > 0.001 || abs($direct['qty_open'] - 5) > 0.001) { // PO-regel zelf, niet + 2
    fail('direct + reservering niet dubbel: ' . json_encode($direct));
}
// Voorraadreservering (Source_Type 32): PWR-ontvangst tonen.
$stockLine = array_merge($resLine, ['item_no' => 'PK-T435497', 'material_status' => 'V']);
$stock = elpis_apply_receipt_expectations(['PRJ2608099' => [$stockLine]], [
    $resRow(3, false, 1003, 'PRJ2608099', 96419, 2, 'PK-T435497'), $resRow(3, true, 32, '', 555, 2, 'PK-T435497'),
], [], [], ['stock_ledger' => [555 => ['document_no' => 'PWR2601849', 'posting_date' => '2026-02-16']]])['PRJ2608099'][0];
$stockText = elpis_line_expected_receipt_display($stock);
if (!str_starts_with($stockText, 'Uit voorraad') || !str_contains($stockText, 'PWR2601849 (16-02-2026)')) {
    fail('uit voorraad met PWR: ' . $stockText . ' ' . json_encode($stock));
}
// Pick aangemaakt zonder ontvangst op de regel: niet "Reeds ontvangen".
$pick = planningLine(['material_status' => 'M', 'remaining_qty' => 0, 'qty_received' => 0, 'purchase_order_no' => '', 'qty_ordered' => 0, 'qty_open' => 0]);
if (elpis_line_expected_receipt_display($pick) !== 'Status M, geen ontvangst of reservering gevonden') {
    fail('pick aangemaakt: ' . elpis_line_expected_receipt_display($pick));
}
$fetched = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query) use (&$fetched): array { $fetched[] = [$entity, $query]; return [['Entry_No' => 555, 'Document_No' => 'PWR2601849', 'Posting_Date' => '2026-02-16']]; };
$ledgerMap = elpis_fetch_stock_supply_ledger_entries('X', [elpis_normalize_reservation_entry($resRow(3, true, 32, '', 555, 2))], 0);
if (($fetched[0][0] ?? '') !== ELPI_ITEM_LEDGER_ENTITY || ($fetched[0][1]['$filter'] ?? '') !== 'Entry_No eq 555' || ($ledgerMap[555]['document_no'] ?? '') !== 'PWR2601849' || ($ledgerMap[555]['posting_date'] ?? '') !== '2026-02-16') {
    fail('voorraadpost op Entry_No ophalen: ' . json_encode([$fetched, $ledgerMap]));
}
unset($GLOBALS['ELPIS_FETCH_OBSERVER'], $GLOBALS['ELPIS_TODAY']);

// Te bestellen = Aantal min gereserveerd (voorraad + inkoop).
$GLOBALS['ELPIS_TODAY'] = '2026-10-28';
$sevLine = array_merge($resLine, ['item_no' => 'PK-SEV152D', 'quantity' => 16, 'qty_to_order' => 16, 'material_status' => 'V']);
$sev = elpis_apply_receipt_expectations(['PRJ2608099' => [$sevLine]], [
    $resRow(7, false, 1003, 'PRJ2608099', 96419, 10, 'PK-SEV152D'), $resRow(7, true, 32, '', 777, 10, 'PK-SEV152D'),
], [], [])['PRJ2608099'][0];
if (abs($sev['qty_to_order'] - 6) > 0.001) {
    fail('deels uit voorraad: rest te bestellen: ' . json_encode($sev));
}
$full = elpis_apply_receipt_expectations(['PRJ2608099' => [array_merge($sevLine, ['quantity' => 2, 'qty_to_order' => 2])]], [
    $resRow(8, false, 1003, 'PRJ2608099', 96419, 1, 'PK-SEV152D'), $resRow(8, true, 32, '', 778, 1, 'PK-SEV152D'),
    $resRow(9, false, 1003, 'PRJ2608099', 96419, 1, 'PK-SEV152D'), $resRow(9, true, 32, '', 779, 1, 'PK-SEV152D'),
], [], [])['PRJ2608099'][0];
if (abs($full['qty_to_order']) > 0.001) {
    fail('volledig uit voorraad: 0 te bestellen: ' . json_encode($full));
}
$none = elpis_apply_receipt_expectations(['PRJ2608099' => [array_merge($sevLine, ['quantity' => 1, 'qty_to_order' => 1, 'job_contract_entry_no' => 109650])]], [
    $resRow(8, false, 1003, 'PRJ2608099', 96419, 1, 'PK-SEV152D'), $resRow(8, true, 32, '', 778, 1, 'PK-SEV152D'),
], [], [])['PRJ2608099'][0];
if (abs($none['qty_to_order'] - 1) > 0.001) {
    fail('zonder reservering blijft Aantal: ' . json_encode($none));
}
$pickRes = planningLine(['material_status' => 'M', 'remaining_qty' => 0, 'qty_received' => 0, 'purchase_order_no' => '', 'reserved_from_stock' => true]);
if (elpis_line_expected_receipt_display($pickRes) === 'Status M, geen ontvangst of reservering gevonden') {
    fail('M met reservering is niet onbewezen');
}
unset($GLOBALS['ELPIS_TODAY']);

// PR33: Usage_Link, M-label, deels gedekt
$row = ['Job_No'=>'P','Line_No'=>1,'No'=>'X','Quantity'=>2,'Remaining_Qty'=>0,'Usage_Link'=>false,'KVT_Status_Material'=>'M'];
$n = elpis_normalize_planning_line_row($row);
if ($n['remaining_qty'] !== null || elpis_line_is_received($n)) { echo "FAIL usage_link false remaining\n"; exit(1); }
if (elpis_line_material_status_label($n) !== 'Status M, geen ontvangst of reservering gevonden') { echo "FAIL M neutral label\n"; exit(1); }
if (elpis_line_expected_receipt_display($n) !== 'Status M, geen ontvangst of reservering gevonden') { echo "FAIL M neutral display: ".elpis_line_expected_receipt_display($n)."\n"; exit(1); }
$n2 = elpis_normalize_planning_line_row(['Usage_Link'=>true,'Remaining_Qty'=>0,'Quantity'=>2,'KVT_Status_Material'=>'M','KVT_Qty_in_Whse_Pick'=>2]);
if ($n2['remaining_qty'] !== 0.0 || elpis_line_material_status_label($n2) !== 'Pick aangemaakt') { echo "FAIL usage_link true / pick\n"; exit(1); }
if (elpis_line_material_status_label(['material_status'=>'M','kvt_qty_picked'=>1]) !== 'Deels gepickt') { echo "FAIL partial pick\n"; exit(1); }
if (elpis_line_material_status_label(['material_status'=>'M','completely_picked'=>true]) !== 'Gepicked') { echo "FAIL complete pick\n"; exit(1); }
$p = ['quantity'=>16,'reserved_qty'=>1,'reserved_purchase_qty'=>0,'reserved_from_stock'=>true,'stock_receipts'=>['PWR2635059'=>'2026-01-01']];
if (elpis_line_partial_stock_text($p) !== 'Uit voorraad 1 (PWR2635059) · 15 nog te bestellen') { echo "FAIL partial: ".elpis_line_partial_stock_text($p)."\n"; exit(1); }
if (elpis_line_partial_stock_text(['quantity'=>1]+$p) !== '') { echo "FAIL full cover\n"; exit(1); }

// PR34: M met ontvangst/PO is niet de neutrale tekst
$g = elpis_line_material_status_group(['material_status'=>'M','quantity'=>4,'qty_received'=>4,'received_date'=>'2026-07-21','completely_received'=>true]);
if ($g['key'] !== 'M_RECEIVED' || $g['label'] !== 'Reeds ontvangen (21-07-2026)') { echo "FAIL M received: ".json_encode($g)."\n"; exit(1); }
$g = elpis_line_material_status_group(['material_status'=>'M','quantity'=>2]);
if ($g['key'] !== 'M_UNPROVEN') { echo "FAIL M unproven\n"; exit(1); }
if (elpis_line_is_pick_without_receipt(['material_status'=>'M','purchase_order_no'=>'PO1'])) { echo "FAIL M with PO\n"; exit(1); }

// PR34: inkoopeenheid -> basiseenheid
$uomLines = elpis_apply_purchase_uom('X', [
    ['item_no'=>'I1','uom_code'=>'','quantity'=>3.0,'outstanding_qty'=>1.0,'uom_factor'=>1.0],
    elpis_normalize_purchase_order_line(['Document_No'=>'P','Line_No'=>1,'No'=>'I2','Quantity'=>2,'Outstanding_Quantity'=>2,'Unit_of_Measure_Code'=>'']),
]);
if ($uomLines[0]['quantity'] !== 3.0 || $uomLines[1]['quantity'] !== 2.0 || $uomLines[1]['uom_factor'] !== 1.0) { echo "FAIL uom default 1\n"; exit(1); }
echo "OK\n";
