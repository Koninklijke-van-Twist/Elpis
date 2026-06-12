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
$savedCompany = (string) ($userPrefs['elpis_company'] ?? '');
$requestedCompany = trim((string) ($_GET['company'] ?? ''));
$requestedManager = $companyReloaded ? '' : trim((string) ($_GET['manager'] ?? ''));
$discoveredSelection = elpis_discover_selection_for_new_user(
    $companies,
    $userEmail,
    $requestedCompany,
    $requestedManager,
    $savedCompany,
    $savedManagersByCompany
);
$preselectedManager = null;

if (is_array($discoveredSelection)) {
    $company = (string) ($discoveredSelection['company'] ?? '');
    if ($company === '' || !in_array($company, $companies, true)) {
        $company = elpis_resolve_company_choice($companies, $requestedCompany, $savedCompany);
    } else {
        $preselectedManager = trim((string) ($discoveredSelection['manager'] ?? ''));
    }
} else {
    $company = elpis_resolve_company_choice($companies, $requestedCompany, $savedCompany);
}

$projectManager = '';

try {
    auth_set_current_company_context($company);

    elpis_progress_emit(['step' => 'managers', 'status' => 'start']);
    $projectManagers = elpis_fetch_project_managers($company);

    if ($preselectedManager !== null && $preselectedManager !== '') {
        $projectManager = elpis_pick_manager_from_list($projectManagers, $preselectedManager);
        if ($projectManager === '') {
            $projectManager = elpis_resolve_manager_choice(
                $projectManagers,
                $requestedManager,
                $company,
                $savedManagersByCompany,
                $userEmail
            );
        }
    } else {
        $projectManager = elpis_resolve_manager_choice(
            $projectManagers,
            $requestedManager,
            $company,
            $savedManagersByCompany,
            $userEmail
        );
    }

    elpis_progress_emit(['step' => 'managers', 'status' => 'done']);

    if ($companyReloaded || $projectManager === '') {
        if ($company !== '') {
            elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
        }
        elpis_progress_emit(['complete' => true]);
        exit;
    }

    elpis_progress_emit(['step' => 'projects', 'status' => 'start']);
    $projects = elpis_fetch_projects_for_manager($company, $projectManager);
    $projectNos = array_map(static function (array $project): string {
        return (string) ($project['no'] ?? '');
    }, $projects);
    $lineChunks = elpis_planning_lines_chunk_count($projectNos);
    if ($lineChunks > 0) {
        elpis_progress_emit(['lineChunks' => $lineChunks]);
    }
    elpis_progress_emit(['step' => 'projects', 'status' => 'done']);

    if ($projects !== [] && $lineChunks > 0) {
        $chunks = array_chunk(elpis_normalize_project_nos($projectNos), ELPI_PLANNING_LINES_CHUNK_SIZE);
        foreach ($chunks as $chunkIndex => $chunk) {
            elpis_progress_emit(['step' => 'lines_' . $chunkIndex, 'status' => 'start']);
            elpis_fetch_planning_lines_chunk($company, $chunk);
            elpis_progress_emit(['step' => 'lines_' . $chunkIndex, 'status' => 'done']);
        }
    }

    if ($company !== '') {
        elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
    }

    elpis_progress_emit(['complete' => true]);
} catch (Throwable $loadError) {
    elpis_progress_emit(['error' => true]);
}
