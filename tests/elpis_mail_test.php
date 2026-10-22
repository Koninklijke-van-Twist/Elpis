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

$mailLines = elpis_apply_receipt_expectations(['PRJ1' => [
    [
        'job_task_no' => '200-000-050',
        'line_no' => 10000,
        'item_no' => 'PI20001186',
        'description' => 'Ontvangen via inkoop',
        'quantity' => 2,
        'qty_received' => 0,
        'purchase_order_no' => 'PO12601846',
        'purchase_order_line_no' => 10000,
        'work_order_no' => '4066161',
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'expected_receipt_date' => '2026-10-01',
        'material_status' => 'I',
        'receipt_state' => '',
        'received_date' => '',
    ],
    [
        'job_task_no' => '200-000-050',
        'line_no' => 20000,
        'item_no' => 'PI20000169',
        'description' => 'Ontvangen via post',
        'quantity' => 3,
        'qty_received' => 0,
        'purchase_order_no' => '',
        'purchase_order_line_no' => 0,
        'work_order_no' => '153390',
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'expected_receipt_date' => '2026-10-01',
        'material_status' => 'I',
        'receipt_state' => '',
        'received_date' => '',
    ],
    [
        'job_task_no' => '200-000-050',
        'line_no' => 30000,
        'item_no' => 'PI24003010',
        'description' => 'Nog open',
        'quantity' => 2,
        'qty_received' => 0,
        'purchase_order_no' => 'PO12609901',
        'purchase_order_line_no' => 10000,
        'work_order_no' => '153390',
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'expected_receipt_date' => '2026-10-01',
        'material_status' => 'I',
        'receipt_state' => '',
        'received_date' => '',
    ],
]], [], [[
    'document_no' => 'PO12601846',
    'line_no' => 10000,
    'item_no' => 'PI20001186',
    'completely_received' => true,
    'outstanding_qty' => 0,
], [
    'document_no' => 'PO12609901',
    'line_no' => 10000,
    'item_no' => 'PI24003010',
    'completely_received' => false,
    'outstanding_qty' => 2,
]], [], [
    'receipts' => [[
        'Job_No' => 'PRJ1',
        'Job_Task_No' => '200-000-050',
        'Item_No' => 'PI20000169',
        'Posting_Date' => '2026-10-09',
        'Quantity' => 3,
    ]],
]);
$mailed = [];
foreach ($mailLines['PRJ1'] as $mailLine) {
    $mailLine['project_no'] = 'PRJ1';
    if (elpis_mail_line_matches_content($mailLine, 'both')) {
        $mailed[] = $mailLine;
    }
}
[$mailSubject, $mailPlain, $mailHtml] = elpis_digest_message('KVT\\RDBOER', 'rdboer@kvt.nl', $mailed, true);
if ($mailSubject === '' || count($mailed) !== 1 || !str_contains($mailPlain, 'PI24003010') || str_contains($mailPlain, 'PI20001186') || str_contains($mailPlain, 'PI20000169')) {
    fail('PM-mail slaat volledig ontvangen regels over: ' . $mailPlain);
}
if (!str_contains($mailPlain, '2 weken te laat') || !str_contains($mailPlain, 'Te laat')) {
    fail('te late inkoopregel noemt de duur vanaf de verwachte ontvangstdatum: ' . $mailPlain);
}
if (stripos($mailHtml, '<style') !== false || !str_contains($mailHtml, 'background-color:#fdebec') || !str_contains($mailHtml, 'color:#b42318') || !str_contains($mailHtml, 'background-color:#00529B')) {
    fail('mail gebruikt inline stijl zoals de site, zonder style-blok');
}
if (!str_contains($mailHtml, 'Montserrat') || !str_contains($mailHtml, 'Toelichting') || !str_contains($mailHtml, '2 weken te laat')) {
    fail('mailtabel heeft het sitelettertype en een toelichting');
}

if (elpis_mail_lateness_text(1, 'day') !== '1 dag te laat' || elpis_mail_lateness_text(8, 'day') !== '8 dagen te laat') {
    fail('dagen hebben enkelvoud en meervoud');
}
if (elpis_mail_lateness_text(1, 'week') !== '1 week te laat' || elpis_mail_lateness_text(3, 'week') !== '3 weken te laat') {
    fail('weken hebben enkelvoud en meervoud');
}
if (elpis_mail_lateness_text(1, 'month') !== '1 maand te laat' || elpis_mail_lateness_text(2, 'month') !== '2 maanden te laat') {
    fail('maanden hebben enkelvoud en meervoud');
}
if (elpis_mail_lateness_for_days(1) !== '1 dag te laat' || elpis_mail_lateness_for_days(14) !== '14 dagen te laat') {
    fail('tot 14 dagen blijft het dagen');
}
if (elpis_mail_lateness_for_days(15) !== '2 weken te laat' || elpis_mail_lateness_for_days(21) !== '3 weken te laat' || elpis_mail_lateness_for_days(59) !== '8 weken te laat') {
    fail('na 14 dagen tot ongeveer 2 maanden wordt het weken: ' . elpis_mail_lateness_for_days(15) . ' / ' . elpis_mail_lateness_for_days(59));
}
if (elpis_mail_lateness_for_days(60) !== '2 maanden te laat' || elpis_mail_lateness_for_days(90) !== '3 maanden te laat') {
    fail('vanaf ongeveer 2 maanden wordt het maanden: ' . elpis_mail_lateness_for_days(60) . ' / ' . elpis_mail_lateness_for_days(90));
}

