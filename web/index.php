<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/odata.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/elpis_data.php';

/**
 * Functies
 */

function elpis_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function elpis_url(array $params = []): string
{
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }
    unset($query['lang'], $query['_loaded']);

    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'), '?') ?: 'index.php';
    $query['lang'] = getCurrentLanguage();

    return $path . '?' . http_build_query($query);
}

function elpis_qty_is_zero(float $value): bool
{
    return abs($value) < 0.00001;
}

function elpis_search_tokens(string $query): array
{
    $query = strtolower(trim($query));
    if ($query === '') {
        return [];
    }

    return array_values(array_filter(preg_split('/\s+/', $query) ?: [], static function (string $token): bool {
        return $token !== '';
    }));
}

function elpis_matches_search_tokens(string $haystack, array $tokens): bool
{
    if ($tokens === []) {
        return true;
    }

    $haystack = strtolower($haystack);
    foreach ($tokens as $token) {
        if (!str_contains($haystack, $token)) {
            return false;
        }
    }

    return true;
}

function elpis_sort_projects_for_display(array $projects, array $linesByProject): array
{
    if ($projects === []) {
        return [];
    }

    $indexed = [];
    foreach ($projects as $index => $project) {
        if (!is_array($project)) {
            continue;
        }

        $projectNo = (string) ($project['no'] ?? '');
        $indexed[] = [
            'project' => $project,
            'index' => $index,
            'has_lines' => $projectNo !== '' && ($linesByProject[$projectNo] ?? []) !== [],
        ];
    }

    usort($indexed, static function (array $left, array $right): int {
        if ($left['has_lines'] !== $right['has_lines']) {
            return $left['has_lines'] ? -1 : 1;
        }

        return $left['index'] <=> $right['index'];
    });

    return array_map(static function (array $row): array {
        return $row['project'];
    }, $indexed);
}

function elpis_line_row_class(float $toOrder, float $ordered, float $received, float $open): string
{
    $sum = $toOrder + $ordered;

    if ($sum < $received) {
        return 'elpis-row--alert';
    }

    if ($open > 0) {
        return 'elpis-row--warn';
    }

    if (elpis_qty_is_zero($toOrder) && $ordered >= $received) {
        return 'elpis-row--ok';
    }

    if ($sum > $received) {
        return 'elpis-row--orange';
    }

    if ($toOrder < $received && $sum >= $received) {
        return 'elpis-row--warn';
    }

    return '';
}

function elpis_render_nightly_gate(): void
{
    ?><!DOCTYPE html>
<html lang="<?= elpis_h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= elpis_h(LOC('app.title')) ?></title>
    <link rel="stylesheet" href="brand.css">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="box.svg" type="image/svg+xml">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; }
        .elpis-nightly { text-align: center; padding: 24px; max-width: 28rem; }
        .elpis-nightly img { max-height: 48px; }
        .elpis-nightly p { color: var(--kvt-muted); }
    </style>
</head>
<body>
    <div class="elpis-nightly">
        <img src="logo-website.png" alt="KVT">
        <p id="elpis-nightly-text"><?= elpis_h(LOC('elpis.nightly.waiting')) ?></p>
    </div>
    <script>
    (function () {
        var text = document.getElementById('elpis-nightly-text');
        var failed = <?= json_encode(LOC('elpis.nightly.failed'), JSON_UNESCAPED_UNICODE) ?>;
        function poll() {
            fetch('nightly.php?format=json', { cache: 'no-store' }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data.status === 'ok' || data.status === 'fresh' || data.status === 'failed') {
                    window.location.reload();
                    return;
                }
                if (data.status === 'running') {
                    window.setTimeout(poll, 3000);
                    return;
                }
                if (text) {
                    text.textContent = failed;
                }
            }).catch(function () {
                if (text) {
                    text.textContent = failed;
                }
            });
        }
        poll();
    })();
    </script>
</body>
</html>
    <?php
}

/**
 * Page load
 */

$pageStartedAt = microtime(true);

if (elpis_nightly_needs_run()) {
    elpis_render_nightly_gate();
    exit;
}

$userEmail = elpis_current_user_email();
$userPrefs = function_exists('loadUserPrefs') ? loadUserPrefs($userEmail) : [];
$savedManagersByCompany = elpis_load_manager_prefs($userEmail);
$companyReloaded = isset($_GET['reload_managers']);

$companies = elpis_companies_for_page();
$savedCompany = (string) ($userPrefs['elpis_company'] ?? '');
$requestedCompany = trim((string) ($_GET['company'] ?? ''));
$requestedManager = $companyReloaded ? '' : trim((string) ($_GET['manager'] ?? ''));
$openProjectNo = $companyReloaded ? '' : trim((string) ($_GET['project'] ?? ''));
$projectSearchQuery = trim((string) ($_GET['q'] ?? ''));
$projectSearchTokens = elpis_search_tokens($projectSearchQuery);
$companyChosen = $requestedCompany !== '' && in_array($requestedCompany, $companies, true);
$company = $companyChosen ? $requestedCompany : '';

$projectManager = '';
$deferProjectsLoad = $companyChosen && !isset($_GET['_loaded']);

$errorKey = '';
$projectManagers = [];
$projects = [];

if ($companyChosen) {
    auth_set_current_company_context($company);

    try {
        $projectManagers = elpis_fetch_project_managers($company);
        $projectManager = elpis_resolve_manager_choice(
            $projectManagers,
            $requestedManager,
            $company,
            $savedManagersByCompany,
            $userEmail
        );

        if ($projectManager !== '' && !$deferProjectsLoad) {
            $projects = elpis_fetch_projects_for_manager($company, $projectManager);
        }

        if (!$deferProjectsLoad) {
            elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
        }

        if (!$deferProjectsLoad && $openProjectNo !== '') {
            $knownProjectNos = array_map(static function (array $project): string {
                return (string) ($project['no'] ?? '');
            }, $projects);
            if (!in_array($openProjectNo, $knownProjectNos, true)) {
                $openProjectNo = '';
            }
        }
    } catch (Throwable $loadError) {
        $errorKey = 'elpis.error.load_failed';
    }
}

elpis_log_timing(
    $company,
    'page',
    ['$filter' => 'manager=' . $projectManager . ' projects=' . count($projects) . ' deferred=' . ($deferProjectsLoad ? '1' : '0')],
    count($projects),
    $pageStartedAt,
    $deferProjectsLoad ? 'deferred' : 'html'
);

$projects = elpis_sort_projects_for_display($projects, []);

