<?php

/**
 * Includes/requires
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logincheck.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/elpis_data.php';

/**
 * Functies
 */

function elpis_maillog_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Page load
 */

$tz = new DateTimeZone('Europe/Amsterdam');
$dateParam = (string) ($_GET['date'] ?? '');
$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam) === 1
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam, $tz)
    : false;
if ($day === false) {
    $day = new DateTimeImmutable('today', $tz);
}
$date = $day->format('Y-m-d');
$prev = $day->modify('-1 day')->format('Y-m-d');
$next = $day->modify('+1 day')->format('Y-m-d');
$today = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$entries = elpis_maillog_read($date);
$failed = count(array_filter($entries, static fn($e) => ($e['status'] ?? '') !== 'ok'));

?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elpis maillog <?= elpis_maillog_h($date) ?></title>
    <link rel="stylesheet" href="brand.css?v=<?= (int) @filemtime(__DIR__ . '/brand.css') ?>">
    <link rel="icon" href="box.svg" type="image/svg+xml">
    <style>
        .elpis-page { max-width: 1100px; margin: 0 auto; padding: 20px 16px 32px; }
        .elpis-header { margin-bottom: 20px; padding-bottom: 12px; border-bottom: 3px solid var(--kvt-main-blue); }
        .elpis-header img { max-height: 48px; width: auto; }
        .elpis-card { background: #fff; border: 1px solid var(--kvt-line); border-radius: 14px; padding: 18px 16px; }
        .elpis-card h1 { margin: 0 0 8px; color: var(--kvt-perkins-blue); font-size: 1.4rem; }
        .elpis-muted { color: var(--kvt-muted); }
        .elpis-nav { display: flex; gap: 16px; align-items: center; margin: 12px 0; }
        .elpis-nav a { color: var(--kvt-perkins-blue); font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid var(--kvt-line); }
        tr.is-failed > td { background: var(--kvt-row-alert); }
        tr.is-ok > td { background: var(--kvt-row-ok); }
        details table { margin-top: 6px; font-size: .9em; background: #fff; }
    </style>
</head>
<body>
<div class="elpis-page">
    <header class="elpis-header">
        <a href="index.php"><img src="logo-website.png" alt="KVT"></a>
    </header>
    <section class="elpis-card">
        <h1 class="brand-display">Maillog <?= elpis_maillog_h($date) ?></h1>
        <p class="elpis-muted"><?= count($entries) ?> mail(s), <?= (int) $failed ?> mislukt. Tijden in Europe/Amsterdam; logs blijven <?= ELPIS_MAILLOG_RETENTION_DAYS ?> dagen bewaard.</p>
        <nav class="elpis-nav">
            <a href="maillog.php?date=<?= elpis_maillog_h($prev) ?>">&larr; <?= elpis_maillog_h($prev) ?></a>
            <form method="get" action="maillog.php"><input type="date" name="date" value="<?= elpis_maillog_h($date) ?>" onchange="this.form.submit()"></form>
            <?php if ($date < $today): ?>
                <a href="maillog.php?date=<?= elpis_maillog_h($next) ?>"><?= elpis_maillog_h($next) ?> &rarr;</a>
                <a href="maillog.php">Vandaag</a>
            <?php endif; ?>
        </nav>
        <?php if ($entries === []): ?>
            <p class="elpis-muted">Geen mails gelogd op deze dag.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>Tijdstip</th><th>Ontvanger</th><th>Onderwerp</th><th>Regels</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): $ok = ($entry['status'] ?? '') === 'ok'; $lines = is_array($entry['lines'] ?? null) ? $entry['lines'] : []; ?>
                <tr class="<?= $ok ? 'is-ok' : 'is-failed' ?>">
                    <td><?= elpis_maillog_h(substr((string) ($entry['time'] ?? ''), 11)) ?></td>
                    <td><?= elpis_maillog_h((string) ($entry['to'] ?? '')) ?></td>
                    <td>
                        <?= elpis_maillog_h((string) ($entry['subject'] ?? '')) ?>
                        <?php if ($lines !== []): ?>
                            <details>
                                <summary>Regels tonen</summary>
                                <table>
                                    <thead><tr><th>Project</th><th>Artikel</th><th>Status</th><th>Reden</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($lines as $line): ?>
                                        <tr>
                                            <td><?= elpis_maillog_h(trim(($line['company'] ?? '') !== '' ? $line['company'] . ' / ' . ($line['project'] ?? '') : (string) ($line['project'] ?? ''))) ?></td>
                                            <td><?= elpis_maillog_h(trim((string) ($line['item'] ?? '') . ' ' . (string) ($line['description'] ?? ''))) ?></td>
                                            <td><?= elpis_maillog_h((string) ($line['status'] ?? '')) ?></td>
                                            <td><?= elpis_maillog_h((string) ($line['reason'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        <?php endif; ?>
                    </td>
                    <td><?= isset($entry['line_count']) ? (int) $entry['line_count'] : '&ndash;' ?></td>
                    <td><?= $ok ? 'Gelukt' : 'Mislukt: ' . elpis_maillog_h((string) ($entry['error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
