<?php
/**
 * Simuleert een onbereikbare Mímir en controleert de directe BC-fallback.
 * Run: php tests/mimir_fallback_test.php
 */

$logFile = sys_get_temp_dir() . '/elpis-mimir-fallback-test.log';
@unlink($logFile);
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];

$calls = [];
$GLOBALS['ELPIS_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    $isEntity = preg_match("#/ODataV4/Company\\(#", $url) === 1
        || preg_match('#/ODataV4/Company%28#i', $url) === 1;
    $isCompanyList = preg_match('#/ODataV4/Compan#', $url) === 1;
    if ($isCompanyList && !$isEntity) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};

require dirname(__DIR__) . '/web/odata.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function fallback_log(): string
{
    global $logFile;
    $raw = @file_get_contents($logFile);
    return is_string($raw) ? $raw : '';
}

function fallback_count(): int
{
    return substr_count(fallback_log(), '[Elpis] Mímir failed, falling back to direct OData:');
}

if (odata_mimir_connect_timeout_seconds() !== 10) {
    fail('connect-timeout moet 10s zijn');
}
if (odata_mimir_timeout_seconds_for_sapi('cli') !== 600) {
    fail('CLI-timeout moet 600s blijven');
}
if (odata_mimir_timeout_seconds_for_sapi('fpm-fcgi') !== 90 || odata_mimir_timeout_seconds_for_sapi('apache2handler') !== 90) {
    fail('web-timeout moet ongeveer 90s zijn');
}
if (PHP_SAPI === 'cli' && odata_mimir_timeout_seconds() !== 600) {
    fail('huidige CLI-sapi moet de lange timeout gebruiken');
}

