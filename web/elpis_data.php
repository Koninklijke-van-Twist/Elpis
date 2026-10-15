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

const ELPI_PROJECT_MANAGERS_TTL = ELPI_CACHE_TTL;

const ELPI_MANAGER_ALL = '*';

const ELPI_PLANNING_LINES_CHUNK_SIZE = 12;

/** BC-brontypes op Reservation Entry (Database::). */
const ELPI_ITEM_LEDGER_SOURCE_TYPE = 32;
const ELPI_PURCHASE_LINE_SOURCE_TYPE = 39;
const ELPI_JOB_PLANNING_SOURCE_TYPE = 1003;

/**
 * Gepubliceerde entity sets.
 * Reservation_Entries: standaardpagina Reservation Entries, zelfde underscore-stijl als Job_Planning_Lines (Horae).
 * PurchaseOrderLines: bevestigd in Ratatoskr. ItemList: voorbeelden in Mímir (Inventory).
 * Planningsregels zelf blijven AppProjectInkoopPlanningsRegel (Job Planning Line-pagina van deze app).
 */
const ELPI_RESERVATION_ENTRY_ENTITY = 'Reservation_Entries';
const ELPI_PURCHASE_ORDER_LINE_ENTITY = 'PurchaseOrderLines';
const ELPI_ITEM_LIST_ENTITY = 'ItemList';

/**
 * Functies
 */

function elpis_escape_odata_string(string $value): string
{
    return str_replace("'", "''", trim($value));
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
    if (odata_mimir_enabled()) {
        $ttlForMimir = $ttl === 0 ? 3600 : $ttl;
        return odata_mimir_or_direct(
            static function () use ($company, $entitySet, $query, $ttlForMimir): array {
                return odata_mimir_query_impl($company, $entitySet, $query, $ttlForMimir);
            },
            static function () use ($company, $entitySet, $query, $ttl): array {
                return elpis_fetch_rows_direct($company, $entitySet, $query, $ttl);
            }
        );
    }

    return elpis_fetch_rows_direct($company, $entitySet, $query, $ttl);
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

    // Bij een open Mímir-circuit direct de oude OData-client, anders opnieuw Mímir in.
    if (odata_mimir_enabled() && function_exists('odata_get_all_direct')) {
        return odata_get_all_direct($url, $auth, $ttl);
    }

    return odata_get_all($url, $auth, $ttl);
}

