<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/odata.php';

/**
 * Constants
 */

const ELPI_PROJECT_MANAGER_EMAIL_MAP = [
    'mhubregtse@ameil.nl' => 'KVT\\mhubregtse',
];

const ELPI_DEFAULT_USER_EMAIL = 'localtester@kvt.nl';

const ELPI_CACHE_TTL = 86400;
/** Mímir max_age for nightly.php warm (4h — cache sharing, nightly still refreshes). */
const ELPI_NIGHTLY_MAX_AGE = 14400;
/** Projectcache bewaart receipt-state; ophogen als die afleiding verandert. */
const ELPI_PROJECT_CACHE_VERSION = 20;
/** Mímir max_age voor lookups tijdens hourly.php (≤ 30 min). */
const ELPI_HOURLY_MAX_AGE = 1800;

const ELPI_PROJECT_MANAGERS_TTL = ELPI_CACHE_TTL;

const ELPI_MANAGER_ALL = '*';

const ELPI_PLANNING_LINES_CHUNK_SIZE = 12;

/** BC-brontypes op Reservation Entry (Database::). */
const ELPI_ITEM_LEDGER_SOURCE_TYPE = 32;
const ELPI_SALES_LINE_SOURCE_TYPE = 37;
const ELPI_PURCHASE_LINE_SOURCE_TYPE = 39;
const ELPI_ASSEMBLY_SOURCE_TYPE = 901;
const ELPI_JOB_PLANNING_SOURCE_TYPE = 1003;
/** Job planning line: Source_Subtype komt als tekst '2' uit OData. */
const ELPI_JOB_PLANNING_SUBTYPE = '2';
/**
 * OData-optie op ReservationEntries.Reservation_Status.
 * Geldige filterwaarden: Reservation, Tracking, Surplus, Prospect.
 * De caption 'Reservering' geeft "not a valid option". Het antwoord kan die caption nog wel tonen.
 */
const ELPI_RESERVATION_STATUS_OPTION = 'Reservation';
/** Nederlandse enum op de planningsregel (Type). */
const ELPI_ITEM_LINE_TYPE = 'Artikel';

/**
 * Gepubliceerde entity sets (geverifieerd in KVT BC).
 * PurchaseOrderLines en ItemList bestaan niet (404).
 * PurchaseLines: Document_Type eq 'Order'. AppItems.InventoryField: voorraad.
 * WerkordersLines: artikelregels, KVT_Expected_Receipt_Date, Planning_Date, LVS_Work_Order_No.
 */
const ELPI_PLANNING_LINE_ENTITY = 'WerkordersLines';
const ELPI_INKOOP_PLANNING_ENTITY = 'AppProjectInkoopPlanningsRegel';
const ELPI_RESERVATION_ENTRY_ENTITY = 'ReservationEntries';
const ELPI_PURCHASE_ORDER_LINE_ENTITY = 'PurchaseLines';
const ELPI_ITEM_LIST_ENTITY = 'AppItems';
const ELPI_ITEM_LEDGER_ENTITY = 'ItemLedgerEntries';
const ELPI_WORK_ORDER_ENTITY = 'AppWerkorders';
const ELPI_USER_SETUP_ENTITY = 'AppUserSetup';
const ELPI_USERS_ENTITY = 'Users';
/** Eigen status 'te laat' in het regelfilter. */
const ELPI_STATUS_LATE = 'LATE';

/**
 * Eigen regelstatus (bepaald door Elpis zelf).
 * ÉÉN plek voor volgorde, labels, kleur en "probleemstatus": de volgorde van deze
 * array is de prioriteit (eerste regel die past wint) én de volgorde in het filter.
 * Kleuren (Tim, punt N): groen = alles OK, oranje = aandacht, rood = echt probleem,
 * grijs = buiten beschouwing. Probleemregel (knop, overzichtsfilter, PM-mail) = oranje + rood.
 *  - label:         LOC-sleutel
 *  - description:   LOC-sleutel uitleg (Info-knop in de statusfilter)
 *  - tone:          ok (groen) / orange (oranje) / alert (rood) / muted (grijs)
 *  - problem:       telt als probleemregel
 *  - receipt_state: oude receipt_state-code (mailvoorkeur late/unconfirmed, nightly-telling)
 */
const ELPI_LINE_STATUSES = [
    'RETURN' => ['label' => 'elpis.status.return', 'description' => 'elpis.status_info.return', 'tone' => 'muted', 'problem' => false, 'receipt_state' => 'not_required'],
    'CLOSED' => ['label' => 'elpis.status.closed', 'description' => 'elpis.status_info.closed', 'tone' => 'muted', 'problem' => false, 'receipt_state' => 'not_required'],
    'PICKED' => ['label' => 'elpis.status.picked', 'description' => 'elpis.status_info.picked', 'tone' => 'ok', 'problem' => false, 'receipt_state' => 'received'],
    'LATE' => ['label' => 'elpis.status.late', 'description' => 'elpis.status_info.late', 'tone' => 'alert', 'problem' => true, 'receipt_state' => 'late'],
    'DELIVERY_LATE' => ['label' => 'elpis.status.delivery_late', 'description' => 'elpis.status_info.delivery_late', 'tone' => 'alert', 'problem' => true, 'receipt_state' => 'late'],
    'ORDER_TOO_LATE' => ['label' => 'elpis.status.order_too_late', 'description' => 'elpis.status_info.order_too_late', 'tone' => 'alert', 'problem' => true, 'receipt_state' => 'late'],
    'NO_DATE' => ['label' => 'elpis.status.no_date', 'description' => 'elpis.status_info.no_date', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'unconfirmed'],
    'TO_ORDER' => ['label' => 'elpis.status.to_order', 'description' => 'elpis.status_info.to_order', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'unconfirmed'],
    // R: al ontvangen, ligt nog in bin INBOUND (locatie KVT); magazijn moet het nog klaarleggen voor pick.
    'INBOUND' => ['label' => 'elpis.status.inbound', 'description' => 'elpis.status_info.inbound', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'stock'],
    // Deels gepicked, rest al ontvangen/gereserveerd voor dit project: nog picken.
    'PARTIAL_RECEIVED' => ['label' => 'elpis.status.partial_received', 'description' => 'elpis.status_info.partial_received', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'stock'],
    'PARTIAL_STOCK' => ['label' => 'elpis.status.partial_stock', 'description' => 'elpis.status_info.partial_stock', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'stock'],
    'PARTIAL_ORDERED' => ['label' => 'elpis.status.partial_ordered', 'description' => 'elpis.status_info.partial_ordered', 'tone' => 'ok', 'problem' => false, 'receipt_state' => 'date'],
    'ORDERED' => ['label' => 'elpis.status.ordered', 'description' => 'elpis.status_info.ordered', 'tone' => 'ok', 'problem' => false, 'receipt_state' => 'date'],
    // Gedekt door ontvangst/reservering/projectvoorraad voor dit project (niet vrije voorraad): nog picken.
    'RECEIVED_TO_PICK' => ['label' => 'elpis.status.received_to_pick', 'description' => 'elpis.status_info.received_to_pick', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'stock'],
    // Alleen als (een deel van) de dekking uit vrije voorraad komt (kolom Voorraad > 0).
    'STOCK' => ['label' => 'elpis.status.stock', 'description' => 'elpis.status_info.stock', 'tone' => 'orange', 'problem' => true, 'receipt_state' => 'stock'],
];

/**
 * BC DateFormula ('10D', '1W', '2M', 'CM', '<1W+CM>') → aantal dagen vanaf $today. null = leeg/onleesbaar.
 */
function elpis_date_formula_days(?string $formula, ?string $today = null): ?int
{
    $formula = strtoupper(str_replace(['<', '>', ' '], '', trim((string) $formula)));
    if ($formula === '' || !preg_match('/^([+-]?(\d*)(CM|C?[DWMQY]))+$/', $formula)) {
        return null;
    }
    $base = new DateTimeImmutable(($today ?? elpis_today()) . ' 00:00:00');
    $date = $base;
    preg_match_all('/([+-]?)(\d*)(CM|C?[DWMQY])/', $formula, $parts, PREG_SET_ORDER);
    foreach ($parts as [, $sign, $num, $unit]) {
        if ($unit === 'CM') {
            $date = $date->modify('last day of this month');
            continue;
        }
        $n = ($num === '' ? 1 : (int) $num) * ($sign === '-' ? -1 : 1);
        $unit = ltrim($unit, 'C');
        $map = ['D' => 'day', 'W' => 'week', 'M' => 'month', 'Q' => 'month', 'Y' => 'year'];
        $date = $date->modify(($unit === 'Q' ? $n * 3 : $n) . ' ' . $map[$unit]);
    }

    return (int) $base->diff($date)->format('%r%a');
}

/**
 * Levertijd in dagen (Ariadne): WerkordersLines.LVS_Critical_Lead_Time, anders AppItems.Lead_Time_Calculation.
 * KVT_Item_Lead_Time niet gebruiken (kapot). null = onbekend → 'Te bestellen' (oranje), nooit 'Te laat besteld'.
 */
function elpis_line_lead_time_days(array $line, ?string $today = null): ?int
{
    if (is_numeric($line['lead_time_days'] ?? null)) {
        return (int) $line['lead_time_days'];
    }
    foreach (['critical_lead_time', 'item_lead_time'] as $field) {
        $days = elpis_date_formula_days((string) ($line[$field] ?? ''), $today);
        if ($days !== null && $days >= 0 && (string) ($line[$field] ?? '') !== '') {
            return $days;
        }
    }

    return null;
}

/** Planningsdatum waartegen getoetst wordt: Planning_Date (bevestigd door Ariadne, = Planned_Delivery_Date). */
function elpis_line_need_date(array $line): string
{
    $date = elpis_normalize_bc_date((string) ($line['planning_date'] ?? ''));

    return $date === '' ? '' : substr($date, 0, 10);
}

/** Alle statussen (centrale tabel) met label, uitleg en kleur, voor de Info-knop. */
function elpis_line_status_info(): array
{
    $out = [];
    foreach (ELPI_LINE_STATUSES as $key => $def) {
        $out[] = ['key' => $key, 'label' => LOC($def['label']), 'description' => LOC($def['description']), 'tone' => $def['tone'], 'problem' => $def['problem']];
    }

    return $out;
}

function elpis_line_status_label(string $key): string
{
    $def = ELPI_LINE_STATUSES[$key] ?? null;

    return $def === null ? $key : LOC($def['label']);
}

/**
 * Vrije voorraad (kolom Voorraad en basis voor 'Op voorraad'), op één plek:
 * AppItems.LVS_InventoryExclJobInventory (geen projectvoorraad) − voorraadreserveringen
 * van andere projecten (ReservationEntries) − gepicked voor andere projecten.
 * $pickedElsewhere is nu altijd 0: Ariadne zoekt uit waar dat in BC staat.
 */
function elpis_item_free_stock(float $inventoryExclJob, float $reservedElsewhere, float $pickedElsewhere = 0.0): float
{
    return max(0.0, $inventoryExclJob - max(0.0, $reservedElsewhere) - max(0.0, $pickedElsewhere));
}

/**
 * Bouwt per artikel (en locatie) de bouwstenen voor vrije voorraad uit ruwe BC-rijen:
 * - ItemLedgerEntries (Open eq true): Remaining_Quantity zonder Job_No = open_no_job; met Job_No = projectvoorraad.
 * - ReservationEntries (Reservering): positieve kant Source_Type 32 = op voorraad; project via de negatieve
 *   kant (zelfde Entry_No, Source_Type 1003 → Source_ID project, Source_Ref_No = Job_Contract_Entry_No).
 * - WerkordersLines (KVT_Qty_Picked > 0): gepickt-niet-geboekt zonder voorraadreservering
 *   = max(0, (picked − posted) − reservering regel (voorraad én inkoop)); overgeslagen als het project projectvoorraad heeft.
 * Index: [ITEM]['loc'][LOC] = ['open' => x, 'reserved' => [PROJECT|'' => x], 'picked' => [PROJECT => x]].
 */
function elpis_build_free_stock_index(array $ileRows, array $reservationRows, array $pickRows): array
{
    $index = [];
    $jobInventory = [];
    $slot = static function (array &$index, string $item, string $loc): void {
        if (!isset($index[$item]['loc'][$loc])) {
            $index[$item]['loc'][$loc] = ['open' => 0.0, 'reserved' => [], 'picked' => []];
        }
    };
    foreach ($ileRows as $row) {
        $item = strtoupper(trim((string) ($row['Item_No'] ?? '')));
        if ($item === '') {
            continue;
        }
        $loc = strtoupper(trim((string) ($row['Location_Code'] ?? '')));
        $job = strtoupper(trim((string) ($row['Job_No'] ?? '')));
        $qty = (float) ($row['Remaining_Quantity'] ?? 0);
        $slot($index, $item, $loc);
        if ($job === '') {
            $index[$item]['loc'][$loc]['open'] += $qty;
        } elseif ($qty > 0.00001) {
            $jobInventory[$item . '|' . $job] = true;
        }
    }
    $pairs = [];
    foreach ($reservationRows as $row) {
        $entry = (int) ($row['Entry_No'] ?? 0);
        if ($entry <= 0 || !elpis_reservation_status_matches((string) ($row['Reservation_Status'] ?? ''))) {
            continue;
        }
        $side = elpis_bc_bool($row['Positive'] ?? false) ? 'pos' : 'neg';
        $pairs[$entry][$side] = $row;
    }
    $stockResByJce = [];
    foreach ($pairs as $pair) {
        $pos = $pair['pos'] ?? null;
        if (!is_array($pos)) {
            continue;
        }
        $neg = $pair['neg'] ?? [];
        $negProject = (int) ($neg['Source_Type'] ?? 0) === ELPI_JOB_PLANNING_SOURCE_TYPE ? strtoupper(trim((string) ($neg['Source_ID'] ?? ''))) : '';
        if ((int) ($pos['Source_Type'] ?? 0) !== ELPI_ITEM_LEDGER_SOURCE_TYPE) {
            // Reservering op inkoop dekt een pick ook (fixture AO-2-70000-22-13: zonder dit 30 i.p.v. 0 gepickt-elders).
            if ($negProject !== '') {
                $jceKey = $negProject . '|' . (int) ($neg['Source_Ref_No'] ?? 0);
                $stockResByJce[$jceKey] = ($stockResByJce[$jceKey] ?? 0.0) + abs((float) ($pos['Quantity_Base'] ?? 0));
            }
            continue;
        }
        $item = strtoupper(trim((string) ($pos['Item_No'] ?? '')));
        $loc = strtoupper(trim((string) ($pos['Location_Code'] ?? '')));
        $qty = abs((float) ($pos['Quantity_Base'] ?? 0));
        if ($item === '' || $qty <= 0.00001) {
            continue;
        }
        $neg = $pair['neg'] ?? [];
        $project = (int) ($neg['Source_Type'] ?? 0) === ELPI_JOB_PLANNING_SOURCE_TYPE ? strtoupper(trim((string) ($neg['Source_ID'] ?? ''))) : '';
        $slot($index, $item, $loc);
        $index[$item]['loc'][$loc]['reserved'][$project] = ($index[$item]['loc'][$loc]['reserved'][$project] ?? 0.0) + $qty;
        if ($project !== '') {
            $jceKey = $project . '|' . (int) ($neg['Source_Ref_No'] ?? 0);
            $stockResByJce[$jceKey] = ($stockResByJce[$jceKey] ?? 0.0) + $qty;
        }
    }
    foreach ($pickRows as $row) {
        $item = strtoupper(trim((string) ($row['No'] ?? '')));
        $project = strtoupper(trim((string) ($row['Job_No'] ?? '')));
        if ($item === '' || $project === '' || isset($jobInventory[$item . '|' . $project])) {
            continue;
        }
        $open = (float) ($row['KVT_Qty_Picked'] ?? 0) - (float) ($row['Qty_Posted'] ?? 0);
        $uncovered = max(0.0, $open - (float) ($stockResByJce[$project . '|' . (int) ($row['Job_Contract_Entry_No'] ?? 0)] ?? 0));
        if ($uncovered <= 0.00001) {
            continue;
        }
        $loc = strtoupper(trim((string) ($row['Location_Code'] ?? '')));
        $slot($index, $item, $loc);
        $index[$item]['loc'][$loc]['picked'][$project] = ($index[$item]['loc'][$loc]['picked'][$project] ?? 0.0) + $uncovered;
    }

    return $index;
}

/**
 * Vrije voorraad voor één regel: per locatie van de regel (leeg = alle locaties opgeteld),
 * min 0. 'free' laat de eigen reserveringen en picks van dit project buiten beschouwing
 * (kolom Voorraad); 'pool' trekt ook de eigen voorraadreserveringen af (die tellen in de
 * dekking al apart), voor de verdeling over de regels.
 */
function elpis_line_free_stock(array $index, string $itemKey, string $location, string $projectKey): array
{
    $itemKey = strtoupper(trim($itemKey));
    $location = strtoupper(trim($location));
    $projectKey = strtoupper(trim($projectKey));
    $slots = (array) ($index[$itemKey]['loc'] ?? []);
    if ($location !== '' && isset($slots[$location])) {
        $slots = [$location => $slots[$location]];
    }
    $open = 0.0; $reservedAll = 0.0; $reservedOwn = 0.0; $pickedOther = 0.0;
    foreach ($slots as $slot) {
        $open += (float) $slot['open'];
        foreach ($slot['reserved'] as $project => $qty) {
            $reservedAll += $qty;
            if ((string) $project === $projectKey) {
                $reservedOwn += $qty;
            }
        }
        foreach ($slot['picked'] as $project => $qty) {
            if ((string) $project !== $projectKey) {
                $pickedOther += $qty;
            }
        }
    }
    $elsewhere = max(0.0, $reservedAll - $reservedOwn);

    return [
        'open_no_job' => $open,
        'reserved_elsewhere' => $elsewhere,
        'picked_elsewhere' => $pickedOther,
        'free' => elpis_item_free_stock($open, $elsewhere, $pickedOther),
        'pool' => elpis_item_free_stock($open, $reservedAll, $pickedOther),
    ];
}

