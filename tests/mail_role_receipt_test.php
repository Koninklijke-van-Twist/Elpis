<?php
/**
 * Mail: alleen-besteller stopt bij ontvangst, rollen per regel, sortering en rolkolommen.
 * Run: php tests/mail_role_receipt_test.php
 */

$_SESSION['lang'] = 'nl';
$GLOBALS['ELPIS_TODAY'] = '2026-10-20';
$GLOBALS['ELPIS_DATA_DIR'] = sys_get_temp_dir() . '/elpis-mail-role-' . getmypid();
$GLOBALS['ELPIS_PROJECT_CACHE_DIR'] = $GLOBALS['ELPIS_DATA_DIR'] . '/projects';
@mkdir($GLOBALS['ELPIS_DATA_DIR'], 0750, true);

require dirname(__DIR__) . '/web/elpis_data.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function row(array $extra): array
{
    return array_merge([
        'quantity' => 1,
        'qty_received' => 0,
        'purchase_order_no' => 'PO1',
        'purchase_outstanding_qty' => 1,
        'completely_received' => false,
        'completely_picked' => false,
        'kvt_qty_picked' => 0,
        'free_stock' => 0,
        'inbound_qty' => 0,
        'expected_receipt_date' => '2026-12-01',
        'planning_date' => '2026-12-15',
        'work_order_no' => 'WO',
        'description' => 'Regel',
        'project_no' => 'P',
    ], $extra);
}

$rdb = ['code' => 'RDB', 'name' => 'Ruud de Boer', 'user_id' => 'KVT\\RDBOER', 'email' => 'rdboer@kvt.nl'];
$jvde = ['code' => 'JVDE', 'name' => 'Johan van der Ende', 'user_id' => 'KVT\\JVDENDE', 'email' => 'jvdende@kvt.nl'];

$received = row([
    'item_no' => 'REC1',
    'qty_received' => 1,
    'purchase_outstanding_qty' => 0,
    'completely_received' => true,
    'expected_receipt_date' => '2026-09-01',
]);
$half = row([
    'item_no' => 'HALF1',
    'quantity' => 4,
    'qty_received' => 2,
    'purchase_outstanding_qty' => 2,
    'expected_receipt_date' => '2026-10-01',
    'planning_date' => '2026-11-30',
]);
$open = row([
    'item_no' => 'OPEN1',
    'expected_receipt_date' => '2026-10-05',
    'planning_date' => '2026-11-30',
]);
$stock = row([
    'item_no' => 'STOK1',
    'purchase_order_no' => '',
    'purchase_outstanding_qty' => 0,
    'qty_received' => 0,
    'free_stock' => 3,
    'expected_receipt_date' => '',
]);
$inbound = row([
    'item_no' => 'INB1',
    'qty_received' => 1,
    'purchase_outstanding_qty' => 0,
    'completely_received' => true,
    'inbound_qty' => 1,
    'expected_receipt_date' => '2026-09-15',
]);
$partialReceived = row([
    'item_no' => 'PRECV',
    'quantity' => 2,
    'kvt_qty_picked' => 1,
    'qty_posted' => 1,
    'qty_received' => 2,
    'purchase_outstanding_qty' => 0,
    'completely_received' => true,
    'expected_receipt_date' => '2026-09-20',
]);

foreach ([
    [$received, 'RECEIVED_TO_PICK'],
    [$half, 'DELIVERY_LATE'],
    [$open, 'DELIVERY_LATE'],
    [$stock, 'STOCK'],
    [$inbound, 'INBOUND'],
    [$partialReceived, 'PARTIAL_RECEIVED'],
] as [$line, $want]) {
    $got = elpis_line_status($line)['key'];
    if ($got !== $want) {
        fail($line['item_no'] . " verwacht $want, kreeg $got");
    }
}
if (!elpis_line_is_problem($received) || !elpis_mail_line_past_receipt($received) || elpis_mail_line_past_receipt($half)) {
    fail('scherm blijft ontvangen een probleem; mailgrens ligt tussen ontvangen en half ontvangen');
}

