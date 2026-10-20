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
const ELPI_PROJECT_CACHE_VERSION = 3;

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
/** Eigen status in het regelfilter, naast KVT_Status_Material. */
const ELPI_STATUS_LATE = 'LATE';

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
                return odata_mimir_query_impl($company, $entitySet, $query, $ttlForMimir);
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

function elpis_try_fetch_rows(string $company, string $entitySet, array $query, int $ttl = ELPI_CACHE_TTL): array
{
    // Een BC-fout, zoals een ongeldige enum, wordt een lege set. De pagina laadt dan
    // door alsof er geen rijen waren. Alleen elpis.timing cache=error bevat de melding.
    $startedAt = microtime(true);
    try {
        return elpis_fetch_rows($company, $entitySet, $query, $ttl);
    } catch (Throwable $error) {
        elpis_log_timing($company, $entitySet, $query, 0, $startedAt, 'error');
        error_log('elpis.timing error entity=' . $entitySet . ' message=' . str_replace(["\n", "\r"], ' ', $error->getMessage()));

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
    return strtoupper(trim((string) ($line['material_status'] ?? ''))) === 'N';
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

function elpis_line_is_received(array $line): bool
{
    if (!empty($line['completely_received']) || !empty($line['completely_picked'])) {
        return true;
    }

    if (elpis_line_purchase_outstanding_closed($line) || elpis_line_inkoop_outstanding_closed($line)) {
        return true;
    }

    $quantity = (float) ($line['quantity'] ?? 0);
    $picked = (float) ($line['kvt_qty_picked'] ?? 0);
    if ($quantity > 0.00001 && $picked + 0.00001 >= $quantity) {
        return true;
    }

    $ledgerQty = elpis_line_ledger_received_qty($line);
    if ($quantity > 0.00001 && $ledgerQty !== null && $ledgerQty + 0.00001 >= $quantity) {
        return true;
    }

    $status = strtoupper(trim((string) ($line['material_status'] ?? '')));
    if (in_array($status, ['G', 'B', 'A', 'C'], true)) {
        return true;
    }

    $remaining = elpis_line_remaining_qty($line);

    return $remaining !== null && $remaining <= 0.00001;
}

function elpis_refresh_receipt_state(array $line): array
{
    if (elpis_line_is_received($line)) {
        $line['receipt_state'] = 'received';
    } elseif (elpis_line_is_not_required($line)) {
        $line['receipt_state'] = 'not_required';
    } elseif (elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? '')) === '') {
        $line['receipt_state'] = 'unconfirmed';
    } elseif (elpis_line_is_late($line)) {
        $line['receipt_state'] = 'late';
    } else {
        $line['receipt_state'] = 'date';
    }

    return $line;
}

