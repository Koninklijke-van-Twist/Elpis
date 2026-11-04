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
$refresh = isset($_GET['refresh']);

if ($company === '' || $projectNo === '') {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'company and project are required']);
    exit;
}

try {
    auth_set_current_company_context($company);
    $lines = elpis_load_project_modal_lines($company, $projectNo, $refresh);
    $cached = elpis_read_project_cache($company, $projectNo);
    $payloadLines = [];
    $statuses = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $payload = elpis_line_public_payload($line, $projectNo);
        $payloadLines[] = $payload;
        $statuses[(string) ($payload['filter_status'] ?? '')] = true;
    }

    // Statusfilter in de volgorde van de centrale tabel ELPI_LINE_STATUSES (alleen statussen die voorkomen).
    $statusList = [];
    foreach (ELPI_LINE_STATUSES as $code => $def) {
        if (isset($statuses[$code])) {
            $statusList[] = [
                'code' => $code,
                'label' => elpis_line_status_label($code),
                'tone' => $def['tone'],
                'problem' => (bool) $def['problem'],
            ];
        }
    }

    echo json_encode([
        'project' => $projectNo,
        'cached_at' => (int) ($cached['cached_at'] ?? time()),
        'refreshed' => $refresh,
        'lines' => $payloadLines,
        'statuses' => $statusList,
        'labels' => [
            'workorder' => LOC('elpis.col.workorder'),
            'item' => LOC('elpis.col.item'),
            'description' => LOC('elpis.col.description'),
            'planning_date' => LOC('elpis.col.planning_date'),
            'line_status' => LOC('elpis.col.line_status'),
            'to_order' => LOC('elpis.col.to_order'),
            'ordered' => LOC('elpis.col.ordered'),
            'open' => LOC('elpis.col.outstanding'),
            'received' => LOC('elpis.col.received'),
            'picked' => LOC('elpis.col.picked'),
            'expected_receipt' => LOC('elpis.col.expected_receipt'),
            'stock' => LOC('elpis.col.stock'),
            'purchaser' => LOC('elpis.col.purchaser'),
            'empty' => LOC('elpis.empty.lines'),
            'line_search' => LOC('elpis.label.line_search'),
            'line_search_placeholder' => LOC('elpis.placeholder.line_search'),
            'status' => LOC('elpis.label.status_filter'),
            'status_all' => LOC('elpis.status.all'),
            'problems_only' => LOC('elpis.label.problems_only'),
            'export_xlsx' => LOC('elpis.btn.export_xlsx'),
            'needed' => LOC('elpis.col.needed'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => true]);
}