function elpis_try_fetch_rows(string $company, string $entitySet, array $query, int $ttl = ELPI_CACHE_TTL): array
{
    try {
        return elpis_fetch_rows($company, $entitySet, $query, $ttl);
    } catch (Throwable $error) {
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

function elpis_line_still_expecting_receipt(array $line): bool
{
    if (!empty($line['completely_received'])) {
        return false;
    }

    $qtyOpen = (float) ($line['qty_open'] ?? 0);
    $qtyToOrder = (float) ($line['qty_to_order'] ?? 0);
    $qtyOrdered = (float) ($line['qty_ordered'] ?? 0);
    $qtyReceived = (float) ($line['qty_received'] ?? 0);

    return $qtyOpen > 0 || $qtyToOrder > 0 || ($qtyOrdered > 0 && $qtyReceived < $qtyOrdered);
}

function elpis_line_expected_receipt_display(array $line): string
{
    if (!elpis_line_still_expecting_receipt($line)) {
        return '';
    }

    $state = (string) ($line['receipt_state'] ?? '');
    $date = elpis_format_display_date((string) ($line['expected_receipt_date'] ?? ''));

    if ($state === 'on_stock') {
        return LOC('elpis.receipt.on_stock');
    }

    if ($state === 'partial') {
        if ($date !== '') {
            return LOC('elpis.receipt.partial', $date);
        }

        return LOC('elpis.receipt.partial_missing');
    }

    if ($state === 'missing') {
        return LOC('elpis.receipt.missing');
    }

    if ($date !== '') {
        return $date;
    }

    if ($state === 'date') {
        return LOC('elpis.receipt.missing');
    }

    return '';
}

function elpis_receipt_sort_value(array $line): string
{
    $state = (string) ($line['receipt_state'] ?? '');
    if ($state === 'on_stock') {
        return '0000-00-00';
    }
    if ($state === 'missing') {
        return '9999-99-99';
    }

    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($date !== '') {
        return substr($date, 0, 10);
    }

    return $state === 'partial' ? '9999-99-99' : '';
}

function elpis_normalize_planning_line_row(array $row): array
{
    $purchaseOrderNo = trim((string) ($row['LVS_Purchase_Order_No'] ?? ''));
    $quantity = (float) ($row['Quantity'] ?? 0);
    $outstanding = (float) ($row['LVS_Outstanding_Qty_Base'] ?? 0);
    $orderedQty = (float) ($row['LVS_Quantity_Order_UoM'] ?? 0);
    if ($orderedQty <= 0 && $purchaseOrderNo !== '') {
        $orderedQty = $quantity;
    }

    $hasPurchaseOrder = $purchaseOrderNo !== '';
    $qtyToOrder = $hasPurchaseOrder ? 0.0 : max($outstanding, $quantity);
    $qtyOrdered = $hasPurchaseOrder ? $orderedQty : 0.0;
    $qtyReceived = $hasPurchaseOrder ? max(0.0, $orderedQty - $outstanding) : 0.0;
    $qtyOpen = max(0.0, $qtyOrdered - $qtyReceived);

    return [
        'job_task_no' => trim((string) ($row['Job_Task_No'] ?? '')),
        'item_no' => trim((string) ($row['No'] ?? '')),
        'description' => trim((string) ($row['Description'] ?? '')),
        'qty_to_order' => $qtyToOrder,
        'qty_ordered' => $qtyOrdered,
        'qty_open' => $qtyOpen,
        'qty_received' => $qtyReceived,
        'purchase_order_no' => $purchaseOrderNo,
        'completely_received' => (bool) ($row['LVS_Completely_Received'] ?? false),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['KVT_Expected_Receipt_Date'] ?? '')),
        'item_kind' => elpis_is_project_item_no((string) ($row['No'] ?? '')) ? 'PI' : 'AO',
        'receipt_state' => '',
        'material_status' => strtoupper(trim((string) ($row['KVT_Status_Material'] ?? ''))),
        'material_status_label' => function_exists('elpis_material_status_label')
            ? elpis_material_status_label((string) ($row['KVT_Status_Material'] ?? ''))
            : '',
        'line_no' => (int) ($row['Line_No'] ?? 0),
    ];
}

function elpis_fetch_project_managers(string $company, int $ttl = ELPI_PROJECT_MANAGERS_TTL): array
{
    $rows = elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'Project_Manager',
        '$filter' => "Project_Manager ne ''",
        '$top' => '500',
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
        if ($normalized['no'] !== '') {
            $projects[] = $normalized;
        }
    }

    return $projects;
}

function elpis_fetch_projects_for_company(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    $rows = elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'No,Description,Project_Manager',
        '$filter' => "Project_Manager ne ''",
        '$orderby' => 'No desc',
        '$top' => '500',
    ], $ttl);

    return elpis_collect_projects_from_rows($rows);
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

function elpis_planning_line_select_fields(): string
{
    return 'Job_No,Job_Task_No,Line_No,No,Description,Quantity,LVS_Quantity_Order_UoM,LVS_Outstanding_Qty_Base,LVS_Purchase_Order_No,LVS_Completely_Received,KVT_Expected_Receipt_Date,KVT_Status_Material';
}