$oneDay = elpis_mail_join_reasons(elpis_mail_line_reasons([
    'expected_receipt_date' => '2026-10-19',
    'material_status' => 'I',
    'receipt_state' => '',
]));
$twoWeeks = elpis_mail_join_reasons(elpis_mail_line_reasons([
    'expected_receipt_date' => '2026-10-01',
    'material_status' => 'I',
    'receipt_state' => 'late',
]));
$eightWeeks = elpis_mail_join_reasons(elpis_mail_line_reasons([
    'expected_receipt_date' => '2026-08-22',
    'material_status' => 'I',
    'receipt_state' => '',
]));
$twoMonths = elpis_mail_join_reasons(elpis_mail_line_reasons([
    'expected_receipt_date' => '2026-08-21',
    'material_status' => 'I',
    'receipt_state' => '',
]));
if ($oneDay !== '1 dag te laat' || $twoWeeks !== '2 weken te laat' || $eightWeeks !== '8 weken te laat' || $twoMonths !== '2 maanden te laat') {
    fail('duur telt vanaf de verwachte ontvangstdatum van Te laat: ' . json_encode([$oneDay, $twoWeeks, $eightWeeks, $twoMonths]));
}
$missing = elpis_mail_join_reasons(elpis_mail_line_reasons($open));
if ($missing !== 'Verwachte ontvangstdatum ontbreekt') {
    fail('ontbrekende datum krijgt een toelichting: ' . $missing);
}
$separated = elpis_mail_join_reasons([
    LOC('elpis.mail.reason.missing_date'),
    elpis_mail_lateness_text(1, 'week'),
]);
if ($separated !== 'Verwachte ontvangstdatum ontbreekt; 1 week te laat') {
    fail('meerdere redenen staan gescheiden: ' . $separated);
}
$reasonHtml = elpis_mail_reasons_html([
    LOC('elpis.mail.reason.missing_date'),
    elpis_mail_lateness_text(1, 'month'),
], "font-family:Montserrat,'Segoe UI',Arial,sans-serif;font-size:14px;color:#111827;");
if (!str_contains($reasonHtml, 'Verwachte ontvangstdatum ontbreekt') || !str_contains($reasonHtml, '1 maand te laat') || substr_count($reasonHtml, '<tr>') < 2) {
    fail('meerdere redenen staan in de mail los van elkaar');
}

[$sampleSubject, $samplePlain, $sampleHtml] = elpis_digest_message('KVT\\RDBOER', 'rdboer@kvt.nl', [$late, $open], true);
if (!str_contains($samplePlain, 'PRJ1 | WO1 | A | Laat | Te laat | 2 weken te laat') || !str_contains($samplePlain, 'Verwachte ontvangstdatum ontbreekt')) {
    fail('platte mail toont Te laat en de toelichting: ' . $samplePlain);
}
if (!str_contains($sampleHtml, 'bgcolor="#fdebec"') || !str_contains($sampleHtml, 'Datum ontbreekt') || str_contains($sampleHtml, '| C |')) {
    fail('te late regel is rood gemarkeerd en de onbevestigde regel blijft in de mail');
}
if ($sampleSubject === '' || !str_starts_with($sampleSubject, '[TEST] ')) {
    fail('voorbeeldmail blijft in testmodus');
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
$pc = $result['per_company']['Koninklijke van Twist'] ?? [];
if (($pc['managers'] ?? 0) !== 1 || ($pc['recipients'] ?? 0) !== 1 || ($pc['lines'] ?? 0) !== 3
    || ($pc['lines_matched'] ?? 0) < 1 || ($pc['sent'] ?? 0) !== 1 || ($pc['no_email'] ?? -1) !== 0
    || !is_array($pc['receipt_states'] ?? null)) {
    fail('per-bedrijf telling klopt niet: ' . json_encode($pc));
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

// Nightly zet de lege-resultaat-retry aan (max_age blijft 14400), en daarna weer uit.
$GLOBALS['ELPIS_NIGHTLY_RETRY_EMPTY'] = false;
$retrySeen = null;
elpis_nightly_warm_live(static function () use (&$retrySeen): array {
    $retrySeen = !empty($GLOBALS['ELPIS_NIGHTLY_RETRY_EMPTY']) && empty($GLOBALS['ELPIS_LIVE_FETCH']);
    return ['by_company' => []];
});
if ($retrySeen !== true) {
    fail('nightly moet de lege-retry aanzetten zonder volledig live te gaan');
}
if (!empty($GLOBALS['ELPIS_NIGHTLY_RETRY_EMPTY'])) {
    fail('nightly zet ELPIS_NIGHTLY_RETRY_EMPTY na afloop terug');
}

echo "OK\n";
