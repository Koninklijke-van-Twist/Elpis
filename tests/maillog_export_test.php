<?php
/**
 * Maillog-export: fail-closed, whitelist, geen pad of secrets.
 * Run: php tests/maillog_export_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-11-06';
$root = sys_get_temp_dir() . '/elpis-maillog-' . getmypid();
$GLOBALS['ELPIS_DATA_DIR'] = $root;
@mkdir($root, 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

$entry = [
    'time' => '2026-11-05 08:01:00',
    'to' => 'reader@example.com',
    'subject' => 'niet exporteren',
    'error' => 'password=geheim',
    'status' => 'ok',
    'lines' => [
        [
            'company' => 'Voorbeeld',
            'project' => 'P1',
            'item' => 'ITEM-1',
            'description' => 'plaatje',
            'status' => 'Ontvangen, nog picken',
            'reason' => 'zie https://example.test/voorkeuren.php?x=1 api_key=abc',
            'ticket' => 'https://secret.test/ticket',
        ],
        [
            'company' => 'Voorbeeld',
            'project' => 'P9',
            'item' => 'ANDER',
            'description' => 'niet meenemen',
            'status' => 'Te laat',
            'reason' => '1 dag te laat',
        ],
    ],
];
$dir = elpis_maillog_dir();
file_put_contents($dir . '/2026-11-05.jsonl', json_encode($entry) . "\n");
file_put_contents($dir . '/../geheim.txt', 'geheim');
$htaccess = (string) file_get_contents($dir . '/.htaccess');
if (!str_contains($htaccess, 'Require all denied')) {
    fail('maillogmap blijft via HTTP dicht');
}

if (elpis_maillog_reader_allowed('') || elpis_maillog_reader_allowed('reader@example.com')) {
    fail('zonder lezerslijst is iedereen geweigerd');
}
file_put_contents(elpis_maillog_readers_path(), '{');
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('kapotte lijst weigert');
}
file_put_contents(elpis_maillog_readers_path(), json_encode(['readers' => ['Reader@example.com']]));
if (!elpis_maillog_reader_allowed('reader@example.com') || elpis_maillog_reader_allowed('ander@example.com')) {
    fail('alleen het genoemde adres mag');
}

[$status, $body] = elpis_maillog_export_response('ander@example.com', [
    'from' => '2026-11-04',
    'to' => '2026-11-06',
    'project' => 'P1',
]);
if ($status !== 403 || isset($body['mails'])) {
    fail('verkeerde gebruiker krijgt geen export');
}
[$status] = elpis_maillog_export_response('', ['from' => '2026-11-05', 'to' => '2026-11-05', 'item' => 'ITEM-1']);
if ($status !== 403) {
    fail('lege identiteit wordt geweigerd');
}

$bad = [
    ['from' => '2026-11-01', 'to' => '2026-11-08', 'item' => 'ITEM-1'],
    ['from' => '../2026-11-05', 'to' => '2026-11-05', 'item' => 'ITEM-1'],
    ['from' => '2026-11-05', 'to' => '2026-11-05', 'file' => '../../auth.php'],
    ['from' => '2026-11-05', 'to' => '2026-11-05', 'project' => '../P1'],
    ['from' => '2026-11-05', 'to' => '2026-11-05'],
];
foreach ($bad as $query) {
    [$status, $body] = elpis_maillog_export_response('reader@example.com', $query);
    if ($status !== 400 || isset($body['mails']) || isset($body['file'])) {
        fail('ongeldige invoer: ' . json_encode($query));
    }
}

[$status, $body] = elpis_maillog_export_response('reader@example.com', [
    'from' => '2026-11-04',
    'to' => '2026-11-06',
    'project' => 'P1',
    'item' => 'ITEM-1',
]);
if ($status !== 200 || count($body['mails'] ?? []) !== 1 || count($body['mails'][0]['lines']) !== 1) {
    fail('export houdt alleen de gevraagde regel: ' . json_encode($body));
}
$line = $body['mails'][0]['lines'][0];
$allowed = ['company', 'project', 'item', 'description', 'status', 'reason'];
if (array_keys($line) !== $allowed || isset($body['mails'][0]['subject']) || isset($body['mails'][0]['error'])) {
    fail('export toont alleen de whitelist');
}
if (str_contains($line['reason'], 'http') || str_contains($line['reason'], 'abc') || str_contains(json_encode($body), 'geheim')) {
    fail('url en geheim blijven in de export: ' . json_encode($body));
}
if ($line['reason'] === '' || $line['status'] !== 'Ontvangen, nog picken') {
    fail('bewaarde status en toelichting blijven: ' . json_encode($line));
}

$endpoint = (string) file_get_contents(dirname(__DIR__) . '/web/maillog_export.php');
if (!str_contains($endpoint, "require_once __DIR__ . '/logincheck.php';")
    || !str_contains($endpoint, 'elpis_maillog_export_response')
    || str_contains($endpoint, 'elpis_mail_send')
    || str_contains($endpoint, 'elpis_run_nightly')
    || str_contains($endpoint, 'cache_clear')) {
    fail('endpoint is login plus alleen-lezen export');
}

echo "OK\n";