$problems = ['content' => 'problems'];
$onlyBuyer = ['mail_as_purchaser' => true, 'mail_as_manager' => false];
$onlyPm = ['mail_as_purchaser' => false, 'mail_as_manager' => true];
$bothRoles = ['mail_as_purchaser' => true, 'mail_as_manager' => true];

if (elpis_mail_line_included($received + $onlyBuyer, $problems)
    || elpis_mail_line_included($inbound + $onlyBuyer, $problems)
    || elpis_mail_line_included($partialReceived + $onlyBuyer, $problems)) {
    fail('alleen besteller: vanaf ontvangst niet in de mail');
}
if (!elpis_mail_line_included($half + $onlyBuyer, $problems) || !elpis_mail_line_included($open + $onlyBuyer, $problems)) {
    fail('alleen besteller: half ontvangen en nog niet binnen blijven');
}
if (!elpis_mail_line_included($stock + $onlyBuyer, $problems)) {
    fail('op voorraad is geen ontvangst en blijft voor de besteller een probleemregel');
}
if (!elpis_mail_line_included($received + $onlyPm, $problems) || !elpis_mail_line_included($received + $bothRoles, $problems)) {
    fail('PO en dubbelrol houden ontvangen volgens de probleemselectie');
}
if (elpis_mail_line_included($received + $onlyBuyer, ['content' => 'all'])
    || elpis_mail_line_included($received + $onlyBuyer, ['content' => 'custom', 'statuses' => ['RECEIVED_TO_PICK']])) {
    fail('alleen besteller: voorbij ontvangst valt ook bij alles/aangepast af');
}
if (elpis_mail_line_included($received + $bothRoles, ['content' => 'custom', 'statuses' => ['LATE']])) {
    fail('dubbelrol volgt de PO-selectie en laat een niet gekozen status weg');
}
if (!elpis_mail_line_included($open + $onlyBuyer, ['content' => 'all'])) {
    fail('alleen besteller: nog niet ontvangen blijft bij alles');
}