/** Haalt ILE/reserveringen/picks alleen voor de artikelen van het project op (chunked, met $select). */
function elpis_fetch_free_stock_index(string $company, array $itemNos, int $ttl = ELPI_CACHE_TTL): array
{
    $items = [];
    foreach ($itemNos as $itemNo) {
        $itemNo = trim((string) $itemNo);
        if ($itemNo !== '') {
            $items[$itemNo] = true;
        }
    }
    $items = elpis_sort_odata_values(array_keys($items));
    $ile = []; $res = []; $picks = [];
    foreach (array_chunk($items, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $ile = array_merge($ile, elpis_try_fetch_rows($company, ELPI_ITEM_LEDGER_ENTITY, [
            '$select' => 'Item_No,Location_Code,Remaining_Quantity,Job_No',
            '$filter' => 'Open eq true and (' . elpis_odata_eq_list('Item_No', $chunk) . ')',
        ], $ttl));
        $res = array_merge($res, elpis_try_fetch_rows($company, ELPI_RESERVATION_ENTRY_ENTITY, [
            '$select' => 'Entry_No,Positive,Item_No,Location_Code,Quantity_Base,Reservation_Status,Source_Type,Source_ID,Source_Ref_No',
            '$filter' => elpis_reservation_status_filter() . ' and (' . elpis_odata_eq_list('Item_No', $chunk) . ')',
        ], $ttl));
        $picks = array_merge($picks, elpis_try_fetch_rows($company, ELPI_PLANNING_LINE_ENTITY, [
            '$select' => 'Job_No,No,Location_Code,KVT_Qty_Picked,Qty_Posted,Job_Contract_Entry_No',
            '$filter' => 'KVT_Qty_Picked gt 0 and (' . elpis_odata_eq_list('No', $chunk) . ')',
        ], $ttl));
    }
    $index = elpis_build_free_stock_index($ile, $res, $picks);
    // Artikel zonder open ILE-rijen: geen index → fallback op AppItems.LVS_InventoryExclJobInventory.
    return $index;
}

/** Gedeelde definitie van "probleemstatus" (knop, projectoverzicht en mail). */
function elpis_status_is_problem(string $key): bool
{
    return !empty(ELPI_LINE_STATUSES[$key]['problem']);
}

function elpis_line_is_problem(array $line): bool
{
    return elpis_status_is_problem(elpis_line_status($line)['key']);
}

function elpis_problem_status_keys(): array
{
    return array_values(array_filter(array_keys(ELPI_LINE_STATUSES), 'elpis_status_is_problem'));
}

/** Afgesloten project: LVS_Document_Status '04 FINISHED' of Status 'Voltooid'. */
function elpis_project_is_closed(array $project): bool
{
    $doc = strtoupper(trim((string) ($project['document_status'] ?? $project['LVS_Document_Status'] ?? '')));
    $status = strtolower(trim((string) ($project['status'] ?? $project['Status'] ?? '')));

    return $doc === '04 FINISHED' || in_array($status, ['voltooid', 'completed', 'finished'], true);
}

/**
 * Gepicked of verbruikt: max(KVT_Qty_Picked, Qty_Posted). Niet optellen: in Mímir
 * (project 150933) hebben regels met Qty_Posted=1 ook KVT_Qty_Picked=1, som telt dubbel.
 */
function elpis_line_picked_or_posted(array $line): float
{
    $posted = (float) ($line['qty_posted'] ?? 0);
    // Remaining_Qty (= Quantity − Qty_Posted) is alleen zinvol bij Usage_Link; dan als fallback.
    $remaining = elpis_line_remaining_qty($line);
    if ($remaining !== null) {
        $posted = max($posted, (float) ($line['quantity'] ?? 0) - $remaining);
    }

    return max(0.0, (float) ($line['kvt_qty_picked'] ?? 0), $posted);
}

/**
 * Dekking van wat nog nodig is (N − gepicked/verbruikt), in deze volgorde, elke stap
 * hooguit het resterende tekort (geen dubbeltelling):
 *  1. ontvangen op de gekoppelde inkoop (ligt in projectvoorraad), minus wat al gepicked is
 *  2. reservering op voorraad (Reserved_Quantity/ReservationEntries minus inkoopreservering)
 *  3. open inkoop: gekoppelde PurchaseLines.Outstanding_Quantity, anders inkoopreservering,
 *     plus toegewezen restant van ongekoppelde inkoop (elpis_allocate_project_supply)
 *  4. vrije voorraad (LVS_InventoryExclJobInventory min reserveringen, per artikel verdeeld)
 */
function elpis_line_coverage(array $line): array
{
    $quantity = (float) ($line['quantity'] ?? 0);
    $picked = elpis_line_picked_or_posted($line);
    $need = max(0.0, $quantity - $picked);
    $take = static function (float $available) use (&$need): float {
        $used = min($need, max(0.0, $available));
        $need -= $used;

        return $used;
    };
    $hasPo = trim((string) ($line['purchase_order_no'] ?? '')) !== '';
    $received = $hasPo ? (float) ($line['qty_received'] ?? 0) : (float) (elpis_line_ledger_received_qty($line) ?? 0);
    $onHand = $take($received - $picked);

    $reservedTotal = $line['reserved_qty'] ?? $line['reserved_quantity'] ?? 0;
    $reservedPurchase = (float) ($line['reserved_purchase_qty'] ?? 0);
    $stockReserved = $take((float) $reservedTotal - $reservedPurchase);

    $open = 0.0;
    if ($hasPo && empty($line['completely_received'])) {
        $outstanding = $line['purchase_outstanding_qty'] ?? null;
        $open = ($outstanding !== null && $outstanding !== '') ? (float) $outstanding : (float) ($line['qty_open'] ?? 0);
    } elseif (!$hasPo) {
        $open = $reservedPurchase;
    }
    $open += (float) ($line['alloc_unlinked_purchase'] ?? 0);
    $openPurchase = $take($open);

    $free = array_key_exists('alloc_free_stock', $line)
        ? (float) $line['alloc_free_stock']
        : (is_numeric($line['free_stock'] ?? null) ? (float) $line['free_stock'] : 0.0);
    $freeStock = $take($free);

    return [
        'quantity' => $quantity,
        'picked' => $picked,
        'on_hand' => $onHand + $stockReserved + $freeStock,
        // Dekking voor DIT project (ontvangen/projectvoorraad + reservering) vs. vrije voorraad.
        'project_on_hand' => $onHand + $stockReserved,
        'free_on_hand' => $freeStock,
        'received_on_hand' => $onHand,
        'open_purchase' => $openPurchase,
        'rest' => $need,
    ];
}

/**
 * Status van een regel volgens ELPI_LINE_STATUSES (eerste passende regel wint).
 *
 * @return array{key: string, label: string, receipt_text: string, date: string, date_missing: bool, open_purchase: float, rest: float}
 */
function elpis_line_status(array $line, ?string $today = null): array
{
    $today = $today ?? elpis_today();
    $cov = elpis_line_coverage($line);
    $n = $cov['quantity'];
    $p = $cov['picked'];
    $b = $cov['open_purchase'];
    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($date === '') {
        $date = elpis_normalize_bc_date((string) ($line['alloc_unlinked_date'] ?? ''));
    }
    $date = $date === '' ? '' : substr($date, 0, 10);
    $partial = $p > 0.00001 && $p + 0.00001 < $n;
    $need = elpis_line_need_date($line);
    $lead = elpis_line_lead_time_days($line, $today);
    $latestOrder = elpis_normalize_bc_date((string) ($line['latest_order_date'] ?? ''));
    $latestOrder = $latestOrder === '' ? '' : substr($latestOrder, 0, 10);
    $rest = $cov['rest'];
    $notOnHand = $b > 0.00001 || $rest > 0.00001;
    $projectOnly = $cov['project_on_hand'] > 0.00001 && $cov['free_on_hand'] <= 0.00001;
    $checks = [
        'RETURN' => $n <= 0.00001,
        'CLOSED' => function_exists('elpis_line_work_order_closed') && elpis_line_work_order_closed($line),
        'PICKED' => !empty($line['completely_picked']) || $p + 0.00001 >= $n,
        // Planningsdatum voorbij en nog niet binnen/gepicked.
        'LATE' => $need !== '' && $need < $today && $notOnHand,
        // Verwachte ontvangst na de planningsdatum, of de verwachte datum is al verstreken.
        'DELIVERY_LATE' => $b > 0.00001 && $date !== '' && ($date < $today || ($need !== '' && $date > $need)),
        // Niets besteld en bestellen vandaag haalt de planningsdatum niet meer.
        // LVS_Latest_Order_Date gaat voor; anders vandaag + levertijd > planningsdatum.
        'ORDER_TOO_LATE' => $rest > 0.00001 && ($latestOrder !== ''
            ? $today > $latestOrder
            : ($lead !== null && $need !== '' && date('Y-m-d', strtotime($today . ' +' . $lead . ' days')) > $need)),
        'NO_DATE' => $b > 0.00001 && $date === '',
        'TO_ORDER' => $rest > 0.00001,
        'INBOUND' => $cov['received_on_hand'] > 0.00001 && (float) ($line['inbound_qty'] ?? 0) > 0.00001,
        'PARTIAL_RECEIVED' => $partial && $b <= 0.00001 && $projectOnly,
        'PARTIAL_STOCK' => $partial && $b <= 0.00001,
        'PARTIAL_ORDERED' => $partial,
        'ORDERED' => $b > 0.00001,
        'RECEIVED_TO_PICK' => $projectOnly,
        'STOCK' => true,
    ];
    $key = 'STOCK';
    foreach (array_keys(ELPI_LINE_STATUSES) as $candidate) {
        if (!empty($checks[$candidate])) {
            $key = $candidate;
            break;
        }
    }

    $display = elpis_format_display_date($date);
    $parts = [];
    switch ($key) {
        case 'RETURN':
        case 'CLOSED':
            $parts[] = '—';
            break;
        case 'PICKED':
            $parts[] = LOC('elpis.receipt.fully_picked');
            break;
        case 'LATE':
            if ($b <= 0.00001) {
                $parts[] = LOC('elpis.receipt.late') . ' – ' . LOC('elpis.receipt.planned', elpis_format_display_date($need));
                break;
            }
            // met open inkoop: zelfde tekst als Levering te laat (PO-refs)
        case 'DELIVERY_LATE':
            $refs = elpis_line_purchase_refs($line);
            $parts[] = LOC('elpis.receipt.late') . ' – ' . ($refs !== '' ? $refs : $display);
            break;
        case 'ORDER_TOO_LATE':
            $parts[] = LOC('elpis.receipt.order_too_late', (string) $lead);
            break;
        case 'INBOUND':
            $parts[] = LOC('elpis.receipt.inbound', elpis_format_qty((float) $line['inbound_qty']));
            break;
        case 'NO_DATE':
            $parts[] = LOC('elpis.receipt.missing');
            break;
        case 'TO_ORDER':
            if ($b > 0.00001 && $display !== '') {
                $parts[] = LOC('elpis.receipt.expected', $display);
            }
            break;
        default:
            if ($b > 0.00001 && $display !== '') {
                $refs = elpis_line_purchase_refs($line);
                $parts[] = $refs !== '' ? $refs : $display;
            } elseif (elpis_line_is_received($line)) {
                $received = elpis_format_display_date((string) ($line['received_date'] ?? ''));
                $parts[] = $received !== '' ? LOC('elpis.receipt.already', $received) : LOC('elpis.receipt.already_nodate');
            } else {
                $refs = elpis_line_stock_receipt_refs($line);
                $parts[] = $refs !== '' ? LOC('elpis.receipt.stock') . ' – ' . $refs : LOC('elpis.receipt.on_stock');
            }
    }
    if ($cov['rest'] > 0.00001 && !in_array($key, ['RETURN', 'CLOSED', 'PICKED'], true)) {
        $parts[] = LOC('elpis.receipt.to_order_suffix', elpis_format_qty($cov['rest']));
    }
    $q = 'elpis_format_qty';
    $numbers = LOC('elpis.reason.numbers', $q($n), $q($p), $q($cov['project_on_hand']), $q($cov['free_on_hand']), $q($b), $q($rest));
    $needDisplay = $need !== '' ? elpis_format_display_date($need) : '—';
    switch ($key) {
        case 'RETURN':
            $why = LOC('elpis.reason.return', $q($n));
            break;
        case 'CLOSED':
            $why = LOC('elpis.reason.closed');
            break;
        case 'LATE':
            $why = LOC('elpis.reason.late', $needDisplay);
            break;
        case 'DELIVERY_LATE':
            $why = LOC('elpis.reason.delivery_late', $display !== '' ? $display : '—', $needDisplay);
            break;
        case 'ORDER_TOO_LATE':
            $why = $latestOrder !== ''
                ? LOC('elpis.reason.order_too_late_date', elpis_format_display_date($latestOrder))
                : LOC('elpis.reason.order_too_late', trim((string) ($line['critical_lead_time'] ?? '')) !== '' ? trim((string) $line['critical_lead_time']) : ($lead . 'D'), $needDisplay);
            break;
        case 'NO_DATE':
            $why = LOC('elpis.reason.no_date');
            break;
        case 'TO_ORDER':
            $why = LOC('elpis.reason.to_order', $q($rest));
            break;
        case 'INBOUND':
            $why = LOC('elpis.reason.inbound', $q((float) ($line['inbound_qty'] ?? 0)));
            break;
        case 'PARTIAL_RECEIVED':
        case 'RECEIVED_TO_PICK':
            $why = LOC('elpis.reason.received_to_pick');
            break;
        case 'PARTIAL_ORDERED':
        case 'ORDERED':
            $why = LOC('elpis.reason.ordered', $display !== '' ? $display : '—');
            break;
        case 'PICKED':
            $why = LOC('elpis.reason.picked');
            break;
        default:
            $why = LOC('elpis.reason.stock');
    }
    $reason = $why . ' · ' . $numbers;
    $label = elpis_line_status_label($key);
    if ($partial && !in_array($key, ['PARTIAL_RECEIVED', 'PARTIAL_STOCK', 'PARTIAL_ORDERED'], true) && $key !== 'PICKED') {
        $label .= ' (' . LOC('elpis.status.partial_lc') . ')';
        $parts[] = LOC('elpis.status.partial_lc') . ' ' . elpis_format_qty($p) . '/' . elpis_format_qty($n);
    }

    return [
        'key' => $key,
        'label' => $label,
        'receipt_text' => implode(' · ', $parts),
        // Waarom deze status (dezelfde regel die won), met de getallen; tooltip in de tabel.
        'reason' => $reason,
        'date' => $date,
        'date_missing' => $key === 'NO_DATE',
        'tone' => ELPI_LINE_STATUSES[$key]['tone'],
        'open_purchase' => $b,
        'rest' => $cov['rest'],
    ];
}

/**
 * Per project: ongekoppelde open inkoop (PurchaseLines met Job_No/Job_Task_No/artikel, niet
 * aan een regel gekoppeld) en vrije voorraad verdelen over het resterende tekort,
 * per regel nooit meer dan het tekort. Eerst gekoppelde inkoop (zit al in de dekking).
 */
function elpis_allocate_project_supply(string $projectKey, array $lines, array $purchaseLines): array
{
    $linked = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $doc = strtoupper(trim((string) ($line['purchase_order_no'] ?? '')));
        if ($doc !== '') {
            $linked[$doc . '|' . (int) ($line['purchase_order_line_no'] ?? 0)] = true;
            $linked[$doc . '|*' . strtoupper(trim((string) ($line['item_no'] ?? '')))] = true;
        }
        foreach (array_keys(is_array($line['reserved_purchase_orders'] ?? null) ? $line['reserved_purchase_orders'] : []) as $po) {
            $linked[strtoupper((string) $po) . '|*' . strtoupper(trim((string) ($line['item_no'] ?? '')))] = true;
        }
    }
    $pool = [];
    foreach ($purchaseLines as $purchase) {
        if (!is_array($purchase)) {
            continue;
        }
        if (!isset($purchase['document_no']) && isset($purchase['Document_No'])) {
            $purchase = elpis_normalize_purchase_order_line($purchase);
        }
        if (strtoupper(trim((string) ($purchase['job_no'] ?? ''))) !== $projectKey) {
            continue;
        }
        $doc = strtoupper(trim((string) ($purchase['document_no'] ?? '')));
        $item = strtoupper(trim((string) ($purchase['item_no'] ?? '')));
        if ($doc === '' || $item === '' || isset($linked[$doc . '|' . (int) ($purchase['line_no'] ?? 0)]) || isset($linked[$doc . '|*' . $item])) {
            continue;
        }
        $open = (float) ($purchase['outstanding_qty'] ?? 0);
        if ($open <= 0.00001) {
            continue;
        }
        $key = strtoupper(trim((string) ($purchase['job_task_no'] ?? ''))) . '|' . $item;
        $pool[$key][] = ['qty' => $open, 'date' => elpis_purchase_line_best_date($purchase), 'doc' => $doc];
    }

    $freePool = [];
    foreach ($lines as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $item = strtoupper(trim((string) ($line['item_no'] ?? '')));
        $line['alloc_unlinked_purchase'] = 0.0;
        $line['alloc_free_stock'] = 0.0;
        $line['alloc_purchase_docs'] = [];
        $rest = elpis_line_coverage($line)['rest'];
        $key = strtoupper(trim((string) ($line['job_task_no'] ?? ''))) . '|' . $item;
        $dates = [];
        foreach ($pool[$key] ?? [] as $i => $supply) {
            if ($rest <= 0.00001) {
                break;
            }
            $use = min($rest, $supply['qty']);
            if ($use <= 0.00001) {
                continue;
            }
            $pool[$key][$i]['qty'] -= $use;
            $rest -= $use;
            $line['alloc_unlinked_purchase'] += $use;
            $line['alloc_purchase_docs'][$supply['doc']] = true;
            if ($supply['date'] !== '') {
                $dates[] = $supply['date'];
            }
        }
        if ($dates !== []) {
            $line['alloc_unlinked_date'] = elpis_latest_date($dates);
        }
        if ($item !== '' && !isset($freePool[$item])) {
            $freePool[$item] = (float) ($line['free_stock_unreserved'] ?? (is_numeric($line['free_stock'] ?? null) ? max(0.0, (float) $line['free_stock']) : 0.0));
        }
        if ($item !== '' && $rest > 0.00001) {
            $use = min($rest, $freePool[$item]);
            $freePool[$item] -= $use;
            $line['alloc_free_stock'] = $use;
        }
        $lines[$index] = $line;
    }

    return $lines;
}

/**
 * Functies
 */

function elpis_escape_odata_string(string $value): string
{
    return str_replace("'", "''", trim($value));
}

function &elpis_fetch_memo_bucket(): array
{
    static $memo = [];

    return $memo;
}

function elpis_reset_fetch_memo(): void
{
    $memo = &elpis_fetch_memo_bucket();
    $memo = [];
}

function elpis_fetch_memo_scope(): string
{
    global $auth, $auth_list, $environment, $mimirApi;

    $user = is_array($auth ?? null) ? (string) ($auth['user'] ?? '') : '';
    $list = '';
    if (is_array($auth_list ?? null)) {
        $names = array_keys($auth_list);
        sort($names, SORT_STRING);
        $list = implode(',', $names);
    }
    $circuit = function_exists('odata_mimir_circuit_open') && odata_mimir_circuit_open() ? '1' : '0';
    $mimir = is_string($mimirApi ?? null) && trim($mimirApi) !== '' ? '1' : '0';
    $env = is_string($environment ?? null) ? $environment : '';

    return $mimir . '|' . $circuit . '|' . $env . '|' . $user . '|' . $list;
}

function elpis_fetch_memo_key(string $company, string $entitySet, array $query, int $ttl): string
{
    ksort($query);

    $live = !empty($GLOBALS['ELPIS_LIVE_FETCH']) ? 'live' : 'cache';

    return elpis_fetch_memo_scope() . "\0" . $ttl . "\0" . $live . "\0" . $company . "\0" . $entitySet . "\0" . json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function elpis_log_timing(string $company, string $entitySet, array $query, int $rowCount, float $startedAt, string $cache): void
{
    $filter = (string) ($query['$filter'] ?? '');
    if (strlen($filter) > 240) {
        $filter = substr($filter, 0, 237) . '...';
    }

    $ms = (int) round((microtime(true) - $startedAt) * 1000);
    error_log(sprintf(
        'elpis.timing company="%s" entity=%s rows=%d ms=%d cache=%s filter="%s"',
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $company),
        $entitySet,
        $rowCount,
        $ms,
        $cache,
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $filter)
    ));
}

function elpis_trace_active(): bool
{
    return is_array($GLOBALS['ELPIS_LINE_TRACE'] ?? null);
}

function elpis_trace_item(): string
{
    return strtoupper(trim((string) ($GLOBALS['ELPIS_TRACE_ITEM'] ?? '')));
}

function elpis_trace_wants_item(string $itemNo): bool
{
    $filter = elpis_trace_item();

    return $filter === '' || strtoupper(trim($itemNo)) === $filter;
}

function elpis_trace(string $step, array $data = []): void
{
    if (!elpis_trace_active()) {
        return;
    }

    if (count($GLOBALS['ELPIS_LINE_TRACE']) >= 500) {
        return;
    }

    $GLOBALS['ELPIS_LINE_TRACE'][] = array_merge(['step' => $step], $data);
}

function elpis_odata_eq_list(string $field, array $values, bool $numeric = false): string
{
    $parts = [];
    foreach ($values as $value) {
        if ($numeric) {
            $parts[] = $field . ' eq ' . (int) $value;
            continue;
        }
        $parts[] = $field . " eq '" . elpis_escape_odata_string((string) $value) . "'";
    }

    if ($parts === []) {
        return '';
    }
    if (count($parts) === 1) {
        return $parts[0];
    }

    return '(' . implode(' or ', $parts) . ')';
}

function elpis_and_filters(array $parts): string
{
    $kept = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $kept[] = $part;
        }
    }

    return implode(' and ', $kept);
}

function elpis_sort_odata_values(array $values, bool $numeric = false): array
{
    if ($numeric) {
        $unique = [];
        foreach ($values as $value) {
            $number = (int) $value;
            if ($number > 0) {
                $unique[$number] = $number;
            }
        }
        $values = array_values($unique);
        sort($values, SORT_NUMERIC);

        return $values;
    }

    $values = array_values($values);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);

    return $values;
}

function elpis_company_entity_url(string $baseUrl, string $environment, string $company, string $entitySet, array $query): string
{
    $safeCompany = elpis_escape_odata_string($company);
    $companySegment = "Company('" . rawurlencode($safeCompany) . "')";
    $url = rtrim($baseUrl, '/') . '/' . rawurlencode($environment) . '/ODataV4/' . $companySegment . '/' . rawurlencode($entitySet);

    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

function elpis_fetch_rows(string $company, string $entitySet, array $query, int $ttl = ELPI_CACHE_TTL): array
{
    $memo = &elpis_fetch_memo_bucket();
    $memoKey = elpis_fetch_memo_key($company, $entitySet, $query, $ttl);
    if (array_key_exists($memoKey, $memo)) {
        elpis_log_timing($company, $entitySet, $query, count($memo[$memoKey]), microtime(true), 'memo');

        return $memo[$memoKey];
    }

    $startedAt = microtime(true);
    if (isset($GLOBALS['ELPIS_FETCH_OBSERVER']) && is_callable($GLOBALS['ELPIS_FETCH_OBSERVER'])) {
        $observed = $GLOBALS['ELPIS_FETCH_OBSERVER']($company, $entitySet, $query, $ttl);
        if (is_array($observed)) {
            $memo[$memoKey] = $observed;
            elpis_log_timing($company, $entitySet, $query, count($observed), $startedAt, 'observer');

            return $observed;
        }
    }

    if (odata_mimir_enabled()) {
        // Ververs-nu zet max_age op 0. Een gewone ttl 0 blijft 1 uur, zoals odata_get_all.
        $ttlForMimir = !empty($GLOBALS['ELPIS_LIVE_FETCH']) ? 0 : ($ttl === 0 ? 3600 : $ttl);
        $rows = odata_mimir_or_direct(
            static function () use ($company, $entitySet, $query, $ttlForMimir): array {
                $rows = odata_mimir_query_impl($company, $entitySet, $query, $ttlForMimir);
                if ($rows === [] && $ttlForMimir > 0 && !empty($GLOBALS['ELPIS_NIGHTLY_RETRY_EMPTY'])) {
                    // Leeg uit de Mímir-cache is verdacht in de nightly: één keer live opnieuw.
                    $rows = odata_mimir_query_impl($company, $entitySet, $query, 0);
                    error_log('elpis.nightly empty-retry company=' . $company . ' entity=' . $entitySet
                        . ' filter="' . (string) ($query['$filter'] ?? '') . '" live_rows=' . count($rows));
                }

                return $rows;
            },
            static function () use ($company, $entitySet, $query, $ttl): array {
                return elpis_fetch_rows_direct($company, $entitySet, $query, $ttl);
            }
        );
    } else {
        $rows = elpis_fetch_rows_direct($company, $entitySet, $query, $ttl);
    }

    $memo[$memoKey] = $rows;
    elpis_log_timing($company, $entitySet, $query, count($rows), $startedAt, 'fetch');

    return $rows;
}

/**
 * Pre-Mímir pad: environment + auth uit auth.php, company-URL en lokale filecache.
 */
function elpis_fetch_rows_direct(string $company, string $entitySet, array $query, int $ttl = ELPI_CACHE_TTL): array
{
    $environment = auth_get_environment_for_company($company, $ttl);
    $auth = auth_get_auth_for_environment($environment);
    if (odata_mimir_enabled() && function_exists('odata_auth_is_usable') && !odata_auth_is_usable($auth) && function_exists('odata_bc_auth_for_fallback')) {
        $resolved = odata_bc_auth_for_fallback([]);
        if (is_array($resolved)) {
            $auth = $resolved;
        }
    }
    $root = $GLOBALS['baseUrl'] ?? null;
    if (!is_string($root) || trim($root) === '' || stripos($root, 'mimir.invalid') !== false) {
        $alt = $GLOBALS['base'] ?? null;
        $root = is_string($alt) ? $alt : '';
    }
    $url = elpis_company_entity_url($root, $environment, $company, $entitySet, $query);

    if (!empty($GLOBALS['ELPIS_LIVE_FETCH']) && function_exists('build_cache_key') && function_exists('cache_path_for_key')) {
        $cachePath = cache_path_for_key(build_cache_key($url, is_array($auth) ? $auth : []));
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
    }

    // Bij een open Mímir-circuit direct de oude OData-client, anders opnieuw Mímir in.
    if (odata_mimir_enabled() && function_exists('odata_get_all_direct')) {
        return odata_get_all_direct($url, $auth, $ttl);
    }

    return odata_get_all($url, $auth, $ttl);
}

/**
 * Bronnen van de ontvangst-/inkoopstatus. Een mislukte query hierop (bv. Mímir-timeout)
 * geeft een onvolledige uitkomst ("geen inkoop"); die mag niet in de projectcache.
 */
function elpis_entity_is_receipt_source(string $entitySet): bool
{
    return in_array($entitySet, [
        ELPI_PLANNING_LINE_ENTITY,
        ELPI_INKOOP_PLANNING_ENTITY,
        ELPI_RESERVATION_ENTRY_ENTITY,
        ELPI_PURCHASE_ORDER_LINE_ENTITY,
        ELPI_ITEM_LEDGER_ENTITY,
    ], true);
}

function elpis_note_receipt_fetch_failure(string $company, string $entitySet, string $message): void
{
    if (!isset($GLOBALS['ELPIS_RECEIPT_FETCH_FAILURES']) || !is_array($GLOBALS['ELPIS_RECEIPT_FETCH_FAILURES'])) {
        $GLOBALS['ELPIS_RECEIPT_FETCH_FAILURES'] = [];
    }
    $GLOBALS['ELPIS_RECEIPT_FETCH_FAILURES'][] = ['company' => $company, 'entity' => $entitySet, 'message' => substr($message, 0, 300)];
}

function elpis_receipt_fetch_failure_count(): int
{
    return count((array) ($GLOBALS['ELPIS_RECEIPT_FETCH_FAILURES'] ?? []));
}

function elpis_try_fetch_rows(string $company, string $entitySet, array $query, int $ttl = ELPI_CACHE_TTL): array
{
    // Een BC-fout, zoals een ongeldige enum, wordt een lege set. De pagina laadt dan
    // door alsof er geen rijen waren. Alleen elpis.timing cache=error bevat de melding.
    $startedAt = microtime(true);
    try {
        $rows = elpis_fetch_rows($company, $entitySet, $query, $ttl);
        elpis_trace('odata', [
            'entity' => $entitySet,
            'filter' => (string) ($query['$filter'] ?? ''),
            'rows' => count($rows),
            'ttl' => $ttl,
            'live' => !empty($GLOBALS['ELPIS_LIVE_FETCH']),
            'error' => '',
        ]);

        return $rows;
    } catch (Throwable $error) {
        elpis_log_timing($company, $entitySet, $query, 0, $startedAt, 'error');
        $message = str_replace(["\n", "\r"], ' ', $error->getMessage());
        error_log('elpis.timing error entity=' . $entitySet . ' message=' . $message);
        if (isset($GLOBALS['ELPIS_FETCH_ERRORS']) && is_array($GLOBALS['ELPIS_FETCH_ERRORS'])) {
            $GLOBALS['ELPIS_FETCH_ERRORS'][] = ['company' => $company, 'entity' => $entitySet, 'message' => substr($message, 0, 300)];
        }
        if ($entitySet === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
            elpis_log_purchase_lines_failure($company, $query, $message);
        }
        if (elpis_entity_is_receipt_source($entitySet)) {
            elpis_note_receipt_fetch_failure($company, $entitySet, $message);
        }
        elpis_trace('odata', [
            'entity' => $entitySet,
            'filter' => (string) ($query['$filter'] ?? ''),
            'rows' => 0,
            'ttl' => $ttl,
            'live' => !empty($GLOBALS['ELPIS_LIVE_FETCH']),
            'error' => $message,
        ]);

        return [];
    }
}

function elpis_default_companies(): array
{
    return [
        'Koninklijke van Twist',
        'Hunter van Twist',
        'KVT Gas',
    ];
}

function elpis_companies_for_page(int $ttl = ELPI_CACHE_TTL): array
{
    try {
        if (odata_mimir_enabled()) {
            // Bij een Mímir-fout haalt list_companies dezelfde namen via directe BC OData op.
            $companies = odata_mimir_list_companies(null);
            // Vul demeter_* globals / map voor eventuele callers.
            try {
                auth_discover_companies_across_active_environments($ttl);
            } catch (Throwable $ignored) {
            }
            if ($companies !== []) {
                return $companies;
            }
        } else {
            $result = auth_discover_companies_across_active_environments($ttl);
            $companies = is_array($result['companies'] ?? null) ? $result['companies'] : [];
            if ($companies !== []) {
                return $companies;
            }
        }
    } catch (Throwable $ignored) {
    }

    return elpis_default_companies();
}

function elpis_is_all_managers_selection(?string $value): bool
{
    return trim((string) $value) === ELPI_MANAGER_ALL;
}

function elpis_format_manager_label(string $manager): string
{
    if (elpis_is_all_managers_selection($manager)) {
        return '';
    }

    $manager = elpis_normalize_bc_username($manager);
    if (str_starts_with($manager, 'KVT\\')) {
        return substr($manager, 4);
    }

    return $manager;
}

function elpis_normalize_bc_username(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (str_contains($value, '\\')) {
        [$domain, $user] = explode('\\', $value, 2);
        return strtoupper(trim($domain)) . '\\' . strtoupper(trim($user));
    }

    return 'KVT\\' . strtoupper($value);
}

function elpis_current_user_email(): string
{
    $email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));

    return $email !== '' ? $email : ELPI_DEFAULT_USER_EMAIL;
}

