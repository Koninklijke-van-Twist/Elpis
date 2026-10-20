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
foreach (["Reservation_Status eq 'Reservation'", 'Positive eq false', 'Source_Type eq 1003', "Item_No eq 'PI20002946'"] as $part) {
    if (!str_contains($stockFilter, $part)) {
        fail('voorraadreservering mist ' . $part . ': ' . $stockFilter);
    }
}
if (str_contains($stockFilter, 'Positive eq true') || str_contains($stockFilter, 'Source_Type eq 32') || filter_ors_cross_fields($stockFilter)) {
    fail('voorraadreservering heeft het oude aanbodfilter: ' . $stockFilter);
}

$GLOBALS['ELPIS_STOCK_RESERVATION_SOURCE_TYPES'] = [39];
$extended = elpis_item_stock_reservation_filter(['A']);
if (!str_contains($extended, 'Source_Type eq 1003') || !str_contains($extended, 'Source_Type eq 39') || filter_ors_cross_fields($extended)) {
    fail('extra werkorder-brontype moet op hetzelfde veld blijven: ' . $extended);
}
unset($GLOBALS['ELPIS_STOCK_RESERVATION_SOURCE_TYPES']);

$ledger = elpis_item_ledger_filter(['PRJ2606522']);
if (!str_contains($ledger, "Entry_Type eq 'Purchase'") || !str_contains($ledger, "Document_Type eq 'Purchase Receipt'") || !str_contains($ledger, "Job_No eq 'PRJ2606522'") || filter_ors_cross_fields($ledger)) {
    fail('ontvangstboekingen zijn niet op het project beperkt: ' . $ledger);
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
if ((elpis_line_tooltips($pi)['workorder'] ?? '') !== '(AppWerkorders -> No)' || (elpis_line_tooltips($pi)['stock'] ?? '') !== '(AppItems -> InventoryField)') {
    fail('tooltips noemen AppWerkorders en AppItems');
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
if (count($stockCalls) !== 2 || !str_contains($stockCalls[0], 'Source_Type eq 1003') || !str_contains($stockCalls[1], 'Source_Type eq 1003')) {
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
