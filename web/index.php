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
    unset($query['lang']);

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

function elpis_sort_projects_open_first(array $projects, string $openProjectNo): array
{
    if ($openProjectNo === '' || $projects === []) {
        return $projects;
    }

    $openProjects = [];
    $otherProjects = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }
        if ((string) ($project['no'] ?? '') === $openProjectNo) {
            $openProjects[] = $project;
            continue;
        }
        $otherProjects[] = $project;
    }

    return array_merge($openProjects, $otherProjects);
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

$errorKey = '';
$projectManagers = [];
$projects = [];
$linesByProject = [];
$planningLines = [];

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

    if ($projectManager !== '') {
        $projects = elpis_fetch_projects_for_manager($company, $projectManager);
        if ($projects !== []) {
            $projectNos = array_map(static function (array $project): string {
                return (string) ($project['no'] ?? '');
            }, $projects);
            $linesByProject = elpis_fetch_planning_lines_by_projects($company, $projectNos);
        }
    }

    if ($company !== '') {
        elpis_save_dropdown_prefs($userEmail, $company, $projectManager);
    }

    if ($openProjectNo !== '') {
        if (isset($linesByProject[$openProjectNo])) {
            $planningLines = $linesByProject[$openProjectNo];
        } else {
            $openProjectNo = '';
        }
    }
} catch (Throwable $loadError) {
    $errorKey = 'elpis.error.load_failed';
}

$projects = elpis_sort_projects_open_first($projects, $openProjectNo);