$names = odata_mimir_list_companies(null);
$expectedNames = ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas'];
if ($names !== $expectedNames) {
    fail('company-fallback gaf ' . json_encode($names) . ' i.p.v. de gesorteerde BC-namen');
}
if (!odata_mimir_circuit_open()) {
    fail('circuit moet open na de eerste Mímir-fout');
}
if (count($calls) !== 1 || strpos($calls[0]['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('company-fallback riep de directe BC-fetch niet aan: ' . json_encode($calls));
}
if ($calls[0]['user'] !== 'bcuser') {
    fail('company-fallback gebruikte niet de BC-credentials');
}

$mimirBase = 'http://192.0.2.1:9';
$started = microtime(true);
$rows = odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    120
);
$elapsed = microtime(true) - $started;
if ($elapsed >= 2.0) {
    fail('circuit breaker sloeg Mímir niet over (' . round($elapsed, 3) . 's)');
}
if (($rows[0]['No'] ?? '') !== 'WO-1') {
    fail('entity-fallback gaf niet de gestubde BC-rijen terug');
}
$entityCall = $calls[1] ?? null;
$expectedEntityUrl = "https://bc.example:7148/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($entityCall) || $entityCall['url'] !== $expectedEntityUrl || $entityCall['user'] !== 'bcuser' || $entityCall['ttl'] !== 120) {
    fail('entity-fallback URL/auth/ttl klopt niet: ' . json_encode($entityCall));
}
if (fallback_count() !== 1) {
    fail('alleen de eerste Mímir-fout mag een fallback loggen, log=' . fallback_log());
}
$log = fallback_log();
if (strpos($log, 'mimir_test_key_should_not_leak') !== false || strpos($log, 'bc-secret') !== false) {
    fail('log bevat een geheim');
}
if (strpos($log, '[Elpis] Mímir failed, falling back to direct OData:') === false) {
    fail('logregel mist het verwachte prefix');
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeQuery = count($calls);
$queryRows = odata_mimir_query('KVT Gas', 'AppResource', ['$select' => 'No,Name'], 60);
if (($queryRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_query viel niet terug op de stub');
}
$queryCall = $calls[$beforeQuery] ?? null;
if (!is_array($queryCall) || strpos($queryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppResource?") !== 0) {
    fail('query-fallback bouwde niet de pre-Mímir BC-URL: ' . json_encode($queryCall));
}

odata_mimir_circuit_reset();
$beforeFetch = count($calls);
$fetchRows = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No",
    15
);
if (($fetchRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_fetch_all viel niet terug');
}
$fetchCall = $calls[$beforeFetch] ?? null;
if (!is_array($fetchCall) || $fetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No") {
    fail('fetch_all-fallback herschreef de URL niet: ' . json_encode($fetchCall));
}

odata_mimir_circuit_reset();
$map = odata_mimir_company_environment_map(null);
if (($map['Hunter van Twist'] ?? '') !== 'Production' || ($map['KVT Gas'] ?? '') !== 'Production') {
    fail('environment-map viel niet terug op BC: ' . json_encode($map));
}

$sandboxAuth = ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret'];
$myEnvAuth = ['mode' => 'basic', 'user' => 'myenv-user', 'pass' => 'myenv-secret'];
$auth_list = [
    'Production' => $auth,
    'Sandbox' => $sandboxAuth,
    'My Env' => $myEnvAuth,
];
$GLOBALS['demeter_company_environment_map'] = [
    'Hunter van Twist' => 'Sandbox',
    'KVT Gas' => 'Production',
];

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$loggedBeforeSecondEnv = fallback_count();
$beforeSpaced = count($calls);
$spacedRows = odata_get_all(
    "https://mimir.invalid/My%20Env/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    10
);
if (($spacedRows[0]['No'] ?? '') !== 'WO-1') {
    fail('gespatieerde environment viel niet terug op de stub');
}
$spacedCall = $calls[$beforeSpaced] ?? null;
$expectedSpacedUrl = "https://bc.example:7148/My%20Env/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($spacedCall) || $spacedCall['url'] !== $expectedSpacedUrl || $spacedCall['user'] !== 'myenv-user') {
    fail('environment-segment werd niet precies één keer geëncodeerd: ' . json_encode($spacedCall));
}
if (is_array($spacedCall) && strpos($spacedCall['url'], 'My%2520Env') !== false) {
    fail('environment-segment is dubbel geëncodeerd');
}

$beforeSandbox = count($calls);
$sandboxRows = odata_get_all(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppProjecten?\$select=No",
    $auth,
    11
);
if (($sandboxRows[0]['No'] ?? '') !== 'WO-1') {
    fail('Sandbox-URL viel niet terug op de stub');
}
$sandboxCall = $calls[$beforeSandbox] ?? null;
if (!is_array($sandboxCall)
    || strpos($sandboxCall['url'], "https://bc.example:7148/Sandbox/ODataV4/Company(") !== 0
    || $sandboxCall['user'] !== 'sandbox-user') {
    fail('URL-segment Sandbox koos niet de bijbehorende auth: ' . json_encode($sandboxCall));
}

$beforeMapped = count($calls);
$mappedRows = odata_mimir_query('Hunter van Twist', 'AppProjecten', ['$select' => 'No'], 12);
if (($mappedRows[0]['No'] ?? '') !== 'WO-1') {
    fail('query voor een bedrijf in de tweede environment viel niet terug');
}
$mappedCall = $calls[$beforeMapped] ?? null;
if (!is_array($mappedCall)
    || strpos($mappedCall['url'], "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppProjecten?") !== 0
    || $mappedCall['user'] !== 'sandbox-user') {
    fail('company-map koos niet Sandbox: ' . json_encode($mappedCall));
}

$beforeMimirSegment = count($calls);
$mimirSegmentRows = odata_get_all(
    "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    13
);
if (($mimirSegmentRows[0]['No'] ?? '') !== 'WO-1') {
    fail('mimir-segment viel niet terug via de company-map');
}
$mimirSegmentCall = $calls[$beforeMimirSegment] ?? null;
$expectedMappedUrl = "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($mimirSegmentCall) || $mimirSegmentCall['url'] !== $expectedMappedUrl || $mimirSegmentCall['user'] !== 'sandbox-user') {
    fail('mimir-segment werd niet herschreven naar de company-environment: ' . json_encode($mimirSegmentCall));
}
if (fallback_count() !== $loggedBeforeSecondEnv + 1) {
    fail('na het openen van het circuit mag niet opnieuw gelogd worden, log=' . fallback_log());
}

$auth_list = ['Production' => $auth];
$callsBeforeReject = count($calls);
$loggedBeforeReject = fallback_count();
$rejectedUrl = false;
try {
    odata_get_all(
        "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppProjecten?\$select=No",
        $auth,
        11
    );
} catch (Throwable $exception) {
    $rejectedUrl = true;
    if ($exception->getMessage() !== 'Geen auth-configuratie gevonden voor environment: Sandbox') {
        fail('ontbrekende Sandbox-auth gaf een andere fout: ' . $exception->getMessage());
    }
}
if (!$rejectedUrl) {
    fail('een geconfigureerde auth_list zonder Sandbox moet de aanroep weigeren');
}
$queryRejected = false;
try {
    odata_mimir_query('Hunter van Twist', 'AppProjecten', ['$select' => 'No'], 12);
} catch (Throwable $exception) {
    $queryRejected = true;
    if (strpos($exception->getMessage(), 'Mímir') === false) {
        fail('gemapt bedrijf zonder Sandbox-auth moet de Mímir-fout houden: ' . $exception->getMessage());
    }
}
if (!$queryRejected) {
    fail('gemapt bedrijf zonder named auth moet stoppen');
}
if (count($calls) !== $callsBeforeReject || fallback_count() !== $loggedBeforeReject) {
    fail('ontbrekende environment-auth mag geen generieke credentials of extra log gebruiken');
}

$auth_list = ['Sandbox' => $sandboxAuth];
$beforePrimary = count($calls);
$primaryRows = odata_get_all(
    "https://mimir.invalid/mimir/ODataV4/Company('Onbekend%20Bedrijf')/AppWerkorders?\$select=No",
    $auth,
    14
);
if (($primaryRows[0]['No'] ?? '') !== 'WO-1') {
    fail('onbekend bedrijf op de primaire environment viel niet terug');
}
$primaryCall = $calls[$beforePrimary] ?? null;
$expectedPrimaryUrl = "https://bc.example:7148/Production/ODataV4/Company('Onbekend%20Bedrijf')/AppWerkorders?\$select=No";
if (!is_array($primaryCall) || $primaryCall['url'] !== $expectedPrimaryUrl || $primaryCall['user'] !== 'bcuser') {
    fail('primaire environment zonder auth_list-key moet $auth gebruiken: ' . json_encode($primaryCall));
}
$beforePrimaryCase = count($calls);
$primaryCaseRows = odata_get_all(
    "https://mimir.invalid/production/ODataV4/Company('Onbekend%20Bedrijf')/AppWerkorders?\$select=No",
    $auth,
    14
);
if (($primaryCaseRows[0]['No'] ?? '') !== 'WO-1') {
    fail('primaire environment met andere hoofdletters viel niet terug');
}
$primaryCaseCall = $calls[$beforePrimaryCase] ?? null;
$expectedPrimaryCaseUrl = "https://bc.example:7148/production/ODataV4/Company('Onbekend%20Bedrijf')/AppWerkorders?\$select=No";
if (!is_array($primaryCaseCall) || $primaryCaseCall['url'] !== $expectedPrimaryCaseUrl || $primaryCaseCall['user'] !== 'bcuser') {
    fail('primaire environment is hoofdlettergevoelig geweigerd: ' . json_encode($primaryCaseCall));
}

$auth_list = [
    'Production' => $auth,
    'Sandbox' => $sandboxAuth,
    'My Env' => $myEnvAuth,
];

$cacheUrl = "https://mimir.invalid/mimir/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders";
$cacheKey = build_cache_key($cacheUrl, $sandboxAuth);
$cacheSuffix = '|sandbox-user|Sandbox';
if (substr($cacheKey, -strlen($cacheSuffix)) !== $cacheSuffix) {
    fail('cache-key gebruikt niet de echte BC-environment: ' . $cacheKey);
}
$explicitCacheKey = build_cache_key(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppProjecten",
    $sandboxAuth
);
if (substr($explicitCacheKey, -strlen($cacheSuffix)) !== $cacheSuffix) {
    fail('cache-key negeert het environment-segment: ' . $explicitCacheKey);
}

odata_mimir_circuit_reset();
$loggedBeforeCaller = fallback_count();
$callsBeforeCaller = count($calls);
$callerThrown = false;
try {
    odata_mimir_fetch_all('https://mimir.invalid/not-an-odata-url', 10);
} catch (Throwable $exception) {
    $callerThrown = true;
    if (strpos($exception->getMessage(), 'OData-URL kon niet worden vertaald') === false) {
        fail('onvertaalbare URL gaf een andere fout: ' . $exception->getMessage());
    }
}
if (!$callerThrown) {
    fail('onvertaalbare URL moet een exception blijven');
}
if (odata_mimir_circuit_open()) {
    fail('een fout uit de caller mag het circuit niet openen');
}
if (fallback_count() !== $loggedBeforeCaller || count($calls) !== $callsBeforeCaller) {
    fail('een fout uit de caller mag geen fallback starten');
}

$beforeCompanyEnvs = count($calls);
odata_direct_companies_as_rows(null);
$sawProductionCompanies = false;
$sawSandboxCompanies = false;
for ($i = $beforeCompanyEnvs; $i < count($calls); $i++) {
    $url = (string) ($calls[$i]['url'] ?? '');
    $user = (string) ($calls[$i]['user'] ?? '');
    if (strpos($url, 'https://bc.example:7148/Production/ODataV4/Company') === 0 && $user === 'bcuser') {
        $sawProductionCompanies = true;
    }
    if (strpos($url, 'https://bc.example:7148/Sandbox/ODataV4/Company') === 0 && $user === 'sandbox-user') {
        $sawSandboxCompanies = true;
    }
}
if (!$sawProductionCompanies || !$sawSandboxCompanies) {
    fail('companylijst moet elke auth_list-environment bevragen: ' . json_encode(array_slice($calls, $beforeCompanyEnvs)));
}

unset($GLOBALS['demeter_company_environment_map']);
$auth_list = ['Production' => $auth];

require dirname(__DIR__) . '/web/elpis_data.php';

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
unset(
    $GLOBALS['demeter_company_environment_map'],
    $GLOBALS['demeter_companies_by_environment'],
    $GLOBALS['demeter_active_environments']
);
$beforeElpis = count($calls);
$elpisRows = elpis_fetch_rows('KVT Gas', 'AppProjecten', ['$select' => 'No'], 30);
if (($elpisRows[0]['No'] ?? '') !== 'WO-1') {
    fail('elpis_fetch_rows (nightly/index) viel niet terug op de stub');
}
$sawHistoricalCompanies = false;
$sawElpisEntity = false;
for ($i = $beforeElpis; $i < count($calls); $i++) {
    $url = (string) ($calls[$i]['url'] ?? '');
    if (strpos($url, 'https://bc.example:7148/Production/ODataV4/Companies?') === 0) {
        $sawHistoricalCompanies = true;
    }
    if (strpos($url, "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppProjecten?") === 0) {
        $sawElpisEntity = true;
        if (($calls[$i]['user'] ?? '') !== 'bcuser') {
            fail('elpis-fallback gebruikte niet de BC-credentials');
        }
    }
}
if (!$sawHistoricalCompanies) {
    fail('elpis-fallback gebruikte niet de pre-Mímir company-discovery: ' . json_encode(array_slice($calls, $beforeElpis)));
}
if (!$sawElpisEntity) {
    fail('elpis-fallback bouwde niet de pre-Mímir entity-URL: ' . json_encode(array_slice($calls, $beforeElpis)));
}

$loggedBeforeRethrow = fallback_count();
$callsBeforeRethrow = count($calls);
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
$rethrown = null;
try {
    odata_get_all('https://mimir.invalid/mimir/ODataV4/Company(\'X\')/AppWerkorders', ['mode' => 'basic', 'user' => '', 'pass' => ''], 30);
    fail('zonder BC-credentials moet de oorspronkelijke Mímir-fout terugkomen');
} catch (Throwable $exception) {
    $rethrown = $exception;
}
if (!$rethrown instanceof Throwable || strpos($rethrown->getMessage(), 'Mímir') === false) {
    fail('hergooide fout is niet de Mímir-fout: ' . ($rethrown instanceof Throwable ? $rethrown->getMessage() : 'geen'));
}
if (stripos($rethrown->getMessage(), 'credential') !== false) {
    fail('hergooide fout maskeert Mímir met een credentials-melding: ' . $rethrown->getMessage());
}
if (count($calls) !== $callsBeforeRethrow) {
    fail('zonder BC-credentials mag de directe fetch niet starten');
}
if (fallback_count() !== $loggedBeforeRethrow) {
    fail('zonder BC-credentials mag er geen fallback gelogd worden');
}

odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];
$loggedBeforeDirect = fallback_count();
$directOnlyUrl = 'https://mimir.invalid/Production/ODataV4/Company(\'KVT%20Gas\')/AppWerkorders?$select=No';
$directRows = odata_get_all($directOnlyUrl, $auth, 45);
if (odata_mimir_circuit_open()) {
    fail('lege $mimirApi mag Mímir niet proberen');
}
if (fallback_count() !== $loggedBeforeDirect) {
    fail('lege $mimirApi mag geen Mímir-fallback loggen');
}
$directCall = $calls[count($calls) - 1] ?? null;
if (($directRows[0]['No'] ?? '') !== 'WO-1' || !is_array($directCall) || $directCall['url'] !== $directOnlyUrl) {
    fail('lege $mimirApi moet de oude directe route ongewijzigd gebruiken: ' . json_encode($directCall));
}

