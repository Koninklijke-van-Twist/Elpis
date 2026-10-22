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
$mailPer = is_array($mail['per_company'] ?? null) ? $mail['per_company'] : [];
foreach ((is_array($summary['per_company'] ?? null) ? $summary['per_company'] : []) as $companyName => $stats) {
    $m = is_array($mailPer[$companyName] ?? null) ? $mailPer[$companyName] : [];
    echo 'company="' . $companyName . '" managers=' . (int) ($stats['managers'] ?? 0)
        . ' projects=' . (int) ($stats['projects'] ?? 0)
        . ' line_projects=' . (int) ($stats['line_projects'] ?? 0)
        . ' lines=' . (int) ($stats['lines'] ?? 0)
        . ' mail_managers=' . (int) ($m['managers'] ?? 0)
        . ' recipients=' . (int) ($m['recipients'] ?? 0)
        . ' empty=' . (int) ($m['empty'] ?? 0)
        . ' sent=' . (int) ($m['sent'] ?? 0) . "\n";
}
foreach ((is_array($summary['fetch_errors'] ?? null) ? $summary['fetch_errors'] : []) as $err) {
    echo 'fetch_error company="' . (string) ($err['company'] ?? '') . '" entity=' . (string) ($err['entity'] ?? '') . ' message=' . (string) ($err['message'] ?? '') . "\n";
}
