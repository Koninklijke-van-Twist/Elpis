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

function elpis_format_qty(float $value): string
{
    if (abs($value - round($value)) < 0.00001) {
        return (string) (int) round($value);
    }

    return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
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

/**
 * Page load
 */

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
$discoveredSelection = elpis_discover_selection_for_new_user(
    $companies,
    $userEmail,
    $requestedCompany,
    $requestedManager,
    $savedCompany,
    $savedManagersByCompany
);
$preselectedManager = null;

if (is_array($discoveredSelection)) {
    $company = (string) ($discoveredSelection['company'] ?? '');
    if ($company === '' || !in_array($company, $companies, true)) {
        $company = elpis_resolve_company_choice($companies, $requestedCompany, $savedCompany);
    } else {
        $preselectedManager = trim((string) ($discoveredSelection['manager'] ?? ''));
    }
} else {
    $company = elpis_resolve_company_choice($companies, $requestedCompany, $savedCompany);
}

$projectManager = '';
$deferProjectsLoad = !isset($_GET['_loaded']);

$errorKey = '';
$projectManagers = [];
$projects = [];
$linesByProject = [];

auth_set_current_company_context($company);

try {
    $projectManagers = elpis_fetch_project_managers($company);
    if ($preselectedManager !== null && $preselectedManager !== '') {
        $projectManager = elpis_pick_manager_from_list($projectManagers, $preselectedManager);
        if ($projectManager === '') {
            $projectManager = elpis_resolve_manager_choice(
                $projectManagers,
                $requestedManager,
                $company,
                $savedManagersByCompany,
                $userEmail
            );
        }
    } else {
        $projectManager = elpis_resolve_manager_choice(
            $projectManagers,
            $requestedManager,
            $company,
            $savedManagersByCompany,
            $userEmail
        );
    }

    if ($projectManager !== '' && !$deferProjectsLoad) {
        $projects = elpis_fetch_projects_for_manager($company, $projectManager);
        $allLinesByProject = elpis_fetch_planning_lines_for_company($company);
        foreach ($projects as $project) {
            $projectNo = (string) ($project['no'] ?? '');
            if ($projectNo === '') {
                continue;
            }
            $linesByProject[$projectNo] = $allLinesByProject[$projectNo] ?? [];
        }
    }

    if ($company !== '' && !$deferProjectsLoad) {
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

$projects = elpis_sort_projects_for_display($projects, $linesByProject);
$materialStatusCodes = elpis_collect_material_status_codes($linesByProject);

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
        .elpis-page { max-width: 1280px; margin: 0 auto; padding: 20px 20px 32px; }
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
        .elpis-project-title { font-weight: 700; color: var(--kvt-perkins-blue); }
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
        table.elpis-table td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
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
                    <?php foreach ($companies as $companyOption): ?>
                        <option value="<?= elpis_h($companyOption) ?>"<?= $companyOption === $company ? ' selected' : '' ?>><?= elpis_h($companyOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
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
        </form>
    </section>

    <?php if ($errorKey !== ''): ?>
        <div class="elpis-alert"><?= elpis_h(LOC($errorKey)) ?></div>
    <?php endif; ?>

    <?php if ($projectManager !== ''): ?>
        <section class="elpis-card"<?= $deferProjectsLoad ? ' data-elpis-deferred-load="1"' : '' ?>>
            <h2><?= elpis_h(LOC('elpis.section.projects')) ?></h2>
            <p class="elpis-muted"><?= elpis_h(LOC('elpis.meta.manager')) ?>: <?= elpis_h(elpis_is_all_managers_selection($projectManager) ? LOC('elpis.manager.all') : elpis_format_manager_label($projectManager)) ?></p>

            <?php if ($deferProjectsLoad): ?>
                <ul class="elpis-project-list" id="elpis-project-list" aria-busy="true"></ul>
            <?php elseif ($projects === []): ?>
                <p class="elpis-muted"><?= elpis_h(LOC('elpis.empty.projects')) ?></p>
            <?php else: ?>
                <div class="elpis-project-toolbar">
                    <div class="elpis-project-toolbar-filters">
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
                        <label>
                            <?= elpis_h(LOC('elpis.label.material_status_filter')) ?>
                            <select id="elpis-material-status-filter">
                                <option value=""><?= elpis_h(LOC('elpis.material_status.all')) ?></option>
                                <?php foreach ($materialStatusCodes as $statusCode): ?>
                                    <option value="<?= elpis_h($statusCode) ?>"><?= elpis_h(elpis_material_status_label($statusCode)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="elpis-project-actions">
                        <button type="button" class="elpis-btn elpis-btn--compact" data-elpis-expand-all><?= elpis_h(LOC('elpis.btn.expand_all')) ?></button>
                        <button type="button" class="elpis-btn elpis-btn--compact" data-elpis-collapse-all><?= elpis_h(LOC('elpis.btn.collapse_all')) ?></button>
                    </div>
                    <p id="elpis-search-empty" class="elpis-muted elpis-search-empty"><?= elpis_h(LOC('elpis.empty.search')) ?></p>
                </div>
                <ul class="elpis-project-list" id="elpis-project-list">
                    <?php foreach ($projects as $projectIndex => $project): ?>
                        <?php
                        $projectNo = (string) ($project['no'] ?? '');
                        $projectLines = $linesByProject[$projectNo] ?? [];
                        $searchBlob = elpis_project_search_blob($project, $projectLines);
                        $projectMatchesSearch = elpis_matches_search_tokens($searchBlob, $projectSearchTokens);
                        $hasLines = $projectLines !== [];
                        $isFocused = $projectNo !== '' && $projectNo === $openProjectNo;
                        $isOpen = $hasLines || $isFocused;
                        $shareUrl = elpis_url([
                            'company' => $company,
                            'manager' => $projectManager,
                            'project' => $projectNo,
                            'q' => null,
                        ]);
                        ?>
                        <li
                            class="elpis-project-item<?= $isOpen ? ' is-open' : '' ?><?= !$hasLines ? ' elpis-project-item--empty' : '' ?><?= !$projectMatchesSearch ? ' is-filtered-out' : '' ?><?= $isFocused ? ' is-highlighted is-pinned' : '' ?>"
                            data-elpis-project-item
                            data-project-no="<?= elpis_h($projectNo) ?>"
                            data-project-index="<?= (int) $projectIndex ?>"
                            data-has-lines="<?= $hasLines ? '1' : '0' ?>"
                            data-search-text="<?= elpis_h($searchBlob) ?>"
                            <?= $isFocused ? ' data-elpis-focus-project="1"' : '' ?>
                        >
                            <div class="elpis-project-header">
                                <button type="button" class="elpis-project-toggle" data-elpis-project-toggle aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                                    <span>
                                        <span class="elpis-project-title"><?= elpis_h($projectNo) ?></span>
                                        <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                            <span> — <?= elpis_h((string) $project['description']) ?></span>
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
                            <div class="elpis-project-panel">
                                <?php if ($projectLines === []): ?>
                                    <p class="elpis-muted"><?= elpis_h(LOC('elpis.empty.lines')) ?></p>
                                <?php else: ?>
                                    <div class="elpis-search-wrap elpis-line-search-wrap">
                                        <label>
                                            <?= elpis_h(LOC('elpis.label.line_search')) ?>
                                            <input
                                                type="search"
                                                class="elpis-line-search"
                                                data-elpis-line-search
                                                placeholder="<?= elpis_h(LOC('elpis.placeholder.line_search')) ?>"
                                                autocomplete="off"
                                                spellcheck="false"
                                            >
                                        </label>
                                    </div>
                                    <div class="elpis-table-wrap">
                                        <table class="elpis-table" data-elpis-sortable-table>
                                            <thead>
                                                <tr>
                                                    <th class="elpis-col-project-no" aria-hidden="true"></th>
                                                    <th><button type="button" class="elpis-sort-btn" data-sort-key="workorder"><?= elpis_h(LOC('elpis.col.workorder')) ?></button></th>
                                                    <th><button type="button" class="elpis-sort-btn" data-sort-key="item"><?= elpis_h(LOC('elpis.col.item')) ?></button></th>
                                                    <th><button type="button" class="elpis-sort-btn" data-sort-key="description"><?= elpis_h(LOC('elpis.col.description')) ?></button></th>
                                                    <th><button type="button" class="elpis-sort-btn" data-sort-key="material_status"><?= elpis_h(LOC('elpis.col.material_status')) ?></button></th>
                                                    <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="to_order"><?= elpis_h(LOC('elpis.col.to_order')) ?></button></th>
                                                    <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="ordered"><?= elpis_h(LOC('elpis.col.ordered')) ?></button></th>
                                                    <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="open"><?= elpis_h(LOC('elpis.col.outstanding')) ?></button></th>
                                                    <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="received"><?= elpis_h(LOC('elpis.col.received')) ?></button></th>
                                                    <th><button type="button" class="elpis-sort-btn" data-sort-key="expected_receipt"><?= elpis_h(LOC('elpis.col.expected_receipt')) ?></button></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($projectLines as $line): ?>
                                                    <?php
                                                    $qtyToOrder = (float) ($line['qty_to_order'] ?? 0);
                                                    $qtyOrdered = (float) ($line['qty_ordered'] ?? 0);
                                                    $qtyOpen = (float) ($line['qty_open'] ?? max(0.0, $qtyOrdered - (float) ($line['qty_received'] ?? 0)));
                                                    $qtyReceived = (float) ($line['qty_received'] ?? 0);
                                                    $materialStatusLabel = (string) ($line['material_status_label'] ?? '');
                                                    if ($materialStatusLabel === '' && ($line['material_status'] ?? '') !== '') {
                                                        $materialStatusLabel = elpis_material_status_label((string) $line['material_status']);
                                                    }
                                                    $expectedReceiptDisplay = elpis_line_expected_receipt_display($line);
                                                    $rowClass = elpis_line_row_class($qtyToOrder, $qtyOrdered, $qtyReceived, $qtyOpen);
                                                    $rowClasses = array_filter([$rowClass]);
                                                    ?>
                                                    <tr
                                                        data-elpis-line-row
                                                        data-search-text="<?= elpis_h(elpis_line_search_blob($line, $projectNo)) ?>"
                                                        data-sort-workorder="<?= elpis_h(strtolower((string) ($line['job_task_no'] ?? ''))) ?>"
                                                        data-sort-item="<?= elpis_h(strtolower((string) ($line['item_no'] ?? ''))) ?>"
                                                        data-sort-description="<?= elpis_h(strtolower((string) ($line['description'] ?? ''))) ?>"
                                                        data-sort-material_status="<?= elpis_h(strtolower($materialStatusLabel)) ?>"
                                                        data-sort-to_order="<?= elpis_h((string) $qtyToOrder) ?>"
                                                        data-sort-ordered="<?= elpis_h((string) $qtyOrdered) ?>"
                                                        data-sort-open="<?= elpis_h((string) $qtyOpen) ?>"
                                                        data-sort-received="<?= elpis_h((string) $qtyReceived) ?>"
                                                        data-sort-expected_receipt="<?= elpis_h((string) ($line['expected_receipt_date'] ?? '')) ?>"
                                                        data-material-status="<?= elpis_h((string) ($line['material_status'] ?? '')) ?>"
                                                        <?= $rowClasses !== [] ? ' class="' . elpis_h(implode(' ', $rowClasses)) . '"' : '' ?>
                                                    >
                                                        <td class="elpis-col-project-no"><?= elpis_h($projectNo) ?></td>
                                                        <td><?= elpis_h((string) ($line['job_task_no'] ?? '')) ?></td>
                                                        <td><?= elpis_h((string) ($line['item_no'] ?? '')) ?></td>
                                                        <td><?= elpis_h((string) ($line['description'] ?? '')) ?></td>
                                                        <td><?= elpis_h($materialStatusLabel) ?></td>
                                                        <td class="num"><?= elpis_h(elpis_format_qty($qtyToOrder)) ?></td>
                                                        <td class="num"><?= elpis_h(elpis_format_qty($qtyOrdered)) ?></td>
                                                        <td class="num"><?= elpis_h(elpis_format_qty($qtyOpen)) ?></td>
                                                        <td class="num"><?= elpis_h(elpis_format_qty($qtyReceived)) ?></td>
                                                        <td class="date"><?= elpis_h($expectedReceiptDisplay) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!function_exists('odata_mimir_enabled') || !odata_mimir_enabled()): ?>
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

<script>
(function () {
    var DELAY_MS = 500;
    var loader = document.getElementById('elpis-loader');
    var stepsGrid = document.getElementById('elpis-loader-steps');
    if (!loader) {
        return;
    }

    var timer = null;

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
        stepIds.forEach(function (stepId) {
            addStepSlot(stepId);
        });
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

    function buildStepIds(url) {
        var stepIds = ['managers'];

        if (!url.searchParams.has('reload_managers')) {
            stepIds.push('projects', 'lines');
        }

        return stepIds;
    }

    function navigateTo(url) {
        window.location.href = url.pathname + url.search;
    }

    function handleProgressEvent(eventData) {
        if (eventData.error) {
            throw new Error('progress failed');
        }

        if (eventData.status === 'done' && eventData.step) {
            markStepDone(eventData.step);
        }
    }

    function runProgressAndNavigate(url, options) {
        options = options || {};
        var delayMs = typeof options.delayMs === 'number' ? options.delayMs : 0;
        var loadTimer = null;

        function startProgressLoad() {
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
                if (navigationStarted) {
                    return;
                }
                navigationStarted = true;
                sessionStorage.setItem('elpis_skip_loader', '1');
                url.searchParams.set('_loaded', '1');
                navigateTo(url);
            }

            fetch(progressUrl.toString()).then(function (response) {
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
            }).catch(function () {
                finishNavigation();
            });
        }

        if (delayMs > 0) {
            loadTimer = window.setTimeout(startProgressLoad, delayMs);
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

    if (sessionStorage.getItem('elpis_skip_loader')) {
        sessionStorage.removeItem('elpis_skip_loader');
        hideLoader();

        var cleanUrl = new URL(window.location.href);
        if (cleanUrl.searchParams.has('_loaded')) {
            cleanUrl.searchParams.delete('_loaded');
            window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search);
        }
    }

    var deferredProjectsSection = document.querySelector('[data-elpis-deferred-load="1"]');
    if (deferredProjectsSection) {
        var deferredUrl = new URL(window.location.href);
        runProgressAndNavigate(deferredUrl, {
            delayMs: DELAY_MS
        });
    }
})();

(function () {
    var searchInput = document.getElementById('elpis-project-search');
    var searchEmpty = document.getElementById('elpis-search-empty');
    var materialFilter = document.getElementById('elpis-material-status-filter');
    var projectItems = document.querySelectorAll('[data-elpis-project-item]');
    var loaderMeta = document.getElementById('elpis-loader-meta');
    var numericSortKeys = {
        to_order: true,
        ordered: true,
        open: true,
        received: true
    };

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

    function setProjectOpen(item, isOpen) {
        item.classList.toggle('is-open', isOpen);
        var toggle = item.querySelector('[data-elpis-project-toggle]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }

    function syncProjectSearchToUrl() {
        if (!searchInput) {
            return;
        }

        var url = new URL(window.location.href);
        var query = searchInput.value.trim();
        if (query === '') {
            url.searchParams.delete('q');
        } else {
            url.searchParams.set('q', query);
        }

        window.history.replaceState({}, '', url.pathname + url.search);
    }

    function applyProjectFilters() {
        var query = normalizeSearchQuery(searchInput ? searchInput.value : '');
        var tokens = query === '' ? [] : query.split(/\s+/).filter(Boolean);
        var statusFilter = materialFilter ? materialFilter.value : '';
        var pinnedProjectNo = focusProjectNo();
        var visibleCount = 0;

        projectItems.forEach(function (item) {
            var projectHaystack = item.getAttribute('data-search-text') || '';
            var projectMatches = matchesTokens(projectHaystack, tokens);
            var projectNo = item.getAttribute('data-project-no') || '';
            var isPinned = pinnedProjectNo !== '' && projectNo === pinnedProjectNo;
            var matchingLineCount = 0;

            item.querySelectorAll('[data-elpis-line-row]').forEach(function (row) {
                var rowHaystack = row.getAttribute('data-search-text') || '';
                var rowMatchesSearch = matchesTokens(rowHaystack, tokens);
                var lineSearchInput = item.querySelector('[data-elpis-line-search]');
                var lineTokens = [];
                if (lineSearchInput) {
                    var lineQuery = normalizeSearchQuery(lineSearchInput.value);
                    lineTokens = lineQuery === '' ? [] : lineQuery.split(/\s+/).filter(Boolean);
                }
                var rowMatchesLineSearch = matchesTokens(rowHaystack, lineTokens);
                var rowStatus = row.getAttribute('data-material-status') || '';
                var rowMatchesStatus = statusFilter === '' || rowStatus === statusFilter;
                var rowVisible = rowMatchesSearch && rowMatchesLineSearch && rowMatchesStatus;
                row.classList.toggle('is-filtered-out', !rowVisible);
                if (rowVisible) {
                    matchingLineCount += 1;
                }
            });

            var hasLineFilter = statusFilter !== '';
            var projectVisible = projectMatches && (!hasLineFilter || matchingLineCount > 0);
            if (isPinned) {
                projectVisible = true;
            }

            item.classList.toggle('is-filtered-out', !projectVisible);
            item.classList.toggle('is-pinned', isPinned);
            item.classList.toggle('is-highlighted', isPinned);
            if (projectVisible) {
                visibleCount += 1;
            }
        });

        if (searchEmpty) {
            var hasActiveFilter = tokens.length > 0 || statusFilter !== '';
            searchEmpty.classList.toggle('is-visible', hasActiveFilter && visibleCount === 0);
        }

        syncProjectSearchToUrl();
    }

    function applyLineSearch(input) {
        applyProjectFilters();
    }

    function highlightFocusedProject() {
        var projectNo = focusProjectNo();
        if (projectNo === '') {
            return;
        }

        var target = null;
        projectItems.forEach(function (item) {
            if ((item.getAttribute('data-project-no') || '') === projectNo) {
                target = item;
            }
        });

        if (!target) {
            return;
        }

        setProjectOpen(target, true);
        target.classList.add('is-pinned', 'is-highlighted');
        target.classList.remove('is-filtered-out');
        window.setTimeout(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
    }

    function copyProjectLink(button) {
        var relativeUrl = button.getAttribute('data-copy-url') || '';
        var absoluteUrl = relativeUrl === '' ? window.location.href : new URL(relativeUrl, window.location.href).href;
        var okMessage = loaderMeta ? loaderMeta.getAttribute('data-copy-ok') : '';
        var failMessage = loaderMeta ? loaderMeta.getAttribute('data-copy-fail') : '';

        function copied() {
            button.setAttribute('title', okMessage || 'OK');
        }

        function failed() {
            button.setAttribute('title', failMessage || '');
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(absoluteUrl).then(copied).catch(failed);
            return;
        }

        var helper = document.createElement('textarea');
        helper.value = absoluteUrl;
        document.body.appendChild(helper);
        helper.select();
        try {
            document.execCommand('copy');
            copied();
        } catch (error) {
            failed();
        }
        helper.remove();
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyProjectFilters);
    }

    if (materialFilter) {
        materialFilter.addEventListener('change', applyProjectFilters);
    }

    applyProjectFilters();
    highlightFocusedProject();

    document.querySelectorAll('[data-elpis-line-search]').forEach(function (input) {
        input.addEventListener('input', function () {
            applyLineSearch(input);
        });
    });

    document.querySelectorAll('[data-elpis-project-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('[data-elpis-project-item]');
            if (!item) {
                return;
            }

            setProjectOpen(item, !item.classList.contains('is-open'));
        });
    });

    document.querySelectorAll('[data-elpis-copy-link]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            copyProjectLink(button);
        });
    });

    var expandAll = document.querySelector('[data-elpis-expand-all]');
    var collapseAll = document.querySelector('[data-elpis-collapse-all]');
    if (expandAll) {
        expandAll.addEventListener('click', function () {
            projectItems.forEach(function (item) {
                setProjectOpen(item, true);
            });
        });
    }
    if (collapseAll) {
        collapseAll.addEventListener('click', function () {
            projectItems.forEach(function (item) {
                setProjectOpen(item, false);
            });
        });
    }

    window.addEventListener('popstate', function () {
        applyProjectFilters();
        highlightFocusedProject();
    });

    document.querySelectorAll('[data-elpis-sortable-table]').forEach(function (table) {
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

                table.querySelectorAll('.elpis-sort-btn').forEach(function (otherButton) {
                    otherButton.classList.remove('is-asc', 'is-desc');
                });
                button.classList.add(sortState.asc ? 'is-asc' : 'is-desc');

                var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-elpis-line-row]'));
                rows.sort(function (left, right) {
                    var leftValue = left.getAttribute('data-sort-' + sortKey) || '';
                    var rightValue = right.getAttribute('data-sort-' + sortKey) || '';
                    var compareValue;

                    if (numericSortKeys[sortKey]) {
                        compareValue = parseFloat(leftValue) - parseFloat(rightValue);
                    } else {
                        compareValue = leftValue.localeCompare(rightValue, undefined, {
                            numeric: true,
                            sensitivity: 'base'
                        });
                    }

                    return sortState.asc ? compareValue : -compareValue;
                });

                rows.forEach(function (row) {
                    tbody.appendChild(row);
                });
            });
        });
    });
})();
</script>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