function elpis_line_is_late(array $line, ?string $today = null): bool
{
    if (elpis_line_is_received($line) || elpis_line_is_not_required($line)) {
        return false;
    }

    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($date === '') {
        return false;
    }

    $today = $today ?? elpis_today();

    return substr($date, 0, 10) < $today;
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

function elpis_line_expected_receipt_display(array $line): string
{
    $line = elpis_refresh_receipt_state($line);
    $state = (string) ($line['receipt_state'] ?? '');
    if ($state === 'received' || ($state === '' && elpis_line_is_received($line))) {
        $date = elpis_format_display_date((string) ($line['received_date'] ?? ''));
        if ($date !== '') {
            return LOC('elpis.receipt.already', $date);
        }

        return LOC('elpis.receipt.already_nodate');
    }

    if ($state === 'not_required') {
        return LOC('elpis.material_status.N');
    }

    if ($state === 'late' || ($state === '' && elpis_line_is_late($line))) {
        return LOC('elpis.receipt.late');
    }

    $date = elpis_format_display_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($date !== '') {
        return $date;
    }

    if ($state === 'unconfirmed' || $state === 'missing') {
        return LOC('elpis.receipt.missing');
    }

    return '';
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

function elpis_line_quantities(float $quantity, string $purchaseOrderNo, float $orderedQty, float $outstanding): array
{
    $hasPurchaseOrder = $purchaseOrderNo !== '';
    if ($orderedQty <= 0 && $hasPurchaseOrder) {
        $orderedQty = $quantity;
    }

    if ($hasPurchaseOrder) {
        $qtyReceived = max(0.0, $orderedQty - $outstanding);

        return [
            'qty_to_order' => 0.0,
            'qty_ordered' => $orderedQty,
            'qty_open' => max(0.0, $orderedQty - $qtyReceived),
            'qty_received' => $qtyReceived,
        ];
    }

    return [
        'qty_to_order' => max($outstanding, $quantity),
        'qty_ordered' => 0.0,
        'qty_open' => 0.0,
        'qty_received' => 0.0,
    ];
}

function elpis_normalize_planning_line_row(array $row): array
{
    $purchaseOrderNo = trim((string) ($row['LVS_Purchase_Order_No'] ?? ''));
    $quantity = (float) ($row['Quantity'] ?? 0);
    $remainingQty = array_key_exists('Remaining_Qty', $row) ? (float) $row['Remaining_Qty'] : null;
    $reservedQuantity = array_key_exists('Reserved_Quantity', $row) ? (float) $row['Reserved_Quantity'] : null;
    $quantities = elpis_line_quantities(
        $quantity,
        $purchaseOrderNo,
        (float) ($row['LVS_Quantity_Order_UoM'] ?? 0),
        (float) ($row['LVS_Outstanding_Qty_Base'] ?? 0)
    );
    $materialStatus = strtoupper(trim((string) ($row['KVT_Status_Material'] ?? '')));
    $planning = elpis_planning_date_from_row($row);

    return array_merge($quantities, [
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
        'planning_date' => $planning['date'],
        'planning_date_field' => $planning['field'],
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['KVT_Expected_Receipt_Date'] ?? '')),
        'expected_receipt_source' => '',
        'received_date' => '',
        'free_stock' => null,
        'receipt_state' => '',
        'covered_by_stock' => 0.0,
        'material_status' => $materialStatus,
        'material_status_label' => $materialStatus !== '' && function_exists('elpis_material_status_label')
            ? elpis_material_status_label($materialStatus)
            : '',
        'line_no' => (int) ($row['Line_No'] ?? 0),
    ]);
}