function elpis_load_manager_prefs(string $email): array
{
    if (!function_exists('loadUserPrefs')) {
        return [];
    }

    $prefs = loadUserPrefs($email);
    $raw = $prefs['elpis_managers_by_company'] ?? '{}';
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
}

function elpis_save_dropdown_prefs(string $email, string $company, string $manager): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    $managersByCompany = elpis_load_manager_prefs($email);
    if ($manager !== '') {
        $managersByCompany[$company] = $manager;
    }

    saveUserPref($email, 'elpis_company', $company);
    saveUserPref(
        $email,
        'elpis_managers_by_company',
        json_encode($managersByCompany, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function elpis_resolve_company_choice(array $companies, ?string $requested, ?string $saved): string
{
    if ($companies === []) {
        return '';
    }

    $requested = trim((string) $requested);
    if ($requested !== '' && in_array($requested, $companies, true)) {
        return $requested;
    }

    $saved = trim((string) $saved);
    if ($saved !== '' && in_array($saved, $companies, true)) {
        return $saved;
    }

    return (string) $companies[0];
}

function elpis_pick_manager_from_list(array $managers, string $preferred): string
{
    $preferred = elpis_normalize_bc_username($preferred);
    if ($preferred === '') {
        return '';
    }

    if (in_array($preferred, $managers, true)) {
        return $preferred;
    }

    foreach ($managers as $candidate) {
        if (strcasecmp($candidate, $preferred) === 0) {
            return $candidate;
        }
    }

    return '';
}

function elpis_user_has_saved_selection_prefs(string $email, string $savedCompany, array $savedManagersByCompany): bool
{
    if (trim($savedCompany) !== '') {
        return true;
    }

    return $savedManagersByCompany !== [];
}

function elpis_discover_selection_for_new_user(
    array $companies,
    string $email,
    string $requestedCompany,
    string $requestedManager,
    string $savedCompany,
    array $savedManagersByCompany,
    int $ttl = ELPI_PROJECT_MANAGERS_TTL
): ?array {
    if ($requestedCompany !== '' || $requestedManager !== '') {
        return null;
    }

    if (elpis_user_has_saved_selection_prefs($email, $savedCompany, $savedManagersByCompany)) {
        return null;
    }

    $bcUser = elpis_resolve_project_manager_from_email($email);
    if ($bcUser === null || $bcUser === '') {
        return null;
    }

    foreach ($companies as $company) {
        $managers = elpis_fetch_project_managers($company, $ttl);
        $match = elpis_pick_manager_from_list($managers, $bcUser);
        if ($match === '') {
            continue;
        }

        return [
            'company' => $company,
            'manager' => $match,
        ];
    }

    return null;
}

function elpis_resolve_manager_choice(
    array $managers,
    ?string $requested,
    string $company,
    array $savedManagersByCompany,
    ?string $email
): string {
    if (elpis_is_all_managers_selection($requested)) {
        return ELPI_MANAGER_ALL;
    }

    $savedForCompany = trim((string) ($savedManagersByCompany[$company] ?? ''));
    if (elpis_is_all_managers_selection($savedForCompany) && (string) $requested === '') {
        return ELPI_MANAGER_ALL;
    }

    if ($managers === []) {
        return '';
    }

    $fromRequest = elpis_pick_manager_from_list($managers, (string) $requested);
    if ($fromRequest !== '') {
        return $fromRequest;
    }

    if (!elpis_is_all_managers_selection($savedForCompany)) {
        $fromSaved = elpis_pick_manager_from_list($managers, $savedForCompany);
        if ($fromSaved !== '') {
            return $fromSaved;
        }
    }

    $fromEmail = elpis_resolve_project_manager_from_email($email);
    if ($fromEmail !== null) {
        $fromEmailMatch = elpis_pick_manager_from_list($managers, $fromEmail);
        if ($fromEmailMatch !== '') {
            return $fromEmailMatch;
        }
    }

    return (string) $managers[0];
}

function elpis_resolve_project_manager_from_email(?string $email): ?string
{
    $email = strtolower(trim((string) $email));
    if ($email === '') {
        return null;
    }

    if (isset(ELPI_PROJECT_MANAGER_EMAIL_MAP[$email])) {
        return elpis_normalize_bc_username(ELPI_PROJECT_MANAGER_EMAIL_MAP[$email]);
    }

    $localPart = strstr($email, '@', true);
    if (!is_string($localPart) || trim($localPart) === '') {
        return null;
    }

    return elpis_normalize_bc_username($localPart);
}

function elpis_normalize_project_row(array $row): array
{
    return [
        'no' => trim((string) ($row['No'] ?? '')),
        'description' => trim((string) ($row['Description'] ?? '')),
        'status' => trim((string) ($row['Status'] ?? '')),
        'document_status' => trim((string) ($row['LVS_Document_Status'] ?? '')),
        'project_manager' => elpis_normalize_bc_username((string) ($row['Project_Manager'] ?? '')),
    ];
}

function elpis_normalize_bc_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || str_starts_with($value, '0001-01-01')) {
        return '';
    }

    return $value;
}

function elpis_format_display_date(?string $value): string
{
    $value = elpis_normalize_bc_date($value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d-m-Y', $timestamp);
}

function elpis_line_remaining_qty(array $line): ?float
{
    if (!array_key_exists('remaining_qty', $line) || $line['remaining_qty'] === null || $line['remaining_qty'] === '') {
        return null;
    }

    return (float) $line['remaining_qty'];
}

function elpis_today(): string
{
    $override = $GLOBALS['ELPIS_TODAY'] ?? null;
    if (is_string($override) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $override) === 1) {
        return $override;
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');
}

function elpis_line_is_not_required(array $line): bool
{
    return in_array(elpis_line_status($line)['key'], ['RETURN', 'CLOSED'], true);
}

function elpis_line_purchase_outstanding_closed(array $line): bool
{
    if (!array_key_exists('purchase_outstanding_qty', $line)) {
        return false;
    }

    $value = $line['purchase_outstanding_qty'];
    if ($value === null || $value === '') {
        return false;
    }

    return (float) $value <= 0.00001;
}

function elpis_line_purchase_outstanding_open(array $line): bool
{
    if (!array_key_exists('purchase_outstanding_qty', $line)) {
        return false;
    }

    $value = $line['purchase_outstanding_qty'];
    if ($value === null || $value === '') {
        return false;
    }

    return (float) $value > 0.00001;
}

function elpis_line_inkoop_outstanding_closed(array $line): bool
{
    if (trim((string) ($line['purchase_order_no'] ?? '')) === '') {
        return false;
    }
    if (!array_key_exists('inkoop_outstanding_qty', $line)) {
        return false;
    }

    $value = $line['inkoop_outstanding_qty'];
    if ($value === null || $value === '') {
        return false;
    }

    return (float) $value <= 0.00001;
}

function elpis_line_ledger_received_qty(array $line): ?float
{
    if (!array_key_exists('ledger_received_qty', $line)) {
        return null;
    }

    $value = $line['ledger_received_qty'];
    if ($value === null || $value === '') {
        return null;
    }

    return (float) $value;
}

function elpis_line_shown_ordered_qty(array $line): float
{
    return (float) ($line['qty_ordered'] ?? 0);
}

function elpis_line_shown_received_qty(array $line): float
{
    return (float) ($line['qty_received'] ?? 0);
}

function elpis_line_is_received(array $line): bool
{
    if (!empty($line['completely_received']) || !empty($line['completely_picked'])) {
        return true;
    }

    $quantity = (float) ($line['quantity'] ?? 0);
    $picked = (float) ($line['kvt_qty_picked'] ?? 0);
    if ($quantity > 0.00001 && $picked + 0.00001 >= $quantity) {
        return true;
    }

    $posted = (float) ($line['qty_posted'] ?? 0);
    if ($quantity > 0.00001 && $posted + 0.00001 >= $quantity) {
        return true;
    }

    $ledgerQty = elpis_line_ledger_received_qty($line);
    if ($quantity > 0.00001 && $ledgerQty !== null && $ledgerQty + 0.00001 >= $quantity) {
        return true;
    }

    if (elpis_line_purchase_outstanding_closed($line)) {
        return true;
    }

    // Outstanding_Quantity > 0 op de inkoopregel: de regel is niet ontvangen,
    // ook niet als Remaining_Qty of een afgeleide Ontvangen dat suggereert.
    if (elpis_line_purchase_outstanding_open($line)) {
        return false;
    }

    $remaining = elpis_line_remaining_qty($line);

    return $remaining !== null && $remaining <= 0.00001;
}

function elpis_refresh_receipt_state(array $line): array
{
    $key = elpis_line_status($line)['key'];
    $state = ELPI_LINE_STATUSES[$key]['receipt_state'] ?? '';
    if (in_array($key, ['STOCK', 'PARTIAL_STOCK', 'RECEIVED_TO_PICK', 'PARTIAL_RECEIVED'], true) && elpis_line_is_received($line)) {
        $state = 'received';
    }
    $line['receipt_state'] = $state;

    return $line;
}

function elpis_line_is_late(array $line, ?string $today = null): bool
{
    return elpis_line_status($line, $today)['tone'] === 'alert';
}

function elpis_line_still_expecting_receipt(array $line): bool
{
    if (elpis_line_is_received($line) || elpis_line_is_not_required($line)) {
        return false;
    }

    $remaining = elpis_line_remaining_qty($line);
    if ($remaining !== null) {
        return $remaining > 0.00001;
    }

    if (!empty($line['completely_received'])) {
        return false;
    }

    $quantity = (float) ($line['quantity'] ?? 0);
    $qtyReceived = (float) ($line['qty_received'] ?? 0);
    if ($quantity > 0.00001) {
        return $qtyReceived + 0.00001 < $quantity;
    }

    $qtyOpen = (float) ($line['qty_open'] ?? 0);
    $qtyToOrder = (float) ($line['qty_to_order'] ?? 0);
    $qtyOrdered = (float) ($line['qty_ordered'] ?? 0);

    return $qtyOpen > 0 || $qtyToOrder > 0 || ($qtyOrdered > 0 && $qtyReceived < $qtyOrdered);
}

/** Statustekst voor de statuskolom. */
function elpis_line_status_text(array $line): string
{
    return elpis_line_status_group($line)['label'];
}

/**
 * Filtersleutel + tekst voor de statuskolom.
 *
 * @return array{key: string, label: string, filter_label: string, reason: string}
 */
function elpis_line_status_group(array $line): array
{
    $status = elpis_line_status($line);

    return ['key' => $status['key'], 'label' => $status['label'], 'filter_label' => elpis_line_status_label($status['key']), 'reason' => $status['reason']];
}

/** "PO12605553, 24-09-2026" (meerdere: gescheiden door "; "). */
function elpis_line_purchase_refs(array $line): string
{
    $orders = is_array($line['reserved_purchase_orders'] ?? null) ? $line['reserved_purchase_orders'] : [];
    $direct = strtoupper(trim((string) ($line['purchase_order_no'] ?? '')));
    if ($direct !== '' && !isset($orders[$direct])) {
        $orders = [$direct => (string) ($line['expected_receipt_date'] ?? '')] + $orders;
    }
    $parts = [];
    foreach ($orders as $poNo => $date) {
        $date = elpis_format_display_date((string) $date);
        $parts[] = $date !== '' ? $poNo . ', ' . $date : (string) $poNo;
    }

    return implode('; ', $parts);
}

/** "PWR2601849 (16-02-2026)" (meerdere: gescheiden door ", "). */
function elpis_line_stock_receipt_refs(array $line): string
{
    $receipts = is_array($line['stock_receipts'] ?? null) ? $line['stock_receipts'] : [];
    $parts = [];
    foreach ($receipts as $docNo => $date) {
        $date = elpis_format_display_date((string) $date);
        $parts[] = $date !== '' ? $docNo . ' (' . $date . ')' : (string) $docNo;
    }

    return implode(', ', $parts);
}

function elpis_line_expected_receipt_display(array $line): string
{
    return elpis_line_status($line)['receipt_text'];
}

/** Hoeveelheid zonder reservering ("nog te bestellen"), 0 als volledig gedekt. */
function elpis_line_unreserved_qty(array $line): float
{
    $quantity = (float) ($line['quantity'] ?? 0);
    $reserved = (float) ($line['reserved_qty'] ?? 0);
    if ($quantity <= 0.00001 || $reserved <= 0.00001 || $reserved >= $quantity - 0.00001) {
        return 0.0;
    }

    return $quantity - $reserved;
}

/** "Uit voorraad 1 (PWR2635059) · 15 nog te bestellen". */
function elpis_line_partial_stock_text(array $line): string
{
    $toOrder = elpis_line_unreserved_qty($line);
    if ($toOrder <= 0.00001 || empty($line['reserved_from_stock'])) {
        return '';
    }
    $stockQty = (float) ($line['reserved_qty'] ?? 0) - (float) ($line['reserved_purchase_qty'] ?? 0);
    if ($stockQty <= 0.00001) {
        $stockQty = (float) ($line['reserved_qty'] ?? 0);
    }
    $refs = array_keys(is_array($line['stock_receipts'] ?? null) ? $line['stock_receipts'] : []);
    $text = LOC('elpis.receipt.stock') . ' ' . elpis_format_qty($stockQty);
    if ($refs !== []) {
        $text .= ' (' . implode(', ', $refs) . ')';
    }

    return $text . ' · ' . LOC('elpis.receipt.to_order_suffix', elpis_format_qty($toOrder));
}

function elpis_receipt_sort_value(array $line): string
{
    $state = (string) ($line['receipt_state'] ?? '');
    if ($state === 'received' || $state === 'not_required') {
        return '0000-00-00';
    }

    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($date !== '') {
        return substr($date, 0, 10);
    }

    return '9999-99-99';
}

function elpis_line_quantities(float $quantity, string $purchaseOrderNo, float $orderedQty, ?float $outstanding = null): array
{
    $hasPurchaseOrder = $purchaseOrderNo !== '';
    if ($orderedQty <= 0 && $hasPurchaseOrder) {
        $orderedQty = $quantity;
    }

    if ($hasPurchaseOrder) {
        // null: outstanding is onbekend. LVS_Outstanding_Qty_Base van de
        // werkorderregel is dat vaak 0 zonder dat er iets ontvangen is.
        if ($outstanding === null) {
            return [
                'qty_to_order' => 0.0,
                'qty_ordered' => $orderedQty,
                'qty_open' => $orderedQty,
                'qty_received' => 0.0,
            ];
        }

        $qtyReceived = max(0.0, $orderedQty - $outstanding);

        return [
            'qty_to_order' => 0.0,
            'qty_ordered' => $orderedQty,
            'qty_open' => max(0.0, $orderedQty - $qtyReceived),
            'qty_received' => $qtyReceived,
        ];
    }

    return [
        'qty_to_order' => max($outstanding ?? 0.0, $quantity),
        'qty_ordered' => 0.0,
        'qty_open' => 0.0,
        'qty_received' => 0.0,
    ];
}

function elpis_apply_purchase_line_quantities(array $line): array
{
    if (trim((string) ($line['purchase_order_no'] ?? '')) === '') {
        return $line;
    }

    $ordered = (float) ($line['qty_ordered'] ?? 0);
    if ($ordered <= 0.00001) {
        $ordered = (float) ($line['quantity'] ?? 0);
    }
    if ($ordered <= 0.00001) {
        return $line;
    }

    if (!empty($line['completely_received'])) {
        $line['qty_to_order'] = 0.0;
        $line['qty_ordered'] = $ordered;
        $line['qty_open'] = 0.0;
        $line['qty_received'] = $ordered;

        return $line;
    }

    if (!array_key_exists('purchase_outstanding_qty', $line) || $line['purchase_outstanding_qty'] === null || $line['purchase_outstanding_qty'] === '') {
        return $line;
    }

    $outstanding = (float) $line['purchase_outstanding_qty'];
    $received = max(0.0, $ordered - $outstanding);
    $line['qty_to_order'] = 0.0;
    $line['qty_ordered'] = $ordered;
    $line['qty_received'] = $received;
    $line['qty_open'] = max(0.0, $ordered - $received);

    return $line;
}

function elpis_normalize_planning_line_row(array $row): array
{
    $purchaseOrderNo = trim((string) ($row['LVS_Purchase_Order_No'] ?? ''));
    $quantity = (float) ($row['Quantity'] ?? 0);
    // Remaining_Qty = Quantity - Qty_Posted geldt alleen bij Usage_Link = true;
    // zonder Usage_Link is het altijd 0 en zegt het niets (findings.md).
    $usageLink = array_key_exists('Usage_Link', $row) ? elpis_bc_bool($row['Usage_Link']) : null;
    $bcRemainingQty = array_key_exists('Remaining_Qty', $row) ? (float) $row['Remaining_Qty'] : null;
    $remainingQty = $usageLink === true ? $bcRemainingQty : null;
    $reservedQuantity = array_key_exists('Reserved_Quantity', $row) ? (float) $row['Reserved_Quantity'] : null;
    $quantities = elpis_line_quantities(
        $quantity,
        $purchaseOrderNo,
        (float) ($row['LVS_Quantity_Order_UoM'] ?? 0),
        null
    );
    $planning = elpis_planning_date_from_row($row);
    $line = array_merge($quantities, [
        'job_task_no' => trim((string) ($row['Job_Task_No'] ?? '')),
        'job_contract_entry_no' => (int) ($row['Job_Contract_Entry_No'] ?? 0),
        'work_order_no' => trim((string) ($row['LVS_Work_Order_No'] ?? '')),
        'item_no' => trim((string) ($row['No'] ?? '')),
        'description' => trim((string) ($row['Description'] ?? '')),
        'quantity' => $quantity,
        'remaining_qty' => $remainingQty,
        'reserved_quantity' => $reservedQuantity,
        'purchase_order_no' => $purchaseOrderNo,
        'purchase_order_line_no' => (int) ($row['LVS_Purchase_Order_Line_No'] ?? 0),
        'completely_received' => elpis_bc_bool($row['LVS_Completely_Received'] ?? false),
        'completely_picked' => elpis_bc_bool($row['KVT_Completely_Picked'] ?? false),
        'kvt_qty_picked' => (float) ($row['KVT_Qty_Picked'] ?? 0),
        'qty_posted' => (float) ($row['Qty_Posted'] ?? 0),
        'location_code' => strtoupper(trim((string) ($row['Location_Code'] ?? ''))),
        'kvt_qty_in_whse_pick' => (float) ($row['KVT_Qty_in_Whse_Pick'] ?? 0),
        'usage_link' => $usageLink,
        'bc_remaining_qty' => $bcRemainingQty,
        'planning_date' => $planning['date'],
        'planning_date_field' => $planning['field'],
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['KVT_Expected_Receipt_Date'] ?? '')),
        'critical_lead_time' => trim((string) ($row['LVS_Critical_Lead_Time'] ?? '')),
        'latest_order_date' => elpis_normalize_bc_date((string) ($row['LVS_Latest_Order_Date'] ?? '')),
        'expected_receipt_source' => '',
        'received_date' => '',
        'free_stock' => null,
        'receipt_state' => '',
        'covered_by_stock' => 0.0,
        'line_no' => (int) ($row['Line_No'] ?? 0),
    ]);
    $qtySource = $purchaseOrderNo !== ''
        ? 'WerkordersLines.Quantity via elpis_line_quantities; Outstanding_Quantity nog niet gezet'
        : 'WerkordersLines.Quantity, geen inkoopordernummer';
    elpis_trace_normalized_line($line, $qtySource);

    return $line;
}

function elpis_trace_normalized_line(array $line, string $qtySource): void
{
    if (!elpis_trace_wants_item((string) ($line['item_no'] ?? ''))) {
        return;
    }

    elpis_trace('normalize', [
        'item_no' => (string) ($line['item_no'] ?? ''),
        'line_no' => (int) ($line['line_no'] ?? 0),
        'job_task_no' => (string) ($line['job_task_no'] ?? ''),
        'work_order_no' => (string) ($line['work_order_no'] ?? ''),
        'purchase_order_no' => (string) ($line['purchase_order_no'] ?? ''),
        'purchase_order_line_no' => (int) ($line['purchase_order_line_no'] ?? 0),
        'quantity' => (float) ($line['quantity'] ?? 0),
        'qty_ordered' => (float) ($line['qty_ordered'] ?? 0),
        'qty_open' => (float) ($line['qty_open'] ?? 0),
        'qty_received' => (float) ($line['qty_received'] ?? 0),
        'qty_source' => $qtySource,
    ]);
}

function elpis_planning_date_from_row(array $row): array
{
    foreach ([
        'Planning_Date' => 'Planning_Date',
        'Planned_Delivery_Date' => 'Planned_Delivery_Date',
        'Promised_Delivery_Date' => 'Promised_Delivery_Date',
        'Requested_Delivery_Date' => 'Requested_Delivery_Date',
    ] as $field => $label) {
        $date = elpis_normalize_bc_date((string) ($row[$field] ?? ''));
        if ($date !== '') {
            return ['date' => substr($date, 0, 10), 'field' => $label];
        }
    }

    return ['date' => '', 'field' => 'Planning_Date'];
}