?><!DOCTYPE html>
<html lang="<?= elpis_h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= elpis_h(LOC('app.title')) ?></title>
    <link rel="stylesheet" href="brand.css">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="favicon.ico" sizes="any">
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
        .elpis-project-toggle {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border: 0;
            background: #fff;
            color: var(--kvt-text);
            text-align: left;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
        }
        .elpis-project-toggle:hover { background: #f2f9ff; }
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
        .elpis-search-wrap { margin: 14px 0 16px; }
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
        .elpis-project-item.is-filtered-out { display: none; }
        table.elpis-table tbody tr.is-filtered-out { display: none; }
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
        table.elpis-table tbody tr.elpis-row--alert { background: var(--kvt-row-alert); }
        table.elpis-table tbody tr.elpis-row--orange { background: var(--kvt-row-orange); }
        table.elpis-table tbody tr.elpis-row--warn { background: var(--kvt-row-warn); }
        table.elpis-table tbody tr.elpis-row--ok { background: var(--kvt-row-ok); }
        @media (min-width: 640px) {
            .elpis-form-grid { grid-template-columns: 1fr 1fr; align-items: end; }
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
        .elpis-loader-title { margin: 0; font-family: var(--kvt-font-display); font-size: 1.1rem; }
        .elpis-loader-text { margin: 0; color: var(--kvt-muted); font-size: 0.92rem; }
        @keyframes elpis-loader-spin { to { transform: rotate(360deg); } }
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
                        <?php foreach ($projectManagers as $managerOption): ?>
                            <option value="<?= elpis_h($managerOption) ?>"<?= strcasecmp($managerOption, $projectManager) === 0 ? ' selected' : '' ?>><?= elpis_h($managerOption) ?></option>
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
        <section class="elpis-card">
            <h2><?= elpis_h(LOC('elpis.section.projects')) ?></h2>
            <p class="elpis-muted"><?= elpis_h(LOC('elpis.meta.manager')) ?>: <?= elpis_h($projectManager) ?></p>

            <?php if ($projects === []): ?>
                <p class="elpis-muted"><?= elpis_h(LOC('elpis.empty.projects')) ?></p>
            <?php else: ?>
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
                    <p id="elpis-search-empty" class="elpis-muted elpis-search-empty"><?= elpis_h(LOC('elpis.empty.search')) ?></p>
                </div>
                <ul class="elpis-project-list" id="elpis-project-list">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectNo = (string) ($project['no'] ?? '');
                        $projectLines = $linesByProject[$projectNo] ?? [];
                        $searchBlob = elpis_project_search_blob($project, $projectLines);
                        $projectMatchesSearch = elpis_matches_search_tokens($searchBlob, $projectSearchTokens);
                        $isOpen = $projectNo !== '' && $projectNo === $openProjectNo;
                        $toggleUrl = elpis_url([
                            'company' => $company,
                            'manager' => $projectManager,
                            'project' => $isOpen ? null : $projectNo,
                            'q' => $projectSearchQuery !== '' ? $projectSearchQuery : null,
                        ]);
                        ?>
                        <li
                            class="elpis-project-item<?= $isOpen ? ' is-open' : '' ?><?= !$projectMatchesSearch ? ' is-filtered-out' : '' ?>"
                            data-elpis-project-item
                            data-search-text="<?= elpis_h($searchBlob) ?>"
                        >
                            <a class="elpis-project-toggle elpis-nav" data-elpis-project-toggle href="<?= elpis_h($toggleUrl) ?>">
                                <span>
                                    <span class="elpis-project-title"><?= elpis_h($projectNo) ?></span>
                                    <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                        <span> — <?= elpis_h((string) $project['description']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="elpis-project-chevron" aria-hidden="true">›</span>
                            </a>
                            <?php if ($isOpen): ?>
                                <div class="elpis-project-panel">
                                    <?php if ($planningLines === []): ?>
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
                                                        <th><button type="button" class="elpis-sort-btn" data-sort-key="workorder"><?= elpis_h(LOC('elpis.col.workorder')) ?></button></th>
                                                        <th><button type="button" class="elpis-sort-btn" data-sort-key="item"><?= elpis_h(LOC('elpis.col.item')) ?></button></th>
                                                        <th><button type="button" class="elpis-sort-btn" data-sort-key="description"><?= elpis_h(LOC('elpis.col.description')) ?></button></th>
                                                        <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="to_order"><?= elpis_h(LOC('elpis.col.to_order')) ?></button></th>
                                                        <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="ordered"><?= elpis_h(LOC('elpis.col.ordered')) ?></button></th>
                                                        <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="open"><?= elpis_h(LOC('elpis.col.outstanding')) ?></button></th>
                                                        <th class="num"><button type="button" class="elpis-sort-btn num" data-sort-key="received"><?= elpis_h(LOC('elpis.col.received')) ?></button></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($planningLines as $line): ?>
                                                        <?php
                                                        $qtyToOrder = (float) ($line['qty_to_order'] ?? 0);
                                                        $qtyOrdered = (float) ($line['qty_ordered'] ?? 0);
                                                        $qtyOpen = (float) ($line['qty_open'] ?? max(0.0, $qtyOrdered - (float) ($line['qty_received'] ?? 0)));
                                                        $qtyReceived = (float) ($line['qty_received'] ?? 0);
                                                        $rowClass = elpis_line_row_class($qtyToOrder, $qtyOrdered, $qtyReceived, $qtyOpen);
                                                        $rowClasses = array_filter([$rowClass]);
                                                        ?>
                                                        <tr
                                                            data-elpis-line-row
                                                            data-search-text="<?= elpis_h(elpis_line_search_blob($line)) ?>"
                                                            data-sort-workorder="<?= elpis_h(strtolower((string) ($line['job_task_no'] ?? ''))) ?>"
                                                            data-sort-item="<?= elpis_h(strtolower((string) ($line['item_no'] ?? ''))) ?>"
                                                            data-sort-description="<?= elpis_h(strtolower((string) ($line['description'] ?? ''))) ?>"
                                                            data-sort-to_order="<?= elpis_h((string) $qtyToOrder) ?>"
                                                            data-sort-ordered="<?= elpis_h((string) $qtyOrdered) ?>"
                                                            data-sort-open="<?= elpis_h((string) $qtyOpen) ?>"
                                                            data-sort-received="<?= elpis_h((string) $qtyReceived) ?>"
                                                            <?= $rowClasses !== [] ? ' class="' . elpis_h(implode(' ', $rowClasses)) . '"' : '' ?>
                                                        >
                                                            <td><?= elpis_h((string) ($line['job_task_no'] ?? '')) ?></td>
                                                            <td><?= elpis_h((string) ($line['item_no'] ?? '')) ?></td>
                                                            <td><?= elpis_h((string) ($line['description'] ?? '')) ?></td>
                                                            <td class="num"><?= elpis_h(elpis_format_qty($qtyToOrder)) ?></td>
                                                            <td class="num"><?= elpis_h(elpis_format_qty($qtyOrdered)) ?></td>
                                                            <td class="num"><?= elpis_h(elpis_format_qty($qtyOpen)) ?></td>
                                                            <td class="num"><?= elpis_h(elpis_format_qty($qtyReceived)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?= injectTimerHtml([
        'statusUrl' => 'odata.php?action=cache_status',
        'deleteUrl' => 'odata.php?action=cache_delete',
        'clearUrl' => 'odata.php?action=cache_clear',
        'title' => 'Cachebestanden',
        'label' => 'Cache',
        'css' => '{{root}} .odata-cache-widget{top:16px;right:16px;left:auto;} {{root}} .odata-cache-popout{top:64px;right:16px;left:auto;}',
    ]) ?>
</div>

<div id="elpis-loader" class="elpis-loader" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="elpis-loader-panel">
        <div class="elpis-loader-spinner" aria-hidden="true"></div>
        <p class="elpis-loader-title"><?= elpis_h(LOC('elpis.loader.wait')) ?></p>
        <p class="elpis-loader-text"><?= elpis_h(LOC('elpis.loader.loading')) ?></p>
    </div>
</div>

<script>
(function () {
    var DELAY_MS = 500;
    var loader = document.getElementById('elpis-loader');
    if (!loader) {
        return;
    }

    var timer = null;

    function showLoader() {
        loader.classList.add('is-visible');
        loader.setAttribute('aria-hidden', 'false');
        loader.setAttribute('aria-busy', 'true');
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

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.elpis-nav[href], .lang-switcher-item a');
        if (!trigger) {
            return;
        }
        if (trigger.tagName === 'A' && trigger.target === '_blank') {
            return;
        }
        scheduleLoader();
    }, true);

    document.querySelectorAll('form.elpis-nav').forEach(function (form) {
        form.addEventListener('submit', function () {
            scheduleLoader();
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
        scheduleLoader();
        filterForm.submit();
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
})();

(function () {
    var searchInput = document.getElementById('elpis-project-search');
    var searchEmpty = document.getElementById('elpis-search-empty');
    var projectItems = document.querySelectorAll('[data-elpis-project-item]');
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
        syncProjectToggleLinks(query);
    }

    function syncProjectToggleLinks(query) {
        document.querySelectorAll('[data-elpis-project-toggle]').forEach(function (link) {
            var url = new URL(link.href, window.location.href);
            if (query === '') {
                url.searchParams.delete('q');
            } else {
                url.searchParams.set('q', query);
            }
            link.href = url.pathname + url.search;
        });
    }

    function applyProjectSearch() {
        var query = normalizeSearchQuery(searchInput ? searchInput.value : '');
        var tokens = query === '' ? [] : query.split(/\s+/).filter(Boolean);
        var visibleCount = 0;

        projectItems.forEach(function (item) {
            var projectHaystack = item.getAttribute('data-search-text') || '';
            var projectMatches = matchesTokens(projectHaystack, tokens);

            item.classList.toggle('is-filtered-out', !projectMatches);
            if (projectMatches) {
                visibleCount += 1;
            }
        });

        if (searchEmpty) {
            searchEmpty.classList.toggle('is-visible', tokens.length > 0 && visibleCount === 0);
        }

        syncProjectSearchToUrl();
    }

    function applyLineSearch(input) {
        var panel = input.closest('.elpis-project-panel');
        if (!panel) {
            return;
        }

        var query = normalizeSearchQuery(input.value);
        var tokens = query === '' ? [] : query.split(/\s+/).filter(Boolean);

        panel.querySelectorAll('[data-elpis-line-row]').forEach(function (row) {
            var rowHaystack = row.getAttribute('data-search-text') || '';
            var rowMatches = matchesTokens(rowHaystack, tokens);
            row.classList.toggle('is-filtered-out', !rowMatches);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyProjectSearch);
        applyProjectSearch();
    }

    document.querySelectorAll('[data-elpis-line-search]').forEach(function (input) {
        input.addEventListener('input', function () {
            applyLineSearch(input);
        });
    });

    document.querySelectorAll('[data-elpis-project-toggle]').forEach(function (link) {
        link.addEventListener('click', function () {
            var query = searchInput ? searchInput.value.trim() : '';
            if (query === '') {
                return;
            }

            var url = new URL(link.href, window.location.href);
            url.searchParams.set('q', query);
            link.href = url.pathname + url.search;
        }, true);
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
