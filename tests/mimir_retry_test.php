<?php
/**
 * Een tijdelijke Mímir HTTP 500 wordt opnieuw geprobeerd en opent het circuit niet.
 * Run: php tests/mimir_retry_test.php
 */

$mimirApi = 'mimir_test_key';
$mimirBase = 'http://mimir.invalid/api';

require dirname(__DIR__) . '/web/odata.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

$sleeps = [];
$GLOBALS['ELPIS_MIMIR_RETRY_SLEEP'] = static function (int $attempt) use (&$sleeps): void {
    $sleeps[] = $attempt;
};

// 1) 500, 500, 200: rijen terug, circuit dicht.
$responses = [[500, ''], [500, ''], [200, '{"value":[{"No":"P1"}]}']];
$calls = 0;
$GLOBALS['ELPIS_MIMIR_TRANSPORT'] = static function () use (&$responses, &$calls): array {
    $calls++;
    return array_shift($responses);
};
$rows = odata_mimir_query_impl('Hunter van Twist', 'AppProjecten', ['$select' => 'No'], 0);
if ($rows !== [['No' => 'P1']]) {
    fail('verwachtte rij na retry, kreeg ' . json_encode($rows));
}
if ($calls !== 3 || $sleeps !== [1, 2]) {
    fail('verwachtte 3 pogingen en 2 pauzes, kreeg ' . $calls . ' / ' . json_encode($sleeps));
}
if (odata_mimir_circuit_open()) {
    fail('circuit mag niet open na een herstelde 500');
}

// 2) 400 wordt niet herhaald.
$responses = [[400, '{"error":"bad"}'], [200, '{"value":[]}']];
$calls = 0;
try {
    odata_mimir_query_impl('Hunter van Twist', 'AppProjecten', [], 0);
    fail('400 moet een exception geven');
} catch (Exception $e) {
}
if ($calls !== 1) {
    fail('400 mag niet herhaald worden, pogingen=' . $calls);
}

// 3) Aanhoudende 500: na 3 pogingen circuit open.
$responses = [[500, ''], [500, ''], [500, ''], [200, '{"value":[]}']];
$calls = 0;
try {
    odata_mimir_query_impl('Hunter van Twist', 'AppProjecten', [], 0);
    fail('aanhoudende 500 moet een exception geven');
} catch (Exception $e) {
}
if ($calls !== 3 || !odata_mimir_circuit_open()) {
    fail('aanhoudende 500: verwachtte 3 pogingen en open circuit, kreeg ' . $calls);
}

echo "ok\n";