function elpis_fetch_project_managers(string $company, int $ttl = ELPI_PROJECT_MANAGERS_TTL): array
{
    $rows = elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'Project_Manager',
        // Geen "Project_Manager ne ''" in de query: Mímir geeft daarop sinds de taalwissel van
        // POWERBISERV (22-10-2026) 200 met een lege set. Lege managers vallen client-side weg.
    ], $ttl);

    $managers = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $manager = elpis_normalize_bc_username((string) ($row['Project_Manager'] ?? ''));
        if ($manager === '' || isset($seen[$manager])) {
            continue;
        }
        $seen[$manager] = true;
        $managers[] = $manager;
    }

    natcasesort($managers);
    return array_values($managers);
}

function elpis_collect_projects_from_rows(array $rows): array
{
    $projects = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = elpis_normalize_project_row($row);
        if ($normalized['no'] !== '' && $normalized['project_manager'] !== '' && !elpis_project_is_closed($normalized)) {
            $projects[] = $normalized;
        }
    }

    return $projects;
}

function elpis_fetch_projects_for_company(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    return elpis_collect_projects_from_rows(elpis_fetch_project_rows_for_company($company, $ttl));
}

/** Ruwe AppProjecten-rijen (voor tellingen in de nightly), zonder client-side filter. */
function elpis_fetch_project_rows_for_company(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    return elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'No,Description,Project_Manager,Status,LVS_Document_Status',
        // Geen "Project_Manager ne ''" in de query: Mímir geeft daarop sinds de taalwissel van
        // POWERBISERV (22-10-2026) 200 met een lege set. Lege managers vallen client-side weg.
        '$orderby' => 'No desc',
    ], $ttl);
}

function elpis_filter_projects_for_manager(array $projects, string $projectManager): array
{
    if (elpis_is_all_managers_selection($projectManager)) {
        return array_values($projects);
    }

    $manager = elpis_normalize_bc_username($projectManager);
    if ($manager === '') {
        return [];
    }

    return array_values(array_filter($projects, static function (array $project) use ($manager): bool {
        return strcasecmp((string) ($project['project_manager'] ?? ''), $manager) === 0;
    }));
}

function elpis_fetch_projects_for_manager(string $company, string $projectManager, int $ttl = ELPI_CACHE_TTL): array
{
    return elpis_filter_projects_for_manager(
        elpis_fetch_projects_for_company($company, $ttl),
        $projectManager
    );
}

function elpis_planning_line_type_filter(): string
{
    // Geen Type-filter in OData meer: de geldige optietekst hangt af van de taal van de
    // BC-gebruiker ('Artikel' in NL, 'Item' in EN) en een ongeldige optie laat de hele query
    // falen. elpis_row_is_item_type() filtert client-side.
    return '';
}

/**
 * Normaliseert een BC-optiewaarde/caption: hoofdletters, zonder nummer-prefix ("02 IN PROGRESS"
 * -> "IN PROGRESS"), underscores en dubbele spaties.
 */
function elpis_normalize_option_value(string $value): string
{
    $value = strtoupper(trim(str_replace(['_', '-'], ' ', $value)));
    $value = (string) preg_replace('/\s+/', ' ', $value);

    return $value;
}

function elpis_option_without_prefix(string $value): string
{
    return trim((string) preg_replace('/^\d+\s+/', '', elpis_normalize_option_value($value)));
}

/**
 * Taalonafhankelijke vergelijking met een lijst bekende captions (NL/EN/DE) of nummer-prefixen.
 *
 * @param list<string> $candidates
 */
function elpis_option_matches(string $value, array $candidates): bool
{
    $full = elpis_normalize_option_value($value);
    if ($full === '') {
        return false;
    }
    $bare = elpis_option_without_prefix($value);
    foreach ($candidates as $candidate) {
        $c = elpis_normalize_option_value((string) $candidate);
        if ($c === '') {
            continue;
        }
        if ($c === $full || $c === $bare || elpis_option_without_prefix((string) $candidate) === $bare) {
            return true;
        }
    }

    return false;
}

/** Planningsregel-Type "Artikel" in NL, "Item" in EN, "Artikel" in DE; ook optienummer 1. */
function elpis_row_is_item_type(array $row): bool
{
    if (!array_key_exists('Type', $row)) {
        return true;
    }
    $type = trim((string) $row['Type']);
    if ($type === '') {
        return true;
    }

    return $type === '1' || elpis_option_matches($type, [ELPI_ITEM_LINE_TYPE, 'Item', 'Artikel']);
}

/**
 * Zelfde veld als gechunckte OR (BC heeft geen in), daarna AND met Type.
 * On-prem OData staat geen OR tussen verschillende velden toe.
 *
 * @return list<string>
 */
function elpis_job_scoped_filters(array $projectNos): array
{
    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos($projectNos));
    $filters = [];
    foreach (array_chunk($projectNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $filters[] = elpis_and_filters([
            elpis_odata_eq_list('Job_No', $chunk),
            elpis_planning_line_type_filter(),
        ]);
    }

    return $filters;
}

function elpis_project_nos_from_projects(array $projects): array
{
    $projectNos = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }
        $projectNo = trim((string) ($project['no'] ?? ''));
        if ($projectNo !== '') {
            $projectNos[] = $projectNo;
        }
    }

    return elpis_normalize_project_nos($projectNos);
}

function elpis_werkorders_line_select_fields(): string
{
    return 'Job_No,Job_Task_No,Line_No,Job_Contract_Entry_No,Type,No,Description,Quantity,Remaining_Qty,Reserved_Quantity,KVT_Expected_Receipt_Date,Planning_Date,Planned_Delivery_Date,LVS_Work_Order_No,LVS_Purchase_Order_No,LVS_Purchase_Order_Line_No,KVT_Qty_Picked,KVT_Completely_Picked,KVT_Qty_in_Whse_Pick,Qty_Posted,Usage_Link,Location_Code,LVS_Critical_Lead_Time,LVS_Latest_Order_Date';
}

function elpis_inkoop_planning_select_fields(): string
{
    // KVT_Qty_Picked en KVT_Completely_Picked staan op WerkordersLines, niet hier.
    // LVS_Completely_Received van deze planningsregel telt als volledig ontvangen.
    return 'Job_No,Job_Task_No,Line_No,No,Type,KVT_Expected_Receipt_Date,Planned_Delivery_Date,Promised_Delivery_Date,Requested_Delivery_Date,Reserved_Quantity,Remaining_Qty,LVS_Purchase_Order_No,LVS_Purchase_Order_Line_No,LVS_Work_Order_No,LVS_Outstanding_Qty_Base,LVS_Completely_Received';
}

function elpis_planning_line_select_fields(): string
{
    return elpis_inkoop_planning_select_fields();
}

function elpis_planning_line_match_key(string $jobNo, string $jobTaskNo, int $lineNo): string
{
    return strtoupper(trim($jobNo)) . '|' . strtoupper(trim($jobTaskNo)) . '|' . $lineNo;
}

function elpis_collect_planning_line_row(array $row, array &$lines): void
{
    if (!is_array($row)) {
        return;
    }

    if (!elpis_row_is_item_type($row)) {
        return;
    }
    $normalized = elpis_normalize_planning_line_row($row);
    if ($normalized['item_no'] !== '' || $normalized['description'] !== '') {
        $lines[] = $normalized;
    }
}

function elpis_fetch_planning_lines_for_project(string $company, string $projectNo, int $ttl = ELPI_CACHE_TTL): array
{
    $projectNo = trim($projectNo);
    if ($projectNo === '') {
        return [];
    }

    $filters = elpis_job_scoped_filters([$projectNo]);
    if ($filters === []) {
        return [];
    }

    $rows = elpis_try_fetch_rows($company, ELPI_PLANNING_LINE_ENTITY, [
        '$filter' => $filters[0],
        '$select' => elpis_werkorders_line_select_fields(),
        '$orderby' => 'Job_Task_No asc,Line_No asc',
        '$top' => '500',
    ], $ttl);

    $lines = [];
    foreach ($rows as $row) {
        elpis_collect_planning_line_row($row, $lines);
    }
    if ($lines === []) {
        return [];
    }

    $byProject = elpis_enrich_lines_with_inkoop_planning($company, [$projectNo => $lines], $ttl);

    return $byProject[$projectNo] ?? [];
}

function elpis_normalize_project_nos(array $projectNos): array
{
    return array_values(array_unique(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $projectNos))));
}

function elpis_planning_lines_chunk_count(array $projectNos): int
{
    $projectNos = elpis_normalize_project_nos($projectNos);

    if ($projectNos === []) {
        return 0;
    }

    return (int) ceil(count($projectNos) / ELPI_PLANNING_LINES_CHUNK_SIZE);
}

function elpis_fetch_planning_lines_chunk(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL): array
{
    $projectNos = elpis_normalize_project_nos($projectNos);
    $byProject = [];

    foreach ($projectNos as $projectNo) {
        $byProject[$projectNo] = [];
    }

    if ($projectNos === []) {
        return $byProject;
    }

    foreach (elpis_job_scoped_filters($projectNos) as $filter) {
        $rows = elpis_try_fetch_rows($company, ELPI_PLANNING_LINE_ENTITY, [
            '$filter' => $filter,
            '$select' => elpis_werkorders_line_select_fields(),
            '$orderby' => 'Job_No asc,Job_Task_No asc,Line_No asc',
        ], $ttl);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jobNo = trim((string) ($row['Job_No'] ?? ''));
            if ($jobNo === '' || !isset($byProject[$jobNo])) {
                continue;
            }
            elpis_collect_planning_line_row($row, $byProject[$jobNo]);
        }
    }

    $hasLines = false;
    foreach ($byProject as $lines) {
        if ($lines !== []) {
            $hasLines = true;
            break;
        }
    }
    if (!$hasLines) {
        return $byProject;
    }

    return elpis_enrich_lines_with_inkoop_planning($company, $byProject, $ttl);
}

function elpis_fetch_planning_lines_for_company(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    $projects = elpis_fetch_projects_for_company($company, $ttl);

    return elpis_fetch_planning_lines_by_projects($company, elpis_project_nos_from_projects($projects), $ttl);
}

function elpis_fetch_planning_lines_by_projects(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL): array
{
    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos($projectNos));
    $byProject = [];
    foreach ($projectNos as $projectNo) {
        $byProject[$projectNo] = [];
    }
    if ($projectNos === []) {
        return $byProject;
    }

    $startedAt = microtime(true);
    foreach (array_chunk($projectNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $chunkLines = elpis_fetch_planning_lines_chunk($company, $chunk, $ttl);
        foreach ($chunk as $projectNo) {
            $byProject[$projectNo] = $chunkLines[$projectNo] ?? [];
        }
    }
    elpis_log_timing(
        $company,
        ELPI_PLANNING_LINE_ENTITY,
        ['$filter' => 'projects=' . count($projectNos)],
        count($byProject),
        $startedAt,
        'scoped'
    );

    return $byProject;
}

function elpis_enrich_lines_with_inkoop_planning(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL): array
{
    if ($linesByProject === []) {
        return $linesByProject;
    }

    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos(array_map(static function ($projectNo): string {
        return (string) $projectNo;
    }, array_keys($linesByProject))));
    if ($projectNos === []) {
        return $linesByProject;
    }

    $rows = [];
    foreach (elpis_job_scoped_filters($projectNos) as $filter) {
        foreach (elpis_try_fetch_rows($company, ELPI_INKOOP_PLANNING_ENTITY, [
            '$filter' => $filter,
            '$select' => elpis_inkoop_planning_select_fields(),
            '$orderby' => 'Job_No asc,Job_Task_No asc,Line_No asc',
        ], $ttl) as $row) {
            if (is_array($row) && elpis_row_is_item_type($row)) {
                $rows[] = $row;
            }
        }
    }

    return elpis_attach_inkoop_planning_rows($linesByProject, $rows);
}

function elpis_inkoop_supply_key(string $jobNo, string $itemNo, string $workOrderNo): string
{
    return strtoupper(trim($jobNo)) . '|' . strtoupper(trim($itemNo)) . '|' . strtoupper(trim($workOrderNo));
}

/**
 * Job|taak|regelnr koppelt een werkorderregel aan een inkoopplanningsregel
 * die het inkoopdocument nog niet op de projectregel heeft.
 * Staat LVS_Purchase_Order_No + LVS_Purchase_Order_Line_No al op de
 * projectregel, dan blijft dat paar staan. Job_Task_No van PurchaseLines
 * is bij deze regels het werkordernummer, terwijl de projectregel zelf
 * geen werkorder toont; zoeken op lege taak vindt die inkoopregel niet.
 *
 * @param array<string, list<array<string, mixed>>> $linesByProject
 * @param list<array<string, mixed>> $inkoopRows
 * @return array<string, list<array<string, mixed>>>
 */
function elpis_attach_inkoop_planning_rows(array $linesByProject, array $inkoopRows): array
{
    $byPlanningKey = [];
    $bySupply = [];
    foreach ($inkoopRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $jobNo = (string) ($row['Job_No'] ?? '');
        $planningKey = elpis_planning_line_match_key(
            $jobNo,
            (string) ($row['Job_Task_No'] ?? ''),
            (int) ($row['Line_No'] ?? 0)
        );
        if ($planningKey !== '||0') {
            $byPlanningKey[$planningKey] = $row;
        }
        $itemNo = trim((string) ($row['No'] ?? ''));
        if (trim($jobNo) === '' || $itemNo === '') {
            continue;
        }
        $supplyKey = elpis_inkoop_supply_key($jobNo, $itemNo, (string) ($row['LVS_Work_Order_No'] ?? ''));
        $bySupply[$supplyKey][] = $row;
    }

    foreach ($linesByProject as $projectNo => $lines) {
        if (!is_array($lines)) {
            continue;
        }
        $consumedSupply = [];
        $mergedByPlanningKey = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $planningKey = elpis_planning_line_match_key(
                (string) $projectNo,
                (string) ($line['job_task_no'] ?? ''),
                (int) ($line['line_no'] ?? 0)
            );
            if (!isset($byPlanningKey[$planningKey])) {
                continue;
            }
            $row = $byPlanningKey[$planningKey];
            if (elpis_planning_line_has_purchase_link($line)) {
                if (elpis_trace_wants_item((string) ($line['item_no'] ?? ''))) {
                    elpis_trace('inkoop', [
                        'item_no' => (string) ($line['item_no'] ?? ''),
                        'line_no' => (int) ($line['line_no'] ?? 0),
                        'action' => 'paar-op-planningsregel-blijft',
                        'purchase_order_no' => (string) ($line['purchase_order_no'] ?? ''),
                        'purchase_order_line_no' => (int) ($line['purchase_order_line_no'] ?? 0),
                    ]);
                }
                continue;
            }
            $linesByProject[$projectNo][$index] = elpis_merge_inkoop_planning_line($line, $row);
            $mergedByPlanningKey[$index] = true;
            $itemNo = trim((string) ($row['No'] ?? $line['item_no'] ?? ''));
            if ($itemNo !== '') {
                $consumedSupply[elpis_inkoop_supply_key((string) ($row['Job_No'] ?? $projectNo), $itemNo, (string) ($row['LVS_Work_Order_No'] ?? ''))] = true;
            }
        }

        $pending = [];
        foreach ($linesByProject[$projectNo] as $index => $line) {
            if (!is_array($line) || isset($mergedByPlanningKey[$index])) {
                continue;
            }
            // Document én regelnummer op de projectregel zijn de koppeling naar
            // PurchaseLines. Een lege werkorder of een taak die gelijk is aan het
            // werkordernummer mag dat paar niet vervangen.
            if (elpis_planning_line_has_purchase_link($line)) {
                continue;
            }
            $itemNo = trim((string) ($line['item_no'] ?? ''));
            if ($itemNo === '') {
                continue;
            }
            $supplyKey = elpis_inkoop_supply_key((string) $projectNo, $itemNo, (string) ($line['work_order_no'] ?? ''));
            if (isset($consumedSupply[$supplyKey])) {
                continue;
            }
            $pending[$supplyKey][] = $index;
        }

        foreach ($pending as $supplyKey => $indexes) {
            $candidates = $bySupply[$supplyKey] ?? [];
            if (count($indexes) !== 1 || count($candidates) !== 1 || !is_array($candidates[0])) {
                continue;
            }
            $linesByProject[$projectNo][$indexes[0]] = elpis_merge_inkoop_planning_line(
                $linesByProject[$projectNo][$indexes[0]],
                $candidates[0]
            );
        }
    }

    return $linesByProject;
}

function elpis_planning_line_has_purchase_link(array $line): bool
{
    return trim((string) ($line['purchase_order_no'] ?? '')) !== ''
        && (int) ($line['purchase_order_line_no'] ?? 0) > 0;
}

function elpis_merge_inkoop_planning_line(array $line, array $inkoopRow): array
{
    $purchaseOrderNo = trim((string) ($inkoopRow['LVS_Purchase_Order_No'] ?? ''));
    $quantity = (float) ($line['quantity'] ?? 0);
    if ($quantity <= 0.00001) {
        $quantity = (float) ($inkoopRow['Quantity'] ?? 0);
    }
    $keepPlanningLink = elpis_planning_line_has_purchase_link($line);
    $quantities = elpis_line_quantities(
        $quantity,
        $purchaseOrderNo,
        (float) ($inkoopRow['LVS_Quantity_Order_UoM'] ?? 0),
        null
    );
    if ($purchaseOrderNo !== '' && !$keepPlanningLink) {
        $line = array_merge($line, $quantities);
        $line['purchase_order_no'] = $purchaseOrderNo;
        $line['quantity'] = $quantity;
    }

    $purchaseOrderLineNo = (int) ($inkoopRow['LVS_Purchase_Order_Line_No'] ?? 0);
    $noWorkOrder = trim((string) ($line['work_order_no'] ?? '')) === ''
        && trim((string) ($inkoopRow['LVS_Work_Order_No'] ?? '')) === '';
    if (!$keepPlanningLink && $purchaseOrderLineNo > 0 && ($noWorkOrder || (int) ($line['purchase_order_line_no'] ?? 0) <= 0)) {
        $line['purchase_order_line_no'] = $purchaseOrderLineNo;
    }

    $workOrderNo = trim((string) ($inkoopRow['LVS_Work_Order_No'] ?? ''));
    if (!$keepPlanningLink && $workOrderNo !== '' && trim((string) ($line['work_order_no'] ?? '')) === '') {
        $line['work_order_no'] = $workOrderNo;
    }

    if (trim((string) ($line['planning_date'] ?? '')) === '') {
        $planning = elpis_planning_date_from_row($inkoopRow);
        $line['planning_date'] = $planning['date'];
        $line['planning_date_field'] = $planning['field'];
    }

    $kvtDate = elpis_normalize_bc_date((string) ($inkoopRow['KVT_Expected_Receipt_Date'] ?? ''));
    if ($kvtDate !== '' && elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? '')) === '') {
        $line['expected_receipt_date'] = substr($kvtDate, 0, 10);
    }

    if ((int) ($line['job_contract_entry_no'] ?? 0) <= 0) {
        $line['job_contract_entry_no'] = (int) ($inkoopRow['Job_Contract_Entry_No'] ?? 0);
    }

    $line['completely_received'] = !empty($line['completely_received']) || elpis_bc_bool($inkoopRow['LVS_Completely_Received'] ?? false);
    if (array_key_exists('LVS_Outstanding_Qty_Base', $inkoopRow) && $inkoopRow['LVS_Outstanding_Qty_Base'] !== null && $inkoopRow['LVS_Outstanding_Qty_Base'] !== '') {
        $line['inkoop_outstanding_qty'] = (float) $inkoopRow['LVS_Outstanding_Qty_Base'];
    }

    return $line;
}

function elpis_bc_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'ja'], true);
}

function elpis_reservation_select_fields(): string
{
    return 'Entry_No,Positive,Item_No,Quantity_Base,Reservation_Status,Source_Type,Source_Subtype,Source_ID,Source_Ref_No,Expected_Receipt_Date';
}

function elpis_reservation_status_filter(): string
{
    return "Reservation_Status eq '" . ELPI_RESERVATION_STATUS_OPTION . "'";
}

function elpis_reservation_status_matches(string $status): bool
{
    $status = trim($status);

    return elpis_option_matches($status, [ELPI_RESERVATION_STATUS_OPTION, 'Reservering', 'Reservierung']);
}

function elpis_purchase_line_select_fields(): string
{
    // LVS_Completely_Received bestaat niet op PurchaseLines en laat de query
    // met HTTP 400 vallen. Ontvangen is Quantity min Outstanding_Quantity.
    return 'Document_Type,Document_No,Line_No,Type,No,Job_No,Job_Task_No,Job_Line_Type,Quantity,Outstanding_Quantity,Expected_Receipt_Date,Unit_of_Measure_Code';
}

function elpis_log_purchase_lines_failure(string $company, array $query, string $message): void
{
    if (!isset($GLOBALS['ELPIS_PURCHASE_LINE_ERRORS']) || !is_array($GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'])) {
        $GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] = [];
    }
    $entry = [
        'company' => $company,
        'filter' => (string) ($query['$filter'] ?? ''),
        'select' => (string) ($query['$select'] ?? ''),
        'message' => $message,
    ];
    $GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'][] = $entry;
    error_log(sprintf(
        'elpis.purchase_lines FAILED company="%s" select="%s" filter="%s" message="%s"',
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $company),
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $entry['select']),
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $entry['filter']),
        str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $message)
    ));
}

function elpis_purchase_line_open_filter(): string
{
    return "Document_Type eq 'Order'";
}

function elpis_reservation_demand_filter(array $projectNos): string
{
    return elpis_and_filters([
        elpis_reservation_status_filter(),
        'Source_Type eq ' . ELPI_JOB_PLANNING_SOURCE_TYPE,
        "Source_Subtype eq '" . elpis_escape_odata_string(ELPI_JOB_PLANNING_SUBTYPE) . "'",
        'Positive eq false',
        elpis_odata_eq_list('Source_ID', $projectNos),
    ]);
}

function elpis_reservation_supply_filter(array $entryNos): string
{
    return elpis_and_filters([
        elpis_reservation_status_filter(),
        'Positive eq true',
        elpis_odata_eq_list('Source_Type', [ELPI_ITEM_LEDGER_SOURCE_TYPE, ELPI_PURCHASE_LINE_SOURCE_TYPE], true),
        elpis_odata_eq_list('Entry_No', $entryNos, true),
    ]);
}

/**
 * Source_Types die vrije voorraad verlagen (Ariadne).
 * 1003 projectplanningsregel, ook werkorders; Source_ID is het projectnummer.
 * 901 assemblage (ASS…). 37 verkooporderregel.
 * 32 (artikelpost) telt niet mee. Eigen project alleen bij 1003 uitsluiten.
 * Een ongefilterde ReservationEntries-query duurt ~40s; altijd op Item_No
 * begrenzen. Nightly schrijft die gefilterde antwoorden in de OData-cache.
 *
 * @return list<int>
 */
function elpis_stock_reservation_source_types(): array
{
    $types = [
        ELPI_SALES_LINE_SOURCE_TYPE,
        ELPI_ASSEMBLY_SOURCE_TYPE,
        ELPI_JOB_PLANNING_SOURCE_TYPE,
    ];
    $extra = $GLOBALS['ELPIS_STOCK_RESERVATION_SOURCE_TYPES'] ?? [];
    if (is_array($extra)) {
        foreach ($extra as $type) {
            $type = (int) $type;
            if ($type > 0) {
                $types[] = $type;
            }
        }
    }

    $types = array_values(array_unique(array_filter($types, static function (int $type): bool {
        return $type !== ELPI_ITEM_LEDGER_SOURCE_TYPE;
    })));
    sort($types, SORT_NUMERIC);

    return $types;
}

function elpis_item_stock_reservation_filter(array $itemNos): string
{
    return elpis_and_filters([
        elpis_reservation_status_filter(),
        'Positive eq false',
        elpis_odata_eq_list('Source_Type', elpis_stock_reservation_source_types(), true),
        elpis_odata_eq_list('Item_No', $itemNos),
    ]);
}

