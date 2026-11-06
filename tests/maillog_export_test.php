<?php
/**
 * Maillog-export: echte login, leesgrenzen, redactie, geen schrijven.
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

function verify_rotating_api_key(string $oid, string $key): bool
{
    return $oid === 'oid-real' && $key === 'key-real';
}

function readerUser(): array
{
    return ['email' => 'reader@example.com', 'api_key' => 'key-real', 'oid' => 'oid-real'];
}

if (elpis_maillog_existing_dir() !== null || is_dir($root . '/maillog')) {
    fail('lezen maakt de maillogmap niet aan');
}

$dir = elpis_maillog_dir();
$entry = [
    'time' => '2026-11-05 08:01:00',
    'to' => 'reader@example.com',
    'subject' => 'niet exporteren',
    'error' => 'password geheim',
    'status' => 'ok',
    'lines' => [
        [
            'company' => 'Voorbeeld',
            'project' => 'P1',
            'item' => 'ITEM-1',
            'description' => 'plaatje',
            'status' => 'Ontvangen, nog picken',
            'reason' => 'zie https://example.test/voorkeuren.php?x=1 en voorkeuren.php?id=9 access_token=abc api_key:zzz Authorization Bearer tok password los password:=pw',
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
file_put_contents($dir . '/2026-11-05.jsonl', json_encode($entry) . "\n");
$readers = $dir . '/readers.json';
file_put_contents($readers, json_encode(['readers' => ['Reader@example.com']]));
chmod($readers, 0640);
if ((string) file_get_contents($dir . '/.htaccess') !== ELPIS_MAILLOG_HTACCESS) {
    fail('maillogmap blijft via HTTP dicht');
}

if (elpis_maillog_reader_allowed('') || elpis_maillog_reader_allowed('ander@example.com')) {
    fail('onbekend adres wordt geweigerd');
}
if (!elpis_maillog_reader_allowed('reader@example.com')) {
    fail('toegelaten lezer wordt herkend');
}
chmod($readers, 0644);
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('wereldleesbare lijst wordt geweigerd');
}
chmod($readers, 0640);
$savedRules = (string) file_get_contents($dir . '/.htaccess');
file_put_contents($dir . '/.htaccess', $savedRules . "Require all granted\n");
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('verruimde htaccess blijft vertrouwd');
}
file_put_contents($dir . '/.htaccess', $savedRules);
chmod($dir, 0757);
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('wereldtoegankelijke maillogmap blijft vertrouwd');
}
chmod($dir, 0750);
chmod($dir . '/.htaccess', 0666);
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('wereldschrijfbare htaccess blijft vertrouwd');
}
chmod($dir . '/.htaccess', 0644);
unlink($dir . '/.htaccess');
[$status] = elpis_maillog_export_response(readerUser(), [
    'from' => '2026-11-05', 'to' => '2026-11-05', 'item' => 'ITEM-1',
], false);
if ($status !== 403 || is_file($dir . '/.htaccess')) {
    fail('export herstelt geen htaccess en geeft geen log');
}
file_put_contents($dir . '/.htaccess', ELPIS_MAILLOG_HTACCESS);

$query = ['from' => '2026-11-04', 'to' => '2026-11-06', 'project' => 'P1', 'item' => 'ITEM-1'];
[$status, $body] = elpis_maillog_export_response(['email' => 'reader@example.com'], $query, false);
if ($status !== 403) {
    fail('sessie zonder loginbewijs wordt geweigerd');
}
[$status, $body] = elpis_maillog_export_response(readerUser(), $query, true);
if ($status !== 403 || isset($body['mails'])) {
    fail('trusted request wordt geweigerd');
}
[$status] = elpis_maillog_export_response(
    ['email' => 'ander@example.com', 'api_key' => 'key-real', 'oid' => 'oid-real'],
    $query,
    false
);
if ($status !== 403) {
    fail('echte login buiten de lezerslijst wordt geweigerd');
}

foreach ([
    ['from' => '2026-11-01', 'to' => '2026-11-08', 'item' => 'ITEM-1'],
    ['from' => '../2026-11-05', 'to' => '2026-11-05', 'item' => 'ITEM-1'],
    ['from' => '2026-11-05', 'to' => '2026-11-05', 'file' => '../../auth.php'],
    ['from' => '2026-11-05', 'to' => '2026-11-05', 'project' => '../P1'],
    ['from' => '2026-11-05', 'to' => '2026-11-05'],
] as $bad) {
    [$status, $body] = elpis_maillog_export_response(readerUser(), $bad, false);
    if ($status !== 400 || isset($body['mails'])) {
        fail('ongeldige invoer: ' . json_encode($bad));
    }
}

[$status, $body] = elpis_maillog_export_response(readerUser(), $query, false);
if ($status !== 200 || empty($body['complete']) || count($body['mails'] ?? []) !== 1 || count($body['mails'][0]['lines']) !== 1) {
    fail('export houdt alleen de gevraagde regel: ' . json_encode($body));
}
$line = $body['mails'][0]['lines'][0];
if (array_keys($line) !== ['company', 'project', 'item', 'description', 'status', 'reason']
    || isset($body['mails'][0]['subject']) || isset($body['mails'][0]['error'])) {
    fail('export toont alleen de whitelist');
}
$encoded = json_encode($body);
foreach (['http', 'abc', 'zzz', 'Bearer tok', 'password los', 'password:=pw', 'geheim', 'voorkeuren.php'] as $secret) {
    if (str_contains($encoded, $secret)) {
        fail('credentials blijven in de export: ' . $encoded);
    }
}
if (!str_contains($line['reason'], 'zie') || $line['status'] !== 'Ontvangen, nog picken') {
    fail('gewone toelichting blijft: ' . json_encode($line));
}

$outside = $root . '/outside.jsonl';
file_put_contents($outside, json_encode(['to' => 'reader@example.com', 'status' => 'ok', 'lines' => [[
    'project' => 'P1', 'item' => 'ITEM-1', 'reason' => 'BUITEN-GEHEIM',
]]]) . "\n");
[$status, $body] = elpis_maillog_export_response(readerUser(), [
    'from' => '2026-11-03', 'to' => '2026-11-03', 'item' => 'ITEM-1',
], false);
if ($status !== 200 || empty($body['complete']) || ($body['mails'] ?? null) !== []) {
    fail('afgeronde scan zonder treffer is leeg én compleet: ' . json_encode($body));
}

$escaped = $root . '/escaped-readers.json';
file_put_contents($escaped, json_encode(['readers' => ['reader@example.com']]));
chmod($escaped, 0640);
rename($readers, $readers . '.real');
symlink($escaped, $readers);
if (elpis_maillog_reader_allowed('reader@example.com')) {
    fail('lezerslijst buiten de maillogmap wordt gebruikt');
}
unlink($readers);
rename($readers . '.real', $readers);

symlink($outside, $dir . '/2026-11-04.jsonl');
[$status, $body] = elpis_maillog_export_response(readerUser(), [
    'from' => '2026-11-04', 'to' => '2026-11-04', 'item' => 'ITEM-1',
], false);
if ($status !== 413 || !empty($body['mails']) || str_contains(json_encode($body), 'BUITEN-GEHEIM') || !isset($body['complete']) || $body['complete'] !== false) {
    fail('symlink buiten de map levert geen stille lege export: ' . json_encode($body));
}
unlink($dir . '/2026-11-04.jsonl');

file_put_contents($dir . '/2026-11-06.jsonl', str_repeat('A', 9000) . "\n");
[$status, $body] = elpis_maillog_export_response(readerUser(), [
    'from' => '2026-11-06', 'to' => '2026-11-06', 'item' => 'ITEM-1',
], false);
if ($status !== 413 || ($body['complete'] ?? true) !== false || isset($body['mails'])) {
    fail('te lange regel is onvolledig, niet leeg=alles: ' . json_encode($body));
}
$bulk = '';
for ($i = 0; $i < 200; $i++) {
    $bulk .= str_repeat('B', 6000) . "\n";
}
file_put_contents($dir . '/2026-11-02.jsonl', $bulk);
[$status, $body] = elpis_maillog_export_response(readerUser(), [
    'from' => '2026-11-02', 'to' => '2026-11-02', 'item' => 'ITEM-1',
], false);
if ($status !== 413 || ($body['complete'] ?? true) !== false || isset($body['mails'])) {
    fail('te veel bytes is onvolledig: ' . json_encode($body));
}

$endpoint = (string) file_get_contents(dirname(__DIR__) . '/web/maillog_export.php');
if (!str_contains($endpoint, "require_once __DIR__ . '/logincheck.php';")
    || !str_contains($endpoint, 'is_trusted_requester')
    || str_contains($endpoint, 'elpis_mail_send')
    || str_contains($endpoint, 'cache_clear')) {
    fail('endpoint blijft login plus alleen-lezen');
}

$repo = dirname(__DIR__);
$authFile = $repo . '/web/auth.php';
$libFile = $repo . '/login/lib.php';
if (is_file($authFile) || is_file($libFile)) {
    fail('HTTP-test raakt een bestaande auth.php of login/lib.php niet aan');
}
$httpRoot = sys_get_temp_dir() . '/elpis-maillog-http-' . getmypid();
$server = null;
$createdAuth = false;
$createdLib = false;
$createdLoginDir = false;
$cleanup = static function () use (&$server, &$createdAuth, &$createdLib, &$createdLoginDir, $authFile, $libFile, $repo, $httpRoot): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
        $server = null;
    }
    if ($createdAuth) {
        @unlink($authFile);
    }
    if ($createdLib) {
        @unlink($libFile);
    }
    if ($createdLoginDir) {
        @rmdir($repo . '/login');
    }
    putenv('ELPIS_MAILLOG_TEST_DATA');
};
register_shutdown_function($cleanup);

$GLOBALS['ELPIS_DATA_DIR'] = $httpRoot;
$httpDir = elpis_maillog_dir();
touch($httpDir . '/.htaccess', 1700000000);
$beforeHt = filemtime($httpDir . '/.htaccess');
file_put_contents($httpDir . '/readers.json', json_encode(['readers' => ['first@example.com']]));
chmod($httpDir . '/readers.json', 0640);
file_put_contents($httpDir . '/2026-11-06.jsonl', json_encode([
    'time' => '2026-11-06 08:00:00',
    'to' => 'other@example.com',
    'subject' => 'niet-exporteren',
    'status' => 'ok',
    'lines' => [[
        'company' => 'Voorbeeld',
        'project' => 'P1',
        'item' => 'ITEM-HTTP',
        'description' => 'plaatje',
        'status' => 'Te laat',
        'reason' => '1 dag te laat access_token=SUPERSECRET',
    ]],
]) . "\n");

file_put_contents($authFile, "<?php\n\$allowedUsers = ['first@example.com'];\n\$data = getenv('ELPIS_MAILLOG_TEST_DATA');\nif (!is_string(\$data) || \$data === '') { http_response_code(500); exit; }\n\$GLOBALS['ELPIS_DATA_DIR'] = \$data;\n");
$createdAuth = true;
if (!is_dir($repo . '/login')) {
    if (!mkdir($repo . '/login', 0750)) {
        fail('loginmap voor de HTTP-test ontbreekt');
    }
    $createdLoginDir = true;
}
file_put_contents($libFile, <<<'PHP'
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        foreach ($array as $item) {
            if ($callback($item)) {
                return true;
            }
        }
        return false;
    }
}
function verify_rotating_api_key(string $oid, string $key): bool
{
    return hash_equals('oid-real', $oid) && hash_equals('key-real', $key);
}
PHP);
$createdLib = true;
putenv('ELPIS_MAILLOG_TEST_DATA=' . $httpRoot);

$port = 18765;
$cmd = [PHP_BINARY, '-d', 'display_errors=0', '-S', '127.0.0.1:' . $port, '-t', $repo . '/tests', $repo . '/tests/maillog_http_router.php'];
$server = proc_open($cmd, [1 => ['file', '/tmp/elpis-maillog-http.log', 'w'], 2 => ['file', '/tmp/elpis-maillog-http.err', 'w']], $pipes, $repo);
if (!is_resource($server)) {
    fail('HTTP-server start niet');
}
$up = false;
for ($i = 0; $i < 50; $i++) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $err, 0.1);
    if (is_resource($probe)) {
        fclose($probe);
        $up = true;
        break;
    }
    usleep(100000);
}
if (!$up) {
    fail('HTTP-server luistert niet: ' . (string) @file_get_contents('/tmp/elpis-maillog-http.err'));
}

$base = 'http://127.0.0.1:' . $port;
$query = '/export?from=2026-11-06&to=2026-11-06&item=ITEM-HTTP';
$curl = static function (string $url, string $jar, array $headers) use ($base): array {
    $tmp = tempnam(sys_get_temp_dir(), 'elpis-body');
    $args = ['curl', '-sS', '-D', '-', '-o', $tmp, '-c', $jar, '-b', $jar, '--max-time', '8'];
    foreach ($headers as $header) {
        $args[] = '-H';
        $args[] = $header;
    }
    $args[] = $base . $url;
    $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($args, $spec, $out);
    $raw = stream_get_contents($out[1]);
    $err = stream_get_contents($out[2]);
    fclose($out[1]);
    fclose($out[2]);
    $code = proc_close($proc);
    $body = (string) file_get_contents($tmp);
    @unlink($tmp);
    $status = 0;
    if (preg_match('/^HTTP\/\S+\s+(\d+)/m', $raw, $match) === 1) {
        $status = (int) $match[1];
    }
    return [$code, $status, $body, $err];
};
$secret = static function (string $body) : bool {
    return str_contains($body, 'SUPERSECRET') || str_contains($body, 'ITEM-HTTP') || str_contains($body, 'other@example.com');
};

$jarPolluted = sys_get_temp_dir() . '/elpis-jar-polluted-' . getmypid();
$jarReal = sys_get_temp_dir() . '/elpis-jar-real-' . getmypid();
[, $status, $body] = $curl('/touch-login', $jarPolluted, ['X-Test-Remote: 127.0.0.1', 'X-Test-Server: 10.1.1.1']);
if ($status !== 200 || !str_contains($body, 'touched')) {
    fail('trusted loginpad zette geen sessie: ' . $status . ' ' . $body);
}
[, $status, $body] = $curl($query, $jarPolluted, ['X-Test-Remote: 203.0.113.8', 'X-Test-Server: 10.1.1.1']);
if ($status !== 403 || $secret($body)) {
    fail('vervuilde sessie krijgt de log: ' . $status . ' ' . $body);
}
$jarLocal = sys_get_temp_dir() . '/elpis-jar-local-' . getmypid();
[, $status, $body] = $curl($query, $jarLocal, ['X-Test-Remote: 127.0.0.1', 'X-Test-Server: 10.1.1.1']);
if ($status !== 403 || $secret($body)) {
    fail('localhost zonder login krijgt de log: ' . $status . ' ' . $body);
}
[, $status, $body] = $curl($query, $jarLocal, ['X-Test-Remote: 10.9.9.9', 'X-Test-Server: 10.9.9.9']);
if ($status !== 403 || $secret($body)) {
    fail('REMOTE_ADDR===SERVER_ADDR krijgt de log: ' . $status . ' ' . $body);
}
[, $status, $body] = $curl('/seed-real', $jarReal, ['X-Test-Remote: 203.0.113.8', 'X-Test-Server: 10.1.1.1']);
if ($status !== 200 || !str_contains($body, 'seeded')) {
    fail('echte login seed faalt: ' . $status . ' ' . $body);
}
[, $status, $body] = $curl($query, $jarReal, ['X-Test-Remote: 203.0.113.8', 'X-Test-Server: 10.1.1.1']);
$decoded = json_decode($body, true);
if ($status !== 200 || !is_array($decoded) || empty($decoded['complete']) || ($decoded['mails'][0]['to'] ?? '') !== 'other@example.com'
    || ($decoded['mails'][0]['lines'][0]['item'] ?? '') !== 'ITEM-HTTP' || str_contains($body, 'SUPERSECRET')
    || str_contains($body, 'niet-exporteren')) {
    fail('echte login krijgt geen minimale export: ' . $status . ' ' . $body);
}
if (filemtime($httpDir . '/.htaccess') !== $beforeHt || !is_file($httpDir . '/.htaccess')) {
    fail('export schrijft de maillogmap');
}
$fresh = sys_get_temp_dir() . '/elpis-maillog-ro-' . getmypid();
mkdir($fresh, 0750);
$GLOBALS['ELPIS_DATA_DIR'] = $fresh;
elpis_maillog_export_response(readerUser(), ['from' => '2026-11-06', 'to' => '2026-11-06', 'item' => 'ITEM-1'], false);
if (is_dir($fresh . '/maillog') || is_file($fresh . '/.htaccess')) {
    fail('een lege datamap wordt door export aangemaakt');
}
$GLOBALS['ELPIS_DATA_DIR'] = $httpRoot;
@unlink($jarPolluted);
@unlink($jarReal);
@unlink($jarLocal);
$cleanup();

echo "OK\n";
