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

if (ELPI_CACHE_TTL !== 86400) {
    fail('de UI houdt de app-TTL van 24 uur');
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

if (elpis_odata_eq_list('Job_No', ["O'Brien"]) !== "Job_No eq 'O''Brien'") {
    fail('OData-quotes moeten verdubbeld worden');
}
if (elpis_odata_eq_list('No', ['A', 'B']) !== "(No eq 'A' or No eq 'B')") {
    fail('voorraadfilter moet een OR op hetzelfde veld No zijn');
}

$jobFilters = [];
for ($i = 1; $i <= 13; $i++) {
    $jobFilters[] = 'PRJ' . $i;
}
$jobFilters = elpis_job_scoped_filters($jobFilters);
if (count($jobFilters) !== 2 || substr_count($jobFilters[0], 'Job_No eq') !== 12 || substr_count($jobFilters[1], 'Job_No eq') !== 1) {
    fail('planningsregels moeten in chunks van 12 projecten: ' . json_encode($jobFilters));
}
if (!str_contains($jobFilters[0], ") and Type eq 'Artikel'") || $jobFilters[1] !== "Job_No eq 'PRJ13' and Type eq 'Artikel'") {
    fail('jobfilter moet Type met AND koppelen: ' . json_encode($jobFilters));
}
foreach ($jobFilters as $jobFilter) {
    if (filter_ors_cross_fields($jobFilter) || $jobFilter === "Type eq 'Artikel'") {
        fail('jobfilter scant de hele tabel of OR-t over velden: ' . $jobFilter);
    }
}

$demandFilter = elpis_reservation_demand_filter(['PRJ2605044', 'PRJ2608308']);
foreach ([
    "Reservation_Status eq 'Reservering'",
    'Source_Type eq 1003',
    "Source_Subtype eq '2'",
    'Positive eq false',
    "Source_ID eq 'PRJ2608308'",
    "Source_ID eq 'PRJ2605044'",
] as $part) {
    if (!str_contains($demandFilter, $part)) {
        fail('vraagfilter mist ' . $part . ': ' . $demandFilter);
    }
}
if (str_contains($demandFilter, 'Positive eq true') || filter_ors_cross_fields($demandFilter) || $demandFilter === elpis_reservation_status_filter()) {
    fail('vraagfilter mag geen volledige reserveringstabel zijn: ' . $demandFilter);
}

$supplyFilter = elpis_reservation_supply_filter([2941903, 10]);
foreach ([
    "Reservation_Status eq 'Reservering'",
    'Positive eq true',
    'Source_Type eq 32',
    'Source_Type eq 39',
    'Entry_No eq 10',
    'Entry_No eq 2941903',
] as $part) {
    if (!str_contains($supplyFilter, $part)) {
        fail('aanbodfilter mist ' . $part . ': ' . $supplyFilter);
    }
}
if (str_contains($supplyFilter, 'Positive eq false') || filter_ors_cross_fields($supplyFilter)) {
    fail('aanbodfilter OR-t over verschillende velden: ' . $supplyFilter);
}

$purchaseFilter = elpis_purchase_documents_filter(['PO12601384']);
foreach ([
    'Outstanding_Quantity gt 0',
    "Type eq 'Artikel'",
    "Document_No eq 'PO12601384'",
] as $part) {
    if (!str_contains($purchaseFilter, $part)) {
        fail('inkoopfilter mist ' . $part . ': ' . $purchaseFilter);
    }
}
if (filter_ors_cross_fields($purchaseFilter) || $purchaseFilter === elpis_purchase_line_open_filter()) {
    fail('open inkoopregels mogen niet bedrijfswijd worden opgehaald: ' . $purchaseFilter);
}

$stockReservationFilter = elpis_item_stock_reservation_filter(['ITEM-9', 'ITEM-1']);
foreach ([
    "Reservation_Status eq 'Reservering'",
    'Positive eq true',
    'Source_Type eq 32',
    "Item_No eq 'ITEM-1'",
    "Item_No eq 'ITEM-9'",
] as $part) {
    if (!str_contains($stockReservationFilter, $part)) {
        fail('voorraadreserveringsfilter mist ' . $part . ': ' . $stockReservationFilter);
    }
}
if (str_contains($stockReservationFilter, 'Positive eq false') || str_contains($stockReservationFilter, 'Source_Type eq 39') || filter_ors_cross_fields($stockReservationFilter)) {
    fail('voorraadreserveringen mogen niet de hele tabel of inkoopaanbod zijn: ' . $stockReservationFilter);
}

$mergedReservations = elpis_merge_reservations(
    [stockSupply(10, 'ITEM-1', 8), stockSupply(99, 'ITEM-1', 1)],
    [stockSupply(10, 'ITEM-1', 8)]
);
if (count($mergedReservations) !== 2) {
    fail('dezelfde voorraadreservering mag niet dubbel tellen: ' . count($mergedReservations));
}

if (elpis_line_needs_receipt_lookup(line(['expected_receipt_date' => '2026-01-02']))) {
    fail('een gevulde ontvangstdatum hoeft geen reservering of voorraad meer');
}
if (elpis_line_needs_receipt_lookup(line(['remaining_qty' => 0]))) {
    fail('Remaining_Qty 0 hoeft geen lookup');
}
if (!elpis_line_needs_receipt_lookup(line())) {
    fail('een open regel zonder datum moet wel opgezocht worden');
}

$neededProjects = elpis_projects_needing_reservations([
    'B' => [line()],
    'A' => [line(['expected_receipt_date' => '2026-02-02'])],
]);
if ($neededProjects !== ['B']) {
    fail('alleen projecten zonder ontvangstdatum vragen reserveringen: ' . json_encode($neededProjects));
}

$stockItems = elpis_collect_stock_item_nos([
    'P' => [
        line(['item_no' => 'NEED']),
        line(['item_no' => 'DATED', 'expected_receipt_date' => '2026-03-03']),
        line(['item_no' => 'DONE', 'remaining_qty' => 0]),
    ],
]);
if ($stockItems !== ['NEED']) {
    fail('voorraad alleen voor regels die nog een datum zoeken: ' . json_encode($stockItems));
}

$documents = elpis_collect_purchase_document_nos([
    'P' => [
        line(['purchase_order_no' => 'PO-NEED']),
        line(['purchase_order_no' => 'PO-DATED', 'expected_receipt_date' => '2026-03-03']),
    ],
], [
    purchaseSupply(1, 'NEED', 1, 'PO-SUP', 10000, '0001-01-01'),
    purchaseSupply(2, 'NEED', 1, 'PO-HASDATE', 20000, '2026-04-04'),
]);
if ($documents !== ['PO-NEED', 'PO-SUP']) {
    fail('alleen inkooporders zonder datum worden opgehaald: ' . json_encode($documents));
}

elpis_reset_fetch_memo();
$seen = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query, int $ttl) use (&$seen): array {
    $seen[] = ['entity' => $entity, 'query' => $query, 'ttl' => $ttl, 'company' => $company];

    return [];
};
elpis_fetch_reservation_entries('Koninklijke van Twist');
elpis_fetch_open_purchase_order_lines('Koninklijke van Twist');
elpis_fetch_planning_lines_for_company('Koninklijke van Twist');
if ($seen === []) {
    fail('projectfetch moet AppProjecten nog wel vragen');
}
foreach ($seen as $call) {
    $filter = (string) ($call['query']['$filter'] ?? '');
    if ($call['entity'] === ELPI_RESERVATION_ENTRY_ENTITY || $call['entity'] === ELPI_PURCHASE_ORDER_LINE_ENTITY || $call['entity'] === ELPI_PLANNING_LINE_ENTITY) {
        fail('ongefilterde fetch mag BC niet raken: ' . $call['entity'] . ' ' . $filter);
    }
    if ($call['entity'] === 'AppProjecten' && (!str_contains($filter, "Project_Manager ne ''") || !str_contains((string) ($call['query']['$select'] ?? ''), 'No'))) {
        fail('projectenquery is verbreed: ' . json_encode($call['query']));
    }
}

