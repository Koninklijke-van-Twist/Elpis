<?php
declare(strict_types=1);
require_once __DIR__ . '/../web/elpis_data.php';
$fail = 0;
$info = elpis_line_status_info();
if (count($info) !== count(ELPI_LINE_STATUSES)) { echo "FAIL count\n"; $fail++; }
foreach (ELPI_LINE_STATUSES as $key => $def) {
    if (empty($def['description'])) { echo "FAIL no description $key\n"; $fail++; continue; }
}
foreach ($info as $row) {
    if ($row['description'] === '' || str_starts_with($row['description'], 'elpis.')) { echo "FAIL untranslated {$row['key']}\n"; $fail++; }
}
$loc = file_get_contents(__DIR__ . '/../web/localization.php');
foreach (ELPI_LINE_STATUSES as $key => $def) {
    if (substr_count($loc, "'" . $def['description'] . "' =>") < 2) { echo "FAIL NL/EN missing {$def['description']}\n"; $fail++; }
}
$idx = file_get_contents(__DIR__ . '/../web/index.php');
foreach (['elpis-status-info-btn', 'aria-controls="elpis-status-info"', 'elpis_line_status_info()', "'Escape'"] as $needle) {
    if (!str_contains($idx, $needle)) { echo "FAIL index missing $needle\n"; $fail++; }
}
echo $fail ? "status_info_test: $fail failures\n" : "status_info_test: OK\n";
exit($fail ? 1 : 0);
