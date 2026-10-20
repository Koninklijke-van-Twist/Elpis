<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/elpis_data.php';

/**
 * Page load
 */

ignore_user_abort(true);
set_time_limit(0);

$wantsJson = (($_GET['format'] ?? '') === 'json');
header('Content-Type: ' . ($wantsJson ? 'application/json' : 'text/plain') . '; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$startedAt = microtime(true);
$force = isset($_GET['force']);
$result = elpis_run_nightly($force);
$elapsed = round(microtime(true) - $startedAt, 1);
$result['elapsed_s'] = $elapsed;

if (($result['status'] ?? '') === 'failed') {
    http_response_code(500);
}

if ($wantsJson) {
    $mail = is_array($result['mail'] ?? null) ? $result['mail'] : [];
    unset($mail['details']);
    $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
    unset($summary['by_company']);
    echo json_encode([
        'status' => (string) ($result['status'] ?? 'failed'),
        'error' => (string) ($result['error'] ?? ''),
        'elapsed_s' => $elapsed,
        'summary' => $summary,
        'mail' => $mail,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$status = (string) ($result['status'] ?? 'failed');
if ($status === 'failed') {
    echo "Elpis nightly cache refresh FAILED\n";
    echo (string) ($result['error'] ?? '') . "\n";
    exit;
}

if ($status === 'running') {
    echo "Elpis nightly cache refresh RUNNING\n";
    exit;
}

if ($status === 'fresh') {
    echo "Elpis nightly cache refresh FRESH\n";
    echo 'date=' . (string) ($result['date'] ?? '') . "\n";
    exit;
}

$summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
$mail = is_array($result['mail'] ?? null) ? $result['mail'] : [];
echo "Elpis nightly cache refresh OK\n";
echo 'companies=' . (int) ($summary['companies'] ?? 0) . "\n";
echo 'managers=' . (int) ($summary['managers'] ?? 0) . "\n";
echo 'projects=' . (int) ($summary['projects'] ?? 0) . "\n";
echo 'planning_line_projects=' . (int) ($summary['planning_line_projects'] ?? 0) . "\n";
echo 'mails_sent=' . (int) ($mail['sent'] ?? 0) . "\n";
echo 'mail_test_mode=' . (!empty($mail['test_mode']) ? '1' : '0') . "\n";
echo 'ttl=' . ELPI_NIGHTLY_MAX_AGE . "\n";
echo 'elapsed_s=' . $elapsed . "\n";
