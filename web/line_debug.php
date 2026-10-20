<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/elpis_data.php';

/**
 * Page load
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => true]);
    exit;
}

$company = trim((string) ($_GET['company'] ?? ''));
$projectNo = trim((string) ($_GET['project'] ?? ''));
$itemNo = trim((string) ($_GET['debug_line'] ?? ''));
$refresh = !isset($_GET['cached']);

if ($company === '' || $projectNo === '' || $itemNo === '') {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'company, project and debug_line are required',
    ]);
    exit;
}

try {
    auth_set_current_company_context($company);
    echo json_encode(
        elpis_debug_project_line($company, $projectNo, $itemNo, $refresh),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => true]);
}
