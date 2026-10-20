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
if (!str_contains(elpis_purchase_line_select_fields(), 'Outstanding_Quantity') || !str_contains(elpis_purchase_line_select_fields(), 'LVS_Completely_Received')) {
    fail('inkoopselect moet Outstanding_Quantity en LVS_Completely_Received lezen');
}

$jobFilters = elpis_job_scoped_filters(array_map(static fn (int $i): string => 'P' . $i, range(1, 13)));
if (count($jobFilters) !== 2 || !str_contains($jobFilters[0], "Type eq 'Artikel'") || filter_ors_cross_fields($jobFilters[0])) {
    fail('planningsregels blijven in chunks van 12 op Job_No');
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
if (elpis_line_expected_receipt_display($pi) !== 'Te laat') {
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
if (($onTime['PRJ2606522'][0]['receipt_state'] ?? '') !== 'date' || elpis_line_expected_receipt_display($onTime['PRJ2606522'][0]) !== '08-10-2026') {
    fail('vóór de datum blijft de verwachte ontvangstdatum zichtbaar');
}
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$staleOnTime = $onTime['PRJ2606522'][0];
if (elpis_line_expected_receipt_display($staleOnTime) !== 'Te laat' || elpis_line_row_tone($staleOnTime) !== 'alert') {
    fail('een gecachte regel met verstreken datum wordt alsnog te laat');
}

$promisedOnly = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine()]], [], [[
    'document_no' => 'PO12606033',
    'line_no' => 10000,
    'expected_receipt_date' => '',
    'promised_receipt_date' => '2026-10-21',
    'planned_receipt_date' => '2026-10-01',
    'completely_received' => false,
]], []);
if (($promisedOnly['PRJ2606522'][0]['expected_receipt_date'] ?? '') !== '2026-10-21' || !str_contains((string) ($promisedOnly['PRJ2606522'][0]['expected_receipt_source'] ?? ''), 'Promised_Receipt_Date')) {
    fail('Promised gaat voor op Planned: ' . json_encode($promisedOnly['PRJ2606522'][0]));
}

$kvtWins = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine(['expected_receipt_date' => '2026-12-01'])]], [], $purchase, []);
if (($kvtWins['PRJ2606522'][0]['expected_receipt_date'] ?? '') !== '2026-12-01') {
    fail('KVT_Expected_Receipt_Date gaat voor op de inkoopregel');
}