function elpis_collect_planning_line_row(array $row, array &$lines): void
{
    if (!is_array($row)) {
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

    $escaped = elpis_escape_odata_string($projectNo);
    $rows = elpis_try_fetch_rows($company, 'AppProjectInkoopPlanningsRegel', [
        '$filter' => "Job_No eq '" . $escaped . "' and Type eq 'Artikel'",
        '$select' => elpis_planning_line_select_fields(),
        '$orderby' => 'Job_Task_No asc,Line_No asc',
        '$top' => '500',
    ], $ttl);

    $lines = [];
    foreach ($rows as $row) {
        elpis_collect_planning_line_row($row, $lines);
    }

    return $lines;
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

    $filters = [];
    foreach ($projectNos as $projectNo) {
        $filters[] = "Job_No eq '" . elpis_escape_odata_string($projectNo) . "'";
    }

    $rows = elpis_try_fetch_rows($company, 'AppProjectInkoopPlanningsRegel', [
        '$filter' => '(' . implode(' or ', $filters) . ") and Type eq 'Artikel'",
        '$select' => elpis_planning_line_select_fields(),
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

    return $byProject;
}

function elpis_fetch_planning_lines_for_company(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    $rows = elpis_try_fetch_rows($company, 'AppProjectInkoopPlanningsRegel', [
        '$filter' => "Type eq 'Artikel'",
        '$select' => elpis_planning_line_select_fields(),
        '$orderby' => 'Job_No asc,Job_Task_No asc,Line_No asc',
    ], $ttl);

    $byProject = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $jobNo = trim((string) ($row['Job_No'] ?? ''));
        if ($jobNo === '') {
            continue;
        }

        if (!isset($byProject[$jobNo])) {
            $byProject[$jobNo] = [];
        }

        elpis_collect_planning_line_row($row, $byProject[$jobNo]);
    }

    return $byProject;
}

function elpis_fetch_planning_lines_by_projects(string $company, array $projectNos, int $ttl = ELPI_CACHE_TTL): array
{
    $projectNos = elpis_normalize_project_nos($projectNos);
    $allLines = elpis_fetch_planning_lines_for_company($company, $ttl);
    $byProject = [];

    foreach ($projectNos as $projectNo) {
        $byProject[$projectNo] = $allLines[$projectNo] ?? [];
    }

    return $byProject;
}

function elpis_collect_material_status_codes(array $linesByProject): array
{
    $codes = [];
    foreach ($linesByProject as $lines) {
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $code = strtoupper(trim((string) ($line['material_status'] ?? '')));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }
    }

    $list = array_keys($codes);
    usort($list, static function (string $left, string $right): int {
        return strcasecmp(elpis_material_status_label($left), elpis_material_status_label($right));
    });

    return $list;
}

function elpis_is_project_item_no(string $itemNo): bool
{
    return preg_match('/^PI(?:$|[\s\-_]|[0-9])/i', trim($itemNo)) === 1;
}

function elpis_item_kind(array $line): string
{
    $explicit = strtoupper(trim((string) ($line['item_kind'] ?? '')));
    if ($explicit === 'PI' || $explicit === 'AO') {
        return $explicit;
    }

    return elpis_is_project_item_no((string) ($line['item_no'] ?? '')) ? 'PI' : 'AO';
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
    return 'Entry_No,Positive,Item_No,Quantity_Base,Reservation_Status,Source_Type,Source_Subtype,Source_ID,Source_Ref_No,Expected_Receipt_Date,Quantity';
}

function elpis_reservation_demand_filter(): string
{
    return 'Source_Type eq ' . ELPI_JOB_PLANNING_SOURCE_TYPE;
}

function elpis_reservation_purchase_filter(): string
{
    return 'Source_Type eq ' . ELPI_PURCHASE_LINE_SOURCE_TYPE;
}

function elpis_reservation_stock_filter(): string
{
    return 'Source_Type eq ' . ELPI_ITEM_LEDGER_SOURCE_TYPE;
}

function elpis_purchase_line_select_fields(): string
{
    return 'Document_No,Line_No,No,Type,Expected_Receipt_Date,Promised_Receipt_Date,Planned_Receipt_Date,Outstanding_Quantity';
}

function elpis_purchase_line_open_filter(): string
{
    return 'Outstanding_Quantity gt 0';
}

function elpis_item_inventory_select_fields(): string
{
    return 'No,Inventory';
}

function elpis_receipt_filters_are_department_independent(string $filter): bool
{
    return preg_match('/afdeling|global_dimension|responsibility_center|department/i', $filter) !== 1;
}

function elpis_reservation_qty(array $entry): float
{
    $base = abs((float) ($entry['quantity_base'] ?? 0));
    if ($base > 0.00001) {
        return $base;
    }

    return abs((float) ($entry['quantity'] ?? 0));
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
        'source_subtype' => (int) ($row['Source_Subtype'] ?? 0),
        'source_id' => trim((string) ($row['Source_ID'] ?? '')),
        'source_ref_no' => (int) ($row['Source_Ref_No'] ?? 0),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['Expected_Receipt_Date'] ?? '')),
    ];
}

function elpis_normalize_purchase_order_line(array $row): array
{
    return [
        'document_no' => trim((string) ($row['Document_No'] ?? '')),
        'line_no' => (int) ($row['Line_No'] ?? 0),
        'item_no' => trim((string) ($row['No'] ?? '')),
        'type' => trim((string) ($row['Type'] ?? '')),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['Expected_Receipt_Date'] ?? '')),
        'promised_receipt_date' => elpis_normalize_bc_date((string) ($row['Promised_Receipt_Date'] ?? '')),
        'planned_receipt_date' => elpis_normalize_bc_date((string) ($row['Planned_Receipt_Date'] ?? '')),
        'outstanding_qty' => (float) ($row['Outstanding_Quantity'] ?? 0),
    ];
}

