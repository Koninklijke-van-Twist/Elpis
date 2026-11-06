<?php

/**
 * Alleen-lezen export voor diagnoselezers.
 * Eerst de gewone login. Een trusted/localhost-identiteit telt niet.
 * Het adres moet daarnaast in data/maillog/readers.json staan, in de map
 * die HTTP al weigert. Dit is beheerdiagnose: een treffer kan andermans adres tonen.
 * Geen BC, geen mail, geen cache-refresh.
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

$user = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$trusted = function_exists('is_trusted_requester') && is_trusted_requester();
[$status, $body] = elpis_maillog_export_response($user, $_GET, $trusted);
http_response_code($status);
echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