elpis_reset_fetch_memo();
$seen = [];
$phases = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query, int $ttl) use (&$seen): array {
    $seen[] = ['entity' => $entity, 'query' => $query, 'ttl' => $ttl];
    $filter = (string) ($query['$filter'] ?? '');
    if ($entity === ELPI_RESERVATION_ENTRY_ENTITY && str_contains($filter, 'Positive eq false')) {
        return [[
            'Entry_No' => 10,
            'Positive' => false,
            'Item_No' => 'ITEM-1',
            'Quantity_Base' => -1,
            'Quantity' => -1,
            'Reservation_Status' => 'Reservering',
            'Source_Type' => 1003,
            'Source_Subtype' => '2',
            'Source_ID' => 'PRJ-OPEN',
            'Source_Ref_No' => 10000,
            'Expected_Receipt_Date' => '0001-01-01',
        ]];
    }
    if ($entity === ELPI_RESERVATION_ENTRY_ENTITY && str_contains($filter, 'Item_No eq')) {
        return [[
            'Entry_No' => 99,
            'Positive' => true,
            'Item_No' => 'ITEM-1',
            'Quantity_Base' => 8,
            'Quantity' => 8,
            'Reservation_Status' => 'Reservering',
            'Source_Type' => 32,
            'Source_Subtype' => '0',
            'Source_ID' => '',
            'Source_Ref_No' => 0,
            'Expected_Receipt_Date' => '0001-01-01',
        ]];
    }
    if ($entity === ELPI_RESERVATION_ENTRY_ENTITY) {
        return [[
            'Entry_No' => 10,
            'Positive' => true,
            'Item_No' => 'ITEM-1',
            'Quantity_Base' => 1,
            'Quantity' => 1,
            'Reservation_Status' => 'Reservering',
            'Source_Type' => 39,
            'Source_Subtype' => '1',
            'Source_ID' => 'PO-1',
            'Source_Ref_No' => 10000,
            'Expected_Receipt_Date' => '0001-01-01',
        ]];
    }
    if ($entity === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
        return [[
            'Document_No' => 'PO-1',
            'Line_No' => 10000,
            'No' => 'ITEM-1',
            'Type' => 'Artikel',
            'Expected_Receipt_Date' => '2026-05-01',
            'Promised_Receipt_Date' => '0001-01-01',
            'Planned_Receipt_Date' => '0001-01-01',
            'Outstanding_Quantity' => 1,
        ]];
    }
    if ($entity === ELPI_ITEM_LIST_ENTITY) {
        return [['No' => 'ITEM-1', 'Inventory' => 10]];
    }

    return [];
};