function elpis_purchase_documents_filter(array $documentNos): string
{
    return elpis_and_filters([
        elpis_purchase_line_open_filter(),
        elpis_odata_eq_list('Document_No', $documentNos),
    ]);
}

function elpis_purchase_jobs_filter(array $jobNos): string
{
    // Geen Job_Task_No en geen Job_Line_Type: een lege taak of alleen
    // werkorderregels slaat PO-regels over waarvan de taak het WO-nummer is.
    return elpis_and_filters([
        elpis_purchase_line_open_filter(),
        elpis_odata_eq_list('Job_No', $jobNos),
    ]);
}

function elpis_item_ledger_filter(array $projectNos): string
{
    return elpis_and_filters([
        "Entry_Type eq 'Purchase'",
        "Document_Type eq 'Purchase Receipt'",
        elpis_odata_eq_list('Job_No', $projectNos),
    ]);
}

function elpis_item_ledger_select_fields(): string
{
    return 'Item_No,Job_No,Job_Task_No,Posting_Date,Entry_Type,Document_Type,Quantity';
}

function elpis_item_inventory_select_fields(): string
{
    // AppItems.InventoryField is de artikelvoorraad. Geen Location_Code:
    // de aftrek van reserveringen geldt voor dezelfde artikelbrede voorraad.
    // Type ('Service') en LVS_Maintenance_Task_Item (taakartikel): fictieve artikelen, Elpis filtert die regels weg.
    return 'No,InventoryField,LVS_InventoryExclJobInventory,Type,LVS_Maintenance_Task_Item,Lead_Time_Calculation';
}

function elpis_receipt_filters_are_department_independent(string $filter): bool
{
    return preg_match('/afdeling|global_dimension|responsibility_center|department/i', $filter) !== 1;
}

function elpis_reservation_qty(array $entry): float
{
    return abs((float) ($entry['quantity_base'] ?? 0));
}

function elpis_normalize_reservation_entry(array $row): array
{
    return [
        'entry_no' => (int) ($row['Entry_No'] ?? 0),
        'positive' => array_key_exists('Positive', $row) ? elpis_bc_bool($row['Positive']) : null,
        'item_no' => trim((string) ($row['Item_No'] ?? '')),
        'quantity_base' => (float) ($row['Quantity_Base'] ?? 0),
        'quantity' => (float) ($row['Quantity'] ?? 0),
        'status' => trim((string) ($row['Reservation_Status'] ?? '')),
        'source_type' => (int) ($row['Source_Type'] ?? 0),
        'source_subtype' => trim((string) ($row['Source_Subtype'] ?? '')),
        'source_id' => trim((string) ($row['Source_ID'] ?? '')),
        'source_ref_no' => (int) ($row['Source_Ref_No'] ?? 0),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['Expected_Receipt_Date'] ?? '')),
    ];
}

function elpis_normalize_purchase_order_line(array $row): array
{
    return [
        'document_no' => trim((string) ($row['Document_No'] ?? $row['document_no'] ?? '')),
        'line_no' => (int) ($row['Line_No'] ?? $row['line_no'] ?? 0),
        'item_no' => trim((string) ($row['No'] ?? $row['item_no'] ?? '')),
        'type' => trim((string) ($row['Type'] ?? $row['type'] ?? '')),
        'job_no' => trim((string) ($row['Job_No'] ?? $row['job_no'] ?? '')),
        'job_task_no' => trim((string) ($row['Job_Task_No'] ?? $row['job_task_no'] ?? '')),
        'job_line_type' => trim((string) ($row['Job_Line_Type'] ?? $row['job_line_type'] ?? '')),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['Expected_Receipt_Date'] ?? $row['expected_receipt_date'] ?? '')),
        'quantity' => array_key_exists('Quantity', $row) ? (float) $row['Quantity'] : (array_key_exists('quantity', $row) ? (float) $row['quantity'] : null),
        'outstanding_qty' => array_key_exists('Outstanding_Quantity', $row) ? (float) $row['Outstanding_Quantity'] : (array_key_exists('outstanding_qty', $row) ? (float) $row['outstanding_qty'] : null),
        'uom_code' => trim((string) ($row['Unit_of_Measure_Code'] ?? $row['uom_code'] ?? '')),
        'uom_factor' => isset($row['uom_factor']) ? (float) $row['uom_factor'] : null,
    ];
}

/**
 * PurchaseLines.Quantity/Outstanding_Quantity staan in de inkoopeenheid; reken
 * om naar de basiseenheid met AppItemUnitOfMeasure.Qty_per_Unit_of_Measure
 * (Item_No = No, Code = Unit_of_Measure_Code). Lege code of geen factor: 1.
 */
function elpis_apply_purchase_uom(string $company, array $lines, int $ttl = ELPI_CACHE_TTL): array
{
    $itemNos = [];
    foreach ($lines as $line) {
        if (($line['uom_factor'] ?? null) === null && ($line['uom_code'] ?? '') !== '' && ($line['item_no'] ?? '') !== '') {
            $itemNos[$line['item_no']] = true;
        }
    }
    $factors = [];
    foreach (array_chunk(elpis_sort_odata_values(array_keys($itemNos)), ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        foreach (elpis_try_fetch_rows($company, 'AppItemUnitOfMeasure', [
            '$select' => 'Item_No,Code,Qty_per_Unit_of_Measure',
            '$filter' => elpis_odata_eq_list('Item_No', $chunk),
        ], $ttl) as $row) {
            $factor = (float) ($row['Qty_per_Unit_of_Measure'] ?? 0);
            if (is_array($row) && $factor > 0.00001) {
                $factors[strtoupper(trim((string) ($row['Item_No'] ?? ''))) . '|' . strtoupper(trim((string) ($row['Code'] ?? '')))] = $factor;
            }
        }
    }
    foreach ($lines as $i => $line) {
        if (($line['uom_factor'] ?? null) !== null) {
            continue;
        }
        $factor = $factors[strtoupper((string) ($line['item_no'] ?? '')) . '|' . strtoupper((string) ($line['uom_code'] ?? ''))] ?? 1.0;
        foreach (['quantity', 'outstanding_qty'] as $field) {
            if (isset($line[$field])) {
                $line[$field] = (float) $line[$field] * $factor;
            }
        }
        $line['uom_factor'] = $factor;
        $lines[$i] = $line;
    }

    return $lines;
}

function elpis_purchase_line_best_date(array $line): string
{
    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));

    return $date === '' ? '' : substr($date, 0, 10);
}

function elpis_latest_date(array $dates): string
{
    $latest = '';
    foreach ($dates as $date) {
        $date = elpis_normalize_bc_date((string) $date);
        if ($date === '') {
            continue;
        }
        $date = substr($date, 0, 10);
        if ($latest === '' || $date > $latest) {
            $latest = $date;
        }
    }

    return $latest;
}

function elpis_line_qty_still_needed(array $line): float
{
    if (!elpis_line_still_expecting_receipt($line)) {
        return 0.0;
    }

    $remaining = elpis_line_remaining_qty($line);
    if ($remaining !== null) {
        return max(0.0, $remaining);
    }

    $quantity = (float) ($line['quantity'] ?? 0);
    if ($quantity > 0.00001) {
        return max(0.0, $quantity - (float) ($line['qty_received'] ?? 0));
    }

    $open = (float) ($line['qty_open'] ?? 0);
    if ($open > 0.00001) {
        return $open;
    }

    $toOrder = (float) ($line['qty_to_order'] ?? 0);
    if ($toOrder > 0.00001) {
        return $toOrder;
    }

    $ordered = (float) ($line['qty_ordered'] ?? 0);
    $received = (float) ($line['qty_received'] ?? 0);
    if ($ordered - $received > 0.00001) {
        return $ordered - $received;
    }

    return 0.0;
}

function elpis_reservation_is_active(array $entry): bool
{
    return elpis_reservation_status_matches((string) ($entry['status'] ?? ''));
}

function elpis_reservation_is_demand(array $entry): bool
{
    if (!elpis_reservation_is_active($entry)) {
        return false;
    }

    if ((int) ($entry['source_type'] ?? 0) !== ELPI_JOB_PLANNING_SOURCE_TYPE) {
        return false;
    }

    if (trim((string) ($entry['source_subtype'] ?? '')) !== ELPI_JOB_PLANNING_SUBTYPE) {
        return false;
    }

    return ($entry['positive'] ?? null) === false;
}

function elpis_reservation_is_stock_supply(array $entry): bool
{
    if (!elpis_reservation_is_active($entry) || ($entry['positive'] ?? null) !== true) {
        return false;
    }

    return (int) ($entry['source_type'] ?? 0) === ELPI_ITEM_LEDGER_SOURCE_TYPE;
}

function elpis_reservation_is_purchase_supply(array $entry): bool
{
    if (!elpis_reservation_is_active($entry) || ($entry['positive'] ?? null) !== true) {
        return false;
    }

    return (int) ($entry['source_type'] ?? 0) === ELPI_PURCHASE_LINE_SOURCE_TYPE;
}

function elpis_reservation_is_stock_demand(array $entry): bool
{
    if (!elpis_reservation_is_active($entry) || ($entry['positive'] ?? null) !== false) {
        return false;
    }

    return in_array((int) ($entry['source_type'] ?? 0), elpis_stock_reservation_source_types(), true);
}

function elpis_fetch_reservation_entries(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    elpis_log_timing(
        $company,
        ELPI_RESERVATION_ENTRY_ENTITY,
        ['$filter' => elpis_reservation_status_filter()],
        0,
        microtime(true),
        'skipped-unscoped'
    );

    return [];
}

