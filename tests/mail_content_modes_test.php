<?php
/**
 * Inhoud van de mail: opslag, mapping van oude keuzes en regelselectie per modus.
 * Run: php tests/mail_content_modes_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-modes-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';
require_once dirname(__DIR__) . '/web/elpis_mail.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

// Mapping
$red = elpis_mail_red_statuses();
sort($red);
if ($red !== ['DELIVERY_LATE', 'LATE', 'ORDER_TOO_LATE']) {
    fail('rode statussen: ' . json_encode($red));
}
$cases = [
    [null, [], 'problems', []],
    ['', [], 'problems', []],
    ['both', [], 'problems', []],
    ['onzin', [], 'problems', []],
    ['late', [], 'custom', elpis_mail_red_statuses()],
    ['unconfirmed', [], 'custom', ['NO_DATE']],
    ['all', [], 'all', []],
    ['problems', ['LATE'], 'problems', []],
    ['custom', [], 'problems', []],
    ['custom', ['BESTAAT_NIET'], 'problems', []],
    ['custom', ['NO_DATE', 'LATE', 'BESTAAT_NIET'], 'custom', null],
];
foreach ($cases as [$in, $st, $wantContent, $wantStatuses]) {
    [$c, $s] = elpis_mail_normalize_content($in, $st);
    if ($c !== $wantContent || ($wantStatuses !== null && $s !== $wantStatuses)) {
        fail('mapping ' . json_encode([$in, $st]) . ' -> ' . json_encode([$c, $s]));
    }
    if ($wantStatuses === null && (count($s) !== 2 || !in_array('LATE', $s, true) || !in_array('NO_DATE', $s, true))) {
        fail('custom houdt alleen geldige statussen: ' . json_encode($s));
    }
}

// Opslag (round-trip) en migratie bij lezen zonder data te wissen
if (function_exists('saveUserPref')) {
    $email = 'modes-test@kvt.nl';
    elpis_save_mail_prefs($email, ['frequency' => 'daily', 'content' => 'custom', 'statuses' => ['NO_DATE', 'LATE']]);
    $p = elpis_mail_prefs_for_email($email);
    if ($p['content'] !== 'custom' || !in_array('NO_DATE', $p['statuses'], true) || !in_array('LATE', $p['statuses'], true) || count($p['statuses']) !== 2) {
        fail('round-trip aangepast: ' . json_encode($p));
    }
    elpis_save_mail_prefs($email, ['frequency' => 'daily', 'content' => 'all']);
    if (elpis_mail_prefs_for_email($email)['content'] !== 'all') {
        fail('round-trip alles');
    }
    saveUserPref($email, 'elpis_mail_content', 'unconfirmed');
    $p = elpis_mail_prefs_for_email($email);
    if ($p['content'] !== 'custom' || $p['statuses'] !== ['NO_DATE']) {
        fail('legacy onbevestigd: ' . json_encode($p));
    }
    if ((loadUserPrefs($email)['elpis_mail_content'] ?? '') !== 'unconfirmed') {
        fail('migratie mag opgeslagen waarde niet overschrijven');
    }
    saveUserPref($email, 'elpis_mail_content', 'late');
    if (elpis_mail_prefs_for_email($email)['statuses'] !== elpis_mail_red_statuses()) {
        fail('legacy te laat');
    }
} else {
    fail('saveUserPref ontbreekt');
}

// Selectie per modus met echte regels (status via elpis_line_status)
$base = ['job_task_no' => '1', 'line_no' => 1, 'item_no' => 'X', 'quantity' => 1, 'qty_to_order' => 0, 'qty_ordered' => 1,
    'qty_open' => 1, 'qty_received' => 0, 'purchase_order_no' => 'PO1', 'purchase_order_line_no' => 1, 'work_order_no' => '',
    'completely_received' => false, 'completely_picked' => false, 'kvt_qty_picked' => 0, 'expected_receipt_date' => '',
    'planning_date' => '2026-11-30', 'receipt_state' => '', 'received_date' => ''];
$lines = [
    'NO_DATE' => $base,
    'RETURN' => array_merge($base, ['quantity' => 0]),
    'LATE' => array_merge($base, ['expected_receipt_date' => '2026-10-01', 'planning_date' => '2026-10-05']),
    'ORDERED' => array_merge($base, ['expected_receipt_date' => '2026-11-01']),
    'PICKED' => array_merge($base, ['completely_received' => true, 'completely_picked' => true, 'qty_received' => 1, 'qty_open' => 0, 'kvt_qty_picked' => 1]),
];
foreach ($lines as $k => $line) {
    if (elpis_line_status($line)['key'] !== $k) {
        fail("testregel $k heeft status " . elpis_line_status($line)['key']);
    }
}
$pick = static function ($prefs) use ($lines): array {
    return array_keys(array_filter($lines, static fn ($l) => elpis_mail_line_matches_content($l, $prefs)));
};
$expect = [
    'alles' => [['content' => 'all'], ['NO_DATE', 'LATE', 'ORDERED', 'PICKED']],
    'probleemregels' => [['content' => 'problems'], ['NO_DATE', 'LATE']],
    'aangepast' => [['content' => 'custom', 'statuses' => ['ORDERED', 'RETURN']], ['RETURN', 'ORDERED']],
    'legacy beide' => ['both', ['NO_DATE', 'LATE']],
    'legacy te laat' => ['late', ['LATE']],
    'legacy onbevestigd' => ['unconfirmed', ['NO_DATE']],
];
foreach ($expect as $name => [$prefs, $want]) {
    $got = $pick($prefs);
    if ($got !== $want) {
        fail("$name: " . json_encode($got));
    }
}

echo "OK mail_content_modes_test\n";
