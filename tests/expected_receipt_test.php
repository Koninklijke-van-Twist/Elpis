<?php
/**
 * Afleiding van verwachte ontvangstdatums voor projectartikelen (PI) en voorraadartikelen (AO).
 * Run: php tests/expected_receipt_test.php
 */

$_SESSION['lang'] = 'nl';

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function line(array $overrides = []): array
{
    return array_merge([
        'job_task_no' => '100',
        'line_no' => 10000,
        'item_no' => '1000',
        'description' => 'Voorraadartikel',
        'qty_to_order' => 0.0,
        'qty_ordered' => 4.0,
        'qty_open' => 4.0,
        'qty_received' => 0.0,
        'purchase_order_no' => '',
        'completely_received' => false,
        'expected_receipt_date' => '',
        'item_kind' => '',
        'receipt_state' => '',
    ], $overrides);
}

function demand(int $entryNo, string $jobNo, int $lineNo, string $itemNo, float $qty): array
{
    return [
        'entry_no' => $entryNo,
        'positive' => false,
        'item_no' => $itemNo,
        'quantity_base' => -$qty,
        'quantity' => -$qty,
        'status' => 'Reservation',
        'source_type' => ELPI_JOB_PLANNING_SOURCE_TYPE,
        'source_subtype' => 2,
        'source_id' => $jobNo,
        'source_ref_no' => $lineNo,
        'expected_receipt_date' => '',
    ];
}

function stockSupply(int $entryNo, string $itemNo, float $qty): array
{
    return [
        'entry_no' => $entryNo,
        'positive' => true,
        'item_no' => $itemNo,
        'quantity_base' => $qty,
        'quantity' => $qty,
        'status' => 'Reservation',
        'source_type' => ELPI_ITEM_LEDGER_SOURCE_TYPE,
        'source_subtype' => 0,
        'source_id' => '',
        'source_ref_no' => 0,
        'expected_receipt_date' => '',
    ];
}

function purchaseSupply(int $entryNo, string $itemNo, float $qty, string $documentNo, int $lineNo, string $date): array
{
    return [
        'entry_no' => $entryNo,
        'positive' => true,
        'item_no' => $itemNo,
        'quantity_base' => $qty,
        'quantity' => $qty,
        'status' => 'Reservation',
        'source_type' => ELPI_PURCHASE_LINE_SOURCE_TYPE,
        'source_subtype' => 1,
        'source_id' => $documentNo,
        'source_ref_no' => $lineNo,
        'expected_receipt_date' => $date,
    ];
}

if (!elpis_is_project_item_no('PI100') || !elpis_is_project_item_no('pi-20') || !elpis_is_project_item_no('PI')) {
    fail('PI-nummerreeks wordt niet herkend');
}
if (elpis_is_project_item_no('PISTON') || elpis_is_project_item_no('AO100') || elpis_is_project_item_no('1000')) {
    fail('gewone artikelen mogen niet als PI tellen');
}

foreach ([
    elpis_reservation_demand_filter(),
    elpis_reservation_purchase_filter(),
    elpis_reservation_stock_filter(),
    elpis_purchase_line_open_filter(),
    "Type eq 'Artikel'",
] as $filter) {
    if (!elpis_receipt_filters_are_department_independent($filter)) {
        fail('filter is aan een afdeling gebonden: ' . $filter);
    }
}

if (!str_contains(elpis_planning_line_select_fields(), 'Line_No') || !str_contains(elpis_planning_line_select_fields(), 'KVT_Expected_Receipt_Date')) {
    fail('planningsregel-select mist Line_No of KVT_Expected_Receipt_Date');
}
if (ELPI_NIGHTLY_MAX_AGE !== 14400) {
    fail('nightly max_age moet 4 uur blijven');
}
if (ELPI_RESERVATION_ENTRY_ENTITY !== 'Reservation_Entries' || ELPI_PURCHASE_ORDER_LINE_ENTITY !== 'PurchaseOrderLines' || ELPI_ITEM_LIST_ENTITY !== 'ItemList') {
    fail('entity-setnamen zijn verschoven');
}

$purchaseLines = [
    [
        'document_no' => 'IO-9',
        'line_no' => 20000,
        'item_no' => '4000',
        'expected_receipt_date' => '',
        'promised_receipt_date' => '2026-09-01',
        'planned_receipt_date' => '2026-08-01',
    ],
    [
        'document_no' => 'IO-9',
        'line_no' => 30000,
        'item_no' => '4001',
        'expected_receipt_date' => '',
        'promised_receipt_date' => '',
        'planned_receipt_date' => '2026-08-15',
    ],
    [
        'document_no' => 'PO1',
        'line_no' => 10000,
        'item_no' => '2000',
        'expected_receipt_date' => '2026-01-01',
        'promised_receipt_date' => '',
        'planned_receipt_date' => '',
    ],
];

