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

const ELPIS_MAILLOG_EXPORT_MAX_DAYS = 7;
const ELPIS_MAILLOG_EXPORT_MAX_LINES = 100;

function elpis_maillog_readers_path(): string
{
    return elpis_data_dir() . '/maillog_readers.json';
}

/** Fail-closed: geen bestand, geen geldige lijst, of het adres staat er niet in. */
function elpis_maillog_reader_allowed(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $path = elpis_maillog_readers_path();
    if (!is_file($path)) {
        return false;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    $readers = is_array($decoded) ? ($decoded['readers'] ?? null) : null;
    if (!is_array($readers)) {
        return false;
    }
    foreach ($readers as $reader) {
        if (is_string($reader) && strtolower(trim($reader)) === $email) {
            return true;
        }
    }

    return false;
}

function elpis_maillog_redact(string $value): string
{
    $value = preg_replace('#https?://\S+#i', '[url]', $value) ?? $value;
    $value = preg_replace('/\b[\w.-]*voorkeuren\.php\S*/i', '[url]', $value) ?? $value;
    $value = preg_replace('/\b(api[_-]?key|token|bearer|password)\s*[:=]\s*\S+/i', '$1=[redacted]', $value) ?? $value;

    return $value;
}

function elpis_maillog_export_dates(string $from, string $to): ?array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable
        || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to
        || $end < $start) {
        return null;
    }
    if ((int) $start->diff($end)->days > ELPIS_MAILLOG_EXPORT_MAX_DAYS - 1) {
        return null;
    }
    $dates = [];
    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $dates[] = $day->format('Y-m-d');
    }

    return $dates;
}

function elpis_maillog_export_filter(array $query): ?array
{
    if (isset($query['file']) || isset($query['path']) || isset($query['dir'])) {
        return null;
    }
    $from = (string) ($query['from'] ?? '');
    $to = (string) ($query['to'] ?? '');
    $dates = elpis_maillog_export_dates($from, $to);
    if ($dates === null) {
        return null;
    }
    $clean = static function (string $value, int $max): ?string {
        $value = trim($value);
        if ($value === '' || strlen($value) > $max || preg_match('/[\/\\\\]/', $value) === 1) {
            return null;
        }

        return $value;
    };
    $project = trim((string) ($query['project'] ?? ''));
    $item = trim((string) ($query['item'] ?? ''));
    $company = trim((string) ($query['company'] ?? ''));
    $recipient = strtolower(trim((string) ($query['recipient'] ?? '')));
    if ($project === '' && $item === '' && $recipient === '') {
        return null;
    }
    if ($project !== '' && $clean($project, 40) === null) {
        return null;
    }
    if ($item !== '' && $clean($item, 40) === null) {
        return null;
    }
    if ($company !== '' && $clean($company, 80) === null) {
        return null;
    }
    if ($recipient !== '' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return [
        'dates' => $dates,
        'project' => $project,
        'item' => $item,
        'company' => $company,
        'recipient' => $recipient,
    ];
}

function elpis_maillog_day_path(string $date): ?string
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return null;
    }
    $dir = realpath(elpis_maillog_dir());
    if ($dir === false) {
        return null;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $date . '.jsonl';
    if (!is_file($path)) {
        return null;
    }
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $real;
}

function elpis_maillog_export(array $filter): array
{
    $mails = [];
    $count = 0;
    $truncated = false;
    foreach ($filter['dates'] as $date) {
        $path = elpis_maillog_day_path($date);
        if ($path === null) {
            continue;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            continue;
        }
        while (($row = fgets($handle)) !== false) {
            $entry = json_decode($row, true);
            if (!is_array($entry)) {
                continue;
            }
            $to = strtolower(trim((string) ($entry['to'] ?? '')));
            if ($filter['recipient'] !== '' && $to !== $filter['recipient']) {
                continue;
            }
            $lines = [];
            foreach (is_array($entry['lines'] ?? null) ? $entry['lines'] : [] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $project = trim((string) ($line['project'] ?? ''));
                $item = trim((string) ($line['item'] ?? ''));
                $company = trim((string) ($line['company'] ?? ''));
                if ($filter['project'] !== '' && strcasecmp($project, $filter['project']) !== 0) {
                    continue;
                }
                if ($filter['item'] !== '' && strcasecmp($item, $filter['item']) !== 0) {
                    continue;
                }
                if ($filter['company'] !== '' && strcasecmp($company, $filter['company']) !== 0) {
                    continue;
                }
                $lines[] = [
                    'company' => elpis_maillog_redact($company),
                    'project' => elpis_maillog_redact($project),
                    'item' => elpis_maillog_redact($item),
                    'description' => elpis_maillog_redact(trim((string) ($line['description'] ?? ''))),
                    'status' => elpis_maillog_redact(trim((string) ($line['status'] ?? ''))),
                    'reason' => elpis_maillog_redact(trim((string) ($line['reason'] ?? ''))),
                ];
                $count++;
                if ($count >= ELPIS_MAILLOG_EXPORT_MAX_LINES) {
                    $truncated = true;
                    break 2;
                }
            }
            if ($lines === []) {
                continue;
            }
            $mails[] = [
                'time' => elpis_maillog_redact(trim((string) ($entry['time'] ?? ''))),
                'to' => elpis_maillog_redact($to),
                'send_status' => ($entry['status'] ?? '') === 'ok' ? 'ok' : 'failed',
                'lines' => $lines,
            ];
        }
        fclose($handle);
        if ($truncated) {
            break;
        }
    }

    return [
        'from' => $filter['dates'][0],
        'to' => $filter['dates'][count($filter['dates']) - 1],
        'truncated' => $truncated,
        'mails' => $mails,
    ];
}

function elpis_maillog_export_response(string $email, array $query): array
{
    if (!elpis_maillog_reader_allowed($email)) {
        return [403, ['error' => 'forbidden']];
    }
    $filter = elpis_maillog_export_filter($query);
    if ($filter === null) {
        return [400, ['error' => 'bad_request']];
    }

    return [200, elpis_maillog_export($filter)];
}