$received = elpis_apply_receipt_expectations(['PRJ2606522' => [planningLine()]], [], [[
    'document_no' => 'PO12606033',
    'line_no' => 10000,
    'expected_receipt_date' => '2026-10-08',
    'completely_received' => true,
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
])]], [], [[
    'document_no' => 'PO12601846',
    'line_no' => 10000,
    'item_no' => 'PI20001186',
    'expected_receipt_date' => '2026-09-01',
    'completely_received' => true,
]], []);
$kvtCompletelyLine = $kvtCompletely['P'][0];
if (($kvtCompletelyLine['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($kvtCompletelyLine) || elpis_line_expected_receipt_display($kvtCompletelyLine) === 'Te laat' || elpis_mail_line_matches_content($kvtCompletelyLine, 'both')) {
    fail('LVS_Completely_Received telt ook met een KVT-datum: ' . json_encode($kvtCompletelyLine));
}
if ((elpis_line_tooltips($kvtCompletelyLine)['expected_receipt'] ?? '') !== '(PurchaseLines -> LVS_Completely_Received)') {
    fail('tooltip noemt LVS_Completely_Received: ' . json_encode(elpis_line_tooltips($kvtCompletelyLine)));
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
        if (($sampleLine['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($sampleLine) !== 'Te laat' || !elpis_mail_line_matches_content($sampleLine, 'both')) {
            fail($itemNo . ' hoort te laat te blijven: ' . json_encode($sampleLine));
        }
        continue;
    }
    if (($sampleLine['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($sampleLine) || elpis_line_expected_receipt_display($sampleLine) === 'Te laat' || elpis_mail_line_matches_content($sampleLine, 'late') || elpis_mail_line_matches_content($sampleLine, 'both')) {
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
if (($statusTLine['material_status'] ?? '') !== 'T' || elpis_line_filter_status($statusTLine) !== 'T' || elpis_line_row_tone($statusTLine) !== 'alert' || elpis_line_expected_receipt_display($statusTLine) === 'Te laat') {
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
if (($spoed['receipt_state'] ?? '') !== 'received' || elpis_line_is_late($spoed) || elpis_line_expected_receipt_display($spoed) === 'Te laat' || elpis_line_row_tone($spoed) === 'alert' || ($spoed['material_status'] ?? '') !== 'O' || elpis_mail_line_matches_content($spoed, 'both')) {
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
if (($partialNoWo['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($partialNoWo) !== 'Te laat' || ($partialNoWo['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($partialNoWo, 'both')) {
    fail('deels ontvangen zonder werkorder blijft te laat: ' . json_encode($partialNoWo));
}
if (($statusTNoWo['work_order_no'] ?? '') !== '' || ($statusTNoWo['material_status'] ?? '') !== 'T' || elpis_line_filter_status($statusTNoWo) !== 'T' || elpis_line_row_tone($statusTNoWo) !== 'alert' || elpis_line_expected_receipt_display($statusTNoWo) === 'Te laat') {
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
    if (elpis_line_is_received($shownLine) || !elpis_line_is_late($shownLine) || ($shownLine['receipt_state'] ?? '') !== 'late' || ($shownPayload['expected_receipt'] ?? '') !== 'Te laat' || ($shownPayload['receipt_state'] ?? '') !== 'late' || ($shownLine['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($shownLine, 'both')) {
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
if (elpis_line_shown_received_qty($shownPartial) + 0.00001 >= elpis_line_shown_ordered_qty($shownPartial) || ($shownPartial['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($shownPartial) !== 'Te laat' || !elpis_line_is_late($shownPartial) || ($shownPartial['material_status'] ?? '') !== 'O' || !elpis_mail_line_matches_content($shownPartial, 'both')) {
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
if (($shownStatusT['material_status'] ?? '') !== 'T' || ($shownStatusT['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($shownStatusT) !== 'Te laat' || elpis_line_row_tone($shownStatusT) !== 'alert' || !elpis_line_is_late($shownStatusT)) {
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
if (($shownStatusTFull['material_status'] ?? '') !== 'T' || ($shownStatusTFull['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($shownStatusTFull) !== 'Te laat' || elpis_line_row_tone($shownStatusTFull) !== 'alert' || !elpis_line_is_late($shownStatusTFull) || elpis_line_shown_received_qty($shownStatusTFull) > 0.00001) {
    fail('materiaalstatus T zonder echte ontvangst blijft te laat en rood: ' . json_encode($shownStatusTFull));
}

// BC: niet ontvangen, maar LVS_Outstanding_Qty_Base op de werkorderregel is 0.
// Ontvangen mag alleen uit de inkoopregel (Outstanding_Quantity) of een ontvangstpost.
$openPurchaseCases = [
    ['item_no' => 'PI24003010', 'work_order_no' => '4066162', 'line_no' => 8000, 'purchase_order_no' => '4500124003', 'quantity' => 2.0, 'outstanding_qty' => 2.0],
    ['item_no' => 'PI28127010', 'work_order_no' => '4066162', 'line_no' => 13000, 'purchase_order_no' => '4500128127', 'quantity' => 1.0, 'outstanding_qty' => 1.0],
    ['item_no' => 'PI27751010', 'work_order_no' => '4066164', 'line_no' => 14000, 'purchase_order_no' => '4500127751', 'quantity' => 1.0, 'outstanding_qty' => 1.0],
];
$openPurchaseLines = [];
$openPurchaseInkoop = [];
$openPurchaseOrders = [];
foreach ($openPurchaseCases as $case) {
    $openPurchaseLines[] = elpis_normalize_planning_line_row([
        'Job_No' => 'PRJ4066162',
        'Job_Task_No' => '100',
        'Line_No' => $case['line_no'],
        'No' => $case['item_no'],
        'Description' => $case['item_no'],
        'Quantity' => $case['quantity'],
        'Remaining_Qty' => $case['quantity'],
        'LVS_Work_Order_No' => $case['work_order_no'],
        'LVS_Purchase_Order_No' => $case['purchase_order_no'],
        'LVS_Purchase_Order_Line_No' => 10000,
        'LVS_Quantity_Order_UoM' => $case['quantity'],
        'LVS_Outstanding_Qty_Base' => 0,
        'LVS_Completely_Received' => false,
        'KVT_Expected_Receipt_Date' => '2026-09-01',
        'KVT_Status_Material' => 'X',
    ]);
    $normalizedOpen = $openPurchaseLines[array_key_last($openPurchaseLines)];
    if (elpis_line_shown_received_qty($normalizedOpen) > 0.00001 || abs(elpis_line_shown_ordered_qty($normalizedOpen) - $case['quantity']) > 0.00001) {
        fail($case['item_no'] . ' leidt Ontvangen af uit de werkorderregel: ' . json_encode($normalizedOpen));
    }
    $openPurchaseInkoop[] = [
        'Job_No' => 'PRJ4066162',
        'Job_Task_No' => '100',
        'Line_No' => $case['line_no'],
        'No' => $case['item_no'],
        'LVS_Work_Order_No' => $case['work_order_no'],
        'LVS_Purchase_Order_No' => $case['purchase_order_no'],
        'LVS_Purchase_Order_Line_No' => 10000,
        'LVS_Quantity_Order_UoM' => $case['quantity'],
        'LVS_Outstanding_Qty_Base' => 0,
        'LVS_Completely_Received' => false,
        'KVT_Status_Material' => 'X',
    ];
    $openPurchaseOrders[] = [
        'document_no' => $case['purchase_order_no'],
        'line_no' => 10000,
        'item_no' => $case['item_no'],
        'outstanding_qty' => $case['outstanding_qty'],
        'completely_received' => false,
    ];
}
$openPurchase = elpis_apply_receipt_expectations(
    elpis_attach_inkoop_planning_rows(['PRJ4066162' => $openPurchaseLines], $openPurchaseInkoop),
    [],
    $openPurchaseOrders,
    []
);
foreach ($openPurchase['PRJ4066162'] as $openLine) {
    $itemNo = (string) ($openLine['item_no'] ?? '');
    $payload = elpis_line_public_payload($openLine, 'PRJ4066162');
    $ordered = elpis_line_shown_ordered_qty($openLine);
    $received = elpis_line_shown_received_qty($openLine);
    if ($received > 0.00001 || abs($ordered - $received) <= 0.00001 || ($payload['qty_received'] ?? -1) > 0.00001 || ($payload['qty_ordered'] ?? 0) <= 0.00001) {
        fail($itemNo . ' toont ontvangen = besteld terwijl Outstanding_Quantity open is: ' . json_encode($payload));
    }
    if (elpis_line_is_received($openLine) || ($openLine['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($openLine) !== 'Te laat' || ($payload['expected_receipt'] ?? '') !== 'Te laat' || ($openLine['material_status'] ?? '') !== 'X' || !empty($openLine['completely_received']) || elpis_line_row_tone($openLine) !== 'alert' || !elpis_mail_line_matches_content($openLine, 'both')) {
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
if (abs(elpis_line_shown_received_qty($openPartialLine) - 1) > 0.00001 || abs(elpis_line_shown_ordered_qty($openPartialLine) - 2) > 0.00001 || ($openPartialLine['receipt_state'] ?? '') !== 'late' || elpis_line_expected_receipt_display($openPartialLine) !== 'Te laat' || elpis_line_is_received($openPartialLine)) {
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
if (($ledgerPartialLine['receipt_state'] ?? '') === 'received' || elpis_line_expected_receipt_display($ledgerPartialLine) !== 'Te laat' || ($ledgerPartialLine['material_status'] ?? '') !== 'I') {
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
        return [['No' => 'WO2610118', 'Description' => 'Werkorder']];
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
    if ($call['entity'] === ELPI_PURCHASE_ORDER_LINE_ENTITY && !str_contains($filter, "Document_No eq 'PO12606033'")) {
        fail('inkoop is niet tot het document beperkt: ' . $filter);
    }
    if ($call['entity'] === ELPI_ITEM_LIST_ENTITY && !str_contains($filter, "No eq 'PI20002946'")) {
        fail('voorraad is niet tot het artikel beperkt: ' . $filter);
    }
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
if (ELPI_PROJECT_CACHE_VERSION !== 5) {
    fail('projectcacheversie moet 5 zijn zodat afgeleide Ontvangen uit #12 opnieuw wordt berekend');
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
if (($reread['lines'][0]['receipt_state'] ?? '') === 'received' || !elpis_line_is_late($reread['lines'][0]) || elpis_line_expected_receipt_display($reread['lines'][0]) !== 'Te laat' || ($reread['lines'][0]['material_status'] ?? '') !== 'T' || elpis_line_row_tone($reread['lines'][0]) !== 'alert') {
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

echo "OK\n";