$lookupLines = [
    'PRJ-OPEN' => [line([
        'item_no' => 'ITEM-1',
        'purchase_order_no' => 'PO-LINE',
        'job_contract_entry_no' => 10000,
    ])],
    'PRJ-DATED' => [line([
        'item_no' => 'DATED-ITEM',
        'purchase_order_no' => 'PO-SKIP',
        'expected_receipt_date' => '2026-01-01',
    ])],
    'PRJ-DONE' => [line([
        'item_no' => 'DONE-ITEM',
        'remaining_qty' => 0,
    ])],
];
$context = elpis_load_receipt_context('Koninklijke van Twist', $lookupLines, ELPI_CACHE_TTL, null, static function (array $event) use (&$phases): void {
    $phases[] = ($event['step'] ?? '') . ':' . ($event['status'] ?? '');
});
$entities = array_map(static function (array $call): string {
    return $call['entity'];
}, $seen);
if ($entities !== [
    ELPI_RESERVATION_ENTRY_ENTITY,
    ELPI_RESERVATION_ENTRY_ENTITY,
    ELPI_RESERVATION_ENTRY_ENTITY,
    ELPI_PURCHASE_ORDER_LINE_ENTITY,
    ELPI_ITEM_LIST_ENTITY,
]) {
    fail('ontvangstcontext haalt de verkeerde sets op: ' . json_encode($entities));
}
foreach ($seen as $call) {
    $filter = (string) ($call['query']['$filter'] ?? '');
    $select = (string) ($call['query']['$select'] ?? '');
    if ($call['ttl'] !== ELPI_CACHE_TTL) {
        fail('UI-query moet ELPI_CACHE_TTL als max_age houden');
    }
    if ($select === '' || filter_ors_cross_fields($filter)) {
        fail('query mist $select of OR-t over velden: ' . json_encode($call));
    }
    if ($call['entity'] === ELPI_RESERVATION_ENTRY_ENTITY && !str_contains($filter, 'Source_ID eq') && !str_contains($filter, 'Entry_No eq') && !str_contains($filter, 'Item_No eq')) {
        fail('reserveringen zijn niet tot project, Entry_No of artikel beperkt: ' . $filter);
    }
    if ($call['entity'] === ELPI_RESERVATION_ENTRY_ENTITY && str_contains($filter, 'Item_No eq') && (!str_contains($filter, 'Source_Type eq 32') || str_contains($filter, 'Source_Type eq 39') || !str_contains($filter, "Item_No eq 'ITEM-1'"))) {
        fail('voorraadreserveringen moeten op het getoonde artikel en Source_Type 32 blijven: ' . $filter);
    }
    if ($call['entity'] === ELPI_PURCHASE_ORDER_LINE_ENTITY && (!str_contains($filter, "Document_No eq 'PO-LINE'") || !str_contains($filter, "Document_No eq 'PO-1'") || str_contains($filter, 'PO-SKIP'))) {
        fail('inkooporders zijn niet beperkt tot de getoonde regels: ' . $filter);
    }
    if ($call['entity'] === ELPI_ITEM_LIST_ENTITY && ($filter !== "No eq 'ITEM-1'" || !str_contains($select, 'Inventory'))) {
        fail('voorraadquery moet alleen het benodigde artikel selecteren: ' . $filter);
    }
    if (str_contains($filter, 'PRJ-DATED') || str_contains($filter, 'PRJ-DONE') || str_contains($filter, 'DATED-ITEM') || str_contains($filter, 'DONE-ITEM')) {
        fail('query bevat een project of artikel dat niet opgezocht hoeft te worden: ' . $filter);
    }
}
if ($phases !== ['reservations:start', 'reservations:done', 'purchase:start', 'purchase:done', 'inventory:start', 'inventory:progress', 'inventory:done']) {
    fail('voortgangsfasen wijken af: ' . json_encode($phases));
}
$seenBeforeRepeat = count($seen);
elpis_load_receipt_context('Koninklijke van Twist', $lookupLines, ELPI_CACHE_TTL);
if (count($seen) !== $seenBeforeRepeat) {
    fail('dezelfde ontvangstcontext werd opnieuw bij BC opgehaald');
}
$appliedOpen = elpis_apply_receipt_expectations(
    $lookupLines,
    $context['reservations'],
    $context['purchase_lines'],
    $context['inventory']
)['PRJ-OPEN'][0];
if (($appliedOpen['receipt_state'] ?? '') === 'on_stock' || abs((float) ($appliedOpen['covered_by_stock'] ?? -1) - 2) > 0.00001) {
    fail('voorraad die op een ander project gereserveerd is moet van de vrije voorraad af: ' . json_encode($appliedOpen));
}

