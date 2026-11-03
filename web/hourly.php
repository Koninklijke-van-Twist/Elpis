<?php

/**
 * Elk heel uur aanroepen (cron). Verstuurt PM-digests in het uur dat de PM in
 * voorkeuren.php koos (Europe/Amsterdam, standaard 08:00), max 1x per dag.
 * De data komt uit de laatste nightly.php-run.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/elpis_data.php';

ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$result = elpis_run_hourly();
$mail = is_array($result['mail'] ?? null) ? $result['mail'] : [];
echo 'Elpis hourly ' . strtoupper((string) $result['status']) . "\n";
echo 'hour=' . (int) $result['hour'] . "\n";
echo 'mails_sent=' . (int) ($mail['sent'] ?? 0) . "\n";
