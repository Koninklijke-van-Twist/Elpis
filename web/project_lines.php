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
    $statuses = [ELPI_STATUS_LATE => true];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $payloadLines[] = elpis_line_public_payload($line, $projectNo);
        $material = strtoupper(trim((string) ($line['material_status'] ?? '')));
        if ($material !== '') {
            $statuses[$material] = true;
        }
    }

    $statusList = [];
    $statusList[] = [
        'code' => ELPI_STATUS_LATE,
        'label' => LOC('elpis.receipt.late'),
        'tone' => elpis_status_tone(ELPI_STATUS_LATE),
    ];
    $materialCodes = array_keys($statuses);
    $materialCodes = array_values(array_filter($materialCodes, static function (string $code): bool {
        return $code !== ELPI_STATUS_LATE;
    }));
    usort($materialCodes, static function (string $left, string $right): int {
        return strcasecmp(elpis_material_status_label($left), elpis_material_status_label($right));
    });
    foreach ($materialCodes as $code) {
        $statusList[] = [
            'code' => $code,
            'label' => elpis_material_status_label($code),
            'tone' => elpis_status_tone($code),
        ];
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
            'material_status' => LOC('elpis.col.material_status'),
            'to_order' => LOC('elpis.col.to_order'),
            'ordered' => LOC('elpis.col.ordered'),
            'open' => LOC('elpis.col.outstanding'),
            'received' => LOC('elpis.col.received'),
            'expected_receipt' => LOC('elpis.col.expected_receipt'),
            'stock' => LOC('elpis.col.stock'),
            'empty' => LOC('elpis.empty.lines'),
            'line_search' => LOC('elpis.label.line_search'),
            'line_search_placeholder' => LOC('elpis.placeholder.line_search'),
            'status' => LOC('elpis.label.material_status_filter'),
            'status_all' => LOC('elpis.material_status.all'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['error' => true]);
}