$po = elpis_mail_po_statuses();
$problemKeys = elpis_problem_status_keys();
sort($po);
sort($problemKeys);
if ($po !== ['DELIVERY_LATE', 'INBOUND', 'LATE', 'LEAD_TIME_INVALID', 'NO_DATE', 'ORDER_TOO_LATE', 'PARTIAL_RECEIVED', 'PARTIAL_STOCK', 'RECEIVED_TO_PICK', 'STOCK', 'TO_ORDER']
    || $po !== $problemKeys) {
    fail('PO-preset wijkt af van de probleemstatussen: ' . json_encode($po));
}
if (ELPIS_MAIL_PURCHASER_STATUSES !== ['LEAD_TIME_INVALID', 'LATE', 'DELIVERY_LATE', 'ORDER_TOO_LATE', 'NO_DATE', 'TO_ORDER']) {
    fail('besteller-preset: ' . json_encode(ELPIS_MAIL_PURCHASER_STATUSES));
}
$purchaser = ['content' => 'purchaser'];
if (elpis_mail_line_matches_content($received, $purchaser) || elpis_mail_line_matches_content($inbound, $purchaser)
    || elpis_mail_line_matches_content($partialReceived, $purchaser) || elpis_mail_line_matches_content($stock, $purchaser)) {
    fail('besteller-preset laat magazijn, voorraad en pick weg');
}
if (!elpis_mail_line_matches_content($half, $purchaser) || !elpis_mail_line_matches_content($open, $purchaser)
    || !elpis_mail_line_matches_content($stock, $problems) || !elpis_mail_line_matches_content($received, $problems)) {
    fail('PO-preset houdt voorraad en ontvangen; besteller-preset houdt het leverprobleem');
}
if (elpis_mail_line_included($received + $bothRoles, $purchaser) || elpis_mail_line_included($stock + $onlyPm, $purchaser)) {
    fail('besteller-preset geldt ook op regels waar je projectmanager bent');
}
if (!elpis_mail_line_included($half + $onlyBuyer, $purchaser) || !elpis_mail_line_included($half + $bothRoles, $purchaser)) {
    fail('gedeeltelijk ontvangen met leverprobleem blijft in de besteller-preset');
}
$customPick = ['content' => 'custom', 'statuses' => ['STOCK', 'RECEIVED_TO_PICK']];
if (!elpis_mail_line_included($stock + $onlyBuyer, $customPick) || elpis_mail_line_included($received + $onlyBuyer, $customPick)) {
    fail('aangepast houdt voorraad voor alleen-besteller en haalt de ontvangstgrens niet onderuit');
}
if (!elpis_mail_line_included($received + $bothRoles, ['content' => 'custom', 'statuses' => ['RECEIVED_TO_PICK']])) {
    fail('dubbelrol met aangepast houdt ontvangen-nog-picken');
}
if (elpis_default_mail_prefs()['content'] !== 'problems') {
    fail('default blijft de PO-preset');
}
if (!function_exists('saveUserPref')) {
    fail('saveUserPref ontbreekt');
}
elpis_save_mail_prefs('preset-role@kvt.nl', ['frequency' => 'daily', 'content' => 'purchaser', 'statuses' => ['STOCK']]);
$stored = elpis_mail_prefs_for_email('preset-role@kvt.nl');
if ($stored['content'] !== 'purchaser' || $stored['statuses'] !== []) {
    fail('bewaren besteller-preset: ' . json_encode($stored));
}
saveUserPref('preset-role@kvt.nl', 'elpis_mail_content', 'problems');
saveUserPref('preset-role@kvt.nl', 'elpis_mail_statuses', json_encode(['LATE']));
$legacy = elpis_mail_prefs_for_email('preset-role@kvt.nl');
if ($legacy['content'] !== 'problems' || $legacy['statuses'] !== []
    || !elpis_mail_line_included($received + $onlyPm, $legacy) || !elpis_mail_line_included($stock + $onlyBuyer, $legacy)) {
    fail('oude probleemregels blijven de ruime PO-selectie: ' . json_encode($legacy));
}

$sent = [];
$GLOBALS['ELPIS_MAIL_SENDER'] = static function (string $to, string $subject, string $plain, string $html) use (&$sent): bool {
    $sent[] = compact('to', 'subject', 'plain', 'html');

    return true;
};
$GLOBALS['ELPIS_FETCH_OBSERVER'] = static function (string $company, string $entity): array {
    if ($entity === 'AppUserSetup') {
        return [
            ['User_ID' => 'KVT\\RDBOER', 'Email' => 'rdboer@kvt.nl'],
            ['User_ID' => 'KVT\\JVDENDE', 'Email' => 'jvdende@kvt.nl'],
            ['User_ID' => 'KVT\\XPM', 'Email' => 'xpm@kvt.nl'],
        ];
    }

    return [];
};

$mine = static function () use (&$sent): array {
    return array_values(array_filter($sent, static fn (array $mail): bool => str_contains($mail['plain'], 'rdboer@kvt.nl')));
};

// Alleen besteller.
$sent = [];
elpis_reset_fetch_memo();
elpis_dispatch_pm_digests(['K' => [
    'projects' => [['no' => 'PB', 'description' => 'B', 'project_manager' => 'KVT\\XPM']],
    'lines' => ['PB' => [
        array_merge($open, ['purchasers' => [$rdb]]),
        array_merge($received, ['purchasers' => [$rdb]]),
        array_merge($half, ['purchasers' => [$rdb]]),
    ]],
]], '2026-10-20');
$buyerMail = $mine();
if (count($buyerMail) !== 1) {
    fail('alleen besteller krijgt één mail: ' . count($buyerMail));
}
$buyerPlain = $buyerMail[0]['plain'];
if (substr_count($buyerPlain, '| OPEN1 |') !== 1 || substr_count($buyerPlain, '| HALF1 |') !== 1 || str_contains($buyerPlain, '| REC1 |')) {
    fail('alleen besteller houdt open en half, niet ontvangen: ' . $buyerPlain);
}
if (!str_contains($buyerMail[0]['html'], '>PO<') || str_contains($buyerMail[0]['html'], '>Besteller<') || !str_contains($buyerPlain, '| XPM')) {
    fail('besteller-mail toont de PO en geen bestellerkolom: ' . $buyerPlain);
}

