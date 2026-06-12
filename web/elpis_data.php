<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/odata.php';

/**
 * Constants
 */

const ELPI_PROJECT_MANAGER_EMAIL_MAP = [
    'mhubregtse@ameil.nl' => 'KVT\\mhubregtse',
];

const ELPI_DEFAULT_USER_EMAIL = 'localtester@kvt.nl';

const ELPI_PROJECT_MANAGERS_TTL = 86400;

const ELPI_MANAGER_ALL = '*';

const ELPI_PLANNING_LINES_CHUNK_SIZE = 12;

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

function elpis_fetch_rows(string $company, string $entitySet, array $query, int $ttl = 3600): array
{
    global $baseUrl;

    $environment = auth_get_environment_for_company($company, $ttl);
    $auth = auth_get_auth_for_environment($environment);
    $url = elpis_company_entity_url($baseUrl, $environment, $company, $entitySet, $query);

    return odata_get_all($url, $auth, $ttl);
}

function elpis_try_fetch_rows(string $company, string $entitySet, array $query, int $ttl = 3600): array
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

function elpis_companies_for_page(int $ttl = 3600): array
{
    try {
        $result = auth_discover_companies_across_active_environments($ttl);
        $companies = is_array($result['companies'] ?? null) ? $result['companies'] : [];
        if ($companies !== []) {
            return $companies;
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

function elpis_fetch_projects_for_company(string $company, int $ttl = 3600): array
{
    $rows = elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'No,Description,Status,Project_Manager',
        '$filter' => "Project_Manager ne ''",
        '$orderby' => 'No desc',
        '$top' => '500',
    ], $ttl);

    return elpis_collect_projects_from_rows($rows);
}

function elpis_fetch_projects_for_manager(string $company, string $projectManager, int $ttl = 3600): array
{
    if (elpis_is_all_managers_selection($projectManager)) {
        return elpis_fetch_projects_for_company($company, $ttl);
    }

    $manager = elpis_normalize_bc_username($projectManager);
    if ($manager === '') {
        return [];
    }

    $escaped = elpis_escape_odata_string($manager);
    $rows = elpis_try_fetch_rows($company, 'AppProjecten', [
        '$select' => 'No,Description,Status,Project_Manager',
        '$filter' => "Project_Manager eq '" . $escaped . "'",
        '$orderby' => 'No desc',
        '$top' => '200',
    ], $ttl);

    return elpis_collect_projects_from_rows($rows);
}

function elpis_planning_line_select_fields(): string
{
    return 'Job_No,Job_Task_No,Line_No,Type,No,Description,Quantity,LVS_Quantity_Order_UoM,LVS_Outstanding_Qty_Base,LVS_Purchase_Order_No,LVS_Completely_Received';
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

function elpis_fetch_planning_lines_for_project(string $company, string $projectNo, int $ttl = 3600): array
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

function elpis_fetch_planning_lines_chunk(string $company, array $projectNos, int $ttl = 3600): array
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

function elpis_fetch_planning_lines_by_projects(string $company, array $projectNos, int $ttl = 3600): array
{
    $projectNos = elpis_normalize_project_nos($projectNos);

    if ($projectNos === []) {
        return [];
    }

    $byProject = [];
    foreach ($projectNos as $projectNo) {
        $byProject[$projectNo] = [];
    }

    $chunks = array_chunk($projectNos, ELPI_PLANNING_LINES_CHUNK_SIZE);
    foreach ($chunks as $chunk) {
        $chunkResult = elpis_fetch_planning_lines_chunk($company, $chunk, $ttl);
        foreach ($chunkResult as $jobNo => $lines) {
            foreach ($lines as $line) {
                $byProject[$jobNo][] = $line;
            }
        }
    }

    return $byProject;
}

function elpis_line_search_blob(array $line): string
{
    return strtolower(implode(' ', array_filter([
        (string) ($line['job_task_no'] ?? ''),
        (string) ($line['item_no'] ?? ''),
        (string) ($line['description'] ?? ''),
    ], static function (string $value): bool {
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
        $parts[] = elpis_line_search_blob($line);
    }

    return trim(implode(' ', array_filter($parts, static function (string $value): bool {
        return $value !== '';
    })));
}