$authFile = sys_get_temp_dir() . '/elpis-auth-globals-' . getmypid() . '.php';
file_put_contents($authFile, <<<'PHP'
<?php
$baseUrl = 'https://from-file.example:7148/';
$base = 'https://from-file.example:7148/';
$environment = 'Sandbox';
$auth = ['mode' => 'basic', 'user' => 'file-user', 'pass' => 'file-secret'];
$auth_list = [
    'Sandbox' => ['mode' => 'basic', 'user' => 'file-user', 'pass' => 'file-secret'],
];
$mimirApi = 'file-mimir-key';
$mimirBase = 'http://127.0.0.1:9';
PHP
);
$GLOBALS['ELPIS_AUTH_PHP_PATH'] = $authFile;
$GLOBALS['ELPIS_AUTH_PHP_INCLUDED'] = [];
$GLOBALS['baseUrl'] = 'https://already.example:7148/';
$GLOBALS['environment'] = 'Production';
$GLOBALS['auth'] = ['mode' => 'basic', 'user' => 'kept-user', 'pass' => 'kept-secret'];
unset($GLOBALS['auth_list'], $GLOBALS['base']);
odata_bc_ensure_config_loaded();
$filledList = (static function (): array {
    global $baseUrl, $environment, $auth, $auth_list;
    return [
        'baseUrl' => $baseUrl ?? null,
        'environment' => $environment ?? null,
        'user' => is_array($auth) ? (string) ($auth['user'] ?? '') : '',
        'listUser' => (isset($auth_list['Sandbox']) && is_array($auth_list['Sandbox']))
            ? (string) ($auth_list['Sandbox']['user'] ?? '')
            : '',
    ];
})();
if ($filledList['baseUrl'] !== 'https://already.example:7148/'
    || $filledList['environment'] !== 'Production'
    || $filledList['user'] !== 'kept-user'
    || $filledList['listUser'] !== 'file-user') {
    fail('auth.php moet auth_list aanvullen zonder gezette credentials te overschrijven: ' . json_encode($filledList));
}