// Alleen PO.
$sent = [];
elpis_reset_fetch_memo();
elpis_dispatch_pm_digests(['K' => [
    'projects' => [['no' => 'PA', 'description' => 'A', 'project_manager' => 'KVT\\RDBOER']],
    'lines' => ['PA' => [array_merge($received, ['purchasers' => [$jvde]])]],
]], '2026-10-20');
$pmMail = $mine();
if (count($pmMail) !== 1 || substr_count($pmMail[0]['plain'], '| REC1 |') !== 1) {
    fail('alleen PO houdt de ontvangen regel: ' . ($pmMail[0]['plain'] ?? ''));
}
if (!str_contains($pmMail[0]['html'], '>Besteller<') || str_contains($pmMail[0]['html'], '>PO<')
    || !str_contains($pmMail[0]['plain'], 'Johan van der Ende (JVDE)')) {
    fail('PO-mail toont de besteller en geen PO-kolom');
}

// Dezelfde regel, dubbelrol: één mail, één regel, ontvangen blijft.
$sent = [];
elpis_reset_fetch_memo();
elpis_dispatch_pm_digests(['K' => [
    'projects' => [['no' => 'PA', 'description' => 'A', 'project_manager' => 'KVT\\RDBOER']],
    'lines' => ['PA' => [array_merge($received, ['purchasers' => [$rdb]])]],
]], '2026-10-20');
$dual = $mine();
if (count($dual) !== 1 || substr_count($dual[0]['plain'], '| REC1 |') !== 1) {
    fail('dubbelrol: één mail en één regel: ' . count($dual));
}
if (!str_contains($dual[0]['html'], '>PO<') || !str_contains($dual[0]['html'], '>Besteller<')) {
    fail('dubbelrol toont beide kolommen');
}

// PO op A, alleen besteller op B.
$sent = [];
elpis_reset_fetch_memo();
elpis_dispatch_pm_digests(['K' => [
    'projects' => [
        ['no' => 'PA', 'description' => 'A', 'project_manager' => 'KVT\\RDBOER'],
        ['no' => 'PB', 'description' => 'B', 'project_manager' => 'KVT\\XPM'],
    ],
    'lines' => [
        'PA' => [array_merge($received, ['item_no' => 'REC-A', 'purchasers' => [$jvde]])],
        'PB' => [
            array_merge($received, ['item_no' => 'REC-B', 'purchasers' => [$rdb]]),
            array_merge($open, ['item_no' => 'OPEN-B', 'purchasers' => [$rdb]]),
        ],
    ],
]], '2026-10-20');
$mixed = $mine();
if (count($mixed) !== 1) {
    fail('gemengde rollen: één mail: ' . count($mixed));
}
$mixedPlain = $mixed[0]['plain'];
if (substr_count($mixedPlain, '| REC-A |') !== 1 || substr_count($mixedPlain, '| OPEN-B |') !== 1 || str_contains($mixedPlain, 'REC-B')) {
    fail('PO-regel A blijft, besteller-ontvangen B valt weg, open B blijft: ' . $mixedPlain);
}
if (!str_contains($mixed[0]['html'], '>PO<') || !str_contains($mixed[0]['html'], '>Besteller<')
    || !str_contains($mixedPlain, 'Johan van der Ende (JVDE)') || !str_contains($mixedPlain, '| XPM')) {
    fail('gemengde rollen tonen PO en besteller');
}