function elpis_fetch_reservation_supplies(string $company, array $entryNos, int $ttl): array
{
    $entryNos = elpis_sort_odata_values($entryNos, true);
    if ($entryNos === []) {
        return [];
    }

    $supplies = [];
    $seen = [];
    foreach (array_chunk($entryNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_RESERVATION_ENTRY_ENTITY, [
            '$select' => elpis_reservation_select_fields(),
            '$filter' => elpis_reservation_supply_filter($chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = elpis_normalize_reservation_entry($row);
            if ($normalized['entry_no'] <= 0 || !elpis_reservation_is_active($normalized) || ($normalized['positive'] ?? null) !== true) {
                continue;
            }
            $key = $normalized['entry_no'] . '|' . $normalized['source_type'] . '|' . strtoupper($normalized['source_id']) . '|' . $normalized['source_ref_no'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $supplies[] = $normalized;
        }
    }

    return $supplies;
}

function elpis_fetch_reservation_entries_for_projects(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos($projectNos));
    if ($company === '' || $projectNos === []) {
        elpis_log_timing($company, ELPI_RESERVATION_ENTRY_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $startedAt = microtime(true);
    $demands = [];
    foreach (array_chunk($projectNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_RESERVATION_ENTRY_ENTITY, [
            '$select' => elpis_reservation_select_fields(),
            '$filter' => elpis_reservation_demand_filter($chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = elpis_normalize_reservation_entry($row);
            if ($normalized['entry_no'] > 0 && elpis_reservation_is_demand($normalized)) {
                $demands[] = $normalized;
            }
        }
    }

    $entryNos = [];
    foreach ($demands as $demand) {
        $entryNo = (int) ($demand['entry_no'] ?? 0);
        if ($entryNo > 0) {
            $entryNos[$entryNo] = $entryNo;
        }
    }

    $entries = elpis_merge_reservations($demands, elpis_fetch_reservation_supplies($company, array_values($entryNos), $ttl));
    elpis_log_timing(
        $company,
        ELPI_RESERVATION_ENTRY_ENTITY,
        ['$filter' => 'projects=' . count($projectNos)],
        count($entries),
        $startedAt,
        'scoped'
    );

    return $entries;
}

function elpis_fetch_open_purchase_order_lines(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    elpis_log_timing(
        $company,
        ELPI_PURCHASE_ORDER_LINE_ENTITY,
        ['$filter' => elpis_purchase_line_open_filter()],
        0,
        microtime(true),
        'skipped-unscoped'
    );

    return [];
}

function elpis_fetch_open_purchase_order_lines_for_documents(string $company, array $documentNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $documentNos = elpis_sort_odata_values(elpis_normalize_project_nos($documentNos));
    if ($company === '' || $documentNos === []) {
        elpis_log_timing($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $lines = [];
    foreach (array_chunk($documentNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $lines = elpis_merge_purchase_order_lines($lines, elpis_try_fetch_rows($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [
            '$select' => elpis_purchase_line_select_fields(),
            '$filter' => elpis_purchase_documents_filter($chunk),
        ], $ttl));
    }

    return elpis_apply_purchase_uom($company, $lines, $ttl);
}

function elpis_fetch_open_purchase_order_lines_for_jobs(string $company, array $jobNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $jobNos = elpis_sort_odata_values(elpis_normalize_project_nos($jobNos));
    if ($company === '' || $jobNos === []) {
        elpis_log_timing($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [], 0, microtime(true), 'skipped-job');

        return [];
    }

    $lines = [];
    foreach (array_chunk($jobNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $lines = elpis_merge_purchase_order_lines($lines, elpis_try_fetch_rows($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [
            '$select' => elpis_purchase_line_select_fields(),
            '$filter' => elpis_purchase_jobs_filter($chunk),
        ], $ttl));
    }

    return elpis_apply_purchase_uom($company, $lines, $ttl);
}

function elpis_merge_purchase_order_lines(array ...$sets): array
{
    $lines = [];
    $seen = [];
    foreach ($sets as $set) {
        foreach ($set as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = isset($row['document_no']) && !isset($row['Document_No'])
                ? $row
                : elpis_normalize_purchase_order_line($row);
            $documentNo = trim((string) ($normalized['document_no'] ?? ''));
            if ($documentNo === '') {
                continue;
            }
            $key = strtoupper($documentNo) . '|' . (int) ($normalized['line_no'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $lines[] = $normalized;
        }
    }

    return $lines;
}

function elpis_item_inventory_chunk_count(array $itemNos): int
{
    $unique = [];
    foreach ($itemNos as $itemNo) {
        $itemNo = trim((string) $itemNo);
        if ($itemNo !== '') {
            $unique[$itemNo] = true;
        }
    }

    if ($unique === []) {
        return 0;
    }

    return (int) ceil(count($unique) / ELPI_PLANNING_LINES_CHUNK_SIZE);
}

function elpis_fetch_item_inventory(string $company, array $itemNos, int $ttl = ELPI_CACHE_TTL, ?callable $onChunk = null, ?array &$skipItems = null): array
{
    $skipItems = [];
    $unique = [];
    foreach ($itemNos as $itemNo) {
        $itemNo = trim((string) $itemNo);
        if ($itemNo !== '') {
            $unique[$itemNo] = true;
        }
    }
    $itemNos = elpis_sort_odata_values(array_keys($unique));
    if ($itemNos === []) {
        elpis_log_timing($company, ELPI_ITEM_LIST_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $map = [];
    $chunks = array_chunk($itemNos, ELPI_PLANNING_LINES_CHUNK_SIZE);
    $totalChunks = count($chunks);
    $doneChunks = 0;
    foreach ($chunks as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_ITEM_LIST_ENTITY, [
            '$select' => elpis_item_inventory_select_fields(),
            '$filter' => elpis_odata_eq_list('No', $chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $no = trim((string) ($row['No'] ?? ''));
            if ($no === '') {
                continue;
            }
            $GLOBALS['ELPIS_ITEM_LEAD_TIME'][strtoupper($no)] = trim((string) ($row['Lead_Time_Calculation'] ?? ''));
            if (($row['Type'] ?? '') === 'Service' || ($row['LVS_Maintenance_Task_Item'] ?? false) === true) {
                $skipItems[strtoupper($no)] = true;
            }
            // Vrije voorraad = LVS_InventoryExclJobInventory (InventoryField = alle locaties
            // incl. projectvoorraad LVS_Job_Inventory, die niet vrij is). Fallback: InventoryField.
            $field = array_key_exists('LVS_InventoryExclJobInventory', $row) && $row['LVS_InventoryExclJobInventory'] !== null && $row['LVS_InventoryExclJobInventory'] !== ''
                ? 'LVS_InventoryExclJobInventory' : 'InventoryField';
            if (!array_key_exists($field, $row) || $row[$field] === '' || $row[$field] === null) {
                continue;
            }
            $map[$no] = (float) $row[$field];
        }
        $doneChunks++;
        if ($onChunk !== null) {
            $onChunk($doneChunks, $totalChunks);
        }
    }

    return $map;
}

function elpis_reservation_row_key(array $entry): string
{
    return (int) ($entry['entry_no'] ?? 0)
        . '|' . (($entry['positive'] ?? null) === true ? '1' : '0')
        . '|' . (int) ($entry['source_type'] ?? 0)
        . '|' . strtoupper(trim((string) ($entry['source_id'] ?? '')))
        . '|' . (int) ($entry['source_ref_no'] ?? 0)
        . '|' . strtoupper(trim((string) ($entry['item_no'] ?? '')));
}

function elpis_merge_reservations(array ...$groups): array
{
    $merged = [];
    $seen = [];
    foreach ($groups as $group) {
        foreach ($group as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = elpis_reservation_row_key($entry);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $entry;
        }
    }

    return $merged;
}

function elpis_fetch_item_stock_reservations(string $company, array $itemNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $itemNos = elpis_sort_odata_values(elpis_normalize_project_nos($itemNos));
    if ($company === '' || $itemNos === []) {
        elpis_log_timing($company, ELPI_RESERVATION_ENTRY_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $entries = [];
    foreach (array_chunk($itemNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_RESERVATION_ENTRY_ENTITY, [
            '$select' => elpis_reservation_select_fields(),
            '$filter' => elpis_item_stock_reservation_filter($chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = elpis_normalize_reservation_entry($row);
            if ($normalized['entry_no'] > 0 && elpis_reservation_is_stock_demand($normalized)) {
                $entries[] = $normalized;
            }
        }
    }

    return $entries;
}

function elpis_line_needs_receipt_lookup(array $line): bool
{
    if (!elpis_line_still_expecting_receipt($line)) {
        return false;
    }

    return elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? '')) === '';
}

function elpis_collect_stock_item_nos(array $linesByProject): array
{
    $items = [];
    foreach ($linesByProject as $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $itemNo = trim((string) ($line['item_no'] ?? ''));
            if ($itemNo !== '') {
                $items[$itemNo] = true;
            }
        }
    }

    return elpis_sort_odata_values(array_keys($items));
}

function elpis_collect_work_order_nos(array $linesByProject): array
{
    $numbers = [];
    foreach ($linesByProject as $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $number = trim((string) ($line['work_order_no'] ?? ''));
            if ($number !== '') {
                $numbers[$number] = true;
            }
        }
    }

    return elpis_sort_odata_values(array_keys($numbers));
}

function elpis_projects_needing_reservations(array $linesByProject): array
{
    $projectNos = [];
    foreach ($linesByProject as $projectNo => $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            if (!is_array($line) || !elpis_line_needs_receipt_lookup($line)) {
                continue;
            }
            $projectNos[] = (string) $projectNo;
            break;
        }
    }

    return elpis_sort_odata_values(elpis_normalize_project_nos($projectNos));
}

function elpis_collect_purchase_document_nos(array $linesByProject, array $reservations = []): array
{
    $documents = [];
    foreach ($linesByProject as $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $documentNo = trim((string) ($line['purchase_order_no'] ?? ''));
            if ($documentNo !== '') {
                $documents[$documentNo] = true;
            }
        }
    }
    // Reserveringen op een inkoopregel: Source_ID is het inkoopordernummer.
    foreach ($reservations as $entry) {
        if (!is_array($entry) || !elpis_reservation_is_purchase_supply($entry)) {
            continue;
        }
        $documentNo = trim((string) ($entry['source_id'] ?? ''));
        if ($documentNo !== '') {
            $documents[$documentNo] = true;
        }
    }

    return elpis_sort_odata_values(array_keys($documents));
}

function elpis_collect_purchase_job_nos(array $linesByProject): array
{
    $jobs = [];
    foreach ($linesByProject as $projectNo => $lines) {
        if (!is_array($lines)) {
            continue;
        }
        $projectNo = trim((string) $projectNo);
        if ($projectNo !== '') {
            $jobs[$projectNo] = true;
        }
    }

    return elpis_sort_odata_values(array_keys($jobs));
}

function elpis_fetch_item_ledger_receipts(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos($projectNos));
    if ($company === '' || $projectNos === []) {
        elpis_log_timing($company, ELPI_ITEM_LEDGER_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $rowsOut = [];
    foreach (array_chunk($projectNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_ITEM_LEDGER_ENTITY, [
            '$select' => elpis_item_ledger_select_fields(),
            '$filter' => elpis_item_ledger_filter($chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (is_array($row)) {
                $rowsOut[] = $row;
            }
        }
    }

    return $rowsOut;
}

/**
 * Artikelposten waar een voorraadreservering (Source_Type 32, Source_Ref_No = Entry_No) op staat:
 * Document_No is de ontvangst (PWR…), Posting_Date de boekdatum.
 *
 * @return array<int, array{document_no: string, posting_date: string}>
 */
function elpis_fetch_stock_supply_ledger_entries(string $company, array $reservations, int $ttl = ELPI_CACHE_TTL): array
{
    $entryNos = [];
    foreach ($reservations as $entry) {
        if (is_array($entry) && elpis_reservation_is_stock_supply($entry) && (int) ($entry['source_ref_no'] ?? 0) > 0) {
            $entryNos[] = (string) (int) $entry['source_ref_no'];
        }
    }
    $entryNos = elpis_sort_odata_values(array_values(array_unique($entryNos)), true);
    $out = [];
    foreach (array_chunk($entryNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_ITEM_LEDGER_ENTITY, [
            '$select' => 'Entry_No,Document_No,Posting_Date',
            '$filter' => elpis_odata_eq_list('Entry_No', $chunk, true),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row) || (int) ($row['Entry_No'] ?? 0) <= 0) {
                continue;
            }
            $out[(int) $row['Entry_No']] = [
                'document_no' => trim((string) ($row['Document_No'] ?? '')),
                'posting_date' => substr(elpis_normalize_bc_date((string) ($row['Posting_Date'] ?? '')), 0, 10),
            ];
        }
    }

    return $out;
}

function elpis_work_order_select_fields(): string
{
    // AppWerkorders.Status (bevestigd door Ariadne), Nederlandse waarden.
    return 'No,Task_Description,Status';
}

/** Werkorderstatussen waarvan de regels niet meer in de PM-mail horen. */
function elpis_closed_work_order_statuses(): array
{
    return [
        // Nederlands
        'AFGESLOTEN', 'GECONTROLEERD', 'GEANNULEERD', 'GEFACTUREERD', 'UITGEVOERD',
        // Engels (andere bedrijven)
        'COMPLETED', 'CHECKED', 'CLOSED', 'CANCELLED', 'CANCELED', 'INVOICED', 'EXECUTED', 'PERFORMED', 'FINISHED',
        // Duits
        'ABGESCHLOSSEN', 'GEPRUEFT', 'GEPRÜFT', 'STORNIERT', 'FAKTURIERT', 'AUSGEFUEHRT', 'AUSGEFÜHRT',
    ];
}

function elpis_line_work_order_closed(array $line): bool
{
    $status = (string) ($line['work_order_status'] ?? '');

    return elpis_option_matches($status, elpis_closed_work_order_statuses());
}

function elpis_fetch_work_orders(string $company, array $workOrderNos, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    $workOrderNos = elpis_sort_odata_values(elpis_normalize_project_nos($workOrderNos));
    if ($company === '' || $workOrderNos === []) {
        elpis_log_timing($company, ELPI_WORK_ORDER_ENTITY, [], 0, microtime(true), 'skipped');

        return [];
    }

    $map = [];
    foreach (array_chunk($workOrderNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_WORK_ORDER_ENTITY, [
            '$select' => elpis_work_order_select_fields(),
            '$filter' => elpis_odata_eq_list('No', $chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $no = trim((string) ($row['No'] ?? ''));
            if ($no === '') {
                continue;
            }
            $map[strtoupper($no)] = [
                'no' => $no,
                'description' => trim((string) ($row['Task_Description'] ?? '')),
                'status' => trim((string) ($row['Status'] ?? '')),
            ];
        }
    }

    return $map;
}

function elpis_receipt_group_key(string $job, string $task, string $item): ?string
{
    $job = strtoupper(trim($job));
    $item = strtoupper(trim($item));
    if ($job === '' || $item === '') {
        return null;
    }

    return $job . '|' . strtoupper(trim($task)) . '|' . $item;
}

function elpis_line_receipt_group_key(string $projectNo, array $line): ?string
{
    return elpis_receipt_group_key(
        $projectNo,
        (string) ($line['job_task_no'] ?? ''),
        (string) ($line['item_no'] ?? '')
    );
}

function elpis_receipt_posting_index(array $rows): array
{
    $map = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = elpis_normalize_bc_date((string) ($row['Posting_Date'] ?? $row['posting_date'] ?? ''));
        $key = elpis_receipt_group_key(
            (string) ($row['Job_No'] ?? $row['job_no'] ?? ''),
            (string) ($row['Job_Task_No'] ?? $row['job_task_no'] ?? ''),
            (string) ($row['Item_No'] ?? $row['item_no'] ?? '')
        );
        if ($key === null || $date === '') {
            continue;
        }
        $date = substr($date, 0, 10);
        if (!isset($map[$key]) || $date > $map[$key]) {
            $map[$key] = $date;
        }
    }

    return $map;
}

function elpis_receipt_quantity_index(array $rows): array
{
    $map = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!array_key_exists('Quantity', $row) && !array_key_exists('quantity', $row)) {
            continue;
        }
        $key = elpis_receipt_group_key(
            (string) ($row['Job_No'] ?? $row['job_no'] ?? ''),
            (string) ($row['Job_Task_No'] ?? $row['job_task_no'] ?? ''),
            (string) ($row['Item_No'] ?? $row['item_no'] ?? '')
        );
        if ($key === null) {
            continue;
        }
        $map[$key] = ($map[$key] ?? 0.0) + (float) ($row['Quantity'] ?? $row['quantity'] ?? 0);
    }

    return $map;
}

function elpis_load_receipt_context(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL, ?callable $onInventoryChunk = null, ?callable $onPhase = null): array
{
    $empty = [
        'reservations' => [],
        'purchase_lines' => [],
        'inventory' => [],
        'receipts' => [],
        'work_orders' => [],
    ];
    $company = trim($company);
    if ($company === '') {
        return $empty;
    }

    $emit = static function (string $step, string $status, array $extra = []) use ($onPhase): void {
        if ($onPhase === null) {
            return;
        }
        $onPhase(array_merge(['step' => $step, 'status' => $status], $extra));
    };

    $startedAt = microtime(true);
    $itemNos = elpis_collect_stock_item_nos($linesByProject);
    $projectNos = elpis_sort_odata_values(elpis_normalize_project_nos(array_map('strval', array_keys($linesByProject))));

    $emit('reservations', 'start');
    $reservations = elpis_merge_reservations(
        elpis_fetch_item_stock_reservations($company, $itemNos, $ttl),
        elpis_fetch_reservation_entries_for_projects($company, $projectNos, $ttl)
    );
    $emit('reservations', 'done');

    $emit('purchase', 'start');
    $purchaseLines = elpis_merge_purchase_order_lines(
        elpis_fetch_open_purchase_order_lines_for_documents(
            $company,
            elpis_collect_purchase_document_nos($linesByProject, $reservations),
            $ttl
        ),
        elpis_fetch_open_purchase_order_lines_for_jobs(
            $company,
            elpis_collect_purchase_job_nos($linesByProject),
            $ttl
        )
    );
    $emit('purchase', 'done');

    $inventoryChunks = elpis_item_inventory_chunk_count($itemNos);
    $emit('inventory', 'start', ['done' => 0, 'total' => $inventoryChunks]);
    $inventory = elpis_fetch_item_inventory(
        $company,
        $itemNos,
        $ttl,
        static function (int $done, int $total) use ($emit, $onInventoryChunk): void {
            if ($onInventoryChunk !== null) {
                $onInventoryChunk($done, $total);
            }
            $emit('inventory', 'progress', ['done' => $done, 'total' => $total]);
        },
        $skipItems
    );
    $emit('inventory', 'done', ['done' => $inventoryChunks, 'total' => $inventoryChunks]);

    $emit('receipts', 'start');
    $receipts = elpis_fetch_item_ledger_receipts($company, $projectNos, $ttl);
    $emit('receipts', 'done');

    $stockLedger = elpis_fetch_stock_supply_ledger_entries($company, $reservations, $ttl);
    $emit('receipts', 'done');

    $workOrders = elpis_fetch_work_orders($company, elpis_collect_work_order_nos($linesByProject), $ttl);
    $emit('free_stock', 'start');
    $freeStartedAt = microtime(true);
    $freeStockIndex = elpis_fetch_free_stock_index($company, $itemNos, $ttl);
    elpis_log_timing($company, 'free-stock', ['$filter' => 'items=' . count($itemNos)], count($freeStockIndex), $freeStartedAt, 'scoped');
    $emit('free_stock', 'done');
    elpis_log_timing(
        $company,
        'receipt-context',
        ['$filter' => 'reservations=' . count($reservations) . ' purchase=' . count($purchaseLines) . ' items=' . count($inventory)],
        count($reservations) + count($purchaseLines) + count($inventory),
        $startedAt,
        'scoped'
    );

    return [
        'reservations' => $reservations,
        'purchase_lines' => $purchaseLines,
        'inventory' => $inventory,
        'skip_items' => $skipItems,
        'receipts' => $receipts,
        'stock_ledger' => $stockLedger,
        'work_orders' => $workOrders,
        'free_stock_index' => $freeStockIndex,
    ];
}

function elpis_index_purchase_lines(array $purchaseLines): array
{
    $byDocLine = [];
    $byDocItem = [];
    foreach ($purchaseLines as $line) {
        if (!is_array($line)) {
            continue;
        }
        if (!isset($line['document_no']) && isset($line['Document_No'])) {
            $line = elpis_normalize_purchase_order_line($line);
        }
        $documentNo = strtoupper(trim((string) ($line['document_no'] ?? '')));
        if ($documentNo === '') {
            continue;
        }
        $byDocLine[$documentNo . '|' . (int) ($line['line_no'] ?? 0)] = $line;
        $itemNo = strtoupper(trim((string) ($line['item_no'] ?? '')));
        if ($itemNo !== '') {
            $byDocItem[$documentNo . '|' . $itemNo][] = $line;
        }
    }

    return [$byDocLine, $byDocItem];
}

function elpis_dates_for_document_item(string $documentNo, string $itemNo, array $byDocItem): array
{
    $key = strtoupper(trim($documentNo)) . '|' . strtoupper(trim($itemNo));
    $dates = [];
    foreach ($byDocItem[$key] ?? [] as $line) {
        if (!is_array($line)) {
            continue;
        }
        $date = elpis_purchase_line_best_date($line);
        if ($date !== '') {
            $dates[] = $date;
        }
    }

    return $dates;
}

function elpis_date_for_purchase_supply(array $supply, array $byDocLine, array $byDocItem): string
{
    $fromEntry = elpis_normalize_bc_date((string) ($supply['expected_receipt_date'] ?? ''));
    if ($fromEntry !== '') {
        return substr($fromEntry, 0, 10);
    }

    $documentNo = strtoupper(trim((string) ($supply['source_id'] ?? '')));
    $lineNo = (int) ($supply['source_ref_no'] ?? 0);
    if ($documentNo !== '' && isset($byDocLine[$documentNo . '|' . $lineNo])) {
        $fromLine = elpis_purchase_line_best_date($byDocLine[$documentNo . '|' . $lineNo]);
        if ($fromLine !== '') {
            return $fromLine;
        }
    }

    $dates = elpis_dates_for_document_item($documentNo, (string) ($supply['item_no'] ?? ''), $byDocItem);

    return elpis_latest_date($dates);
}

function elpis_prepare_reservation_indexes(array $reservations): array
{
    $byEntry = [];
    $demandByJobContract = [];
    $stockReservedByItem = [];

    foreach ($reservations as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!isset($entry['entry_no']) && isset($entry['Entry_No'])) {
            $entry = elpis_normalize_reservation_entry($entry);
        }
        $entryNo = (int) ($entry['entry_no'] ?? 0);
        if ($entryNo <= 0 || !elpis_reservation_is_active($entry)) {
            continue;
        }
        $byEntry[$entryNo][] = $entry;
    }

    foreach ($byEntry as $entryNo => $entries) {
        $stockQty = 0.0;
        $itemKey = '';
        foreach ($entries as $entry) {
            if (elpis_reservation_is_demand($entry)) {
                $jobNo = strtoupper(trim((string) ($entry['source_id'] ?? '')));
                $contractEntryNo = (int) ($entry['source_ref_no'] ?? 0);
                if ($jobNo !== '' && $contractEntryNo > 0) {
                    $demandByJobContract[$jobNo . '|' . $contractEntryNo][] = $entry;
                }
                if ($itemKey === '') {
                    $itemKey = strtoupper(trim((string) ($entry['item_no'] ?? '')));
                }
                continue;
            }
            if (elpis_reservation_is_stock_supply($entry)) {
                $stockQty += elpis_reservation_qty($entry);
                if ($itemKey === '') {
                    $itemKey = strtoupper(trim((string) ($entry['item_no'] ?? '')));
                }
            }
        }
        if ($itemKey !== '' && $stockQty > 0.00001) {
            $stockReservedByItem[$itemKey] = ($stockReservedByItem[$itemKey] ?? 0.0) + $stockQty;
        }
    }

    return [$byEntry, $demandByJobContract, $stockReservedByItem];
}

/**
 * Reservering van de planningsregel (Source_ID = project, Source_Ref_No = Job_Contract_Entry_No)
 * naar de positieve tegenboeking met hetzelfde Entry_No. Inkoopregel: datum via
 * ReservationEntries -> PurchaseLines.Expected_Receipt_Date. Voorraad: regel is gedekt.
 */
function elpis_apply_line_reservations(
    array $line,
    string $projectKey,
    array $reservationByEntry,
    array $demandByJobContract,
    array $byDocLine,
    array $byDocItem
): array {
    $contractEntryNo = (int) ($line['job_contract_entry_no'] ?? 0);
    if ($contractEntryNo <= 0 || $reservationByEntry === []) {
        return $line;
    }

    $demands = $demandByJobContract[strtoupper(trim($projectKey)) . '|' . $contractEntryNo] ?? [];
    $purchaseDates = [];
    $reservedPurchaseQty = 0.0;
    $reservedTotalQty = 0.0;
    $seenEntries = [];
    foreach ($demands as $demand) {
        $entryNo = (int) ($demand['entry_no'] ?? 0);
        if (isset($seenEntries[$entryNo])) {
            continue;
        }
        $seenEntries[$entryNo] = true;
        foreach ($reservationByEntry[$entryNo] ?? [] as $supply) {
            if (!is_array($supply)) {
                continue;
            }
            if (elpis_reservation_is_stock_supply($supply)) {
                $line['reserved_from_stock'] = true;
                $reservedTotalQty += elpis_reservation_qty($supply);
                $receiptNo = trim((string) ($supply['receipt_document_no'] ?? ''));
                if ($receiptNo !== '') {
                    $line['stock_receipts'][$receiptNo] = (string) ($supply['receipt_posting_date'] ?? '');
                }
                continue;
            }
            if (!elpis_reservation_is_purchase_supply($supply)) {
                continue;
            }
            $line['reserved_on_purchase'] = true;
            // Gereserveerde hoeveelheid, niet de hele PO-regel (die kan voor meer projecten zijn).
            $reservedPurchaseQty += elpis_reservation_qty($supply);
            $reservedTotalQty += elpis_reservation_qty($supply);
            $date = elpis_date_for_reserved_purchase_line($supply, $byDocLine, $byDocItem);
            if ($date !== '') {
                $purchaseDates[] = $date;
            }
            $poNo = strtoupper(trim((string) ($supply['source_id'] ?? '')));
            if ($poNo !== '') {
                $line['reserved_purchase_orders'][$poNo] = elpis_latest_date(array_filter([
                    $line['reserved_purchase_orders'][$poNo] ?? '',
                    $date,
                ]));
            }
            if (trim((string) ($line['reservation_purchase_order_no'] ?? '')) === '') {
                $line['reservation_purchase_order_no'] = (string) ($supply['source_id'] ?? '');
            }
        }
    }

    // Alleen als de regel niet al direct (LVS_Purchase_Order_No/Job_No) aan een inkoopregel hangt:
    // anders telt dezelfde inkoop dubbel.
    if ($reservedPurchaseQty > 0.00001 && trim((string) ($line['purchase_order_no'] ?? '')) === ''
        && (float) ($line['qty_ordered'] ?? 0) <= 0.00001) {
        $line['qty_ordered'] = $reservedPurchaseQty;
        $line['qty_open'] = max(0.0, $reservedPurchaseQty - (float) ($line['qty_received'] ?? 0));
    }

    // Te bestellen = Aantal min gereserveerd (voorraad 32 + inkoop 39, alleen Reservation_Status
    // 'Reservation'; tracking telt niet). Nooit hoger dan wat er al stond (directe PO: 0).
    if ($reservedTotalQty > 0.00001) {
        $line['reserved_qty'] = $reservedTotalQty;
        $line['reserved_purchase_qty'] = $reservedPurchaseQty;
        $line['qty_to_order'] = max(0.0, min(
            (float) ($line['qty_to_order'] ?? 0),
            (float) ($line['quantity'] ?? 0) - $reservedTotalQty
        ));
    }

    // Meerdere reserveringen/PO's: altijd de LAATSTE datum (worst case), ook als
    // de gekoppelde PurchaseLines-regel al een (vroegere) datum gaf. KVT blijft leidend.
    $current = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    $fromKvt = $current !== '' && str_starts_with((string) ($line['expected_receipt_source'] ?? ''), 'WerkordersLines');
    if ($purchaseDates !== [] && !$fromKvt) {
        if ($current !== '') {
            $purchaseDates[] = $current;
        }
        $line['expected_receipt_date'] = elpis_latest_date($purchaseDates);
        $line['expected_receipt_source'] = 'ReservationEntries -> PurchaseLines -> Expected_Receipt_Date';
    }

    return $line;
}

/** Eerst PurchaseLines.Expected_Receipt_Date, dan de datum op de reservering zelf. */
function elpis_date_for_reserved_purchase_line(array $supply, array $byDocLine, array $byDocItem): string
{
    $documentNo = strtoupper(trim((string) ($supply['source_id'] ?? '')));
    $lineNo = (int) ($supply['source_ref_no'] ?? 0);
    if ($documentNo !== '' && isset($byDocLine[$documentNo . '|' . $lineNo])) {
        $fromLine = elpis_purchase_line_best_date($byDocLine[$documentNo . '|' . $lineNo]);
        if ($fromLine !== '') {
            return $fromLine;
        }
    }

    return elpis_date_for_purchase_supply($supply, $byDocLine, $byDocItem);
}

/** Uit voorraad of via reservering zonder inkooporder gedekt: geen 'Datum ontbreekt'. */
function elpis_line_covered_without_purchase(array $line): bool
{
    if (trim((string) ($line['purchase_order_no'] ?? '')) !== '' || !empty($line['reserved_on_purchase'])) {
        return false;
    }
    if (!empty($line['reserved_from_stock'])) {
        return true;
    }
    if (!empty($line['covered_from_stock'])) {
        return true;
    }
    $reserved = $line['reserved_quantity'] ?? null;
    $quantity = (float) ($line['quantity'] ?? 0);

    return $reserved !== null && $quantity > 0.00001 && (float) $reserved + 0.00001 >= $quantity;
}

function elpis_apply_receipt_expectations(array $linesByProject, array $reservations, array $purchaseLines, array $inventoryByItem, array $extras = []): array
{
    [$byDocLine, $byDocItem] = elpis_index_purchase_lines($purchaseLines);
    $receiptRows = is_array($extras['receipts'] ?? null) ? $extras['receipts'] : [];
    $postingDates = elpis_receipt_posting_index($receiptRows);
    $receiptQty = elpis_receipt_quantity_index($receiptRows);
    $workOrders = is_array($extras['work_orders'] ?? null) ? $extras['work_orders'] : [];
    $freeStockIndex = is_array($extras['free_stock_index'] ?? null) ? $extras['free_stock_index'] : [];
    $stockLedger = is_array($extras['stock_ledger'] ?? null) ? $extras['stock_ledger'] : [];
    if ($stockLedger !== []) {
        foreach ($reservations as $i => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (!isset($entry['entry_no']) && isset($entry['Entry_No'])) {
                $entry = elpis_normalize_reservation_entry($entry);
            }
            $ref = (int) ($entry['source_ref_no'] ?? 0);
            if (elpis_reservation_is_stock_supply($entry) && isset($stockLedger[$ref])) {
                $entry['receipt_document_no'] = (string) ($stockLedger[$ref]['document_no'] ?? '');
                $entry['receipt_posting_date'] = (string) ($stockLedger[$ref]['posting_date'] ?? '');
                $reservations[$i] = $entry;
            }
        }
    }
    [$reservationByEntry, $demandByJobContract] = elpis_prepare_reservation_indexes($reservations);

    $inventoryKnown = [];
    foreach ($inventoryByItem as $itemNo => $qty) {
        $key = strtoupper(trim((string) $itemNo));
        if ($key === '') {
            continue;
        }
        $inventoryKnown[$key] = (float) $qty;
    }

    $reservedTotal = [];
    $reservedOwn = [];
    foreach ($reservations as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!isset($entry['entry_no']) && isset($entry['Entry_No'])) {
            $entry = elpis_normalize_reservation_entry($entry);
        }
        if (!elpis_reservation_is_stock_demand($entry)) {
            continue;
        }
        $itemKey = strtoupper(trim((string) ($entry['item_no'] ?? '')));
        if ($itemKey === '') {
            continue;
        }
        $qty = elpis_reservation_qty($entry);
        if ($qty <= 0.00001) {
            continue;
        }
        $reservedTotal[$itemKey] = ($reservedTotal[$itemKey] ?? 0.0) + $qty;
        $sourceType = (int) ($entry['source_type'] ?? 0);
        $source = strtoupper(trim((string) ($entry['source_id'] ?? '')));
        if ($sourceType === ELPI_JOB_PLANNING_SOURCE_TYPE && $source !== '') {
            $ownKey = $itemKey . '|' . $source;
            $reservedOwn[$ownKey] = ($reservedOwn[$ownKey] ?? 0.0) + $qty;
        }
    }

    foreach ($linesByProject as $projectNo => $lines) {
        if (!is_array($lines)) {
            continue;
        }
        $projectKey = strtoupper(trim((string) $projectNo));
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $linesByProject[$projectNo][$index] = elpis_finalize_line(
                $line,
                $projectKey,
                $byDocLine,
                $byDocItem,
                $postingDates,
                $workOrders,
                $inventoryKnown,
                $reservedTotal,
                $reservedOwn,
                $reservationByEntry,
                $demandByJobContract,
                $freeStockIndex
            );
        }
        $linesByProject[$projectNo] = elpis_apply_ledger_receipt_quantities(
            $projectKey,
            $linesByProject[$projectNo],
            $receiptQty
        );
        $linesByProject[$projectNo] = elpis_clear_covered_to_order($linesByProject[$projectNo]);
        $linesByProject[$projectNo] = elpis_allocate_project_supply($projectKey, $linesByProject[$projectNo], $purchaseLines);
        foreach ($linesByProject[$projectNo] as $index => $line) {
            if (is_array($line)) {
                $linesByProject[$projectNo][$index] = elpis_refresh_receipt_state($line);
            }
        }
    }

    return $linesByProject;
}

function elpis_match_purchase_line(array $line, array $byDocLine, array $byDocItem): ?array
{
    $documentNo = strtoupper(trim((string) ($line['purchase_order_no'] ?? '')));
    $itemNo = strtoupper(trim((string) ($line['item_no'] ?? '')));
    $purchaseLineNo = (int) ($line['purchase_order_line_no'] ?? 0);
    $exactOnly = trim((string) ($line['work_order_no'] ?? '')) === '';
    $matched = null;
    $reason = 'no_document';
    if ($documentNo === '') {
        $reason = 'no_document';
    } elseif ($purchaseLineNo > 0 && isset($byDocLine[$documentNo . '|' . $purchaseLineNo]) && is_array($byDocLine[$documentNo . '|' . $purchaseLineNo])) {
        $candidate = $byDocLine[$documentNo . '|' . $purchaseLineNo];
        $candidateItem = strtoupper(trim((string) ($candidate['item_no'] ?? '')));
        if ($candidateItem === '' || $itemNo === '' || $candidateItem === $itemNo) {
            $matched = $candidate;
            $reason = 'document_line';
        } elseif ($exactOnly) {
            $reason = 'item_mismatch';
        }
    } elseif ($exactOnly) {
        $reason = $purchaseLineNo > 0 ? 'document_line_missing' : 'no_line_no';
    }

    if ($matched === null && $documentNo !== '' && !$exactOnly) {
        if ($itemNo === '') {
            $reason = 'no_item';
        } else {
            $candidates = $byDocItem[$documentNo . '|' . $itemNo] ?? [];
            if (count($candidates) === 1 && is_array($candidates[0])) {
                $matched = $candidates[0];
                $reason = 'document_item';
            } else {
                $reason = 'item_fallback_ambiguous';
            }
        }
    }

    if (elpis_trace_wants_item((string) ($line['item_no'] ?? ''))) {
        $indexed = [];
        foreach ($byDocLine as $key => $purchaseLine) {
            if (!is_string($key) || !str_starts_with($key, $documentNo . '|')) {
                continue;
            }
            if (count($indexed) >= 30) {
                break;
            }
            $indexed[] = $key;
        }
        elpis_trace('match', [
            'item_no' => (string) ($line['item_no'] ?? ''),
            'line_no' => (int) ($line['line_no'] ?? 0),
            'work_order_no' => (string) ($line['work_order_no'] ?? ''),
            'purchase_order_no' => (string) ($line['purchase_order_no'] ?? ''),
            'purchase_order_line_no' => $purchaseLineNo,
            'reason' => $reason,
            'exact_only' => $exactOnly,
            'indexed_lines' => $indexed,
            'purchase' => elpis_trace_purchase_line($matched),
        ]);
    }

    return $matched;
}

function elpis_trace_purchase_line(?array $purchase): ?array
{
    if (!is_array($purchase)) {
        return null;
    }

    return [
        'document_no' => (string) ($purchase['document_no'] ?? ''),
        'line_no' => (int) ($purchase['line_no'] ?? 0),
        'item_no' => (string) ($purchase['item_no'] ?? ''),
        'job_no' => (string) ($purchase['job_no'] ?? ''),
        'job_task_no' => (string) ($purchase['job_task_no'] ?? ''),
        'job_line_type' => (string) ($purchase['job_line_type'] ?? ''),
        'quantity' => $purchase['quantity'] ?? null,
        'outstanding_qty' => $purchase['outstanding_qty'] ?? null,
    ];
}

function elpis_apply_ledger_receipt_quantities(string $projectNo, array $lines, array $receiptQty): array
{
    // ItemLedgerEntries heeft geen inkooporderregel in de al opgehaalde velden.
    // Alleen sluiten als de som Quantity elke regel op project|taak|artikel dekt.
    $groups = [];
    foreach ($lines as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $key = elpis_line_receipt_group_key($projectNo, $line);
        if ($key === null) {
            continue;
        }
        $groups[$key][] = $index;
    }

    foreach ($groups as $key => $indexes) {
        if (!array_key_exists($key, $receiptQty)) {
            continue;
        }
        $sum = (float) $receiptQty[$key];
        $needed = 0.0;
        foreach ($indexes as $index) {
            $needed += max(0.0, (float) ($lines[$index]['quantity'] ?? 0));
        }
        if ($needed <= 0.00001 || $sum + 0.00001 < $needed) {
            continue;
        }
        foreach ($indexes as $index) {
            $quantity = (float) ($lines[$index]['quantity'] ?? 0);
            if ($quantity <= 0.00001) {
                continue;
            }
            $lines[$index]['ledger_received_qty'] = $quantity;
            $lines[$index]['qty_received'] = $quantity;
            $ordered = (float) ($lines[$index]['qty_ordered'] ?? 0);
            if ($ordered <= 0.00001) {
                $lines[$index]['qty_ordered'] = $quantity;
                $ordered = $quantity;
            }
            // Bekende PO-openstand (basiseenheid) gaat voor: een PO van 100 voor 60 nodig is niet 40 open.
            $outstanding = $lines[$index]['purchase_outstanding_qty'] ?? null;
            $lines[$index]['qty_open'] = $outstanding !== null && $outstanding !== ''
                ? max(0.0, (float) $outstanding)
                : max(0.0, $ordered - $quantity);
            $lines[$index]['qty_to_order'] = 0.0;
            $lines[$index] = elpis_refresh_receipt_state($lines[$index]);
            if (elpis_trace_wants_item((string) ($lines[$index]['item_no'] ?? ''))) {
                elpis_trace('ledger', [
                    'item_no' => (string) ($lines[$index]['item_no'] ?? ''),
                    'line_no' => (int) ($lines[$index]['line_no'] ?? 0),
                    'qty_ordered' => (float) ($lines[$index]['qty_ordered'] ?? 0),
                    'qty_open' => (float) ($lines[$index]['qty_open'] ?? 0),
                    'qty_received' => (float) ($lines[$index]['qty_received'] ?? 0),
                    'qty_source' => 'ItemLedgerEntries.Quantity dekt de plannings-Quantity op project|taak|artikel',
                ]);
            }
        }
    }

    return $lines;
}

function elpis_line_is_picked(array $line): bool
{
    if (!empty($line['completely_picked'])) {
        return true;
    }
    $quantity = (float) ($line['quantity'] ?? 0);

    return $quantity > 0.00001 && elpis_line_picked_or_posted($line) + 0.00001 >= $quantity;
}

function elpis_clear_covered_to_order(array $lines): array
{
    // Een werkorderregel zonder inkoopnummer krijgt Quantity als Te bestellen.
    // Die regel blijft zichtbaar. Te bestellen wordt 0 als hij gepickt is, of
    // als een ontvangen inkoopregel van hetzelfde artikel de behoefte dekt.
    $receivedByItem = [];
    foreach ($lines as $line) {
        if (!is_array($line) || trim((string) ($line['purchase_order_no'] ?? '')) === '') {
            continue;
        }
        if (!elpis_line_is_received($line)) {
            continue;
        }
        $item = strtoupper(trim((string) ($line['item_no'] ?? '')));
        if ($item === '') {
            continue;
        }
        $receivedByItem[$item] = ($receivedByItem[$item] ?? 0.0) + max(0.0, (float) ($line['qty_received'] ?? 0));
    }

    foreach ($lines as $index => $line) {
        if (!is_array($line) || !elpis_line_is_picked($line)) {
            continue;
        }
        $lines[$index]['qty_to_order'] = 0.0;
        if (trim((string) ($line['purchase_order_no'] ?? '')) !== '') {
            continue;
        }
        $item = strtoupper(trim((string) ($line['item_no'] ?? '')));
        if ($item === '' || !isset($receivedByItem[$item])) {
            continue;
        }
        $receivedByItem[$item] = max(0.0, $receivedByItem[$item] - max(0.0, (float) ($line['quantity'] ?? 0)));
    }

    foreach ($lines as $index => $line) {
        if (!is_array($line) || elpis_line_is_picked($line)) {
            continue;
        }
        if (trim((string) ($line['purchase_order_no'] ?? '')) !== '') {
            continue;
        }
        $toOrder = (float) ($line['qty_to_order'] ?? 0);
        if ($toOrder <= 0.00001) {
            continue;
        }
        $item = strtoupper(trim((string) ($line['item_no'] ?? '')));
        $covered = $receivedByItem[$item] ?? 0.0;
        if ($item === '' || $covered + 0.00001 < $toOrder) {
            continue;
        }
        $lines[$index]['qty_to_order'] = 0.0;
        $lines[$index]['covered_from_stock'] = true;
        $receivedByItem[$item] = $covered - $toOrder;
    }

    return $lines;
}

function elpis_finalize_line(
    array $line,
    string $projectKey,
    array $byDocLine,
    array $byDocItem,
    array $postingDates,
    array $workOrders,
    array $inventoryKnown,
    array $reservedTotal,
    array $reservedOwn,
    array $reservationByEntry = [],
    array $demandByJobContract = [],
    array $freeStockIndex = []
): array {
    $workOrderNo = trim((string) ($line['work_order_no'] ?? ''));
    if ($workOrderNo !== '' && isset($workOrders[strtoupper($workOrderNo)]) && is_array($workOrders[strtoupper($workOrderNo)])) {
        $line['work_order_no'] = (string) ($workOrders[strtoupper($workOrderNo)]['no'] ?? $workOrderNo);
        $line['work_order_description'] = (string) ($workOrders[strtoupper($workOrderNo)]['description'] ?? '');
        $line['work_order_status'] = (string) ($workOrders[strtoupper($workOrderNo)]['status'] ?? '');
    }

    // Ook bij een gevulde KVT-datum: die datum blijft de verwachte ontvangst,
    // maar een gesloten inkoopregel mag daar niet meer 'Te laat' van maken.
    $purchase = elpis_match_purchase_line($line, $byDocLine, $byDocItem);
    if (is_array($purchase)) {
        if (array_key_exists('outstanding_qty', $purchase) && $purchase['outstanding_qty'] !== null && $purchase['outstanding_qty'] !== '') {
            $line['purchase_outstanding_qty'] = (float) $purchase['outstanding_qty'];
        }
        // Besteld/openstaand/ontvangen komen van deze PurchaseLines-regel.
        // De planningsregel kan 112 zijn terwijl de inkoopregel Quantity 1 heeft.
        $samePurchaseLine = (int) ($line['purchase_order_line_no'] ?? 0) > 0
            && (int) ($purchase['line_no'] ?? 0) === (int) $line['purchase_order_line_no']
            && strtoupper(trim((string) ($purchase['document_no'] ?? ''))) === strtoupper(trim((string) ($line['purchase_order_no'] ?? '')));
        if ($samePurchaseLine && array_key_exists('quantity', $purchase) && $purchase['quantity'] !== null && (float) $purchase['quantity'] > 0.00001) {
            $line['qty_ordered'] = (float) $purchase['quantity'];
        }
        $line = elpis_apply_purchase_line_quantities($line);
        $qtySource = $samePurchaseLine
            ? 'PurchaseLines.Quantity en Outstanding_Quantity'
            : 'PurchaseLines gekoppeld zonder Quantity op document+regel';
    } else {
        $qtySource = 'ongewijzigd na elpis_line_quantities';
    }
    if (elpis_trace_wants_item((string) ($line['item_no'] ?? ''))) {
        elpis_trace('finalize', [
            'item_no' => (string) ($line['item_no'] ?? ''),
            'line_no' => (int) ($line['line_no'] ?? 0),
            'qty_ordered' => (float) ($line['qty_ordered'] ?? 0),
            'qty_open' => (float) ($line['qty_open'] ?? 0),
            'qty_received' => (float) ($line['qty_received'] ?? 0),
            'qty_source' => $qtySource,
            'purchase' => elpis_trace_purchase_line(is_array($purchase) ? $purchase : null),
        ]);
    }

    $kvtDate = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($kvtDate !== '') {
        $line['expected_receipt_date'] = substr($kvtDate, 0, 10);
        $line['expected_receipt_source'] = 'WerkordersLines -> KVT_Expected_Receipt_Date';
    } elseif (is_array($purchase)) {
        $date = elpis_normalize_bc_date((string) ($purchase['expected_receipt_date'] ?? ''));
        if ($date !== '') {
            $line['expected_receipt_date'] = substr($date, 0, 10);
            $line['expected_receipt_source'] = 'PurchaseLines -> Expected_Receipt_Date';
        }
    }

    $line = elpis_apply_line_reservations($line, $projectKey, $reservationByEntry, $demandByJobContract, $byDocLine, $byDocItem);

    $itemKey = strtoupper(trim((string) ($line['item_no'] ?? '')));
    $postingKey = elpis_line_receipt_group_key($projectKey, $line);
    if ($postingKey !== null && isset($postingDates[$postingKey])) {
        $line['received_date'] = $postingDates[$postingKey];
    }

    $line = elpis_refresh_receipt_state($line);

    if ($itemKey !== '' && isset($freeStockIndex[$itemKey])) {
        $free = elpis_line_free_stock($freeStockIndex, $itemKey, (string) ($line['location_code'] ?? ''), $projectKey);
        $line['reserved_elsewhere'] = $free['reserved_elsewhere'];
        $line['picked_elsewhere'] = $free['picked_elsewhere'];
        $line['free_stock'] = $free['free'];
        $line['free_stock_unreserved'] = $free['pool'];
    } elseif ($itemKey !== '' && array_key_exists($itemKey, $inventoryKnown)) {
        $elsewhere = (float) ($reservedTotal[$itemKey] ?? 0) - (float) ($reservedOwn[$itemKey . '|' . $projectKey] ?? 0);
        $line['reserved_elsewhere'] = max(0.0, $elsewhere);
        $line['free_stock'] = elpis_item_free_stock((float) $inventoryKnown[$itemKey], $line['reserved_elsewhere']);
        // Pool voor toewijzing: vrije voorraad min ALLE reserveringen (eigen voorraadreservering telt apart).
        $line['free_stock_unreserved'] = max(0.0, elpis_item_free_stock((float) $inventoryKnown[$itemKey], (float) ($reservedTotal[$itemKey] ?? 0)));
    }

    return $line;
}

function elpis_enrich_planning_lines_with_receipts(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL, ?callable $onInventoryChunk = null, ?callable $onPhase = null): array
{
    $context = elpis_load_receipt_context($company, $linesByProject, $ttl, $onInventoryChunk, $onPhase);
    // Taak- en serviceartikelen (AppItems) helemaal weglaten; zonder artikelinfo blijft de regel staan.
    $skip = (array) ($context['skip_items'] ?? []);
    $leadTimes = (array) ($GLOBALS['ELPIS_ITEM_LEAD_TIME'] ?? []);
    foreach ($linesByProject as $projectNo => $lines) {
        $linesByProject[$projectNo] = array_values(array_filter((array) $lines, static function ($line) use ($skip): bool {
            return !is_array($line) || !isset($skip[strtoupper(trim((string) ($line['item_no'] ?? '')))]);
        }));
        foreach ($linesByProject[$projectNo] as $i => $line) {
            if (is_array($line)) {
                $linesByProject[$projectNo][$i]['item_lead_time'] = (string) ($leadTimes[strtoupper(trim((string) ($line['item_no'] ?? '')))] ?? '');
            }
        }
    }

    $linesByProject = elpis_apply_receipt_expectations(
        $linesByProject,
        $context['reservations'],
        $context['purchase_lines'],
        $context['inventory'],
        [
            'receipts' => $context['receipts'] ?? [],
            'stock_ledger' => $context['stock_ledger'] ?? [],
            'work_orders' => $context['work_orders'] ?? [],
            'free_stock_index' => $context['free_stock_index'] ?? [],
        ]
    );

    $linesByProject = elpis_apply_inbound_bins($linesByProject, elpis_fetch_inbound_bins($company, $linesByProject, $ttl));

    return elpis_attach_purchasers($linesByProject, elpis_fetch_purchasers($company, elpis_collect_line_purchase_docs($linesByProject), $ttl));
}

/**
 * Inkooporders waar een regel op leunt: gekoppeld (LVS_Purchase_Order_No), gereserveerd en de
 * verdeelde rest (elpis_allocate_project_supply). Voorraad/gepickt/te bestellen = geen order.
 */
function elpis_line_purchase_docs(array $line): array
{
    $docs = array_merge(
        [(string) ($line['purchase_order_no'] ?? '')],
        array_map('strval', array_keys(is_array($line['reserved_purchase_orders'] ?? null) ? $line['reserved_purchase_orders'] : [])),
        array_map('strval', array_keys(is_array($line['alloc_purchase_docs'] ?? null) ? $line['alloc_purchase_docs'] : []))
    );
    $out = [];
    foreach ($docs as $doc) {
        $doc = strtoupper(trim($doc));
        if ($doc !== '') {
            $out[$doc] = $doc;
        }
    }

    return array_values($out);
}

function elpis_collect_line_purchase_docs(array $linesByProject): array
{
    $docs = [];
    foreach ($linesByProject as $lines) {
        foreach ((array) $lines as $line) {
            foreach (is_array($line) ? elpis_line_purchase_docs($line) : [] as $doc) {
                $docs[$doc] = $doc;
            }
        }
    }

    return array_values($docs);
}

/**
 * Besteller per inkooporder: PurchaseOrders.Purchaser_Code, anders PostedPurchaseReceipt (Order_No),
 * anders GearchiveerdeInkoopkoppen. Code -> AppUserSetup (User_ID, Email) en SalespersonPurchaserCard
 * (Name, E_Mail). Resultaat: ['docs' => [doc => code], 'people' => [code => [...]]].
 */
function elpis_fetch_purchasers(string $company, array $docs, int $ttl = ELPI_CACHE_TTL): array
{
    $byDoc = [];
    $sources = [['PurchaseOrders', 'No'], ['PostedPurchaseReceipt', 'Order_No'], ['GearchiveerdeInkoopkoppen', 'No']];
    foreach ($sources as [$entity, $field]) {
        $missing = array_values(array_diff($docs, array_keys($byDoc)));
        foreach (array_chunk(elpis_sort_odata_values($missing), ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
            foreach (elpis_try_fetch_rows($company, $entity, ['$select' => $field . ',Purchaser_Code', '$filter' => elpis_odata_eq_list($field, $chunk)], $ttl) as $row) {
                $doc = strtoupper(trim((string) ($row[$field] ?? '')));
                $code = strtoupper(trim((string) ($row['Purchaser_Code'] ?? '')));
                if ($doc !== '' && $code !== '' && !isset($byDoc[$doc])) {
                    $byDoc[$doc] = $code;
                }
            }
        }
    }

    $codes = elpis_sort_odata_values(array_values(array_unique($byDoc)));
    $people = [];
    foreach ($codes as $code) {
        $people[$code] = ['code' => $code, 'name' => '', 'user_id' => '', 'email' => ''];
    }
    $cardEmail = [];
    foreach (array_chunk($codes, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        foreach (elpis_try_fetch_rows($company, 'SalespersonPurchaserCard', ['$select' => 'Code,Name,E_Mail', '$filter' => elpis_odata_eq_list('Code', $chunk)], $ttl) as $row) {
            $code = strtoupper(trim((string) ($row['Code'] ?? '')));
            if (isset($people[$code])) {
                $people[$code]['name'] = trim((string) ($row['Name'] ?? ''));
                $cardEmail[$code] = strtolower(trim((string) ($row['E_Mail'] ?? '')));
            }
        }
        foreach (elpis_try_fetch_rows($company, ELPI_USER_SETUP_ENTITY, ['$select' => 'User_ID,Salespers_Purch_Code,Email', '$filter' => elpis_odata_eq_list('Salespers_Purch_Code', $chunk)], $ttl) as $row) {
            $code = strtoupper(trim((string) ($row['Salespers_Purch_Code'] ?? '')));
            $email = strtolower(trim((string) ($row['Email'] ?? '')));
            if (isset($people[$code]) && $people[$code]['user_id'] === '') {
                $people[$code]['user_id'] = elpis_normalize_bc_username((string) ($row['User_ID'] ?? ''));
                $people[$code]['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
            }
        }
    }
    foreach ($people as $code => $person) {
        if ($person['email'] === '' && filter_var($cardEmail[$code] ?? '', FILTER_VALIDATE_EMAIL)) {
            $people[$code]['email'] = $cardEmail[$code];
        }
    }

    return ['docs' => $byDoc, 'people' => $people];
}

/** Zet $line['purchasers'] (lijst personen) op elke regel met een inkooporder. */
function elpis_attach_purchasers(array $linesByProject, array $purchasers): array
{
    foreach ($linesByProject as $projectNo => $lines) {
        foreach ((array) $lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }
            $found = [];
            foreach (elpis_line_purchase_docs($line) as $doc) {
                $code = (string) ($purchasers['docs'][$doc] ?? '');
                if ($code !== '') {
                    $found[$code] = $purchasers['people'][$code] ?? ['code' => $code, 'name' => '', 'user_id' => '', 'email' => ''];
                }
            }
            ksort($found);
            $linesByProject[$projectNo][$i]['purchasers'] = array_values($found);
        }
    }

    return $linesByProject;
}

/** Sleutel van een besteller voor filter en mail: BC-gebruiker, anders de inkopercode. */
function elpis_purchaser_key(array $person): string
{
    $user = elpis_normalize_bc_username((string) ($person['user_id'] ?? ''));

    return $user !== '' ? $user : strtoupper(trim((string) ($person['code'] ?? '')));
}

function elpis_purchaser_label(array $person): string
{
    $code = strtoupper(trim((string) ($person['code'] ?? '')));
    $name = trim((string) ($person['name'] ?? ''));

    return $name !== '' ? $name . ' (' . $code . ')' : $code;
}

function elpis_line_has_purchaser(array $line, string $key): bool
{
    foreach ((array) ($line['purchasers'] ?? []) as $person) {
        if (is_array($person) && strcasecmp(elpis_purchaser_key($person), $key) === 0) {
            return true;
        }
    }

    return false;
}

/** R: BinContent KVT/INBOUND per artikel (Quantity_Base), alleen projectartikelen, chunked + gecachet. */
function elpis_fetch_inbound_bins(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL): array
{
    $items = [];
    foreach ($linesByProject as $lines) {
        foreach ((array) $lines as $line) {
            $item = is_array($line) ? trim((string) ($line['item_no'] ?? '')) : '';
            if ($item !== '' && elpis_line_coverage($line)['received_on_hand'] > 0.00001) {
                $items[$item] = true;
            }
        }
    }
    $bins = [];
    foreach (array_chunk(elpis_sort_odata_values(array_keys($items)), ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, 'BinContent', [
            '$select' => 'Item_No,Location_Code,Bin_Code,Quantity_Base,Pick_Quantity_Base,Put_away_Quantity_Base',
            '$filter' => "Location_Code eq 'KVT' and Bin_Code eq 'INBOUND' and (" . elpis_odata_eq_list('Item_No', $chunk) . ')',
        ], $ttl);
        foreach ($rows as $row) {
            $item = is_array($row) ? strtoupper(trim((string) ($row['Item_No'] ?? ''))) : '';
            if ($item !== '') {
                $bins[$item] = ($bins[$item] ?? 0.0) + max(0.0, (float) ($row['Quantity_Base'] ?? 0));
            }
        }
    }

    return $bins;
}

/**
 * INBOUND is per artikel, niet per project: verdeel over regels die al ontvangen-maar-niet-gepicked zijn
 * (ontvangst via PO / ILE Inkoop met Job_No van dit project), per regel nooit meer dan dat tekort.
 */
function elpis_apply_inbound_bins(array $linesByProject, array $bins): array
{
    foreach ($linesByProject as $projectNo => $lines) {
        foreach ((array) $lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }
            $item = strtoupper(trim((string) ($line['item_no'] ?? '')));
            $alloc = min((float) ($bins[$item] ?? 0), elpis_line_coverage($line)['received_on_hand']);
            $linesByProject[$projectNo][$i]['inbound_qty'] = max(0.0, $alloc);
            if ($alloc > 0) {
                $bins[$item] -= $alloc;
            }
        }
    }

    return $linesByProject;
}

function elpis_load_shown_lines(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL, ?callable $onPhase = null): array
{
    $emit = static function (string $step, string $status, array $extra = []) use ($onPhase): void {
        if ($onPhase === null) {
            return;
        }
        $onPhase(array_merge(['step' => $step, 'status' => $status], $extra));
    };

    $startedAt = microtime(true);
    $emit('lines', 'start');
    $linesByProject = elpis_fetch_planning_lines_by_projects($company, $projectNos, $ttl);
    $emit('lines', 'done');
    $linesByProject = elpis_enrich_planning_lines_with_receipts($company, $linesByProject, $ttl, null, $onPhase);
    elpis_log_timing(
        $company,
        'shown-lines',
        ['$filter' => 'projects=' . count(elpis_normalize_project_nos($projectNos))],
        count($linesByProject),
        $startedAt,
        'scoped'
    );

    return $linesByProject;
}

function elpis_project_execution_summary(array $lines): array
{
    $latest = '';
    $hasOpen = false;
    $hasMissing = false;
    $hasToOrder = false;
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $status = elpis_line_status($line);
        if (!in_array($status['key'], [ELPI_STATUS_LATE, 'DELIVERY_LATE', 'ORDER_TOO_LATE', 'NO_DATE', 'TO_ORDER', 'ORDERED', 'PARTIAL_ORDERED'], true)) {
            continue;
        }
        $hasOpen = true;
        if ($status['key'] === 'TO_ORDER') {
            $hasToOrder = true;
        }
        if ($status['date_missing']) {
            $hasMissing = true;
        }
        if ($status['date'] !== '' && ($latest === '' || $status['date'] > $latest)) {
            $latest = $status['date'];
        }
    }
    if (!$hasOpen) {
        return ['state' => 'none', 'date' => ''];
    }
    if ($hasToOrder) {
        return ['state' => 'to_order', 'date' => $latest];
    }
    if ($hasMissing) {
        return ['state' => 'missing', 'date' => $latest];
    }
    if ($latest !== '') {
        return ['state' => 'date', 'date' => $latest];
    }

    return ['state' => 'direct', 'date' => ''];
}

function elpis_project_execution_value(array $summary): string
{
    $state = (string) ($summary['state'] ?? 'none');
    if ($state === 'date') {
        return elpis_format_display_date((string) ($summary['date'] ?? ''));
    }
    if ($state === 'direct') {
        return LOC('elpis.summary.direct');
    }
    if ($state === 'missing') {
        return LOC('elpis.summary.missing');
    }
    if ($state === 'to_order') {
        return LOC('elpis.summary.to_order');
    }

    return '';
}

function elpis_clear_odata_cache(): int
{
    if (!function_exists('cache_base_dir')) {
        return 0;
    }

    $dir = cache_base_dir();
    if (!is_dir($dir)) {
        return 0;
    }

    $deleted = 0;
    $entries = @scandir($dir);
    if (!is_array($entries)) {
        return 0;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.cleanup_marker') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_file($path) && str_ends_with(strtolower($entry), '.json') && @unlink($path)) {
            $deleted += 1;
        }
    }

    return $deleted;
}

function elpis_warm_odata_cache(int $ttl = ELPI_CACHE_TTL): array
{
    $summary = [
        'companies' => 0,
        'managers' => 0,
        'projects' => 0,
        'planning_line_projects' => 0,
        'reservation_entries' => 0,
        'purchase_order_lines' => 0,
        'item_inventory' => 0,
        'by_company' => [],
        'per_company' => [],
    ];

    $companies = elpis_companies_for_page($ttl);
    $summary['companies'] = count($companies);

    foreach ($companies as $company) {
        auth_set_current_company_context($company, $ttl);
        $managers = elpis_fetch_project_managers($company, $ttl);
        $summary['managers'] += count($managers);

        $errorsBefore = count((array) ($GLOBALS['ELPIS_FETCH_ERRORS'] ?? []));
        $receiptFailuresBefore = elpis_receipt_fetch_failure_count();
        $projectRows = elpis_fetch_project_rows_for_company($company, $ttl);
        $projects = elpis_collect_projects_from_rows($projectRows);
        $summary['projects'] += count($projects);

        $linesByProject = elpis_load_shown_lines($company, elpis_project_nos_from_projects($projects), $ttl);
        $summary['planning_line_projects'] += count($linesByProject);
        $summary['by_company'][$company] = [
            'projects' => $projects,
            'lines' => $linesByProject,
        ];
        $summary['per_company'][$company] = [
            'managers' => count($managers),
            'projects' => count($projects),
            'line_projects' => count($linesByProject),
            'lines' => array_sum(array_map(static fn ($l): int => is_array($l) ? count($l) : 0, $linesByProject)),
            'project_rows' => count($projectRows),
            'project_managers' => count(array_unique(array_column($projects, 'project_manager'))),
            'fetch_errors' => count((array) ($GLOBALS['ELPIS_FETCH_ERRORS'] ?? [])) - $errorsBefore,
        ];
        $stats = $summary['per_company'][$company];
        error_log('elpis.nightly fetch company="' . $company . '"'
            . ' project_rows=' . $stats['project_rows']
            . ' projects_with_manager=' . $stats['projects']
            . ' project_managers=' . $stats['project_managers']
            . ' managers=' . $stats['managers']
            . ' line_projects=' . $stats['line_projects']
            . ' lines=' . $stats['lines']
            . ' fetch_errors=' . $stats['fetch_errors']);
        $receiptFailures = elpis_receipt_fetch_failure_count() - $receiptFailuresBefore;
        $summary['per_company'][$company]['receipt_fetch_errors'] = $receiptFailures;
        if ($receiptFailures > 0) {
            // Onvolledige ontvangstdata: niet cachen. De modal haalt dan live op.
            error_log('elpis.nightly skip_cache company="' . $company . '" receipt_fetch_errors=' . $receiptFailures);
            continue;
        }
        foreach ($linesByProject as $projectNo => $lines) {
            elpis_write_project_cache($company, (string) $projectNo, is_array($lines) ? $lines : []);
        }
    }

    return $summary;
}

function elpis_line_search_blob(array $line, string $projectNo = ''): string
{
    $statusLabel = elpis_line_status($line)['label'];
    $parts = [
        $projectNo,
        (string) ($line['job_task_no'] ?? ''),
        (string) ($line['work_order_no'] ?? ''),
        (string) ($line['item_no'] ?? ''),
        (string) ($line['description'] ?? ''),
        $statusLabel,
        (string) ($line['planning_date'] ?? ''),
        elpis_line_expected_receipt_display($line),
    ];

    return strtolower(implode(' ', array_filter($parts, static function (string $value): bool {
        return trim($value) !== '';
    })));
}

function elpis_project_search_blob(array $project, array $lines): string
{
    $parts = [
        strtolower(trim((string) ($project['no'] ?? ''))),
        strtolower(trim((string) ($project['description'] ?? ''))),
    ];

    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $parts[] = elpis_line_search_blob($line, (string) ($project['no'] ?? ''));
    }

    $executionValue = elpis_project_execution_value(elpis_project_execution_summary($lines));
    if ($executionValue !== '') {
        $parts[] = strtolower(LOC('elpis.summary.execution_from'));
        $parts[] = strtolower($executionValue);
    }

    return trim(implode(' ', array_filter($parts, static function (string $value): bool {
        return $value !== '';
    })));
}

function elpis_data_dir(): string
{
    $override = $GLOBALS['ELPIS_DATA_DIR'] ?? null;
    $dir = is_string($override) && trim($override) !== '' ? $override : (__DIR__ . '/data');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    return $dir;
}

function elpis_project_cache_dir(): string
{
    $override = $GLOBALS['ELPIS_PROJECT_CACHE_DIR'] ?? null;
    $dir = is_string($override) && trim($override) !== '' ? $override : (__DIR__ . '/cache/elpis_projects');
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function elpis_project_cache_path(string $company, string $projectNo): string
{
    return elpis_project_cache_dir() . '/' . hash('sha256', $company . "\0" . $projectNo) . '.json';
}

function elpis_read_project_cache(string $company, string $projectNo): ?array
{
    $path = elpis_project_cache_path($company, $projectNo);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return null;
    }

    if ((int) ($decoded['expires_at'] ?? 0) < time()) {
        return null;
    }

    if ((int) ($decoded['version'] ?? 0) !== ELPI_PROJECT_CACHE_VERSION) {
        return null;
    }

    if (is_array($decoded['lines'] ?? null)) {
        foreach ($decoded['lines'] as $index => $line) {
            if (is_array($line)) {
                $decoded['lines'][$index] = elpis_refresh_receipt_state($line);
            }
        }
    }

    return $decoded;
}

function elpis_write_project_cache(string $company, string $projectNo, array $lines): void
{
    $path = elpis_project_cache_path($company, $projectNo);
    $now = time();
    $payload = [
        'version' => ELPI_PROJECT_CACHE_VERSION,
        'company' => $company,
        'project_no' => $projectNo,
        'cached_at' => $now,
        'expires_at' => $now + ELPI_CACHE_TTL,
        'lines' => array_values($lines),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }

    $tmp = $path . '.tmp';
    file_put_contents($tmp, $json, LOCK_EX);
    rename($tmp, $path);
}

function elpis_load_project_modal_lines(string $company, string $projectNo, bool $refresh): array
{
    $company = trim($company);
    $projectNo = trim($projectNo);
    if ($company === '' || $projectNo === '') {
        return [];
    }

    if (!$refresh) {
        $cached = elpis_read_project_cache($company, $projectNo);
        if (is_array($cached) && is_array($cached['lines'] ?? null)) {
            elpis_trace('project-cache', [
                'hit' => true,
                'version' => (int) ($cached['version'] ?? 0),
                'cached_at' => (int) ($cached['cached_at'] ?? 0),
            ]);

            return $cached['lines'];
        }
        elpis_trace('project-cache', ['hit' => false, 'skipped_because' => 'miss-of-versie']);
    } else {
        elpis_trace('project-cache', ['hit' => false, 'skipped_because' => 'refresh']);
    }

    $load = static function () use ($company, $projectNo): array {
        $failuresBefore = elpis_receipt_fetch_failure_count();
        $byProject = elpis_load_shown_lines($company, [$projectNo], ELPI_CACHE_TTL);
        if (elpis_receipt_fetch_failure_count() > $failuresBefore) {
            // Liever een foutmelding dan stil "geen inkoop"; niets wegschrijven in de cache.
            throw new RuntimeException('Ontvangstgegevens voor ' . $projectNo . ' konden niet volledig worden opgehaald.');
        }

        return $byProject[$projectNo] ?? [];
    };

    if ($refresh) {
        $previous = $GLOBALS['ELPIS_LIVE_FETCH'] ?? false;
        $GLOBALS['ELPIS_LIVE_FETCH'] = true;
        elpis_reset_fetch_memo();
        try {
            $lines = $load();
        } finally {
            $GLOBALS['ELPIS_LIVE_FETCH'] = $previous;
            elpis_reset_fetch_memo();
        }
    } else {
        $lines = $load();
    }

    elpis_write_project_cache($company, $projectNo, $lines);

    return $lines;
}

function elpis_status_tone(string $code): string
{
    return ELPI_LINE_STATUSES[strtoupper(trim($code))]['tone'] ?? 'orange';
}

function elpis_line_filter_status(array $line): string
{
    return elpis_line_status($line)['key'];
}

function elpis_line_row_tone(array $line): string
{
    return elpis_status_tone(elpis_line_status($line)['key']);
}

function elpis_line_tooltips(array $line): array
{
    $receiptSource = trim((string) ($line['expected_receipt_source'] ?? ''));
    if ((string) ($line['receipt_state'] ?? '') === 'received') {
        if (elpis_normalize_bc_date((string) ($line['received_date'] ?? '')) !== '') {
            $receiptSource = 'ItemLedgerEntries -> Posting_Date';
        } elseif (!empty($line['completely_received'])) {
            $receiptSource = 'AppProjectInkoopPlanningsRegel -> LVS_Completely_Received';
        } elseif (elpis_line_purchase_outstanding_closed($line)) {
            $receiptSource = 'PurchaseLines -> Outstanding_Quantity';
        } elseif (elpis_line_ledger_received_qty($line) !== null) {
            $receiptSource = 'ItemLedgerEntries -> Quantity';
        } else {
            $receiptSource = 'AppProjectInkoopPlanningsRegel -> LVS_Completely_Received';
        }
    }
    if ($receiptSource === '') {
        $receiptSource = 'WerkordersLines -> KVT_Expected_Receipt_Date';
    }

    $planningField = trim((string) ($line['planning_date_field'] ?? 'Planning_Date'));
    if ($planningField === '') {
        $planningField = 'Planning_Date';
    }

    return [
        'workorder' => '(AppWerkorders -> No)',
        'item' => '(WerkordersLines -> No)',
        'description' => '(WerkordersLines -> Description)',
        'planning_date' => '(WerkordersLines -> ' . $planningField . ')',
        'line_status' => '(Elpis: gepicked/verbruikt, voorraad, inkoop)',
        'to_order' => '(WerkordersLines -> Quantity)',
        'ordered' => '(PurchaseLines -> Quantity)',
        'open' => '(PurchaseLines -> Outstanding_Quantity)',
        'received' => '(PurchaseLines -> Outstanding_Quantity)',
        'expected_receipt' => '(' . $receiptSource . ')',
        'stock' => '(ItemLedgerEntries -> Remaining_Quantity, open, zonder Job_No) − (ReservationEntries -> Quantity_Base, Source_Type 32, andere projecten) − (WerkordersLines -> KVT_Qty_Picked − Qty_Posted zonder reservering, andere projecten)',
    ];
}

function elpis_format_qty(float $value): string
{
    if (abs($value - round($value)) < 0.00001) {
        return (string) (int) round($value);
    }

    return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
}

function elpis_line_public_payload(array $line, string $projectNo): array
{
    $line = elpis_refresh_receipt_state($line);
    $statusGroup = elpis_line_status_group($line);

    $freeStock = $line['free_stock'] ?? null;
    $freeDisplay = is_numeric($freeStock) ? elpis_format_qty((float) $freeStock) : '';
    $orderedQty = elpis_line_shown_ordered_qty($line);
    $receivedQty = elpis_line_shown_received_qty($line);

    return [
        'work_order_no' => (string) ($line['work_order_no'] ?? ''),
        'item_no' => (string) ($line['item_no'] ?? ''),
        'description' => (string) ($line['description'] ?? ''),
        'planning_date' => elpis_format_display_date((string) ($line['planning_date'] ?? '')),
        'planning_date_sort' => elpis_normalize_bc_date((string) ($line['planning_date'] ?? '')),
        'status_label' => $statusGroup['label'],
        'status_reason' => $statusGroup['reason'],
        'status_filter' => $statusGroup['key'],
        'status_filter_label' => $statusGroup['filter_label'],
        'qty_to_order' => (float) ($line['qty_to_order'] ?? 0),
        'qty_ordered' => $orderedQty,
        'qty_open' => (float) ($line['qty_open'] ?? 0),
        'qty_received' => $receivedQty,
        'qty_to_order_display' => elpis_format_qty((float) ($line['qty_to_order'] ?? 0)),
        'qty_ordered_display' => elpis_format_qty($orderedQty),
        'qty_open_display' => elpis_format_qty((float) ($line['qty_open'] ?? 0)),
        'qty_received_display' => elpis_format_qty($receivedQty),
        'qty_needed' => (float) ($line['quantity'] ?? 0),
        'qty_picked' => (float) ($line['kvt_qty_picked'] ?? 0),
        'qty_picked_display' => elpis_format_qty((float) ($line['kvt_qty_picked'] ?? 0)) . '/' . elpis_format_qty((float) ($line['quantity'] ?? 0)),
        'qty_picked_tooltip' => sprintf(LOC('elpis.tip.picked'), elpis_format_qty((float) ($line['kvt_qty_picked'] ?? 0)), elpis_format_qty((float) ($line['quantity'] ?? 0))),
        'purchaser' => implode(', ', array_map('elpis_purchaser_label', array_filter((array) ($line['purchasers'] ?? []), 'is_array'))),
        'purchaser_keys' => array_values(array_map('elpis_purchaser_key', array_filter((array) ($line['purchasers'] ?? []), 'is_array'))),
        'is_problem' => elpis_line_is_problem($line),
        'expected_receipt' => elpis_line_expected_receipt_display($line),
        'expected_receipt_sort' => elpis_receipt_sort_value($line),
        'free_stock_display' => $freeDisplay,
        'free_stock_sort' => is_numeric($freeStock) ? (string) (float) $freeStock : '',
        'tone' => elpis_line_row_tone($line),
        'filter_status' => elpis_line_filter_status($line),
        'receipt_state' => (string) ($line['receipt_state'] ?? ''),
        'search' => elpis_line_search_blob($line, $projectNo),
        'tooltips' => elpis_line_tooltips($line),
    ];
}

function elpis_debug_project_cache_meta(string $company, string $projectNo): array
{
    $path = elpis_project_cache_path($company, $projectNo);
    $meta = [
        'layer' => 'project',
        'version_checked' => true,
        'code_version' => ELPI_PROJECT_CACHE_VERSION,
        'present' => is_file($path),
    ];
    if (!is_file($path)) {
        return $meta;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $fileVersion = is_array($decoded) ? (int) ($decoded['version'] ?? 0) : 0;
    $expiresAt = is_array($decoded) ? (int) ($decoded['expires_at'] ?? 0) : 0;
    $meta['file_version'] = $fileVersion;
    $meta['version_match'] = is_array($decoded) && $fileVersion === ELPI_PROJECT_CACHE_VERSION;
    $meta['cached_at'] = is_array($decoded) ? (int) ($decoded['cached_at'] ?? 0) : 0;
    $meta['expires_at'] = $expiresAt;
    $meta['expired'] = $expiresAt < time();
    $meta['filemtime'] = (int) filemtime($path);
    $meta['line_count'] = is_array($decoded['lines'] ?? null) ? count($decoded['lines']) : 0;

    return $meta;
}

function elpis_debug_odata_cache_meta(string $projectNo): array
{
    $meta = [
        'layer' => 'odata_file',
        'version_checked' => false,
        'note' => 'ELPI_PROJECT_CACHE_VERSION geldt niet voor deze bestanden. Ververs nu wist alleen het bestand van de opgevraagde URL op het directe pad; het Mímir-pad slaat deze laag over.',
        'files' => [],
    ];
    if (!function_exists('cache_base_dir') || !is_dir(cache_base_dir())) {
        return $meta;
    }

    $entries = @scandir(cache_base_dir());
    if (!is_array($entries)) {
        return $meta;
    }

    $seen = 0;
    foreach ($entries as $entry) {
        if (!str_ends_with(strtolower($entry), '.json')) {
            continue;
        }
        $seen++;
        if ($seen > 200 || count($meta['files']) >= 20) {
            break;
        }
        $path = cache_base_dir() . DIRECTORY_SEPARATOR . $entry;
        $raw = @file_get_contents($path);
        if ($raw === false || !str_contains($raw, $projectNo)) {
            continue;
        }
        $decoded = json_decode($raw, true);
        $fileMeta = is_array($decoded) && is_array($decoded['_meta'] ?? null) ? $decoded['_meta'] : [];
        $source = (string) ($fileMeta['source_url'] ?? '');
        if ($source !== '' && !str_contains($source, $projectNo)) {
            continue;
        }
        $meta['files'][] = [
            'cached_at' => (int) ($fileMeta['cached_at'] ?? 0),
            'expires_at' => (int) ($fileMeta['expires_at'] ?? 0),
            'filemtime' => (int) filemtime($path),
            'source' => substr($source, 0, 300),
            'version_checked' => false,
        ];
    }

    return $meta;
}

function elpis_debug_cache_layers(string $company, string $projectNo): array
{
    return [
        'project' => elpis_debug_project_cache_meta($company, $projectNo),
        'odata_file' => elpis_debug_odata_cache_meta($projectNo),
        'mimir' => [
            'layer' => 'mimir',
            'version_checked' => false,
            'enabled' => function_exists('odata_mimir_enabled') && odata_mimir_enabled(),
            'max_age_on_refresh' => 0,
            'note' => 'Mímir bewaart BC-antwoorden op max_age. ELPI_PROJECT_CACHE_VERSION zit niet in die sleutel.',
        ],
        'http' => [
            'layer' => 'http',
            'version_checked' => false,
            'cache_control' => 'no-store',
            'browser_fetch' => 'cache: no-store',
        ],
        'memo' => [
            'layer' => 'request_memo',
            'version_checked' => false,
            'reset_on_refresh' => true,
        ],
        'browser' => [
            'layer' => 'browser',
            'version_checked' => false,
            'localStorage' => false,
            'sessionStorage' => 'alleen elpis_skip_loader, geen regelcijfers',
        ],
        'mail' => [
            'layer' => 'pm_mail',
            'version_checked' => false,
            'feeds_modal' => false,
        ],
    ];
}

function elpis_debug_conclusion(array $lines, array $trace): string
{
    $cacheHit = false;
    $purchaseRows = 0;
    $purchaseErrors = [];
    $finalizes = [];
    $normalizes = [];
    foreach ($trace as $event) {
        if (!is_array($event)) {
            continue;
        }
        $step = (string) ($event['step'] ?? '');
        if ($step === 'normalize') {
            $normalizes[] = $event;
        } elseif ($step === 'finalize') {
            $finalizes[] = $event;
        } elseif ($step === 'project-cache' && !empty($event['hit'])) {
            $cacheHit = true;
        } elseif ($step === 'odata' && (string) ($event['entity'] ?? '') === ELPI_PURCHASE_ORDER_LINE_ENTITY) {
            $purchaseRows += (int) ($event['rows'] ?? 0);
            if ((string) ($event['error'] ?? '') !== '') {
                $purchaseErrors[] = (string) $event['error'];
            }
        }
    }

    if ($cacheHit) {
        return 'De modal las de projectcache. Ververs nu slaat die laag over; deze aanroep niet.';
    }
    if ($normalizes === []) {
        return 'WerkordersLines leverde deze artikelregel niet. De modal heeft geen andere tabel voor regels zonder werkorder.';
    }

    $parts = [];
    foreach ($finalizes as $finalize) {
        $purchase = is_array($finalize['purchase'] ?? null) ? $finalize['purchase'] : null;
        $label = (string) ($finalize['item_no'] ?? '') . '/' . (string) ($finalize['line_no'] ?? '');
        $numbers = (string) ($finalize['qty_ordered'] ?? '') . '/' . (string) ($finalize['qty_open'] ?? '') . '/' . (string) ($finalize['qty_received'] ?? '');
        if (is_array($purchase)) {
            $parts[] = $label . ' toont ' . $numbers . ' van PurchaseLines ' . (string) ($purchase['document_no'] ?? '') . '/' . (string) ($purchase['line_no'] ?? '') . '.';
            continue;
        }
        $parts[] = $label . ' toont ' . $numbers . ' uit WerkordersLines.Quantity. finalize liep, PurchaseLines is niet toegepast.';
    }
    if ($purchaseErrors !== []) {
        $parts[] = 'PurchaseLines-fout: ' . $purchaseErrors[0];
    } elseif ($purchaseRows === 0) {
        $parts[] = 'De PurchaseLines-queries gaven 0 rijen.';
    }

    return implode(' ', $parts);
}

function elpis_debug_project_line(string $company, string $projectNo, string $itemNo, bool $refresh): array
{
    $company = trim($company);
    $projectNo = trim($projectNo);
    $itemNo = strtoupper(trim($itemNo));
    $cacheBefore = elpis_debug_cache_layers($company, $projectNo);
    $GLOBALS['ELPIS_LINE_TRACE'] = [];
    $GLOBALS['ELPIS_TRACE_ITEM'] = $itemNo;
    $GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] = [];
    $error = '';
    $lines = [];
    try {
        $lines = elpis_load_project_modal_lines($company, $projectNo, $refresh);
    } catch (Throwable $caught) {
        $error = str_replace(["\n", "\r"], ' ', $caught->getMessage());
    }
    $trace = is_array($GLOBALS['ELPIS_LINE_TRACE'] ?? null) ? $GLOBALS['ELPIS_LINE_TRACE'] : [];
    unset($GLOBALS['ELPIS_LINE_TRACE'], $GLOBALS['ELPIS_TRACE_ITEM']);

    $shown = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        if ($itemNo !== '' && strtoupper(trim((string) ($line['item_no'] ?? ''))) !== $itemNo) {
            continue;
        }
        $payload = elpis_line_public_payload($line, $projectNo);
        $shown[] = [
            'item_no' => (string) ($line['item_no'] ?? ''),
            'line_no' => (int) ($line['line_no'] ?? 0),
            'job_task_no' => (string) ($line['job_task_no'] ?? ''),
            'work_order_no' => (string) ($line['work_order_no'] ?? ''),
            'purchase_order_no' => (string) ($line['purchase_order_no'] ?? ''),
            'purchase_order_line_no' => (int) ($line['purchase_order_line_no'] ?? 0),
            'quantity' => (float) ($line['quantity'] ?? 0),
            'qty_ordered' => (float) ($payload['qty_ordered'] ?? 0),
            'qty_open' => (float) ($payload['qty_open'] ?? 0),
            'qty_received' => (float) ($payload['qty_received'] ?? 0),
            'display' => [
                'ordered' => (string) ($payload['qty_ordered_display'] ?? ''),
                'open' => (string) ($payload['qty_open_display'] ?? ''),
                'received' => (string) ($payload['qty_received_display'] ?? ''),
                'expected_receipt' => (string) ($payload['expected_receipt'] ?? ''),
            ],
            'receipt_state' => (string) ($payload['receipt_state'] ?? ''),
            'purchase_outstanding_qty' => $line['purchase_outstanding_qty'] ?? null,
        ];
    }

    $fetches = [];
    $steps = [];
    foreach ($trace as $event) {
        if (!is_array($event)) {
            continue;
        }
        if (($event['step'] ?? '') === 'odata') {
            $fetches[] = $event;
            continue;
        }
        $steps[] = $event;
    }

    return [
        'project' => $projectNo,
        'company' => $company,
        'debug_line' => $itemNo,
        'refresh' => $refresh,
        'code_version' => ELPI_PROJECT_CACHE_VERSION,
        'error' => $error,
        'display_path' => [
            'page' => 'index.php',
            'endpoint' => 'project_lines.php',
            'render' => 'renderLines schrijft qty_ordered_display, qty_open_display en qty_received_display uit die JSON',
            'function' => 'elpis_load_project_modal_lines',
            'separate_table' => false,
            'client_calculation' => false,
        ],
        'cache_before' => $cacheBefore,
        'cache_after' => elpis_debug_cache_layers($company, $projectNo),
        'fetches' => $fetches,
        'purchase_lines_errors' => is_array($GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] ?? null) ? $GLOBALS['ELPIS_PURCHASE_LINE_ERRORS'] : [],
        'steps' => $steps,
        'lines' => $shown,
        'conclusion' => elpis_debug_conclusion($shown, $trace),
    ];
}

require_once __DIR__ . '/elpis_mail.php';
