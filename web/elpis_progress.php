<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/elpis_data.php';

/**
 * Functies
 */

function elpis_progress_prepare_stream(): void
{
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Accel-Buffering: no');

    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function elpis_progress_emit(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n";

    if (ob_get_level() > 0) {
        ob_flush();
    }

    flush();
}

/**
 * Page load
 */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}

elpis_progress_prepare_stream();

$userEmail = elpis_current_user_email();
$userPrefs = function_exists('loadUserPrefs') ? loadUserPrefs($userEmail) : [];
$savedManagersByCompany = elpis_load_manager_prefs($userEmail);
$companyReloaded = isset($_GET['reload_managers']);

$companies = elpis_companies_for_page();
$requestedCompany = trim((string) ($_GET['company'] ?? ''));
$requestedManager = $companyReloaded ? '' : trim((string) ($_GET['manager'] ?? ''));

if ($requestedCompany === '' || !in_array($requestedCompany, $companies, true)) {
    elpis_progress_emit(['complete' => true]);
    exit;
}

$company = $requestedCompany;
$projectManager = '';

try {
    $progressStartedAt = microtime(true);
    auth_set_current_company_context($company);

    elpis_progress_emit(['step' => 'managers', 'status' => 'start']);
    $projectManagers = elpis_fetch_project_managers($company);
    $projectManager = elpis_resolve_manager_choice(
        $projectManagers,
        $requestedManager,
        $company,
        $savedManagersByCompany,
        $userEmail
    );

    elpis_progress_emit(['step' => 'managers', 'status' => 'done']);

    if ($projectManager === '') {
        if ($company !== '') {
            elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
        }
        elpis_log_timing($company, 'progress', ['$filter' => 'manager='], 0, $progressStartedAt, 'html');
        elpis_progress_emit(['complete' => true]);
        exit;
    }

    elpis_progress_emit(['step' => 'projects', 'status' => 'start']);
    $projects = elpis_fetch_projects_for_manager($company, $projectManager);
    elpis_progress_emit(['step' => 'projects', 'status' => 'done']);

    if ($company !== '') {
        elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
    }

    elpis_log_timing(
        $company,
        'progress',
        ['$filter' => 'manager=' . $projectManager . ' projects=' . count($projects)],
        count($projects),
        $progressStartedAt,
        'html'
    );
    elpis_progress_emit(['complete' => true]);
} catch (Throwable $loadError) {
    elpis_progress_emit(['error' => true]);
}