$GLOBALS['ELPIS_AUTH_PHP_INCLUDED'] = [];
$GLOBALS['baseUrl'] = 'https://already.example:7148/';
$GLOBALS['environment'] = 'mimir';
unset($GLOBALS['auth'], $GLOBALS['auth_list'], $GLOBALS['base']);
odata_bc_ensure_config_loaded();
$copied = (static function (): array {
    global $baseUrl, $base, $environment, $auth, $auth_list;
    return [
        'baseUrl' => $baseUrl ?? null,
        'base' => $base ?? null,
        'environment' => $environment ?? null,
        'user' => is_array($auth) ? (string) ($auth['user'] ?? '') : '',
        'listUser' => (isset($auth_list['Sandbox']) && is_array($auth_list['Sandbox']))
            ? (string) ($auth_list['Sandbox']['user'] ?? '')
            : '',
        'globalBaseUrl' => $GLOBALS['baseUrl'] ?? null,
        'globalEnvironment' => $GLOBALS['environment'] ?? null,
    ];
})();
if ($copied['baseUrl'] !== 'https://already.example:7148/' || $copied['globalBaseUrl'] !== 'https://already.example:7148/') {
    fail('lazy auth.php overschreef een gezette baseUrl: ' . json_encode($copied));
}
if ($copied['environment'] !== 'Sandbox' || $copied['globalEnvironment'] !== 'Sandbox') {
    fail('placeholder-environment werd niet uit auth.php gekopieerd: ' . json_encode($copied));
}
if ($copied['user'] !== 'file-user' || $copied['listUser'] !== 'file-user' || $copied['base'] !== 'https://from-file.example:7148/') {
    fail('auth/auth_list/base kwamen niet in $GLOBALS: ' . json_encode($copied));
}

