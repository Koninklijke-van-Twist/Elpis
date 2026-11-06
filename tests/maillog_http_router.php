<?php

/**
 * Testharnas. Niet deployen. Zet REMOTE_ADDR vóór logincheck.
 */

if (isset($_SERVER['HTTP_X_TEST_REMOTE'])) {
    $_SERVER['REMOTE_ADDR'] = (string) $_SERVER['HTTP_X_TEST_REMOTE'];
}
if (isset($_SERVER['HTTP_X_TEST_SERVER'])) {
    $_SERVER['SERVER_ADDR'] = (string) $_SERVER['HTTP_X_TEST_SERVER'];
}

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
if (str_contains($uri, 'touch-login')) {
    require dirname(__DIR__) . '/web/auth.php';
    require dirname(__DIR__) . '/web/logincheck.php';
    header('Content-Type: text/plain');
    echo 'touched';
    return;
}
if (str_contains($uri, 'seed-real')) {
    session_start();
    $_SESSION['user'] = [
        'email' => 'first@example.com',
        'api_key' => 'key-real',
        'oid' => 'oid-real',
    ];
    header('Content-Type: text/plain');
    echo 'seeded';
    return;
}

require dirname(__DIR__) . '/web/maillog_export.php';
