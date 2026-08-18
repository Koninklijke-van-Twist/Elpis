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

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$startedAt = microtime(true);

try {
    $cleared = elpis_clear_odata_cache();
    $summary = elpis_warm_odata_cache(ELPI_CACHE_TTL);
    $elapsed = round(microtime(true) - $startedAt, 1);

    echo "Elpis nightly cache refresh OK\n";
    echo 'cleared=' . $cleared . "\n";
    echo 'companies=' . (int) ($summary['companies'] ?? 0) . "\n";
    echo 'managers=' . (int) ($summary['managers'] ?? 0) . "\n";
    echo 'projects=' . (int) ($summary['projects'] ?? 0) . "\n";
    echo 'planning_line_projects=' . (int) ($summary['planning_line_projects'] ?? 0) . "\n";
    echo 'ttl=' . ELPI_CACHE_TTL . "\n";
    echo 'elapsed_s=' . $elapsed . "\n";
} catch (Throwable $error) {
    http_response_code(500);
    echo "Elpis nightly cache refresh FAILED\n";
    echo $error->getMessage() . "\n";
}