unset($GLOBALS['baseUrl'], $GLOBALS['base'], $GLOBALS['environment'], $GLOBALS['auth'], $GLOBALS['auth_list']);
$GLOBALS['ELPIS_AUTH_PHP_INCLUDED'] = [];
odata_bc_ensure_config_loaded();
$afterRequireOnce = (static function () use ($authFile): array {
    require_once $authFile;
    global $baseUrl, $environment, $auth;
    return [
        'baseUrl' => $GLOBALS['baseUrl'] ?? null,
        'viaGlobal' => $baseUrl ?? null,
        'environment' => $environment ?? null,
        'user' => is_array($auth) ? (string) ($auth['user'] ?? '') : '',
    ];
})();
if ($afterRequireOnce['baseUrl'] !== 'https://from-file.example:7148/'
    || $afterRequireOnce['viaGlobal'] !== 'https://from-file.example:7148/'
    || $afterRequireOnce['environment'] !== 'Sandbox'
    || $afterRequireOnce['user'] !== 'file-user') {
    fail('require_once na de kopie wist de globals: ' . json_encode($afterRequireOnce));
}
if (strpos(fallback_log(), 'sandbox-secret') !== false || strpos(fallback_log(), 'file-secret') !== false || strpos(fallback_log(), 'bc-secret') !== false || strpos(fallback_log(), 'myenv-secret') !== false || strpos(fallback_log(), 'kept-secret') !== false) {
    fail('log bevat een geheim');
}
@unlink($authFile);

echo "OK\n";