$reservations = [
    demand(5, 'P-200', 10000, '2000', 3),
    purchaseSupply(5, '2000', 3, 'PO1', 10000, '2026-12-01'),
    demand(7, 'P-300', 10000, '3000', 10),
    stockSupply(7, '3000', 4),
    purchaseSupply(7, '3000', 6, 'PO3', 10000, '2026-10-20'),
    demand(8, 'P-301', 10000, '3001', 10),
    stockSupply(8, '3001', 4),
    purchaseSupply(8, '3001', 6, 'PO3', 20000, '2026-10-21'),
];

$lines = [
    'P-100' => [
        line([
            'item_no' => 'PI100',
            'description' => 'Projectartikel',
            'qty_open' => 1,
            'qty_ordered' => 1,
            'expected_receipt_date' => '2026-11-02',
            'line_no' => 10000,
        ]),
        line([
            'item_no' => 'PI200',
            'description' => 'Projectartikel zonder datum',
            'qty_to_order' => 2,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'expected_receipt_date' => '',
            'line_no' => 20000,
        ]),
    ],
    'P-050' => [
        line([
            'item_no' => '1000',
            'qty_open' => 4,
            'qty_ordered' => 4,
            'line_no' => 10000,
        ]),
    ],
    'P-060' => [
        line([
            'item_no' => '1000',
            'qty_open' => 8,
            'qty_ordered' => 8,
            'line_no' => 10000,
        ]),
    ],
    'P-200' => [
        line([
            'item_no' => '2000',
            'qty_open' => 3,
            'qty_ordered' => 3,
            'line_no' => 10000,
        ]),
    ],
    'P-300' => [
        line([
            'item_no' => '3000',
            'qty_open' => 10,
            'qty_ordered' => 10,
            'line_no' => 10000,
        ]),
    ],
    'P-301' => [
        line([
            'item_no' => '3001',
            'qty_open' => 10,
            'qty_ordered' => 10,
            'line_no' => 10000,
        ]),
    ],
    'P-400' => [
        line([
            'item_no' => '4000',
            'qty_open' => 2,
            'qty_ordered' => 2,
            'purchase_order_no' => 'IO-9',
            'line_no' => 10000,
        ]),
        line([
            'item_no' => '4001',
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'purchase_order_no' => 'IO-9',
            'line_no' => 20000,
        ]),
        line([
            'item_no' => '5000',
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'line_no' => 30000,
        ]),
        line([
            'item_no' => '6000',
            'qty_open' => 0,
            'qty_ordered' => 2,
            'qty_received' => 2,
            'completely_received' => true,
            'line_no' => 40000,
        ]),
    ],
];

$result = elpis_apply_receipt_expectations($lines, $reservations, $purchaseLines, [
    '1000' => 6,
    '3000' => 4,
    '3001' => 24,
    '4000' => 0,
    '4001' => 0,
    '5000' => 0,
]);

$pi = $result['P-100'][0];
if (($pi['item_kind'] ?? '') !== 'PI' || ($pi['receipt_state'] ?? '') !== 'date' || ($pi['expected_receipt_date'] ?? '') !== '2026-11-02') {
    fail('PI moet de BC-datum KVT_Expected_Receipt_Date houden: ' . json_encode($pi));
}
if (elpis_line_expected_receipt_display($pi) !== '02-11-2026') {
    fail('PI-datum moet Nederlands leesbaar zijn');
}

$piMissing = $result['P-100'][1];
if (($piMissing['receipt_state'] ?? '') !== 'missing' || elpis_line_expected_receipt_display($piMissing) !== 'Datum ontbreekt') {
    fail('PI zonder inkooporder en zonder datum moet datum ontbreekt tonen');
}

$firstStock = $result['P-050'][0];
$secondStock = $result['P-060'][0];
if (($firstStock['receipt_state'] ?? '') !== 'on_stock') {
    fail('eerste voorraadregel moet op voorraad zijn, voorraad wordt niet twee keer uitgegeven');
}
if (($secondStock['receipt_state'] ?? '') !== 'missing') {
    fail('tweede voorraadregel mag dezelfde voorraad niet nog eens claimen: ' . json_encode($secondStock));
}
if (elpis_line_expected_receipt_display($firstStock) !== 'Op voorraad') {
    fail('voorraadtekst klopt niet');
}

$fromPo = $result['P-200'][0];
if (($fromPo['receipt_state'] ?? '') !== 'date' || ($fromPo['expected_receipt_date'] ?? '') !== '2026-12-01') {
    fail('reservering naar inkooporder moet de Expected Receipt Date van de reservering gebruiken, niet de eerdere orderregel: ' . json_encode($fromPo));
}