// Sortering: oud naar nieuw, lege datum achteraan, gelijke datum stabiel, per bedrijf.
$dated = static fn (string $item, string $date, string $company): array => row([
    'item_no' => $item,
    'company' => $company,
    'expected_receipt_date' => $date,
    'planning_date' => '2026-12-31',
]);
[, $plain] = elpis_digest_message('KVT\\RDBOER', 'rdboer@kvt.nl', [
    $dated('NEW', '2026-11-02', 'Zaak'),
    $dated('OLD2', '2026-10-01', 'Zaak'),
    $dated('MISS', '', 'Zaak'),
    $dated('OLD1', '2026-10-01', 'Zaak'),
    $dated('BNEW', '2026-11-01', 'Ander'),
    $dated('BOLD', '2026-09-01', 'Ander'),
], true);
$pos = static function (string $item) use ($plain) {
    return strpos($plain, '| ' . $item . ' |');
};
foreach (['OLD2', 'OLD1', 'NEW', 'MISS', 'BOLD', 'BNEW'] as $item) {
    if ($pos($item) === false) {
        fail("sorteerregel $item ontbreekt: $plain");
    }
}
if (!($pos('OLD2') < $pos('OLD1') && $pos('OLD1') < $pos('NEW') && $pos('NEW') < $pos('MISS')
    && $pos('MISS') < $pos('BOLD') && $pos('BOLD') < $pos('BNEW'))) {
    fail('sorteervolgorde: ' . $plain);
}
if (strpos($plain, '== Zaak ==') > strpos($plain, '== Ander ==')) {
    fail('bedrijfsvolgorde blijft de invoer');
}

elpis_save_mail_prefs('rdboer@kvt.nl', ['frequency' => 'daily', 'content' => 'purchaser']);
$sent = [];
elpis_reset_fetch_memo();
elpis_dispatch_pm_digests(['K' => [
    'projects' => [
        ['no' => 'PA', 'description' => 'A', 'project_manager' => 'KVT\\RDBOER'],
        ['no' => 'PB', 'description' => 'B', 'project_manager' => 'KVT\\XPM'],
    ],
    'lines' => [
        'PA' => [
            array_merge($received, ['item_no' => 'REC-PO', 'purchasers' => [$jvde]]),
            array_merge($stock, ['item_no' => 'STOK-PO', 'purchasers' => []]),
        ],
        'PB' => [
            array_merge($half, ['item_no' => 'HALF-B', 'purchasers' => [$rdb]]),
            array_merge($received, ['item_no' => 'REC-B', 'purchasers' => [$rdb]]),
        ],
    ],
]], '2026-10-20');
$presetMails = $mine();
if (count($presetMails) !== 1) {
    fail('besteller-preset: één mail: ' . count($presetMails));
}
$presetPlain = $presetMails[0]['plain'];
if (substr_count($presetPlain, '| HALF-B |') !== 1 || str_contains($presetPlain, 'REC-PO')
    || str_contains($presetPlain, 'STOK-PO') || str_contains($presetPlain, 'REC-B')) {
    fail('besteller-preset houdt alleen het open leverprobleem: ' . $presetPlain);
}

$prefsSrc = (string) file_get_contents(dirname(__DIR__) . '/web/voorkeuren.php');
if (!str_contains($prefsSrc, "LOC(\$def['description'])") || !str_contains($prefsSrc, 'ELPIS_MAIL_CONTENT_PURCHASER')) {
    fail('voorkeuren missen de statusuitleg of de besteller-preset');
}
$receivedHint = LOC(ELPI_LINE_STATUSES['RECEIVED_TO_PICK']['description']);
if ($receivedHint !== 'Ontvangen of gereserveerd voor dit project (niet uit vrije voorraad), maar nog niet gepicked.') {
    fail('uitleg komt uit de statusberekening: ' . $receivedHint);
}
foreach (ELPI_LINE_STATUSES as $key => $def) {
    $text = LOC($def['description']);
    if ($text === '' || $text === $def['description']) {
        fail("geen uitleg voor $key");
    }
}

echo "OK\n";