function elpis_purchase_line_best_date(array $line): string
{
    foreach (['expected_receipt_date', 'promised_receipt_date', 'planned_receipt_date'] as $key) {
        $date = elpis_normalize_bc_date((string) ($line[$key] ?? ''));
        if ($date !== '') {
            return substr($date, 0, 10);
        }
    }

    return '';
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

function elpis_reservation_is_demand(array $entry): bool
{
    if ((int) ($entry['source_type'] ?? 0) !== ELPI_JOB_PLANNING_SOURCE_TYPE) {
        return false;
    }

    return ($entry['positive'] ?? null) !== true;
}

function elpis_reservation_is_stock_supply(array $entry): bool
{
    if (($entry['positive'] ?? null) !== true) {
        return false;
    }

    return (int) ($entry['source_type'] ?? 0) === ELPI_ITEM_LEDGER_SOURCE_TYPE;
}

function elpis_reservation_is_purchase_supply(array $entry): bool
{
    if (($entry['positive'] ?? null) !== true) {
        return false;
    }

    return (int) ($entry['source_type'] ?? 0) === ELPI_PURCHASE_LINE_SOURCE_TYPE;
}

function elpis_fetch_reservation_entries(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    $select = elpis_reservation_select_fields();
    $entries = [];
    foreach ([
        elpis_reservation_demand_filter(),
        elpis_reservation_purchase_filter(),
        elpis_reservation_stock_filter(),
    ] as $filter) {
        $rows = elpis_try_fetch_rows($company, ELPI_RESERVATION_ENTRY_ENTITY, [
            '$select' => $select,
            '$filter' => $filter,
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = elpis_normalize_reservation_entry($row);
            if ($normalized['entry_no'] > 0) {
                $entries[] = $normalized;
            }
        }
    }

    return $entries;
}

function elpis_fetch_open_purchase_order_lines(string $company, int $ttl = ELPI_CACHE_TTL): array
{
    $rows = elpis_try_fetch_rows($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [
        '$select' => elpis_purchase_line_select_fields(),
        '$filter' => elpis_purchase_line_open_filter(),
    ], $ttl);

    $lines = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $normalized = elpis_normalize_purchase_order_line($row);
        if ($normalized['document_no'] !== '') {
            $lines[] = $normalized;
        }
    }

    return $lines;
}

function elpis_fetch_item_inventory(string $company, array $itemNos, int $ttl = ELPI_CACHE_TTL): array
{
    $unique = [];
    foreach ($itemNos as $itemNo) {
        $itemNo = trim((string) $itemNo);
        if ($itemNo !== '') {
            $unique[$itemNo] = true;
        }
    }
    $itemNos = array_keys($unique);
    sort($itemNos, SORT_NATURAL | SORT_FLAG_CASE);
    if ($itemNos === []) {
        return [];
    }

    $map = [];
    foreach (array_chunk($itemNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $filters = [];
        foreach ($chunk as $itemNo) {
            $filters[] = "No eq '" . elpis_escape_odata_string($itemNo) . "'";
        }
        $rows = elpis_try_fetch_rows($company, ELPI_ITEM_LIST_ENTITY, [
            '$select' => elpis_item_inventory_select_fields(),
            '$filter' => implode(' or ', $filters),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $no = trim((string) ($row['No'] ?? ''));
            if ($no === '') {
                continue;
            }
            $map[$no] = (float) ($row['Inventory'] ?? 0);
        }
    }

    return $map;
}

function elpis_collect_stock_item_nos(array $linesByProject): array
{
    $items = [];
    foreach ($linesByProject as $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            if (!is_array($line) || !elpis_line_still_expecting_receipt($line)) {
                continue;
            }
            if (elpis_item_kind($line) !== 'AO') {
                continue;
            }
            $itemNo = trim((string) ($line['item_no'] ?? ''));
            if ($itemNo !== '') {
                $items[$itemNo] = true;
            }
        }
    }

    return array_keys($items);
}

function elpis_load_receipt_context(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL): array
{
    $company = trim($company);
    if ($company === '') {
        return [
            'reservations' => [],
            'purchase_lines' => [],
            'inventory' => [],
        ];
    }

    return [
        'reservations' => elpis_fetch_reservation_entries($company, $ttl),
        'purchase_lines' => elpis_fetch_open_purchase_order_lines($company, $ttl),
        'inventory' => elpis_fetch_item_inventory($company, elpis_collect_stock_item_nos($linesByProject), $ttl),
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
    $demandByJobLine = [];
    $stockReservedByItem = [];

    foreach ($reservations as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (!isset($entry['entry_no']) && isset($entry['Entry_No'])) {
            $entry = elpis_normalize_reservation_entry($entry);
        }
        $entryNo = (int) ($entry['entry_no'] ?? 0);
        if ($entryNo <= 0) {
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
                $lineNo = (int) ($entry['source_ref_no'] ?? 0);
                if ($jobNo !== '' && $lineNo > 0) {
                    $demandByJobLine[$jobNo . '|' . $lineNo][] = $entry;
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

    return [$byEntry, $demandByJobLine, $stockReservedByItem];
}

function elpis_apply_receipt_expectations(array $linesByProject, array $reservations, array $purchaseLines, array $inventoryByItem): array
{
    [$byDocLine, $byDocItem] = elpis_index_purchase_lines($purchaseLines);
    [$byEntry, $demandByJobLine, $stockReservedByItem] = elpis_prepare_reservation_indexes($reservations);

    $inventoryKnown = [];
    foreach ($inventoryByItem as $itemNo => $qty) {
        $key = strtoupper(trim((string) $itemNo));
        if ($key === '') {
            continue;
        }
        $inventoryKnown[$key] = (float) $qty;
    }

    $free = [];
    foreach ($inventoryKnown as $itemKey => $qty) {
        $free[$itemKey] = max(0.0, $qty - (float) ($stockReservedByItem[$itemKey] ?? 0));
    }

    $order = [];
    foreach ($linesByProject as $projectNo => $lines) {
        if (!is_array($lines)) {
            continue;
        }
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $order[] = [
                'project' => (string) $projectNo,
                'index' => $index,
                'task' => (string) ($line['job_task_no'] ?? ''),
                'line_no' => (int) ($line['line_no'] ?? 0),
            ];
        }
    }

    usort($order, static function (array $left, array $right): int {
        $projectCompare = strnatcasecmp($left['project'], $right['project']);
        if ($projectCompare !== 0) {
            return $projectCompare;
        }
        $taskCompare = strnatcasecmp($left['task'], $right['task']);
        if ($taskCompare !== 0) {
            return $taskCompare;
        }
        if ($left['line_no'] !== $right['line_no']) {
            return $left['line_no'] <=> $right['line_no'];
        }

        return $left['index'] <=> $right['index'];
    });

    foreach ($order as $ref) {
        $line = $linesByProject[$ref['project']][$ref['index']];
        $line['item_kind'] = elpis_item_kind($line);

        if (!elpis_line_still_expecting_receipt($line)) {
            $line['receipt_state'] = 'received';
            $linesByProject[$ref['project']][$ref['index']] = $line;
            continue;
        }

        if ($line['item_kind'] === 'PI') {
            $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
            $line['expected_receipt_date'] = $date === '' ? '' : substr($date, 0, 10);
            $line['receipt_state'] = $line['expected_receipt_date'] === '' ? 'missing' : 'date';
            $linesByProject[$ref['project']][$ref['index']] = $line;
            continue;
        }

        $needed = elpis_line_qty_still_needed($line);
        $bcDate = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
        $bcDate = $bcDate === '' ? '' : substr($bcDate, 0, 10);
        $jobKey = strtoupper(trim($ref['project'])) . '|' . (int) ($line['line_no'] ?? 0);
        $itemKey = strtoupper(trim((string) ($line['item_no'] ?? '')));
        $stockQty = 0.0;
        $poDates = [];

        if ((int) ($line['line_no'] ?? 0) > 0) {
            foreach ($demandByJobLine[$jobKey] ?? [] as $demand) {
                $entryNo = (int) ($demand['entry_no'] ?? 0);
                foreach ($byEntry[$entryNo] ?? [] as $supply) {
                    if (elpis_reservation_is_stock_supply($supply)) {
                        $stockQty += elpis_reservation_qty($supply);
                        continue;
                    }
                    if (elpis_reservation_is_purchase_supply($supply)) {
                        $date = elpis_date_for_purchase_supply($supply, $byDocLine, $byDocItem);
                        if ($date !== '') {
                            $poDates[] = $date;
                        }
                    }
                }
            }
        }

        $purchaseOrderNo = trim((string) ($line['purchase_order_no'] ?? ''));
        if ($purchaseOrderNo !== '') {
            $poDates = array_merge(
                $poDates,
                elpis_dates_for_document_item($purchaseOrderNo, (string) ($line['item_no'] ?? ''), $byDocItem)
            );
        }

        $fromFree = 0.0;
        if ($itemKey !== '' && isset($inventoryKnown[$itemKey]) && $needed > $stockQty) {
            $fromFree = min((float) ($free[$itemKey] ?? 0), $needed - $stockQty);
            $free[$itemKey] = max(0.0, (float) ($free[$itemKey] ?? 0) - $fromFree);
        }

        $coveredByStock = $stockQty + $fromFree;
        $poDate = elpis_latest_date($poDates);
        $enoughOnStock = $needed <= 0.00001 || $coveredByStock + 0.00001 >= $needed;

        if ($enoughOnStock) {
            $line['receipt_state'] = 'on_stock';
            $line['expected_receipt_date'] = '';
        } elseif ($poDate !== '') {
            $line['receipt_state'] = $coveredByStock > 0.00001 ? 'partial' : 'date';
            $line['expected_receipt_date'] = $poDate;
        } elseif ($bcDate !== '') {
            $line['receipt_state'] = $coveredByStock > 0.00001 ? 'partial' : 'date';
            $line['expected_receipt_date'] = $bcDate;
        } else {
            $line['receipt_state'] = 'missing';
            $line['expected_receipt_date'] = '';
        }

        $linesByProject[$ref['project']][$ref['index']] = $line;
    }

    return $linesByProject;
}

function elpis_enrich_planning_lines_with_receipts(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL): array
{
    $context = elpis_load_receipt_context($company, $linesByProject, $ttl);

    return elpis_apply_receipt_expectations(
        $linesByProject,
        $context['reservations'],
        $context['purchase_lines'],
        $context['inventory']
    );
}

function elpis_project_execution_summary(array $lines): array
{
    $latest = '';
    $hasOpen = false;
    $hasMissing = false;

    foreach ($lines as $line) {
        if (!is_array($line) || !elpis_line_still_expecting_receipt($line)) {
            continue;
        }

        $hasOpen = true;
        $state = (string) ($line['receipt_state'] ?? '');
        if ($state === 'on_stock' || $state === 'received') {
            continue;
        }

        if ($state === 'missing') {
            $hasMissing = true;
            continue;
        }

        $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
        if ($date === '') {
            if ($state !== '') {
                $hasMissing = true;
            }
            continue;
        }

        $date = substr($date, 0, 10);
        if ($latest === '' || $date > $latest) {
            $latest = $date;
        }
    }

    if (!$hasOpen) {
        return ['state' => 'none', 'date' => ''];
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
    ];

    $companies = elpis_companies_for_page($ttl);
    $summary['companies'] = count($companies);

    foreach ($companies as $company) {
        auth_set_current_company_context($company, $ttl);
        $managers = elpis_fetch_project_managers($company, $ttl);
        $summary['managers'] += count($managers);

        $projects = elpis_fetch_projects_for_company($company, $ttl);
        $summary['projects'] += count($projects);

        $linesByProject = elpis_fetch_planning_lines_for_company($company, $ttl);
        $summary['planning_line_projects'] += count($linesByProject);

        $receiptContext = elpis_load_receipt_context($company, $linesByProject, $ttl);
        $summary['reservation_entries'] += count($receiptContext['reservations']);
        $summary['purchase_order_lines'] += count($receiptContext['purchase_lines']);
        $summary['item_inventory'] += count($receiptContext['inventory']);
    }

    return $summary;
}

function elpis_line_search_blob(array $line, string $projectNo = ''): string
{
    $materialStatus = strtoupper(trim((string) ($line['material_status'] ?? '')));
    $parts = [
        $projectNo,
        (string) ($line['job_task_no'] ?? ''),
        (string) ($line['item_no'] ?? ''),
        (string) ($line['description'] ?? ''),
        $materialStatus,
        (string) ($line['item_kind'] ?? ''),
        elpis_line_expected_receipt_display($line),
    ];

    if ($materialStatus !== '' && function_exists('elpis_material_status_label')) {
        $parts[] = elpis_material_status_label($materialStatus);
    }

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