$partial = $result['P-300'][0];
if (($partial['receipt_state'] ?? '') !== 'partial' || ($partial['expected_receipt_date'] ?? '') !== '2026-10-20') {
    fail('gedeeltelijke reservering moet de inkoopdatum tonen: ' . json_encode($partial));
}
if (elpis_line_expected_receipt_display($partial) !== 'Deels op voorraad, verwacht 20-10-2026') {
    fail('gedeeltelijke tekst klopt niet: ' . elpis_line_expected_receipt_display($partial));
}

$covered = $result['P-301'][0];
if (($covered['receipt_state'] ?? '') !== 'on_stock') {
    fail('vrije voorraad moet een gedeeltelijke inkoopreservering alsnog dekken: ' . json_encode($covered));
}

$promised = $result['P-400'][0];
if (($promised['receipt_state'] ?? '') !== 'date' || ($promised['expected_receipt_date'] ?? '') !== '2026-09-01') {
    fail('zonder reservering geldt Promised Receipt Date vóór Planned: ' . json_encode($promised));
}

$planned = $result['P-400'][1];
if (($planned['expected_receipt_date'] ?? '') !== '2026-08-15') {
    fail('Planned Receipt Date is de laatste terugval: ' . json_encode($planned));
}

$missingAo = $result['P-400'][2];
if (($missingAo['receipt_state'] ?? '') !== 'missing') {
    fail('AO zonder order en zonder voorraad moet datum ontbreekt zijn');
}

if (($result['P-400'][3]['receipt_state'] ?? '') !== 'received') {
    fail('volledig ontvangen regel telt niet meer mee');
}

$summary = elpis_project_execution_summary([
    $result['P-100'][0],
    $fromPo,
    $firstStock,
]);
if (($summary['state'] ?? '') !== 'date' || ($summary['date'] ?? '') !== '2026-12-01') {
    fail('uitvoering plannen vanaf moet de laatste verwachte datum zijn: ' . json_encode($summary));
}
if (elpis_project_execution_value($summary) !== '01-12-2026') {
    fail('samenvattingsdatum is niet Nederlands');
}

$blocked = elpis_project_execution_summary([$result['P-100'][0], $piMissing]);
if (($blocked['state'] ?? '') !== 'missing' || elpis_project_execution_value($blocked) !== 'datum ontbreekt') {
    fail('een ontbrekende datum blokkeert de projectsamenvatting');
}

$direct = elpis_project_execution_summary([$firstStock]);
if (($direct['state'] ?? '') !== 'direct' || elpis_project_execution_value($direct) !== 'direct') {
    fail('alles op voorraad moet direct planbaar zijn');
}

$none = elpis_project_execution_summary([$result['P-400'][3]]);
if (($none['state'] ?? '') !== 'none') {
    fail('alleen ontvangen regels geven geen samenvatting');
}

$normalized = elpis_normalize_reservation_entry([
    'Entry_No' => 9,
    'Positive' => true,
    'Item_No' => '1000',
    'Quantity_Base' => 2,
    'Source_Type' => 39,
    'Source_ID' => 'IO-1',
    'Source_Ref_No' => 10000,
    'Expected_Receipt_Date' => '0001-01-01',
]);
if ($normalized['positive'] !== true || $normalized['expected_receipt_date'] !== '' || $normalized['source_type'] !== 39) {
    fail('BC-reserveringsrij wordt niet genormaliseerd: ' . json_encode($normalized));
}

$best = elpis_purchase_line_best_date([
    'expected_receipt_date' => '',
    'promised_receipt_date' => '2026-05-02',
    'planned_receipt_date' => '2026-05-01',
]);
if ($best !== '2026-05-02') {
    fail('datumvolgorde Expected, Promised, Planned klopt niet');
}

if (elpis_receipt_sort_value($firstStock) !== '0000-00-00' || elpis_receipt_sort_value($missingAo) !== '9999-99-99') {
    fail('sorteerwaarde voor voorraad en ontbrekende datum klopt niet');
}

$fallback = elpis_apply_receipt_expectations([
    'P-1' => [
        line([
            'item_no' => '7788',
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'expected_receipt_date' => '2026-07-04',
        ]),
    ],
], [], [], []);
if (($fallback['P-1'][0]['receipt_state'] ?? '') !== 'date' || ($fallback['P-1'][0]['expected_receipt_date'] ?? '') !== '2026-07-04') {
    fail('een gevulde planningsdatum blijft staan als er geen voorraad of inkooporder is: ' . json_encode($fallback['P-1'][0]));
}

echo "OK\n";
