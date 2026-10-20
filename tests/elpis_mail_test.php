<?php
/**
 * E-mailvoorkeuren, testmodus en nightly-lock.
 * Run: php tests/elpis_mail_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-mail-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

$defaults = elpis_default_mail_prefs();
if ($defaults['frequency'] !== 'daily' || $defaults['content'] !== 'both') {
    fail('standaard is dagelijks en beide');
}

$monday = '2026-10-19';
$tuesday = '2026-10-20';
if (!elpis_mail_should_send_on(['frequency' => 'weekly', 'weekdays' => [1], 'content' => 'both'], $monday)) {
    fail('wekelijks hoort op maandag');
}
if (elpis_mail_should_send_on(['frequency' => 'weekly', 'weekdays' => [1], 'content' => 'both'], $tuesday)) {
    fail('wekelijks slaat dinsdag over');
}
if (!elpis_mail_should_send_on(['frequency' => 'custom', 'weekdays' => [2], 'content' => 'both'], $tuesday)) {
    fail('aangepast volgt de aangevinkte dinsdag');
}
if (elpis_mail_should_send_on(['frequency' => 'custom', 'weekdays' => [2], 'content' => 'both'], $monday)) {
    fail('aangepast stuurt niet op een uitgevinkte dag');
}
if (elpis_mail_should_send_on(['frequency' => 'never', 'weekdays' => [1, 2, 3, 4, 5], 'content' => 'both'], $tuesday)) {
    fail('nooit stuurt geen mail');
}

$late = ['receipt_state' => 'late', 'project_no' => 'PRJ1', 'item_no' => 'A', 'description' => 'Laat', 'work_order_no' => 'WO1', 'expected_receipt_date' => '2026-10-01'];
$open = ['receipt_state' => 'unconfirmed', 'project_no' => 'PRJ1', 'item_no' => 'B', 'description' => 'Open', 'work_order_no' => 'WO2', 'expected_receipt_date' => ''];
$ok = ['receipt_state' => 'date', 'project_no' => 'PRJ1', 'item_no' => 'C', 'description' => 'Ok', 'expected_receipt_date' => '2026-11-01'];
if (!elpis_mail_line_matches_content($late, 'late') || elpis_mail_line_matches_content($open, 'late') || elpis_mail_line_matches_content($ok, 'both')) {
    fail('inhoudsfilter houdt alleen te laat en onbevestigd');
}
if (!elpis_mail_line_matches_content($late, 'both') || !elpis_mail_line_matches_content($open, 'both')) {
    fail('beide neemt te laat en onbevestigd mee');
}

$config = elpis_mail_config();
if (!is_file(elpis_mail_config_path())) {
    fail('mailconfig wordt aangemaakt');
}
if (empty($config['test_mode']) || $config['test_recipient'] !== 'tfalken@kvt.nl') {
    fail('testmodus staat standaard aan naar tfalken@kvt.nl');
}

$sent = [];
$GLOBALS['ELPIS_MAIL_SENDER'] = static function (string $to, string $subject, string $plain, string $html) use (&$sent): bool {
    $sent[] = compact('to', 'subject', 'plain', 'html');

    return true;
};
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity, array $query): array {
    if ($entity === 'AppUserSetup') {
        return [['User_ID' => 'KVT\\RDBOER', 'Email' => 'rdboer@kvt.nl']];
    }

    return [];
};

$bundle = [
    'Koninklijke van Twist' => [
        'projects' => [[
            'no' => 'PRJ2606522',
            'description' => 'Test',
            'project_manager' => 'KVT\\RDBOER',
        ]],
        'lines' => [
            'PRJ2606522' => [$late, $open, $ok],
        ],
    ],
];
elpis_reset_fetch_memo();
$result = elpis_dispatch_pm_digests($bundle, $tuesday);
if (($result['sent'] ?? 0) !== 1 || count($sent) !== 1) {
    fail('een pm met treffers krijgt één mail: ' . json_encode($result));
}
if ($sent[0]['to'] !== 'tfalken@kvt.nl') {
    fail('testmodus stuurt niet naar de pm: ' . $sent[0]['to']);
}
if (!str_contains($sent[0]['subject'], '[TEST]') || !str_contains($sent[0]['plain'], 'rdboer@kvt.nl')) {
    fail('testmail noemt de bedoelde ontvanger');
}
if (!str_contains($sent[0]['plain'], 'PI') && !str_contains($sent[0]['plain'], 'A |') && !str_contains($sent[0]['html'], 'voorkeuren.php')) {
    fail('de mail linkt naar de voorkeuren: ' . $sent[0]['html']);
}
if (!str_contains($sent[0]['plain'], 'voorkeuren.php')) {
    fail('platte mail linkt naar voorkeuren');
}
if (str_contains($sent[0]['plain'], '| C |')) {
    fail('een regel met datum hoort niet in de mail');
}

$sent = [];
elpis_save_mail_prefs('rdboer@kvt.nl', ['frequency' => 'never', 'content' => 'both', 'weekdays' => [2]]);
elpis_reset_fetch_memo();
$skipped = elpis_dispatch_pm_digests($bundle, $tuesday);
if (($skipped['sent'] ?? 0) !== 0 || $sent !== []) {
    fail('nooit onderdrukt de mail');
}

elpis_save_mail_prefs('rdboer@kvt.nl', ['frequency' => 'daily', 'content' => 'unconfirmed', 'weekdays' => [1]]);
$sent = [];
elpis_reset_fetch_memo();
$onlyOpen = elpis_dispatch_pm_digests($bundle, $tuesday);
if (($onlyOpen['sent'] ?? 0) !== 1 || !str_contains($sent[0]['plain'], 'B') || str_contains($sent[0]['plain'], '| A |')) {
    fail('alleen onbevestigd laat te laat weg: ' . ($sent[0]['plain'] ?? ''));
}

file_put_contents(elpis_mail_config_path(), json_encode(['test_mode' => false, 'test_recipient' => 'tfalken@kvt.nl']));
$sent = [];
elpis_reset_fetch_memo();
$live = elpis_dispatch_pm_digests($bundle, $tuesday);
if (($sent[0]['to'] ?? '') !== 'rdboer@kvt.nl' || str_contains($sent[0]['subject'] ?? '', '[TEST]')) {
    fail('uitgezette testmodus mailt de projectmanager: ' . json_encode($sent));
}

elpis_nightly_write_state(['date' => '2026-10-20', 'status' => 'ok']);
if (elpis_nightly_needs_run()) {
    fail('een geslaagde run van vandaag hoeft niet opnieuw');
}
elpis_nightly_write_state(['date' => '2026-10-19', 'status' => 'ok']);
if (!elpis_nightly_needs_run()) {
    fail('een run van gisteren is verlopen');
}

$GLOBALS['ELPIS_NIGHTLY_WORK'] = static function (): array {
    return ['by_company' => [], 'companies' => 1];
};
$GLOBALS['ELPIS_MAIL_SENDER'] = static function (): bool {
    return true;
};
$first = elpis_run_nightly(false);
if (($first['status'] ?? '') !== 'ok') {
    fail('nightly met testhaak slaagt: ' . json_encode($first));
}
$second = elpis_run_nightly(false);
if (($second['status'] ?? '') !== 'fresh') {
    fail('tweede run op dezelfde dag slaat over: ' . json_encode($second));
}

$lockPath = elpis_data_dir() . '/nightly.lock';
$readyPath = elpis_data_dir() . '/lock-ready';
@unlink($readyPath);
$holder = proc_open(
    [PHP_BINARY, '-r', '$path=$argv[1];$ready=$argv[2];$h=fopen($path,"c");if($h===false||!flock($h,LOCK_EX|LOCK_NB)){fwrite(STDERR,"lock fail\n");exit(2);}file_put_contents($ready,"1");sleep(20);flock($h,LOCK_UN);fclose($h);', $lockPath, $readyPath],
    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
    $pipes
);
if (!is_resource($holder)) {
    fail('lockproces start niet');
}
fclose($pipes[0]);
$deadline = microtime(true) + 5;
while (!is_file($readyPath) && microtime(true) < $deadline) {
    usleep(20000);
}
if (!is_file($readyPath)) {
    $err = stream_get_contents($pipes[2]);
    proc_terminate($holder);
    proc_close($holder);
    fail('lockproces houdt het bestand niet vast: ' . $err);
}
$busy = elpis_run_nightly(true);
proc_terminate($holder);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($holder);
if (($busy['status'] ?? '') !== 'running') {
    fail('een bezette lock start geen tweede nightly: ' . json_encode($busy));
}

echo "OK\n";