elpis_reset_fetch_memo();
$stockCalls = [];
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query, int $ttl) use (&$stockCalls): array {
    $stockCalls[] = ['entity' => $entity, 'query' => $query];

    return [];
};
$stockItemNos = [];
for ($i = 1; $i <= 13; $i++) {
    $stockItemNos[] = 'ST' . $i;
}
elpis_fetch_item_stock_reservations('Koninklijke van Twist', $stockItemNos, ELPI_CACHE_TTL);
if (count($stockCalls) !== 2) {
    fail('voorraadreserveringen moeten in chunks van 12 artikelen: ' . count($stockCalls));
}
$firstStockFilter = (string) ($stockCalls[0]['query']['$filter'] ?? '');
$secondStockFilter = (string) ($stockCalls[1]['query']['$filter'] ?? '');
if (substr_count($firstStockFilter, 'Item_No eq') !== 12 || substr_count($secondStockFilter, 'Item_No eq') !== 1) {
    fail('artikelchunks kloppen niet: ' . json_encode([$firstStockFilter, $secondStockFilter]));
}
foreach ([$firstStockFilter, $secondStockFilter] as $stockFilter) {
    if (!str_contains($stockFilter, 'Source_Type eq 32') || str_contains($stockFilter, 'Source_Type eq 39') || filter_ors_cross_fields($stockFilter)) {
        fail('artikelchunk scant te breed: ' . $stockFilter);
    }
}
elpis_reset_fetch_memo();
$stockCalls = [];
elpis_fetch_item_stock_reservations('Koninklijke van Twist', [], ELPI_CACHE_TTL);
if ($stockCalls !== []) {
    fail('een lege artikellijst mag geen reserveringsquery doen');
}

