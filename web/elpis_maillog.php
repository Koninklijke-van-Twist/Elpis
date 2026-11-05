<?php

/**
 * Maillog: één JSONL-regel per verstuurde Elpis-mail in data/maillog/YYYY-MM-DD.jsonl
 * (data/** is uitgesloten van de FTP-deploy). Fouten bij loggen breken het versturen nooit.
 */

const ELPIS_MAILLOG_RETENTION_DAYS = 7;

function elpis_maillog_dir(): string
{
    $dir = elpis_data_dir() . '/maillog';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    return $dir;
}

function elpis_maillog_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
}

/**
 * Samenvatting per regel: project, artikel, status en de toelichting zoals in de mail.
 */
function elpis_maillog_lines(array $lines): array
{
    $out = [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $status = elpis_line_status($line);
        $out[] = [
            'company' => (string) ($line['company'] ?? ''),
            'project' => (string) ($line['project_no'] ?? ''),
            'item' => (string) ($line['item_no'] ?? ''),
            'description' => (string) ($line['description'] ?? ''),
            'status' => trim((string) ($status['label'] ?? $status['key'] ?? '')),
            'reason' => elpis_mail_join_reasons(elpis_mail_line_reasons($line)),
        ];
    }

    return $out;
}

function elpis_maillog_write(string $to, string $subject, ?array $lines, bool $ok, string $error = ''): void
{
    try {
        $now = elpis_maillog_now();
        $entry = [
            'time' => $now->format('Y-m-d H:i:s'),
            'to' => $to,
            'subject' => $subject,
            'line_count' => $lines === null ? null : count($lines),
            'status' => $ok ? 'ok' : 'failed',
            'error' => $ok ? '' : $error,
            'lines' => $lines === null ? [] : elpis_maillog_lines($lines),
        ];
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            @file_put_contents(elpis_maillog_dir() . '/' . $now->format('Y-m-d') . '.jsonl', $json . "\n", FILE_APPEND | LOCK_EX);
        }
    } catch (Throwable $ignored) {
    }
}

function elpis_maillog_read(string $dateYmd): array
{
    $path = elpis_maillog_dir() . '/' . $dateYmd . '.jsonl';
    $handle = is_file($path) ? @fopen($path, 'r') : false;
    if ($handle === false) {
        return [];
    }
    flock($handle, LOCK_SH);
    $entries = [];
    while (($row = fgets($handle)) !== false) {
        $entry = json_decode($row, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }
    flock($handle, LOCK_UN);
    fclose($handle);

    return $entries;
}

function elpis_maillog_cleanup(int $days = ELPIS_MAILLOG_RETENTION_DAYS): void
{
    try {
        $cutoff = elpis_maillog_now()->modify('-' . $days . ' days')->format('Y-m-d');
        foreach (glob(elpis_maillog_dir() . '/*.jsonl') ?: [] as $file) {
            if (strcmp(basename($file, '.jsonl'), $cutoff) < 0) {
                @unlink($file);
            }
        }
    } catch (Throwable $ignored) {
    }
}