function elpis_planning_date_from_row(array $row): array
{
    $planning = elpis_normalize_bc_date((string) ($row['Planning_Date'] ?? ''));
    if ($planning !== '') {
        return ['date' => substr($planning, 0, 10), 'field' => 'Planning_Date'];
    }

    $plannedDelivery = elpis_normalize_bc_date((string) ($row['Planned_Delivery_Date'] ?? ''));
    if ($plannedDelivery !== '') {
        return ['date' => substr($plannedDelivery, 0, 10), 'field' => 'Planned_Delivery_Date'];
    }

    return ['date' => '', 'field' => 'Planning_Date'];
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

function elpis_planning_line_type_filter(): string
{
    return "Type eq '" . ELPI_ITEM_LINE_TYPE . "'";
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
    return 'Job_No,Job_Task_No,Line_No,Job_Contract_Entry_No,Type,No,Description,Quantity,Remaining_Qty,Reserved_Quantity,KVT_Expected_Receipt_Date,Planning_Date,Planned_Delivery_Date,LVS_Work_Order_No,LVS_Purchase_Order_No,LVS_Purchase_Order_Line_No,KVT_Status_Material,KVT_Qty_Picked,KVT_Completely_Picked';
}

function elpis_inkoop_planning_select_fields(): string
{
    return 'Job_No,Job_Task_No,Line_No,Job_Contract_Entry_No,No,KVT_Expected_Receipt_Date,Planning_Date,Planned_Delivery_Date,Reserved_Quantity,LVS_Purchase_Order_No,LVS_Purchase_Order_Line_No,LVS_Work_Order_No,LVS_Quantity_Order_UoM,LVS_Outstanding_Qty_Base,LVS_Completely_Received,KVT_Status_Material,KVT_Qty_Picked,KVT_Completely_Picked';
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
            if (is_array($row)) {
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
 * Job|taak|regelnr koppelt een werkorderregel aan de inkoopregel.
 * Een regel zonder werkorder heeft vaak een ander regelnr; die hangt aan
 * hetzelfde project, artikel en lege werkorder, en dáár staat document+regel.
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
            $linesByProject[$projectNo][$index] = elpis_merge_inkoop_planning_line($line, $row);
            $itemNo = trim((string) ($row['No'] ?? $line['item_no'] ?? ''));
            if ($itemNo !== '') {
                $consumedSupply[elpis_inkoop_supply_key((string) ($row['Job_No'] ?? $projectNo), $itemNo, (string) ($row['LVS_Work_Order_No'] ?? ''))] = true;
            }
        }

        $pending = [];
        foreach ($linesByProject[$projectNo] as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            if (trim((string) ($line['purchase_order_no'] ?? '')) !== '' && (int) ($line['purchase_order_line_no'] ?? 0) > 0) {
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

function elpis_merge_inkoop_planning_line(array $line, array $inkoopRow): array
{
    $purchaseOrderNo = trim((string) ($inkoopRow['LVS_Purchase_Order_No'] ?? ''));
    $quantity = (float) ($line['quantity'] ?? 0);
    if ($quantity <= 0.00001) {
        $quantity = (float) ($inkoopRow['Quantity'] ?? 0);
    }
    $quantities = elpis_line_quantities(
        $quantity,
        $purchaseOrderNo,
        (float) ($inkoopRow['LVS_Quantity_Order_UoM'] ?? 0),
        (float) ($inkoopRow['LVS_Outstanding_Qty_Base'] ?? 0)
    );
    if ($purchaseOrderNo !== '') {
        $line = array_merge($line, $quantities);
        $line['purchase_order_no'] = $purchaseOrderNo;
        $line['quantity'] = $quantity;
    }

    $purchaseOrderLineNo = (int) ($inkoopRow['LVS_Purchase_Order_Line_No'] ?? 0);
    if ($purchaseOrderLineNo > 0 && (int) ($line['purchase_order_line_no'] ?? 0) <= 0) {
        $line['purchase_order_line_no'] = $purchaseOrderLineNo;
    }

    $workOrderNo = trim((string) ($inkoopRow['LVS_Work_Order_No'] ?? ''));
    if ($workOrderNo !== '' && trim((string) ($line['work_order_no'] ?? '')) === '') {
        $line['work_order_no'] = $workOrderNo;
    }

    if (trim((string) ($line['planning_date'] ?? '')) === '') {
        $planning = elpis_planning_date_from_row($inkoopRow);
        $line['planning_date'] = $planning['date'];
        $line['planning_date_field'] = $planning['field'];
    }

    $picked = (float) ($inkoopRow['KVT_Qty_Picked'] ?? 0);
    if ($picked > (float) ($line['kvt_qty_picked'] ?? 0)) {
        $line['kvt_qty_picked'] = $picked;
    }
    $line['completely_picked'] = !empty($line['completely_picked']) || elpis_bc_bool($inkoopRow['KVT_Completely_Picked'] ?? false);

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

    $materialStatus = strtoupper(trim((string) ($line['material_status'] ?? '')));
    if ($materialStatus === '') {
        $materialStatus = strtoupper(trim((string) ($inkoopRow['KVT_Status_Material'] ?? '')));
        $line['material_status'] = $materialStatus;
        $line['material_status_label'] = $materialStatus !== '' && function_exists('elpis_material_status_label')
            ? elpis_material_status_label($materialStatus)
            : '';
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
    return 'Entry_No,Positive,Item_No,Quantity_Base,Reservation_Status,Source_Type,Source_Subtype,Source_ID,Source_Ref_No,Expected_Receipt_Date,Quantity';
}

function elpis_reservation_status_filter(): string
{
    return "Reservation_Status eq '" . ELPI_RESERVATION_STATUS_OPTION . "'";
}

function elpis_reservation_status_matches(string $status): bool
{
    $status = trim($status);

    return $status === ELPI_RESERVATION_STATUS_OPTION || $status === 'Reservering';
}

function elpis_purchase_line_select_fields(): string
{
    return 'Document_No,Line_No,No,Document_Type,Expected_Receipt_Date,Promised_Receipt_Date,Planned_Receipt_Date,LVS_Completely_Received,Outstanding_Quantity';
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
    return 'No,InventoryField';
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
        'document_no' => trim((string) ($row['Document_No'] ?? '')),
        'line_no' => (int) ($row['Line_No'] ?? 0),
        'item_no' => trim((string) ($row['No'] ?? '')),
        'type' => trim((string) ($row['Type'] ?? '')),
        'expected_receipt_date' => elpis_normalize_bc_date((string) ($row['Expected_Receipt_Date'] ?? '')),
        'promised_receipt_date' => elpis_normalize_bc_date((string) ($row['Promised_Receipt_Date'] ?? '')),
        'planned_receipt_date' => elpis_normalize_bc_date((string) ($row['Planned_Receipt_Date'] ?? '')),
        'outstanding_qty' => array_key_exists('Outstanding_Quantity', $row) ? (float) $row['Outstanding_Quantity'] : null,
        'completely_received' => elpis_bc_bool($row['LVS_Completely_Received'] ?? false),
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
    $seen = [];
    foreach (array_chunk($documentNos, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_PURCHASE_ORDER_LINE_ENTITY, [
            '$select' => elpis_purchase_line_select_fields(),
            '$filter' => elpis_purchase_documents_filter($chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = elpis_normalize_purchase_order_line($row);
            if ($normalized['document_no'] === '') {
                continue;
            }
            $key = strtoupper($normalized['document_no']) . '|' . $normalized['line_no'];
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

function elpis_fetch_item_inventory(string $company, array $itemNos, int $ttl = ELPI_CACHE_TTL, ?callable $onChunk = null): array
{
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
            if (!array_key_exists('InventoryField', $row) || $row['InventoryField'] === '' || $row['InventoryField'] === null) {
                continue;
            }
            $map[$no] = (float) $row['InventoryField'];
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

    return elpis_sort_odata_values(array_keys($documents));
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
            '$select' => 'No,Description',
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
                'description' => trim((string) ($row['Description'] ?? '')),
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
    $reservations = elpis_fetch_item_stock_reservations($company, $itemNos, $ttl);
    $emit('reservations', 'done');

    $emit('purchase', 'start');
    $purchaseLines = elpis_fetch_open_purchase_order_lines_for_documents(
        $company,
        elpis_collect_purchase_document_nos($linesByProject),
        $ttl
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
        }
    );
    $emit('inventory', 'done', ['done' => $inventoryChunks, 'total' => $inventoryChunks]);

    $emit('receipts', 'start');
    $receipts = elpis_fetch_item_ledger_receipts($company, $projectNos, $ttl);
    $emit('receipts', 'done');

    $workOrders = elpis_fetch_work_orders($company, elpis_collect_work_order_nos($linesByProject), $ttl);
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
        'receipts' => $receipts,
        'work_orders' => $workOrders,
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

function elpis_apply_receipt_expectations(array $linesByProject, array $reservations, array $purchaseLines, array $inventoryByItem, array $extras = []): array
{
    [$byDocLine, $byDocItem] = elpis_index_purchase_lines($purchaseLines);
    $receiptRows = is_array($extras['receipts'] ?? null) ? $extras['receipts'] : [];
    $postingDates = elpis_receipt_posting_index($receiptRows);
    $receiptQty = elpis_receipt_quantity_index($receiptRows);
    $workOrders = is_array($extras['work_orders'] ?? null) ? $extras['work_orders'] : [];

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
                $reservedOwn
            );
        }
        $linesByProject[$projectNo] = elpis_apply_ledger_receipt_quantities(
            $projectKey,
            $linesByProject[$projectNo],
            $receiptQty
        );
    }

    return $linesByProject;
}

function elpis_match_purchase_line(array $line, array $byDocLine, array $byDocItem): ?array
{
    $documentNo = strtoupper(trim((string) ($line['purchase_order_no'] ?? '')));
    if ($documentNo === '') {
        return null;
    }

    $itemNo = strtoupper(trim((string) ($line['item_no'] ?? '')));
    $purchaseLineNo = (int) ($line['purchase_order_line_no'] ?? 0);
    if ($purchaseLineNo > 0 && isset($byDocLine[$documentNo . '|' . $purchaseLineNo]) && is_array($byDocLine[$documentNo . '|' . $purchaseLineNo])) {
        $candidate = $byDocLine[$documentNo . '|' . $purchaseLineNo];
        $candidateItem = strtoupper(trim((string) ($candidate['item_no'] ?? '')));
        if ($candidateItem === '' || $itemNo === '' || $candidateItem === $itemNo) {
            return $candidate;
        }
    }

    if ($itemNo === '') {
        return null;
    }

    $candidates = $byDocItem[$documentNo . '|' . $itemNo] ?? [];
    if (count($candidates) === 1 && is_array($candidates[0])) {
        return $candidates[0];
    }

    return null;
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
            $lines[$index] = elpis_refresh_receipt_state($lines[$index]);
        }
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
    array $reservedOwn
): array {
    $workOrderNo = trim((string) ($line['work_order_no'] ?? ''));
    if ($workOrderNo !== '' && isset($workOrders[strtoupper($workOrderNo)]) && is_array($workOrders[strtoupper($workOrderNo)])) {
        $line['work_order_no'] = (string) ($workOrders[strtoupper($workOrderNo)]['no'] ?? $workOrderNo);
        $line['work_order_description'] = (string) ($workOrders[strtoupper($workOrderNo)]['description'] ?? '');
    }

    // Ook bij een gevulde KVT-datum: die datum blijft de verwachte ontvangst,
    // maar een gesloten inkoopregel mag daar niet meer 'Te laat' van maken.
    $purchase = elpis_match_purchase_line($line, $byDocLine, $byDocItem);
    if (is_array($purchase)) {
        if (!empty($purchase['completely_received'])) {
            $line['completely_received'] = true;
        }
        if (array_key_exists('outstanding_qty', $purchase) && $purchase['outstanding_qty'] !== null && $purchase['outstanding_qty'] !== '') {
            $line['purchase_outstanding_qty'] = (float) $purchase['outstanding_qty'];
        }
    }

    $kvtDate = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    if ($kvtDate !== '') {
        $line['expected_receipt_date'] = substr($kvtDate, 0, 10);
        $line['expected_receipt_source'] = 'WerkordersLines -> KVT_Expected_Receipt_Date';
    } elseif (is_array($purchase)) {
        foreach ([
            'expected_receipt_date' => 'Expected_Receipt_Date',
            'promised_receipt_date' => 'Promised_Receipt_Date',
            'planned_receipt_date' => 'Planned_Receipt_Date',
        ] as $key => $field) {
            $date = elpis_normalize_bc_date((string) ($purchase[$key] ?? ''));
            if ($date === '') {
                continue;
            }
            $line['expected_receipt_date'] = substr($date, 0, 10);
            $line['expected_receipt_source'] = 'PurchaseLines -> ' . $field;
            break;
        }
    }

    $itemKey = strtoupper(trim((string) ($line['item_no'] ?? '')));
    $postingKey = elpis_line_receipt_group_key($projectKey, $line);
    if ($postingKey !== null && isset($postingDates[$postingKey])) {
        $line['received_date'] = $postingDates[$postingKey];
    }

    $line = elpis_refresh_receipt_state($line);

    if ($itemKey !== '' && array_key_exists($itemKey, $inventoryKnown)) {
        $elsewhere = (float) ($reservedTotal[$itemKey] ?? 0) - (float) ($reservedOwn[$itemKey . '|' . $projectKey] ?? 0);
        $line['reserved_elsewhere'] = max(0.0, $elsewhere);
        $line['free_stock'] = (float) $inventoryKnown[$itemKey] - $line['reserved_elsewhere'];
    }

    return $line;
}

function elpis_enrich_planning_lines_with_receipts(string $company, array $linesByProject, int $ttl = ELPI_CACHE_TTL, ?callable $onInventoryChunk = null, ?callable $onPhase = null): array
{
    $context = elpis_load_receipt_context($company, $linesByProject, $ttl, $onInventoryChunk, $onPhase);

    return elpis_apply_receipt_expectations(
        $linesByProject,
        $context['reservations'],
        $context['purchase_lines'],
        $context['inventory'],
        [
            'receipts' => $context['receipts'] ?? [],
            'work_orders' => $context['work_orders'] ?? [],
        ]
    );
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

    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $state = (string) ($line['receipt_state'] ?? '');
        if ($state === 'received' || $state === 'not_required' || elpis_line_is_received($line) || elpis_line_is_not_required($line)) {
            continue;
        }

        $hasOpen = true;
        if ($state === 'unconfirmed' || $state === 'missing') {
            $hasMissing = true;
            continue;
        }

        $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
        if ($date === '') {
            $hasMissing = true;
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
        'by_company' => [],
    ];

    $companies = elpis_companies_for_page($ttl);
    $summary['companies'] = count($companies);

    foreach ($companies as $company) {
        auth_set_current_company_context($company, $ttl);
        $managers = elpis_fetch_project_managers($company, $ttl);
        $summary['managers'] += count($managers);

        $projects = elpis_fetch_projects_for_company($company, $ttl);
        $summary['projects'] += count($projects);

        $linesByProject = elpis_load_shown_lines($company, elpis_project_nos_from_projects($projects), $ttl);
        $summary['planning_line_projects'] += count($linesByProject);
        $summary['by_company'][$company] = [
            'projects' => $projects,
            'lines' => $linesByProject,
        ];
        foreach ($linesByProject as $projectNo => $lines) {
            elpis_write_project_cache($company, (string) $projectNo, is_array($lines) ? $lines : []);
        }
    }

    return $summary;
}

function elpis_line_search_blob(array $line, string $projectNo = ''): string
{
    $materialStatus = strtoupper(trim((string) ($line['material_status'] ?? '')));
    $parts = [
        $projectNo,
        (string) ($line['job_task_no'] ?? ''),
        (string) ($line['work_order_no'] ?? ''),
        (string) ($line['item_no'] ?? ''),
        (string) ($line['description'] ?? ''),
        $materialStatus,
        (string) ($line['planning_date'] ?? ''),
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
            return $cached['lines'];
        }
    }

    $load = static function () use ($company, $projectNo): array {
        $byProject = elpis_load_shown_lines($company, [$projectNo], ELPI_CACHE_TTL);

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
    $code = strtoupper(trim($code));
    if ($code === ELPI_STATUS_LATE || $code === 'T' || $code === 'X') {
        return 'alert';
    }
    if ($code === 'I') {
        return 'orange';
    }
    if (in_array($code, ['V', 'G', 'B', 'A', 'C'], true)) {
        return 'ok';
    }
    if ($code === 'N') {
        return 'muted';
    }

    return 'warn';
}

function elpis_line_filter_status(array $line): string
{
    $line = elpis_refresh_receipt_state($line);
    if ((string) ($line['receipt_state'] ?? '') === 'late') {
        return ELPI_STATUS_LATE;
    }

    return strtoupper(trim((string) ($line['material_status'] ?? '')));
}

function elpis_line_row_tone(array $line): string
{
    $line = elpis_refresh_receipt_state($line);
    if ((string) ($line['receipt_state'] ?? '') === 'late') {
        return 'alert';
    }

    $status = strtoupper(trim((string) ($line['material_status'] ?? '')));
    if ($status === '') {
        return '';
    }

    return elpis_status_tone($status);
}

function elpis_line_tooltips(array $line): array
{
    $receiptSource = trim((string) ($line['expected_receipt_source'] ?? ''));
    if ((string) ($line['receipt_state'] ?? '') === 'received') {
        if (elpis_normalize_bc_date((string) ($line['received_date'] ?? '')) !== '') {
            $receiptSource = 'ItemLedgerEntries -> Posting_Date';
        } elseif (!empty($line['completely_received'])) {
            $receiptSource = 'PurchaseLines -> LVS_Completely_Received';
        } elseif (elpis_line_purchase_outstanding_closed($line)) {
            $receiptSource = 'PurchaseLines -> Outstanding_Quantity';
        } elseif (elpis_line_inkoop_outstanding_closed($line)) {
            $receiptSource = 'AppProjectInkoopPlanningsRegel -> LVS_Outstanding_Qty_Base';
        } else {
            $receiptSource = 'PurchaseLines -> LVS_Completely_Received';
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
        'material_status' => '(WerkordersLines -> KVT_Status_Material)',
        'to_order' => '(WerkordersLines -> Quantity)',
        'ordered' => '(AppProjectInkoopPlanningsRegel -> LVS_Quantity_Order_UoM)',
        'open' => '(AppProjectInkoopPlanningsRegel -> LVS_Outstanding_Qty_Base)',
        'received' => '(AppProjectInkoopPlanningsRegel -> LVS_Outstanding_Qty_Base)',
        'expected_receipt' => '(' . $receiptSource . ')',
        'stock' => '(AppItems -> InventoryField) − (ReservationEntries -> Quantity_Base)',
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
    $materialStatus = (string) ($line['material_status'] ?? '');
    $materialLabel = (string) ($line['material_status_label'] ?? '');
    if ($materialLabel === '' && $materialStatus !== '' && function_exists('elpis_material_status_label')) {
        $materialLabel = elpis_material_status_label($materialStatus);
    }

    $freeStock = $line['free_stock'] ?? null;
    $freeDisplay = is_numeric($freeStock) ? elpis_format_qty((float) $freeStock) : '';

    return [
        'work_order_no' => (string) ($line['work_order_no'] ?? ''),
        'item_no' => (string) ($line['item_no'] ?? ''),
        'description' => (string) ($line['description'] ?? ''),
        'planning_date' => elpis_format_display_date((string) ($line['planning_date'] ?? '')),
        'planning_date_sort' => elpis_normalize_bc_date((string) ($line['planning_date'] ?? '')),
        'material_status' => $materialStatus,
        'material_status_label' => $materialLabel,
        'qty_to_order' => (float) ($line['qty_to_order'] ?? 0),
        'qty_ordered' => (float) ($line['qty_ordered'] ?? 0),
        'qty_open' => (float) ($line['qty_open'] ?? 0),
        'qty_received' => (float) ($line['qty_received'] ?? 0),
        'qty_to_order_display' => elpis_format_qty((float) ($line['qty_to_order'] ?? 0)),
        'qty_ordered_display' => elpis_format_qty((float) ($line['qty_ordered'] ?? 0)),
        'qty_open_display' => elpis_format_qty((float) ($line['qty_open'] ?? 0)),
        'qty_received_display' => elpis_format_qty((float) ($line['qty_received'] ?? 0)),
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

require_once __DIR__ . '/elpis_mail.php';
