<?php
/**
 * Verwachte ontvangst voor alle artikelregels.
 * Koppeling: ReservationEntries.Source_Ref_No = WerkordersLines.Job_Contract_Entry_No.
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
        'line_no' => 61000,
        'job_contract_entry_no' => 10000,
        'item_no' => '1000',
        'description' => 'Voorraadartikel',
        'quantity' => 0.0,
        'qty_to_order' => 0.0,
        'qty_ordered' => 4.0,
        'qty_open' => 4.0,
        'qty_received' => 0.0,
        'purchase_order_no' => '',
        'completely_received' => false,
        'expected_receipt_date' => '',
        'receipt_state' => '',
        'covered_by_stock' => 0.0,
    ], $overrides);
}

function demand(int $entryNo, string $jobNo, int $contractEntryNo, string $itemNo, float $qty, string $subtype = '2'): array
{
    return [
        'entry_no' => $entryNo,
        'positive' => false,
        'item_no' => $itemNo,
        'quantity_base' => -$qty,
        'quantity' => -$qty,
        'status' => 'Reservering',
        'source_type' => ELPI_JOB_PLANNING_SOURCE_TYPE,
        'source_subtype' => $subtype,
        'source_id' => $jobNo,
        'source_ref_no' => $contractEntryNo,
        'expected_receipt_date' => '',
    ];
}

function stockSupply(int $entryNo, string $itemNo, float $qty, string $status = 'Reservering'): array
{
    return [
        'entry_no' => $entryNo,
        'positive' => true,
        'item_no' => $itemNo,
        'quantity_base' => $qty,
        'quantity' => $qty,
        'status' => $status,
        'source_type' => ELPI_ITEM_LEDGER_SOURCE_TYPE,
        'source_subtype' => '0',
        'source_id' => '',
        'source_ref_no' => 0,
        'expected_receipt_date' => '0001-01-01',
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
        'status' => 'Reservering',
        'source_type' => ELPI_PURCHASE_LINE_SOURCE_TYPE,
        'source_subtype' => '1',
        'source_id' => $documentNo,
        'source_ref_no' => $lineNo,
        'expected_receipt_date' => $date,
    ];
}

if (elpis_reservation_status_filter() !== "Reservation_Status eq 'Reservering'") {
    fail('reserveringsfilter moet Reservation_Status eq Reservering zijn');
}
if (elpis_planning_line_type_filter() !== "Type eq 'Artikel'") {
    fail('artikelregels filteren op Type eq Artikel');
}
if (str_contains(elpis_reservation_status_filter(), "'Reservation'") || str_contains(elpis_planning_line_type_filter(), 'Item')) {
    fail('Engelse enum-waarden horen niet in de filters');
}

foreach ([
    elpis_reservation_status_filter(),
    elpis_purchase_line_open_filter(),
    elpis_planning_line_type_filter(),
] as $filter) {
    if (!elpis_receipt_filters_are_department_independent($filter)) {
        fail('filter is aan een afdeling gebonden: ' . $filter);
    }
}

$werkordersSelect = elpis_werkorders_line_select_fields();
if (!str_contains($werkordersSelect, 'Job_Contract_Entry_No') || !str_contains($werkordersSelect, 'Job_Task_No') || !str_contains($werkordersSelect, 'Line_No')) {
    fail('WerkordersLines-select mist Job_Contract_Entry_No, Job_Task_No of Line_No');
}
if (!str_contains($werkordersSelect, 'Remaining_Qty') || !str_contains($werkordersSelect, 'Reserved_Quantity')) {
    fail('WerkordersLines-select mist Remaining_Qty of Reserved_Quantity');
}
if (!str_contains(elpis_inkoop_planning_select_fields(), 'KVT_Expected_Receipt_Date') || !str_contains(elpis_inkoop_planning_select_fields(), 'Job_Contract_Entry_No')) {
    fail('inkoopverrijking mist KVT_Expected_Receipt_Date of Job_Contract_Entry_No');
}
if (str_contains(elpis_reservation_select_fields(), 'Source_Batch_Name')) {
    fail('Source_Batch_Name is leeg op projectreserveringen en wordt niet gebruikt');
}
if (ELPI_NIGHTLY_MAX_AGE !== 14400) {
    fail('nightly max_age moet 4 uur blijven');
}
if (ELPI_RESERVATION_ENTRY_ENTITY !== 'ReservationEntries' || ELPI_PLANNING_LINE_ENTITY !== 'WerkordersLines' || ELPI_INKOOP_PLANNING_ENTITY !== 'AppProjectInkoopPlanningsRegel') {
    fail('entity-setnamen zijn verschoven');
}
if (ELPI_PURCHASE_ORDER_LINE_ENTITY !== 'PurchaseOrderLines' || ELPI_ITEM_LIST_ENTITY !== 'ItemList') {
    fail('inkoop- of voorraadentity is verschoven');
}
if (elpis_item_inventory_chunk_count(range(1, 13)) !== 2 || elpis_item_inventory_chunk_count([]) !== 0) {
    fail('voorraadchunks kloppen niet');
}

$normalizedLine = elpis_normalize_planning_line_row([
    'Job_No' => 'PRJ2608308',
    'Job_Task_No' => 'WO2610572',
    'Line_No' => 50000,
    'Job_Contract_Entry_No' => 99965,
    'Type' => 'Artikel',
    'No' => 'PK-28170082',
    'Description' => 'Pakking',
    'Quantity' => 8,
]);
if (($normalizedLine['job_task_no'] ?? '') !== 'WO2610572' || (int) ($normalizedLine['line_no'] ?? 0) !== 50000 || (int) ($normalizedLine['job_contract_entry_no'] ?? 0) !== 99965) {
    fail('WerkordersLines-regel bewaart taak, regelnummer en contractpost niet: ' . json_encode($normalizedLine));
}
if (abs((float) ($normalizedLine['quantity'] ?? 0) - 8) > 0.00001 || isset($normalizedLine['item_kind'])) {
    fail('planningsregel mag artikelsoort niet uit het nummer afleiden: ' . json_encode($normalizedLine));
}
if (elpis_line_remaining_qty($normalizedLine) !== null) {
    fail('zonder Remaining_Qty op de bronrij blijft de geplande Quantity de terugval');
}

$doneLine = elpis_normalize_planning_line_row([
    'Job_Task_No' => 'WO2610572',
    'Line_No' => 50000,
    'Job_Contract_Entry_No' => 99965,
    'No' => 'PK-28170082',
    'Quantity' => 8,
    'Remaining_Qty' => 0,
    'Reserved_Quantity' => 8,
]);
if (elpis_line_remaining_qty($doneLine) !== 0.0 || abs((float) ($doneLine['reserved_quantity'] ?? -1) - 8) > 0.00001) {
    fail('Remaining_Qty en Reserved_Quantity moeten van WerkordersLines komen: ' . json_encode($doneLine));
}
if (elpis_line_still_expecting_receipt($doneLine) || elpis_line_qty_still_needed($doneLine) !== 0.0) {
    fail('Remaining_Qty 0 is af, ook als Quantity nog 8 is');
}

$merged = elpis_merge_inkoop_planning_line($normalizedLine, [
    'Job_No' => 'PRJ2608308',
    'Job_Task_No' => 'WO2610572',
    'Line_No' => 50000,
    'Job_Contract_Entry_No' => 99965,
    'KVT_Expected_Receipt_Date' => '2026-09-19',
    'KVT_Status_Material' => 'I',
    'LVS_Purchase_Order_No' => '',
]);
if (($merged['expected_receipt_date'] ?? '') !== '2026-09-19' || ($merged['material_status'] ?? '') !== 'I') {
    fail('KVT-datum en materiaalstatus komen van de inkooppagina: ' . json_encode($merged));
}
$emptyDate = elpis_merge_inkoop_planning_line($normalizedLine, [
    'KVT_Expected_Receipt_Date' => '0001-01-01',
]);
if (($emptyDate['expected_receipt_date'] ?? '') !== '') {
    fail('0001-01-01 op KVT_Expected_Receipt_Date is geen datum');
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
    [
        'document_no' => 'PO-LEEG',
        'line_no' => 10000,
        'item_no' => '2001',
        'expected_receipt_date' => '2026-04-02',
        'promised_receipt_date' => '',
        'planned_receipt_date' => '',
    ],
    [
        'document_no' => 'PO12606040',
        'line_no' => 10000,
        'item_no' => 'PK-28170082',
        'expected_receipt_date' => '2026-09-19',
        'promised_receipt_date' => '',
        'planned_receipt_date' => '',
        'outstanding_qty' => 1,
    ],
    [
        'document_no' => 'PO12601384',
        'line_no' => 10000,
        'item_no' => 'AO-1SK1524',
        'expected_receipt_date' => '2026-03-24',
        'promised_receipt_date' => '',
        'planned_receipt_date' => '',
        'outstanding_qty' => 1,
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
    demand(11, 'P-201', 10000, '2001', 2),
    purchaseSupply(11, '2001', 2, 'PO-LEEG', 10000, '0001-01-01'),
    demand(2941903, 'PRJ2608308', 99965, 'PK-28170082', 8),
    purchaseSupply(2941903, 'PK-28170082', 1, 'PO12606040', 10000, '2026-09-19'),
    stockSupply(2941903, 'PK-28170082', 7),
    demand(4102, 'PRJ2605044', 88012, 'AO-1SK1524', 1),
    purchaseSupply(4102, 'AO-1SK1524', 1, 'PO12601384', 10000, '2026-03-24'),
    stockSupply(90, 'VRIJ-1', 10, 'Andere'),
    demand(91, 'ANDERS', 555, 'VAST-1', 10),
    stockSupply(91, 'VAST-1', 10),
    demand(92, 'P-SUB', 10000, 'SUB-1', 1, '0'),
    purchaseSupply(92, 'SUB-1', 1, 'PO-SUB', 10000, '2026-06-01'),
];

$lines = [
    'P-100' => [
        line([
            'item_no' => 'PK-100',
            'description' => 'Artikel met KVT-datum',
            'quantity' => 1,
            'qty_open' => 0,
            'qty_ordered' => 0,
            'qty_to_order' => 1,
            'expected_receipt_date' => '2026-11-02',
            'job_contract_entry_no' => 10000,
            'line_no' => 10000,
        ]),
        line([
            'item_no' => 'AO-200',
            'description' => 'Artikel zonder datum',
            'quantity' => 2,
            'qty_to_order' => 2,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'expected_receipt_date' => '',
            'job_contract_entry_no' => 20000,
            'line_no' => 20000,
        ]),
    ],
    'P-050' => [
        line([
            'item_no' => '1000',
            'qty_open' => 4,
            'qty_ordered' => 4,
            'line_no' => 71000,
        ]),
    ],
    'P-060' => [
        line([
            'item_no' => '1000',
            'qty_open' => 8,
            'qty_ordered' => 8,
            'line_no' => 72000,
        ]),
    ],
    'P-200' => [
        line([
            'item_no' => '2000',
            'qty_open' => 3,
            'qty_ordered' => 3,
            'line_no' => 81000,
            'job_contract_entry_no' => 10000,
        ]),
    ],
    'P-201' => [
        line([
            'item_no' => '2001',
            'qty_open' => 2,
            'qty_ordered' => 2,
            'line_no' => 82000,
            'job_contract_entry_no' => 10000,
        ]),
    ],
    'P-300' => [
        line([
            'item_no' => '3000',
            'qty_open' => 10,
            'qty_ordered' => 10,
            'line_no' => 83000,
        ]),
    ],
    'P-301' => [
        line([
            'item_no' => '3001',
            'qty_open' => 10,
            'qty_ordered' => 10,
            'line_no' => 84000,
        ]),
    ],
    'P-400' => [
        line([
            'item_no' => '4000',
            'qty_open' => 2,
            'qty_ordered' => 2,
            'purchase_order_no' => 'IO-9',
            'line_no' => 10000,
            'job_contract_entry_no' => 40000,
        ]),
        line([
            'item_no' => '4001',
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'purchase_order_no' => 'IO-9',
            'line_no' => 20000,
            'job_contract_entry_no' => 40001,
        ]),
        line([
            'item_no' => '5000',
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'line_no' => 30000,
            'job_contract_entry_no' => 40002,
        ]),
        line([
            'item_no' => '6000',
            'quantity' => 2,
            'qty_open' => 0,
            'qty_ordered' => 2,
            'qty_received' => 2,
            'completely_received' => true,
            'line_no' => 40000,
            'job_contract_entry_no' => 40003,
        ]),
    ],
    'PRJ2608308' => [
        line([
            'job_task_no' => 'WO2610572',
            'line_no' => 50000,
            'job_contract_entry_no' => 99965,
            'item_no' => 'PK-28170082',
            'description' => 'Pakking',
            'quantity' => 8,
            'qty_to_order' => 8,
            'qty_ordered' => 0,
            'qty_open' => 0,
        ]),
        line([
            'job_task_no' => 'WO-ANDERS',
            'line_no' => 99965,
            'job_contract_entry_no' => 11111,
            'item_no' => 'PK-28170082',
            'quantity' => 1,
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
        ]),
        line([
            'job_task_no' => 'WO2610572',
            'line_no' => 60000,
            'job_contract_entry_no' => 99965,
            'item_no' => 'SB-0001',
            'quantity' => 1,
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
        ]),
    ],
    'PRJ2605044' => [
        line([
            'job_task_no' => 'TAK90000',
            'line_no' => 90000,
            'job_contract_entry_no' => 88012,
            'item_no' => 'AO-1SK1524',
            'description' => 'Onderdeel',
            'quantity' => 2,
            'qty_to_order' => 2,
            'qty_ordered' => 0,
            'qty_open' => 0,
        ]),
    ],
    'P-VRIJ' => [
        line([
            'item_no' => 'VRIJ-1',
            'quantity' => 4,
            'qty_to_order' => 4,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'job_contract_entry_no' => 1,
            'line_no' => 10000,
        ]),
    ],
    'P-VAST' => [
        line([
            'item_no' => 'VAST-1',
            'quantity' => 4,
            'qty_to_order' => 4,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'job_contract_entry_no' => 2,
            'line_no' => 10000,
        ]),
    ],
    'P-SUB' => [
        line([
            'item_no' => 'SUB-1',
            'quantity' => 1,
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
        ]),
    ],
    'P-NUL' => [
        line([
            'item_no' => 'NUL-1',
            'quantity' => 1,
            'qty_to_order' => 1,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'expected_receipt_date' => '0001-01-01',
            'job_contract_entry_no' => 3,
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
    'VRIJ-1' => 10,
    'VAST-1' => 10,
]);

$withDate = $result['P-100'][0];
if (($withDate['receipt_state'] ?? '') !== 'date' || ($withDate['expected_receipt_date'] ?? '') !== '2026-11-02') {
    fail('een gevulde KVT-datum geldt voor elk artikel: ' . json_encode($withDate));
}
if (elpis_line_expected_receipt_display($withDate) !== '02-11-2026') {
    fail('datum moet Nederlands leesbaar zijn');
}

$noDate = $result['P-100'][1];
if (($noDate['receipt_state'] ?? '') !== 'missing' || elpis_line_expected_receipt_display($noDate) !== 'Datum ontbreekt') {
    fail('artikel zonder dekking en zonder datum moet datum ontbreekt tonen');
}

$firstStock = $result['P-050'][0];
$secondStock = $result['P-060'][0];
if (($firstStock['receipt_state'] ?? '') !== 'on_stock') {
    fail('eerste voorraadregel moet op voorraad zijn, voorraad wordt niet twee keer uitgegeven');
}
if (($secondStock['receipt_state'] ?? '') !== 'uncovered' || elpis_line_expected_receipt_display($secondStock) !== 'Deels op voorraad, datum ontbreekt') {
    fail('tweede voorraadregel mag dezelfde voorraad niet nog eens claimen: ' . json_encode($secondStock) . ' ' . elpis_line_expected_receipt_display($secondStock));
}
if (elpis_line_expected_receipt_display($firstStock) !== 'Op voorraad') {
    fail('voorraadtekst klopt niet');
}

$fromPo = $result['P-200'][0];
if (($fromPo['receipt_state'] ?? '') !== 'date' || ($fromPo['expected_receipt_date'] ?? '') !== '2026-12-01') {
    fail('reservering naar inkooporder moet de Expected Receipt Date van de reservering gebruiken, niet de eerdere orderregel: ' . json_encode($fromPo));
}

$blankReservationDate = $result['P-201'][0];
if (($blankReservationDate['receipt_state'] ?? '') !== 'date' || ($blankReservationDate['expected_receipt_date'] ?? '') !== '2026-04-02') {
    fail('0001-01-01 op de reservering valt terug op de inkooporderregel: ' . json_encode($blankReservationDate));
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
    fail('regel zonder order en zonder voorraad moet datum ontbreekt zijn');
}

if (($result['P-400'][3]['receipt_state'] ?? '') !== 'received') {
    fail('volledig ontvangen regel telt niet meer mee');
}

$pkLine = $result['PRJ2608308'][0];
if (($pkLine['job_task_no'] ?? '') !== 'WO2610572' || ($pkLine['receipt_state'] ?? '') !== 'partial' || ($pkLine['expected_receipt_date'] ?? '') !== '2026-09-19') {
    fail('PRJ2608308 regel 50000 / contract 99965: 7 op voorraad en 1 verwacht 19-09-2026: ' . json_encode($pkLine));
}
if (elpis_line_expected_receipt_display($pkLine) !== 'Deels op voorraad, verwacht 19-09-2026') {
    fail('PK-tekst klopt niet: ' . elpis_line_expected_receipt_display($pkLine));
}

$lineNoCollision = $result['PRJ2608308'][1];
if (($lineNoCollision['receipt_state'] ?? '') !== 'missing') {
    fail('hetzelfde Line_No als andermans Job_Contract_Entry_No mag die reservering niet erven: ' . json_encode($lineNoCollision));
}

$itemMismatch = $result['PRJ2608308'][2];
if (($itemMismatch['receipt_state'] ?? '') !== 'missing') {
    fail('zelfde contractpost met een ander artikel mag de reservering niet erven: ' . json_encode($itemMismatch));
}

$aoLine = $result['PRJ2605044'][0];
if (($aoLine['receipt_state'] ?? '') !== 'uncovered' || ($aoLine['expected_receipt_date'] ?? '') !== '2026-03-24') {
    fail('PRJ2605044 regel 90000: slechts 1 van 2 gedekt door PO12601384: ' . json_encode($aoLine));
}
if (elpis_line_expected_receipt_display($aoLine) !== 'Verwacht 24-03-2026, rest niet gedekt') {
    fail('ongedekte rest moet zichtbaar zijn: ' . elpis_line_expected_receipt_display($aoLine));
}
$aoSummary = elpis_project_execution_summary([$aoLine]);
if (($aoSummary['state'] ?? '') !== 'missing' || elpis_project_execution_value($aoSummary) !== 'datum ontbreekt') {
    fail('een niet gedekte rest blokkeert uitvoering plannen vanaf');
}

if (($result['P-VRIJ'][0]['receipt_state'] ?? '') !== 'on_stock') {
    fail('een artikelpost met een andere status dan Reservering mag de vrije voorraad niet verlagen: ' . json_encode($result['P-VRIJ'][0]));
}
if (($result['P-VAST'][0]['receipt_state'] ?? '') !== 'missing') {
    fail('een echte Reservering op de artikelpost moet wel van de vrije voorraad af: ' . json_encode($result['P-VAST'][0]));
}
if (($result['P-SUB'][0]['receipt_state'] ?? '') !== 'missing') {
    fail('alleen Source_Subtype 2 is een projectplanningsregel: ' . json_encode($result['P-SUB'][0]));
}
if (($result['P-NUL'][0]['receipt_state'] ?? '') !== 'missing') {
    fail('0001-01-01 op de planningsregel is geen KVT-datum: ' . json_encode($result['P-NUL'][0]));
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

$blocked = elpis_project_execution_summary([$result['P-100'][0], $noDate]);
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

$pkSummary = elpis_project_execution_summary([$pkLine]);
if (($pkSummary['state'] ?? '') !== 'date' || ($pkSummary['date'] ?? '') !== '2026-09-19') {
    fail('volledig gedekte PK-regel plant vanaf 19-09-2026: ' . json_encode($pkSummary));
}

$normalized = elpis_normalize_reservation_entry([
    'Entry_No' => 2941903,
    'Positive' => false,
    'Item_No' => 'PK-28170082',
    'Quantity_Base' => -8,
    'Reservation_Status' => 'Reservering',
    'Source_Type' => 1003,
    'Source_Subtype' => '2',
    'Source_ID' => 'PRJ2608308',
    'Source_Ref_No' => 99965,
    'Expected_Receipt_Date' => '0001-01-01',
]);
if ($normalized['positive'] !== false || $normalized['expected_receipt_date'] !== '' || $normalized['source_subtype'] !== '2' || $normalized['status'] !== 'Reservering') {
    fail('BC-reserveringsrij wordt niet genormaliseerd: ' . json_encode($normalized));
}
if (!elpis_reservation_is_demand($normalized)) {
    fail('vraagzijde is subtype 2, negatief, status Reservering');
}
$asInt = $normalized;
$asInt['source_subtype'] = 2;
if (!elpis_reservation_is_demand($asInt)) {
    fail('subtype 2 als getal moet ook als tekst 2 tellen');
}

$best = elpis_purchase_line_best_date([
    'expected_receipt_date' => '',
    'promised_receipt_date' => '2026-05-02',
    'planned_receipt_date' => '2026-05-01',
]);
if ($best !== '2026-05-02') {
    fail('datumvolgorde Expected, Promised, Planned klopt niet');
}

if (elpis_receipt_sort_value($firstStock) !== '0000-00-00' || elpis_receipt_sort_value($missingAo) !== '9999-99-99' || elpis_receipt_sort_value($aoLine) !== '9999-99-99') {
    fail('sorteerwaarde voor voorraad en ontbrekende datum klopt niet');
}

$fallback = elpis_apply_receipt_expectations([
    'P-1' => [
        line([
            'item_no' => '7788',
            'quantity' => 1,
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

$remainingCases = elpis_apply_receipt_expectations([
    'P-REST0' => [
        line([
            'item_no' => 'PK-28170082',
            'description' => 'Al uitgegeven',
            'quantity' => 8,
            'remaining_qty' => 0,
            'reserved_quantity' => 8,
            'qty_to_order' => 8,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'expected_receipt_date' => '',
        ]),
    ],
    'P-REST1' => [
        line([
            'item_no' => 'PK-28170082',
            'quantity' => 8,
            'remaining_qty' => 1,
            'qty_to_order' => 8,
            'qty_ordered' => 0,
            'qty_open' => 0,
            'job_contract_entry_no' => 99965,
            'line_no' => 50000,
        ]),
    ],
], [
    demand(2941903, 'P-REST1', 99965, 'PK-28170082', 1),
    purchaseSupply(2941903, 'PK-28170082', 1, 'PO12606040', 10000, '2026-09-19'),
], [], []);

$finished = $remainingCases['P-REST0'][0];
if (($finished['receipt_state'] ?? '') !== 'received' || elpis_line_expected_receipt_display($finished) !== '') {
    fail('Remaining_Qty 0 mag geen Datum ontbreekt tonen: ' . json_encode($finished) . ' ' . elpis_line_expected_receipt_display($finished));
}
if ((elpis_project_execution_summary([$finished])['state'] ?? '') !== 'none') {
    fail('een afgeronde regel telt niet mee voor uitvoering plannen vanaf');
}

$onlyRemainder = $remainingCases['P-REST1'][0];
if (($onlyRemainder['receipt_state'] ?? '') !== 'date' || ($onlyRemainder['expected_receipt_date'] ?? '') !== '2026-09-19') {
    fail('alleen Remaining_Qty telt als nog nodig, niet de geplande Quantity: ' . json_encode($onlyRemainder));
}

echo "OK\n";
