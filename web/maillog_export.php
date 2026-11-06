<?php

/**
 * Alleen-lezen export van data/maillog. Login én data/maillog_readers.json.
 * Ontbreekt die lijst, dan weigert het endpoint. Geen BC, geen mail, geen cache-refresh.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/elpis_data.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="maillog.json"');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$email = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
[$status, $body] = elpis_maillog_export_response($email, $_GET);
http_response_code($status);
echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