elpis_reset_fetch_memo();
$seen = [];
$projectNos = [];
for ($i = 1; $i <= 13; $i++) {
    $projectNos[] = 'PRJ' . $i;
}
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query, int $ttl) use (&$seen): array {
    $seen[] = ['entity' => $entity, 'query' => $query, 'ttl' => $ttl];
    if ($entity === ELPI_PLANNING_LINE_ENTITY) {
        return [[
            'Job_No' => 'PRJ1',
            'Job_Task_No' => 'T1',
            'Line_No' => 10000,
            'Job_Contract_Entry_No' => 5,
            'Type' => 'Artikel',
            'No' => 'ITEM',
            'Description' => 'Regel',
            'Quantity' => 1,
            'Remaining_Qty' => 1,
            'Reserved_Quantity' => 0,
        ]];
    }

    return [];
};
elpis_fetch_planning_lines_by_projects('Koninklijke van Twist', $projectNos, ELPI_CACHE_TTL);
if (count($seen) !== 3
    || $seen[0]['entity'] !== ELPI_PLANNING_LINE_ENTITY
    || $seen[1]['entity'] !== ELPI_INKOOP_PLANNING_ENTITY
    || $seen[2]['entity'] !== ELPI_PLANNING_LINE_ENTITY
) {
    fail('planningschunks kloppen niet: ' . json_encode(array_column($seen, 'entity')));
}
if (($seen[0]['query']['$filter'] ?? '') !== ($seen[1]['query']['$filter'] ?? '')) {
    fail('inkoopverrijking moet dezelfde projectchunk gebruiken als WerkordersLines');
}
if (($seen[0]['query']['$select'] ?? '') !== elpis_werkorders_line_select_fields() || ($seen[1]['query']['$select'] ?? '') !== elpis_inkoop_planning_select_fields()) {
    fail('$select van planning of inkoop is verschoven');
}
if (substr_count((string) $seen[0]['query']['$filter'], 'Job_No eq') !== 12 || (string) $seen[2]['query']['$filter'] !== "Job_No eq 'PRJ13' and Type eq 'Artikel'") {
    fail('een chunk zonder regels mag geen tweede inkoopquery doen en moet Job_No bevatten: ' . json_encode(array_column(array_column($seen, 'query'), '$filter')));
}
$seenBeforeRepeat = count($seen);
elpis_fetch_planning_lines_by_projects('Koninklijke van Twist', array_reverse($projectNos), ELPI_CACHE_TTL);
if (count($seen) !== $seenBeforeRepeat) {
    fail('dezelfde projectchunk werd opnieuw opgehaald');
}

$indexSource = file_get_contents(dirname(__DIR__) . '/web/index.php');
$progressSource = file_get_contents(dirname(__DIR__) . '/web/elpis_progress.php');
if (!is_string($indexSource) || !is_string($progressSource)) {
    fail('paginabronnen zijn niet leesbaar');
}
if (str_contains($indexSource, 'elpis_fetch_planning_lines_for_company')
    || str_contains($progressSource, 'elpis_fetch_planning_lines_for_company')
    || str_contains($progressSource, 'elpis_fetch_reservation_entries(')
    || str_contains($progressSource, 'elpis_fetch_open_purchase_order_lines(')
    || !str_contains($indexSource, 'elpis_load_shown_lines')
    || !str_contains($progressSource, 'elpis_load_shown_lines')
) {
    fail('de pagina of de voortgang laadt nog de bedrijfswijde sets');
}

unset($GLOBALS['ELPIS_FETCH_OBSERVER']);
elpis_reset_fetch_memo();

$onlyRemainder = $remainingCases['P-REST1'][0];
if (($onlyRemainder['receipt_state'] ?? '') !== 'date' || ($onlyRemainder['expected_receipt_date'] ?? '') !== '2026-09-19') {
    fail('alleen Remaining_Qty telt als nog nodig, niet de geplande Quantity: ' . json_encode($onlyRemainder));
}

echo "OK\n";