?><!DOCTYPE html>
<html lang="<?= elpis_h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= elpis_h(LOC('app.title')) ?></title>
    <link rel="stylesheet" href="brand.css">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="box.svg" type="image/svg+xml">
    <?php renderLanguageSwitcherStyles(); ?>
    <style>
        .elpis-page { max-width: none; width: 100%; margin: 0 auto; padding: 16px 12px 32px; box-sizing: border-box; }
        .elpis-header {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 3px solid var(--kvt-main-blue);
        }
        .elpis-header img { max-height: 48px; width: auto; }
        .elpis-card {
            background: var(--kvt-panel-bg);
            border: 1px solid var(--kvt-line);
            border-radius: 14px;
            padding: 18px 20px;
            margin-bottom: 18px;
            box-shadow: 0 10px 28px rgba(0, 82, 155, 0.08);
        }
        .elpis-card--hero {
            border-top: 4px solid var(--kvt-perkins-blue);
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        }
        .elpis-card h1.brand-display {
            margin: 0;
            color: var(--kvt-perkins-blue);
            font-size: clamp(1.45rem, 3vw, 1.9rem);
        }
        .elpis-card h2 {
            margin: 0 0 12px;
            color: var(--kvt-perkins-blue);
            font-size: 1.15rem;
        }
        .elpis-form { display: grid; gap: 12px; }
        .elpis-form label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .elpis-form input, .elpis-form select, .elpis-btn {
            font: inherit;
            border-radius: 10px;
            border: 1px solid var(--kvt-line);
            padding: 12px 14px;
        }
        .elpis-form input, .elpis-form select {
            width: 100%;
            box-sizing: border-box;
            background: #fff;
        }
        .elpis-form select:focus { outline: 2px solid rgba(0, 153, 204, 0.35); border-color: var(--kvt-main-blue); }
        .elpis-btn {
            background: linear-gradient(180deg, var(--kvt-main-blue) 0%, var(--kvt-perkins-blue) 100%);
            color: #fff;
            border-color: var(--kvt-perkins-blue);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(0, 82, 155, 0.22);
        }
        .elpis-btn:hover { filter: brightness(1.05); }
        .elpis-btn:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
        .elpis-alert { border: 1px solid #fecaca; background: #fef2f2; color: var(--kvt-danger); border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; }
        .elpis-subtitle { color: var(--kvt-muted); margin: 8px 0 0; max-width: 52rem; }
        .elpis-muted { color: var(--kvt-muted); font-size: 0.92rem; }
        .elpis-project-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .elpis-project-item {
            border: 1px solid var(--kvt-line);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 4px 14px rgba(0, 82, 155, 0.05);
        }
        .elpis-project-header {
            display: flex;
            align-items: stretch;
            background: #fff;
        }
        .elpis-project-toggle {
            flex: 1;
            min-width: 0;
            width: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border: 0;
            background: transparent;
            color: var(--kvt-text);
            text-align: left;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
        }
        button.elpis-project-toggle { appearance: none; }
        .elpis-project-copy {
            appearance: none;
            border: 0;
            border-left: 1px solid var(--kvt-line);
            background: transparent;
            padding: 0 12px;
            cursor: pointer;
            font-size: 1.15rem;
            line-height: 1;
        }
        .elpis-project-copy:hover { background: #eef7fd; }
        .elpis-project-toggle:hover { background: #f2f9ff; }
        .elpis-project-item.is-highlighted {
            box-shadow: 0 0 0 3px rgba(0, 153, 204, 0.55), 0 8px 22px rgba(0, 82, 155, 0.12);
            animation: elpis-highlight-pulse 1.4s ease-out 1;
        }
        @keyframes elpis-highlight-pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 153, 204, 0.75); }
            100% { box-shadow: 0 0 0 3px rgba(0, 153, 204, 0.55), 0 8px 22px rgba(0, 82, 155, 0.12); }
        }
        .elpis-project-item.is-open {
            border-color: rgba(0, 153, 204, 0.45);
            box-shadow: 0 8px 22px rgba(0, 82, 155, 0.1);
        }
        .elpis-project-item.is-open .elpis-project-toggle {
            background: linear-gradient(90deg, #e8f4fc 0%, #f7fbff 100%);
            border-left: 4px solid var(--kvt-main-blue);
        }
        .elpis-project-heading { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; min-width: 0; }
        .elpis-project-title { font-weight: 700; color: var(--kvt-perkins-blue); }
        .elpis-execution-from {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: baseline;
            max-width: 100%;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e8f8ee;
            color: #0f6b3c;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1.35;
        }
        .elpis-execution-from.is-missing { background: #fff4e5; color: #8a4b08; }
        .elpis-receipt--stock { color: #0f6b3c; font-weight: 700; }
        .elpis-receipt--missing { color: #8a4b08; font-weight: 700; }
        .elpis-project-chevron { color: var(--kvt-main-blue); font-size: 1.2rem; transition: transform 0.2s ease; }
        .elpis-project-item.is-open .elpis-project-chevron { transform: rotate(90deg); }
        .elpis-project-item--empty:not(.is-open) {
            background: #f3f3f3;
            border-color: #e3e3e3;
            box-shadow: none;
        }
        .elpis-project-item--empty:not(.is-open) .elpis-project-header,
        .elpis-project-item--empty:not(.is-open) .elpis-project-toggle {
            background: #f3f3f3;
            color: var(--kvt-muted);
        }
        .elpis-project-item--empty:not(.is-open) .elpis-project-toggle:hover { background: #ececec; }
        .elpis-project-item--empty:not(.is-open) .elpis-project-title {
            color: #8a8a8a;
            font-weight: 600;
        }
        .elpis-project-item--empty:not(.is-open) .elpis-project-chevron { color: #9a9a9a; }
        .elpis-project-panel { display: none; border-top: 1px solid var(--kvt-line); padding: 14px 16px 16px; background: #fbfdff; }
        .elpis-project-item.is-open .elpis-project-panel { display: block; }
        .elpis-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--kvt-line);
            border-radius: 10px;
            background: #fff;
        }
        table.elpis-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        table.elpis-table th, table.elpis-table td {
            border-bottom: 1px solid var(--kvt-line);
            padding: 11px 10px;
            text-align: left;
            vertical-align: top;
        }
        .elpis-project-toolbar {
            display: grid;
            gap: 12px;
            margin: 14px 0 16px;
        }
        .elpis-project-toolbar-filters {
            display: grid;
            gap: 12px;
        }
        .elpis-project-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .elpis-project-toolbar label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .elpis-project-toolbar select {
            font: inherit;
            border-radius: 10px;
            border: 1px solid var(--kvt-line);
            padding: 12px 14px;
            width: 100%;
            box-sizing: border-box;
            background: #fff;
        }
        .elpis-search-wrap { margin: 0; }
        .elpis-line-search-wrap { margin: 0 0 12px; }
        .elpis-search-wrap label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .elpis-search-wrap input {
            font: inherit;
            border-radius: 10px;
            border: 1px solid var(--kvt-line);
            padding: 12px 14px;
            width: 100%;
            box-sizing: border-box;
            background: #fff;
        }
        .elpis-search-wrap input:focus { outline: 2px solid rgba(0, 153, 204, 0.35); border-color: var(--kvt-main-blue); }
        .elpis-search-empty { display: none; margin-top: 8px; }
        .elpis-search-empty.is-visible { display: block; }
        .elpis-project-item.is-filtered-out:not(.is-pinned) { display: none; }
        .elpis-project-item.is-pinned { display: block; }
        table.elpis-table tbody tr.is-filtered-out { display: none; }
        table.elpis-table .elpis-col-project-no { display: none; }
        table.elpis-table thead th {
            background: linear-gradient(180deg, var(--kvt-perkins-blue) 0%, #0069b4 100%);
            color: #fff;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 0;
            white-space: nowrap;
        }
        .elpis-sort-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            cursor: pointer;
            text-align: inherit;
        }
        .elpis-sort-btn.num { justify-content: flex-end; }
        .elpis-sort-btn:hover { color: #d9f0ff; }
        .elpis-sort-btn::after {
            content: '↕';
            font-size: 0.72rem;
            opacity: 0.55;
        }
        .elpis-sort-btn.is-asc::after { content: '↑'; opacity: 1; }
        .elpis-sort-btn.is-desc::after { content: '↓'; opacity: 1; }
        table.elpis-table tbody tr:last-child td { border-bottom: 0; }
        table.elpis-table td.elpis-col-description { width: 32%; }
        table.elpis-table td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .elpis-modal {
            position: fixed;
            inset: 0;
            z-index: 11000;
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            padding: 8px;
            box-sizing: border-box;
        }
        .elpis-modal[hidden] { display: none; }
        .elpis-modal-panel {
            background: #fff;
            width: 100%;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            max-height: 100%;
            min-height: 0;
        }
        .elpis-modal-head, .elpis-modal-toolbar { padding: 12px 14px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .elpis-modal-head { justify-content: space-between; border-bottom: 1px solid var(--kvt-line); }
        .elpis-modal-head h2 { margin: 0; color: var(--kvt-perkins-blue); font-size: 1.1rem; }
        .elpis-modal-toolbar { display: grid; }
        .elpis-modal-body { overflow: auto; padding: 0 12px 16px; }
        .elpis-modal-toolbar label { display: grid; gap: 6px; font-weight: 700; color: var(--kvt-perkins-blue); font-size: 0.9rem; }
        .elpis-modal-toolbar input, .elpis-modal-toolbar select { font: inherit; border-radius: 10px; border: 1px solid var(--kvt-line); padding: 12px 14px; width: 100%; box-sizing: border-box; }
        .elpis-btn--compact { padding: 8px 12px; }
        .elpis-opt-alert { background: var(--kvt-row-alert); color: var(--kvt-danger); }
        .elpis-opt-orange { background: var(--kvt-row-orange); color: #9a3412; }
        .elpis-opt-warn { background: var(--kvt-row-warn); color: #854d0e; }
        .elpis-opt-ok { background: var(--kvt-row-ok); color: #0f6b3c; }
        .elpis-opt-muted { background: #f3f4f6; color: var(--kvt-muted); }
        @media (min-width: 640px) {
            .elpis-modal-toolbar { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
        }
        table.elpis-table td.date { white-space: nowrap; }
        table.elpis-table tbody tr.elpis-row--alert { background: var(--kvt-row-alert); }
        table.elpis-table tbody tr.elpis-row--orange { background: var(--kvt-row-orange); }
        table.elpis-table tbody tr.elpis-row--warn { background: var(--kvt-row-warn); }
        table.elpis-table tbody tr.elpis-row--ok { background: var(--kvt-row-ok); }
        @media (min-width: 640px) {
            .elpis-form-grid { grid-template-columns: 1fr 1fr; align-items: end; }
            .elpis-project-toolbar-filters { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); align-items: end; }
        }
        .elpis-loader {
            position: fixed;
            inset: 0;
            z-index: 12000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(255, 255, 255, 0.92);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        .elpis-loader.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .elpis-loader-panel {
            display: grid;
            gap: 12px;
            justify-items: center;
            max-width: 280px;
            text-align: center;
            color: var(--kvt-text);
        }
        .elpis-loader-spinner {
            width: 42px;
            height: 42px;
            border: 3px solid rgba(0, 153, 204, 0.2);
            border-top-color: var(--kvt-main-blue);
            border-radius: 50%;
            animation: elpis-loader-spin 0.8s linear infinite;
        }
        .elpis-loader-steps {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            align-content: center;
            width: 100%;
            max-width: 220px;
            min-height: 20px;
            margin-inline: auto;
        }
        .elpis-load-step {
            width: 20px;
            height: 20px;
            line-height: 0;
        }
        .elpis-load-step img {
            width: 20px;
            height: 20px;
            display: block;
            opacity: 0.2;
            filter: grayscale(1) brightness(1.35);
            transform-origin: center bottom;
            transition: opacity 0.2s ease, filter 0.2s ease;
        }
        .elpis-load-step.is-done img {
            opacity: 1;
            filter: none;
            animation: elpis-box-pop 0.38s cubic-bezier(0.34, 1.45, 0.64, 1) forwards;
        }
        .elpis-loader-title { margin: 0; font-family: var(--kvt-font-display); font-size: 1.1rem; }
        .elpis-loader-text { margin: 0; color: var(--kvt-muted); font-size: 0.92rem; }
        @keyframes elpis-loader-spin { to { transform: rotate(360deg); } }
        @keyframes elpis-box-pop {
            0% { transform: translateY(0) scale(0.92); }
            40% { transform: translateY(-5px) scale(1.18); }
            70% { transform: translateY(1px) scale(0.97); }
            100% { transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body>
<div class="elpis-page">
    <header class="elpis-header">
        <img src="logo-website.png" alt="KVT">
        <?php renderLanguageSwitcher(); ?>
    </header>

    <section class="elpis-card elpis-card--hero">
        <h1 class="brand-display"><?= elpis_h(LOC('elpis.hero.title')) ?></h1>
        <p class="elpis-subtitle"><?= elpis_h(LOC('elpis.hero.subtitle')) ?></p>

        <form id="elpis-filter-form" class="elpis-form elpis-form-grid elpis-nav" method="get" action="index.php" style="margin-top: 16px;">
            <input type="hidden" name="lang" value="<?= elpis_h(getCurrentLanguage()) ?>">
            <label>
                <?= elpis_h(LOC('elpis.label.company')) ?>
                <select name="company" data-elpis-company-select>
                    <?php if (!$companyChosen): ?>
                        <option value=""><?= elpis_h(LOC('elpis.company.choose')) ?></option>
                    <?php endif; ?>
                    <?php foreach ($companies as $companyOption): ?>
                        <option value="<?= elpis_h($companyOption) ?>"<?= $companyOption === $company ? ' selected' : '' ?>><?= elpis_h($companyOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($companyChosen): ?>
            <label>
                <?= elpis_h(LOC('elpis.label.manager')) ?>
                <select name="manager" data-elpis-manager-select<?= $projectManagers === [] ? ' disabled' : '' ?>>
                    <?php if ($projectManagers === []): ?>
                        <option value=""><?= elpis_h(LOC('elpis.empty.managers')) ?></option>
                    <?php else: ?>
                        <option value="<?= elpis_h(ELPI_MANAGER_ALL) ?>"<?= elpis_is_all_managers_selection($projectManager) ? ' selected' : '' ?>><?= elpis_h(LOC('elpis.manager.all')) ?></option>
                        <?php foreach ($projectManagers as $managerOption): ?>
                            <option value="<?= elpis_h($managerOption) ?>"<?= !elpis_is_all_managers_selection($projectManager) && strcasecmp($managerOption, $projectManager) === 0 ? ' selected' : '' ?>><?= elpis_h(elpis_format_manager_label($managerOption)) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>
            <?php else: ?>
                <p class="elpis-muted"><?= elpis_h(LOC('elpis.company.hint')) ?></p>
                <button class="elpis-btn" type="submit"><?= elpis_h(LOC('elpis.btn.show_projects')) ?></button>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($errorKey !== ''): ?>
        <div class="elpis-alert"><?= elpis_h(LOC($errorKey)) ?></div>
    <?php endif; ?>

    <?php if ($companyChosen): ?>
        <section class="elpis-card"<?= $deferProjectsLoad ? ' data-elpis-deferred-load="1"' : '' ?>>
            <h2><?= elpis_h(LOC('elpis.section.projects')) ?></h2>
            <?php if ($projectManager !== ''): ?>
                <p class="elpis-muted"><?= elpis_h(LOC('elpis.meta.manager')) ?>: <?= elpis_h(elpis_is_all_managers_selection($projectManager) ? LOC('elpis.manager.all') : elpis_format_manager_label($projectManager)) ?></p>
            <?php endif; ?>

            <?php if ($deferProjectsLoad): ?>
                <ul class="elpis-project-list" id="elpis-project-list" aria-busy="true"></ul>
            <?php elseif ($projects === []): ?>
                <p class="elpis-muted"><?= elpis_h(LOC('elpis.empty.projects')) ?></p>
            <?php else: ?>
                <div class="elpis-project-toolbar">
                    <div class="elpis-search-wrap">
                        <label>
                            <?= elpis_h(LOC('elpis.label.search')) ?>
                            <input
                                type="search"
                                id="elpis-project-search"
                                name="q"
                                value="<?= elpis_h($projectSearchQuery) ?>"
                                placeholder="<?= elpis_h(LOC('elpis.placeholder.search')) ?>"
                                autocomplete="off"
                                spellcheck="false"
                            >
                        </label>
                    </div>
                    <p id="elpis-search-empty" class="elpis-muted elpis-search-empty"><?= elpis_h(LOC('elpis.empty.search')) ?></p>
                </div>
                <ul class="elpis-project-list" id="elpis-project-list">
                    <?php foreach ($projects as $projectIndex => $project): ?>
                        <?php
                        $projectNo = (string) ($project['no'] ?? '');
                        $cached = $projectNo !== '' ? elpis_read_project_cache($company, $projectNo) : null;
                        $cachedLines = is_array($cached['lines'] ?? null) ? $cached['lines'] : [];
                        $searchBlob = elpis_project_search_blob($project, $cachedLines);
                        $projectMatchesSearch = elpis_matches_search_tokens($searchBlob, $projectSearchTokens);
                        $executionSummary = $cachedLines !== [] ? elpis_project_execution_summary($cachedLines) : ['state' => 'none', 'date' => ''];
                        $executionValue = elpis_project_execution_value($executionSummary);
                        $isFocused = $projectNo !== '' && $projectNo === $openProjectNo;
                        $shareUrl = elpis_url([
                            'company' => $company,
                            'manager' => $projectManager,
                            'project' => $projectNo,
                            'q' => null,
                            'line_q' => null,
                            'line_status' => null,
                        ]);
                        ?>
                        <li
                            class="elpis-project-item<?= !$projectMatchesSearch ? ' is-filtered-out' : '' ?><?= $isFocused ? ' is-highlighted is-pinned' : '' ?>"
                            data-elpis-project-item
                            data-project-no="<?= elpis_h($projectNo) ?>"
                            data-project-index="<?= (int) $projectIndex ?>"
                            data-search-text="<?= elpis_h($searchBlob) ?>"
                            <?= $isFocused ? ' data-elpis-focus-project="1"' : '' ?>
                        >
                            <div class="elpis-project-header">
                                <button type="button" class="elpis-project-toggle" data-elpis-open-project data-project-no="<?= elpis_h($projectNo) ?>" data-project-label="<?= elpis_h(trim($projectNo . ' ' . (string) ($project['description'] ?? ''))) ?>">
                                    <span class="elpis-project-heading">
                                        <span>
                                            <span class="elpis-project-title"><?= elpis_h($projectNo) ?></span>
                                            <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                                <span> — <?= elpis_h((string) $project['description']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($executionValue !== ''): ?>
                                            <span class="elpis-execution-from<?= $executionSummary['state'] === 'missing' ? ' is-missing' : '' ?>">
                                                <span><?= elpis_h(LOC('elpis.summary.execution_from')) ?></span>
                                                <span><?= elpis_h($executionValue) ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="elpis-project-chevron" aria-hidden="true">›</span>
                                </button>
                                <button
                                    type="button"
                                    class="elpis-project-copy"
                                    data-elpis-copy-link
                                    data-copy-url="<?= elpis_h($shareUrl) ?>"
                                    title="<?= elpis_h(LOC('elpis.link.copy')) ?>"
                                    aria-label="<?= elpis_h(LOC('elpis.link.copy')) ?>"
                                >🔗</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!function_exists('odata_mimir_enabled') || !odata_mimir_enabled() || (function_exists('odata_mimir_circuit_open') && odata_mimir_circuit_open())): ?>
        <?= injectTimerHtml([
            'statusUrl' => 'odata.php?action=cache_status',
            'deleteUrl' => 'odata.php?action=cache_delete',
            'clearUrl' => 'odata.php?action=cache_clear',
            'title' => 'Cachebestanden',
            'label' => 'Cache',
            'css' => '{{root}} .odata-cache-widget{top:16px;right:16px;left:auto;} {{root}} .odata-cache-popout{top:64px;right:16px;left:auto;}',
        ]) ?>
    <?php endif; ?>
</div>

<div id="elpis-modal" class="elpis-modal" hidden
     data-label-workorder="<?= elpis_h(LOC('elpis.col.workorder')) ?>"
     data-label-item="<?= elpis_h(LOC('elpis.col.item')) ?>"
     data-label-description="<?= elpis_h(LOC('elpis.col.description')) ?>"
     data-label-planning_date="<?= elpis_h(LOC('elpis.col.planning_date')) ?>"
     data-label-material_status="<?= elpis_h(LOC('elpis.col.material_status')) ?>"
     data-label-to_order="<?= elpis_h(LOC('elpis.col.to_order')) ?>"
     data-label-ordered="<?= elpis_h(LOC('elpis.col.ordered')) ?>"
     data-label-open="<?= elpis_h(LOC('elpis.col.outstanding')) ?>"
     data-label-received="<?= elpis_h(LOC('elpis.col.received')) ?>"
     data-label-expected_receipt="<?= elpis_h(LOC('elpis.col.expected_receipt')) ?>"
     data-label-stock="<?= elpis_h(LOC('elpis.col.stock')) ?>"
     data-empty="<?= elpis_h(LOC('elpis.empty.lines')) ?>"
     data-status-all="<?= elpis_h(LOC('elpis.material_status.all')) ?>"
     data-load-failed="<?= elpis_h(LOC('elpis.error.load_failed')) ?>">
    <div class="elpis-modal-panel" role="dialog" aria-modal="true" aria-labelledby="elpis-modal-title">
        <div class="elpis-modal-head">
            <h2 id="elpis-modal-title"></h2>
            <div>
                <button type="button" class="elpis-btn elpis-btn--compact" data-elpis-refresh><?= elpis_h(LOC('elpis.btn.refresh')) ?></button>
                <button type="button" class="elpis-btn elpis-btn--compact" data-elpis-modal-close><?= elpis_h(LOC('elpis.btn.close')) ?></button>
            </div>
        </div>
        <div class="elpis-modal-toolbar">
            <label>
                <?= elpis_h(LOC('elpis.label.line_search')) ?>
                <input type="search" id="elpis-line-search" name="line_q" value="<?= elpis_h(trim((string) ($_GET['line_q'] ?? ''))) ?>" autocomplete="off" spellcheck="false" placeholder="<?= elpis_h(LOC('elpis.placeholder.line_search')) ?>">
            </label>
            <label>
                <?= elpis_h(LOC('elpis.label.material_status_filter')) ?>
                <select id="elpis-material-status-filter"></select>
            </label>
        </div>
        <div class="elpis-modal-body" id="elpis-modal-body"></div>
    </div>
</div>
<div id="elpis-loader-meta" hidden
     data-copy-ok="<?= elpis_h(LOC('elpis.link.copied')) ?>"
     data-copy-fail="<?= elpis_h(LOC('elpis.link.copy_failed')) ?>"></div>
<div id="elpis-loader" class="elpis-loader" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="elpis-loader-panel">
        <div class="elpis-loader-spinner" aria-hidden="true"></div>
        <div id="elpis-loader-steps" class="elpis-loader-steps" aria-hidden="true"></div>
        <p class="elpis-loader-title"><?= elpis_h(LOC('elpis.loader.wait')) ?></p>
        <p class="elpis-loader-text"><?= elpis_h(LOC('elpis.loader.loading')) ?></p>
    </div>
</div>

<script src="elpis_history.js"></script>
<script>
(function () {
    var DELAY_MS = 500;
    var loader = document.getElementById('elpis-loader');
    var stepsGrid = document.getElementById('elpis-loader-steps');
    if (!loader) {
        return;
    }

    var timer = null;
    var inventoryExpanded = false;
    var pendingNavigationTimer = null;
    var navigationAborted = false;
    var activeProgress = null;

    function showLoader() {
        loader.classList.add('is-visible');
        loader.setAttribute('aria-hidden', 'false');
        loader.setAttribute('aria-busy', 'true');
        if (stepsGrid) {
            stepsGrid.setAttribute('aria-hidden', 'false');
        }
    }

    function hideLoader() {
        loader.classList.remove('is-visible');
        loader.setAttribute('aria-hidden', 'true');
        loader.setAttribute('aria-busy', 'false');
        if (stepsGrid) {
            stepsGrid.setAttribute('aria-hidden', 'true');
            stepsGrid.innerHTML = '';
        }
    }

    function clearLoaderTimer() {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    }

    function scheduleLoader() {
        if (timer !== null) {
            return;
        }
        timer = window.setTimeout(function () {
            timer = null;
            showLoader();
        }, DELAY_MS);
    }

    function addStepSlot(stepId) {
        if (!stepsGrid || stepsGrid.querySelector('[data-step-id="' + stepId + '"]')) {
            return;
        }

        var slot = document.createElement('div');
        slot.className = 'elpis-load-step';
        slot.setAttribute('data-step-id', stepId);
        slot.innerHTML = '<img src="box.svg" width="20" height="20" alt="">';
        stepsGrid.appendChild(slot);
    }

    function resetStepsGrid(stepIds) {
        if (!stepsGrid) {
            return;
        }

        stepsGrid.innerHTML = '';
        inventoryExpanded = false;
        stepIds.forEach(function (stepId) {
            addStepSlot(stepId);
        });
    }

    function markInventoryProgress(done, total) {
        done = parseInt(done, 10) || 0;
        total = parseInt(total, 10) || 0;
        if (!stepsGrid || total <= 1) {
            if (done > 0) {
                markStepDone('inventory');
            }
            return;
        }

        if (!inventoryExpanded) {
            var single = stepsGrid.querySelector('[data-step-id="inventory"]');
            if (single) {
                single.remove();
            }
            inventoryExpanded = true;
        }

        var index;
        for (index = 1; index <= total; index += 1) {
            addStepSlot('inventory-' + index);
        }
        for (index = 1; index <= done && index <= total; index += 1) {
            markStepDone('inventory-' + index);
        }
    }

    function markStepDone(stepId) {
        if (!stepsGrid) {
            return;
        }

        var slot = stepsGrid.querySelector('[data-step-id="' + stepId + '"]');
        if (slot) {
            slot.classList.add('is-done');
        }
    }

    function urlFromForm(form) {
        var url = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
        var formData = new FormData(form);

        formData.forEach(function (value, key) {
            if (String(value) !== '') {
                url.searchParams.set(key, String(value));
            } else {
                url.searchParams.delete(key);
            }
        });

        var currentLang = new URL(window.location.href).searchParams.get('lang');
        if (currentLang) {
            url.searchParams.set('lang', currentLang);
        }

        return url;
    }

    function buildStepIds() {
        return ['managers', 'projects'];
    }

    function navigateTo(url, options) {
        options = options || {};
        var target = url.pathname + url.search;
        if (options.replace) {
            window.location.replace(target);
            return;
        }
        window.location.assign(target);
    }

    function handleProgressEvent(eventData) {
        if (eventData.error) {
            throw new Error('progress failed');
        }

        if (eventData.step === 'inventory' && (eventData.status === 'progress' || eventData.status === 'done')) {
            markInventoryProgress(eventData.done, eventData.total);
            if (eventData.status === 'done' && !inventoryExpanded) {
                markStepDone('inventory');
            }
            return;
        }

        if (eventData.status === 'done' && eventData.step) {
            markStepDone(eventData.step);
        }
    }

    function runProgressAndNavigate(url, options) {
        options = options || {};
        var delayMs = typeof options.delayMs === 'number' ? options.delayMs : 0;

        function startProgressLoad() {
            if (navigationAborted) {
                return;
            }
            clearLoaderTimer();
            showLoader();
            resetStepsGrid(buildStepIds(url));

            var progressUrl = new URL('elpis_progress.php', window.location.href);
            var navigationStarted = false;

            url.searchParams.forEach(function (value, key) {
                if (key === '_loaded') {
                    return;
                }
                progressUrl.searchParams.set(key, value);
            });

            function finishNavigation() {
                if (navigationStarted || navigationAborted) {
                    return;
                }
                navigationStarted = true;
                sessionStorage.setItem('elpis_skip_loader', '1');
                url.searchParams.set('_loaded', '1');
                var replaceNavigation = ElpisHistory.navigationMode(options.history) === 'replace';
                navigateTo(url, { replace: replaceNavigation });
            }

            if (activeProgress) {
                activeProgress.abort();
            }
            activeProgress = typeof AbortController === 'function' ? new AbortController() : null;

            fetch(progressUrl.toString(), {
                signal: activeProgress ? activeProgress.signal : undefined
            }).then(function (response) {
            if (!response.ok || !response.body) {
                throw new Error('progress failed');
            }

            var reader = response.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var streamComplete = false;

            function readChunk() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (!streamComplete) {
                            finishNavigation();
                        }
                        return;
                    }

                    buffer += decoder.decode(result.value, { stream: true });
                    var parts = buffer.split('\n');
                    buffer = parts.pop() || '';

                    parts.forEach(function (line) {
                        var trimmed = line.trim();
                        if (trimmed === '') {
                            return;
                        }

                        var eventData = JSON.parse(trimmed);
                        handleProgressEvent(eventData);

                        if (eventData.complete) {
                            streamComplete = true;
                            finishNavigation();
                        }
                    });

                    return readChunk();
                });
            }

            return readChunk();
            }).catch(function (error) {
                if (navigationAborted || (error && error.name === 'AbortError')) {
                    return;
                }
                finishNavigation();
            });
        }

        if (delayMs > 0) {
            pendingNavigationTimer = window.setTimeout(function () {
                pendingNavigationTimer = null;
                startProgressLoad();
            }, delayMs);
            return;
        }

        startProgressLoad();
    }

    function shouldInterceptNavigation(event) {
        return !(event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey);
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.elpis-nav[href], .lang-switcher-item a');
        if (!trigger || !shouldInterceptNavigation(event)) {
            return;
        }
        if (trigger.tagName === 'A' && trigger.target === '_blank') {
            return;
        }

        event.preventDefault();
        runProgressAndNavigate(new URL(trigger.href, window.location.href));
    }, true);

    document.querySelectorAll('form.elpis-nav').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            runProgressAndNavigate(urlFromForm(form));
        });
    });

    var filterForm = document.getElementById('elpis-filter-form');
    var companySelect = filterForm ? filterForm.querySelector('[data-elpis-company-select]') : null;
    var managerSelect = filterForm ? filterForm.querySelector('[data-elpis-manager-select]') : null;

    function ensureHiddenInput(form, name, value) {
        var input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function syncQueryToFilterForm() {
        var projectSearchInput = document.getElementById('elpis-project-search');
        var query = projectSearchInput ? projectSearchInput.value.trim() : '';
        if (query !== '') {
            ensureHiddenInput(filterForm, 'q', query);
            return;
        }

        var queryInput = filterForm.querySelector('input[name="q"]');
        if (queryInput) {
            queryInput.remove();
        }
    }

    function submitFilterForm() {
        syncQueryToFilterForm();
        runProgressAndNavigate(urlFromForm(filterForm));
    }

    if (companySelect && filterForm) {
        companySelect.addEventListener('change', function () {
            if (managerSelect) {
                managerSelect.removeAttribute('name');
                managerSelect.disabled = true;
            }

            ensureHiddenInput(filterForm, 'reload_managers', '1');
            submitFilterForm();
        });
    }

    if (managerSelect && filterForm && !managerSelect.disabled) {
        managerSelect.addEventListener('change', function () {
            var reloadManagersFlag = filterForm.querySelector('input[name="reload_managers"]');
            if (reloadManagersFlag) {
                reloadManagersFlag.remove();
            }

            submitFilterForm();
        });
    }

    window.addEventListener('pagehide', function () {
        navigationAborted = true;
        if (pendingNavigationTimer !== null) {
            window.clearTimeout(pendingNavigationTimer);
            pendingNavigationTimer = null;
        }
        if (activeProgress) {
            activeProgress.abort();
            activeProgress = null;
        }
    });

    window.addEventListener('pageshow', function (event) {
        navigationAborted = false;
        if (!event.persisted || !document.querySelector('[data-elpis-deferred-load="1"]')) {
            return;
        }
        runProgressAndNavigate(new URL(window.location.href), {
            delayMs: 0,
            history: 'deferred'
        });
    });

    if (sessionStorage.getItem('elpis_skip_loader')) {
        sessionStorage.removeItem('elpis_skip_loader');
        hideLoader();

        var cleanUrl = new URL(window.location.href);
        if (ElpisHistory.hasTransientParams(cleanUrl.searchParams)) {
            ElpisHistory.stripTransientParams(cleanUrl.searchParams);
            window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search);
        }
    }

    var deferredProjectsSection = document.querySelector('[data-elpis-deferred-load="1"]');
    if (deferredProjectsSection) {
        var deferredUrl = new URL(window.location.href);
        runProgressAndNavigate(deferredUrl, {
            delayMs: DELAY_MS,
            history: 'deferred'
        });
    }
})();

(function () {
    var searchInput = document.getElementById('elpis-project-search');
    var searchEmpty = document.getElementById('elpis-search-empty');
    var projectItems = document.querySelectorAll('[data-elpis-project-item]');
    var loaderMeta = document.getElementById('elpis-loader-meta');
    var modal = document.getElementById('elpis-modal');
    var modalTitle = document.getElementById('elpis-modal-title');
    var modalBody = document.getElementById('elpis-modal-body');
    var lineSearch = document.getElementById('elpis-line-search');
    var statusFilter = document.getElementById('elpis-material-status-filter');
    var refreshButton = document.querySelector('[data-elpis-refresh]');
    var loader = document.getElementById('elpis-loader');
    var currentProject = '';
    var currentLines = [];
    var loadGeneration = 0;
    var numericSortKeys = { to_order: true, ordered: true, open: true, received: true, stock: true };
        var toneClass = { alert: 'elpis-row--alert', orange: 'elpis-row--orange', warn: 'elpis-row--warn', ok: 'elpis-row--ok' };
        var optClass = { alert: 'elpis-opt-alert', orange: 'elpis-opt-orange', warn: 'elpis-opt-warn', ok: 'elpis-opt-ok', muted: 'elpis-opt-muted' };
        var optBg = { alert: '#fdebec', orange: '#ffedd5', warn: '#fff8db', ok: '#e8f8ee', muted: '#f3f4f6' };

    function normalizeSearchQuery(value) {
        return String(value || '').toLowerCase().trim();
    }

    function matchesTokens(haystack, tokens) {
        if (tokens.length === 0) {
            return true;
        }
        for (var i = 0; i < tokens.length; i += 1) {
            if (haystack.indexOf(tokens[i]) === -1) {
                return false;
            }
        }
        return true;
    }

    function focusProjectNo() {
        return new URL(window.location.href).searchParams.get('project') || '';
    }

    function projectLabel(projectNo) {
        var opener = null;
        document.querySelectorAll('[data-elpis-open-project]').forEach(function (button) {
            if (button.getAttribute('data-project-no') === projectNo) {
                opener = button;
            }
        });
        if (opener) {
            return opener.getAttribute('data-project-label') || projectNo;
        }
        return projectNo;
    }

    function applyProjectFilters() {
        var query = normalizeSearchQuery(searchInput ? searchInput.value : '');
        var tokens = query === '' ? [] : query.split(/\s+/).filter(Boolean);
        var pinnedProjectNo = focusProjectNo();
        var visibleCount = 0;
        projectItems.forEach(function (item) {
            var projectHaystack = item.getAttribute('data-search-text') || '';
            var projectNo = item.getAttribute('data-project-no') || '';
            var isPinned = pinnedProjectNo !== '' && projectNo === pinnedProjectNo;
            var projectVisible = matchesTokens(projectHaystack, tokens) || isPinned;
            item.classList.toggle('is-filtered-out', !projectVisible);
            item.classList.toggle('is-pinned', isPinned);
            item.classList.toggle('is-highlighted', isPinned);
            if (projectVisible) {
                visibleCount += 1;
            }
        });
        if (searchEmpty) {
            searchEmpty.classList.toggle('is-visible', tokens.length > 0 && visibleCount === 0);
        }
    }

    function copyProjectLink(button) {
        var relativeUrl = button.getAttribute('data-copy-url') || '';
        var absoluteUrl = relativeUrl === '' ? window.location.href : new URL(relativeUrl, window.location.href).href;
        var okMessage = loaderMeta ? loaderMeta.getAttribute('data-copy-ok') : '';
        var failMessage = loaderMeta ? loaderMeta.getAttribute('data-copy-fail') : '';
        function copied() { button.setAttribute('title', okMessage || 'OK'); }
        function failed() { button.setAttribute('title', failMessage || ''); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(absoluteUrl).then(copied).catch(failed);
            return;
        }
        var helper = document.createElement('textarea');
        helper.value = absoluteUrl;
        document.body.appendChild(helper);
        helper.select();
        try { document.execCommand('copy'); copied(); } catch (error) { failed(); }
        helper.remove();
    }

    function showLoaderNow() {
        if (!loader) {
            return;
        }
        loader.classList.add('is-visible');
        loader.setAttribute('aria-hidden', 'false');
        loader.setAttribute('aria-busy', 'true');
    }

    function hideLoaderNow() {
        if (!loader) {
            return;
        }
        loader.classList.remove('is-visible');
        loader.setAttribute('aria-hidden', 'true');
        loader.setAttribute('aria-busy', 'false');
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
        });
    }

    function renderStatusOptions(statuses) {
        if (!statusFilter) {
            return;
        }
        var current = statusFilter.value;
        if (current === '') {
            current = statusFilter.getAttribute('data-desired-status') || '';
        }
        statusFilter.innerHTML = '';
        var all = document.createElement('option');
        all.value = '';
        all.textContent = modal ? (modal.getAttribute('data-status-all') || '') : '';
        statusFilter.appendChild(all);
        (statuses || []).forEach(function (status) {
            var option = document.createElement('option');
            option.value = status.code || '';
            option.textContent = status.label || status.code || '';
            option.className = optClass[status.tone] || '';
            option.style.backgroundColor = optBg[status.tone] || '';
            statusFilter.appendChild(option);
        });
        if (current !== '') {
            statusFilter.value = current;
        }
        paintStatusFilter();
    }

    function paintStatusFilter() {
        if (!statusFilter) {
            return;
        }
        var selected = statusFilter.options[statusFilter.selectedIndex];
        statusFilter.style.backgroundColor = selected ? (selected.style.backgroundColor || '') : '';
    }

    function lineVisible(line) {
        var query = normalizeSearchQuery(lineSearch ? lineSearch.value : '');
        var tokens = query === '' ? [] : query.split(/\s+/).filter(Boolean);
        var status = statusFilter ? statusFilter.value : '';
        var haystack = String(line.search || '').toLowerCase();
        var statusOk = status === '' || line.filter_status === status || (status !== 'LATE' && (line.material_status_filter || line.material_status) === status);
        return matchesTokens(haystack, tokens) && statusOk;
    }

    function renderLines() {
        if (!modalBody) {
            return;
        }
        var visible = currentLines.filter(lineVisible);
        if (visible.length === 0) {
            modalBody.innerHTML = '<p class="elpis-muted">' + esc(modal ? modal.getAttribute('data-empty') : '') + '</p>';
            return;
        }
        var sample = visible[0].tooltips || {};
        function label(name) {
            return modal ? (modal.getAttribute('data-label-' + name) || name) : name;
        }
        var headers = [
            ['workorder', label('workorder'), sample.workorder],
            ['item', label('item'), sample.item],
            ['description', label('description'), sample.description],
            ['planning_date', label('planning_date'), sample.planning_date],
            ['material_status', label('material_status'), sample.material_status],
            ['to_order', label('to_order'), sample.to_order],
            ['ordered', label('ordered'), sample.ordered],
            ['open', label('open'), sample.open],
            ['received', label('received'), sample.received],
            ['expected_receipt', label('expected_receipt'), sample.expected_receipt],
            ['stock', label('stock'), sample.stock]
        ];
        var html = '<div class="elpis-table-wrap"><table class="elpis-table"><thead><tr>';
        headers.forEach(function (header) {
            var numeric = header[0] === 'to_order' || header[0] === 'ordered' || header[0] === 'open' || header[0] === 'received' || header[0] === 'stock';
            html += '<th' + (numeric ? ' class="num"' : '') + '><button type="button" class="elpis-sort-btn' + (numeric ? ' num' : '') + '" data-sort-key="' + header[0] + '" title="' + esc(header[2] || '') + '">' + esc(header[1]) + '</button></th>';
        });
        html += '</tr></thead><tbody>';
        visible.forEach(function (line) {
            var tips = line.tooltips || {};
            var rowClass = toneClass[line.tone] || '';
            html += '<tr data-elpis-line-row class="' + rowClass + '"'
                + ' data-sort-workorder="' + esc(String(line.work_order_no || '').toLowerCase()) + '"'
                + ' data-sort-item="' + esc(String(line.item_no || '').toLowerCase()) + '"'
                + ' data-sort-description="' + esc(String(line.description || '').toLowerCase()) + '"'
                + ' data-sort-planning_date="' + esc(line.planning_date_sort || '') + '"'
                + ' data-sort-material_status="' + esc(String(line.material_status_label || '').toLowerCase()) + '"'
                + ' data-sort-to_order="' + esc(line.qty_to_order) + '"'
                + ' data-sort-ordered="' + esc(line.qty_ordered) + '"'
                + ' data-sort-open="' + esc(line.qty_open) + '"'
                + ' data-sort-received="' + esc(line.qty_received) + '"'
                + ' data-sort-expected_receipt="' + esc(line.expected_receipt_sort || '') + '"'
                + ' data-sort-stock="' + esc(line.free_stock_sort || '') + '">';
            function cell(text, tip, extra) {
                return '<td' + (extra || '') + ' title="' + esc(tip || '') + '">' + esc(text) + '</td>';
            }
            html += cell(line.work_order_no, tips.workorder);
            html += cell(line.item_no, tips.item);
            html += cell(line.description, tips.description, ' class="elpis-col-description"');
            html += cell(line.planning_date, tips.planning_date, ' class="date"');
            html += cell(line.material_status_label, tips.material_status);
            html += cell(line.qty_to_order_display, tips.to_order, ' class="num"');
            html += cell(line.qty_ordered_display, tips.ordered, ' class="num"');
            html += cell(line.qty_open_display, tips.open, ' class="num"');
            html += cell(line.qty_received_display, tips.received, ' class="num"');
            html += cell(line.expected_receipt, tips.expected_receipt, ' class="date"');
            html += cell(line.free_stock_display, tips.stock, ' class="num"');
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        modalBody.innerHTML = html;
        bindSort(modalBody.querySelector('table'));
    }

    function bindSort(table) {
        if (!table) {
            return;
        }
        var sortState = { key: '', asc: true };
        table.querySelectorAll('.elpis-sort-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var sortKey = button.getAttribute('data-sort-key') || '';
                var tbody = table.querySelector('tbody');
                if (!tbody || sortKey === '') {
                    return;
                }
                if (sortState.key === sortKey) {
                    sortState.asc = !sortState.asc;
                } else {
                    sortState.key = sortKey;
                    sortState.asc = true;
                }
                table.querySelectorAll('.elpis-sort-btn').forEach(function (other) {
                    other.classList.remove('is-asc', 'is-desc');
                });
                button.classList.add(sortState.asc ? 'is-asc' : 'is-desc');
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-elpis-line-row]'));
                rows.sort(function (left, right) {
                    var leftValue = left.getAttribute('data-sort-' + sortKey) || '';
                    var rightValue = right.getAttribute('data-sort-' + sortKey) || '';
                    var compareValue = numericSortKeys[sortKey]
                        ? (parseFloat(leftValue) || 0) - (parseFloat(rightValue) || 0)
                        : leftValue.localeCompare(rightValue, undefined, { numeric: true, sensitivity: 'base' });
                    return sortState.asc ? compareValue : -compareValue;
                });
                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    }

    function openModal(projectNo, label) {
        currentProject = projectNo;
        if (modalTitle) {
            modalTitle.textContent = label || projectNo;
        }
        if (modal) {
            modal.hidden = false;
        }
        applyProjectFilters();
    }

    function hideModal() {
        loadGeneration += 1;
        hideLoaderNow();
        if (modal) {
            modal.hidden = true;
        }
        currentProject = '';
        applyProjectFilters();
    }

    function captureLineFilters(view) {
        var lineQuery = view && view.line_q ? view.line_q : '';
        var lineStatus = view && view.line_status ? view.line_status : '';
        if (lineSearch) {
            lineSearch.value = lineQuery;
        }
        if (statusFilter) {
            statusFilter.setAttribute('data-desired-status', lineStatus);
            statusFilter.value = lineStatus;
        }
    }

    function loadProject(projectNo, label, refresh) {
        var generation = ++loadGeneration;
        openModal(projectNo, label);
        captureLineFilters(projectHistory.current());
        showLoaderNow();
        var url = new URL('project_lines.php', window.location.href);
        var page = new URL(window.location.href);
        url.searchParams.set('company', page.searchParams.get('company') || '');
        url.searchParams.set('project', projectNo);
        if (refresh) {
            url.searchParams.set('refresh', '1');
        }
        var lang = page.searchParams.get('lang');
        if (lang) {
            url.searchParams.set('lang', lang);
        }
        fetch(url.toString(), { cache: 'no-store' }).then(function (response) {
            if (!response.ok) {
                throw new Error('load failed');
            }
            return response.json();
        }).then(function (data) {
            if (generation !== loadGeneration) {
                return;
            }
            currentLines = data.lines || [];
            renderStatusOptions(data.statuses || []);
            renderLines();
        }).catch(function () {
            if (generation !== loadGeneration) {
                return;
            }
            if (modalBody) {
                modalBody.innerHTML = '<p class="elpis-alert">' + esc(modal ? modal.getAttribute('data-load-failed') : '') + '</p>';
            }
        }).finally(function () {
            if (generation !== loadGeneration) {
                return;
            }
            hideLoaderNow();
        });
    }

    var projectHistory = ElpisHistory.createProjectHistory({
        onListFilter: function (view) {
            if (searchInput && searchInput.value !== view.q) {
                searchInput.value = view.q;
            }
            applyProjectFilters();
        },
        onOpenProject: function (view) {
            loadProject(view.project, projectLabel(view.project), false);
        },
        onCloseProject: function () {
            hideModal();
        },
        onLineFilter: function (view) {
            captureLineFilters(view);
            if (statusFilter) {
                paintStatusFilter();
            }
            renderLines();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyProjectFilters();
            projectHistory.setListQuery(searchInput.value);
        });
    }
    applyProjectFilters();
    projectHistory.seed();
    projectHistory.install();

    document.querySelectorAll('[data-elpis-open-project]').forEach(function (button) {
        button.addEventListener('click', function () {
            projectHistory.openProject(button.getAttribute('data-project-no') || '');
        });
    });
    document.querySelectorAll('[data-elpis-copy-link]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            copyProjectLink(button);
        });
    });
    if (lineSearch) {
        lineSearch.addEventListener('input', function () {
            renderLines();
            projectHistory.setLineQuery(lineSearch.value);
        });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            statusFilter.setAttribute('data-desired-status', statusFilter.value);
            paintStatusFilter();
            renderLines();
            projectHistory.setStatus(statusFilter.value);
        });
    }
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            if (currentProject !== '') {
                loadProject(currentProject, modalTitle ? modalTitle.textContent : currentProject, true);
            }
        });
    }
    document.querySelectorAll('[data-elpis-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            projectHistory.closeProject();
        });
    });
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                projectHistory.closeProject();
            }
        });
    }

    var initial = focusProjectNo();
    if (initial !== '') {
        var opener = null;
        document.querySelectorAll('[data-elpis-open-project]').forEach(function (button) {
            if (button.getAttribute('data-project-no') === initial) {
                opener = button;
            }
        });
        if (opener) {
            loadProject(initial, opener.getAttribute('data-project-label') || initial, false);
        }
    }
})();
</script>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
