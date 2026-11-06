<?php

/**
 * Maillog: één JSONL-regel per verstuurde Elpis-mail in data/maillog/YYYY-MM-DD.jsonl
 * (data/** is uitgesloten van de FTP-deploy). Fouten bij loggen breken het versturen nooit.
 */

const ELPIS_MAILLOG_RETENTION_DAYS = 7;
const ELPIS_MAILLOG_HTACCESS = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";

function elpis_maillog_dir(): string
{
    $dir = elpis_data_dir() . '/maillog';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, ELPIS_MAILLOG_HTACCESS);
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
const ELPIS_MAILLOG_EXPORT_MAX_LINE_BYTES = 8192;
const ELPIS_MAILLOG_EXPORT_MAX_BYTES = 1048576;

/**
 * Bestaande maillogmap, zonder mkdir. Alleen als .htaccess HTTP nog weigert
 * en de map binnen de datamap blijft (geen symlink naar buiten).
 */
function elpis_maillog_existing_dir(): ?string
{
    $override = $GLOBALS['ELPIS_DATA_DIR'] ?? null;
    $root = is_string($override) && trim($override) !== '' ? $override : (__DIR__ . '/data');
    if (!is_dir($root)) {
        return null;
    }
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return null;
    }
    $dir = $rootReal . DIRECTORY_SEPARATOR . 'maillog';
    if (!is_dir($dir)) {
        return null;
    }
    $real = realpath($dir);
    if ($real === false || !str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)) {
        return null;
    }
    $dirPerms = fileperms($real);
    if ($dirPerms === false || ($dirPerms & 0007) !== 0) {
        return null;
    }
    $htaccess = $real . DIRECTORY_SEPARATOR . '.htaccess';
    $htPerms = is_file($htaccess) ? fileperms($htaccess) : false;
    $rules = $htPerms !== false ? @file_get_contents($htaccess) : false;
    if ($htPerms === false || ($htPerms & 0002) !== 0 || $rules !== ELPIS_MAILLOG_HTACCESS) {
        return null;
    }

    return $real;
}

function elpis_maillog_readers_path(): ?string
{
    $dir = elpis_maillog_existing_dir();
    if ($dir === null) {
        return null;
    }
    $path = $dir . DIRECTORY_SEPARATOR . 'readers.json';
    if (!is_file($path)) {
        return null;
    }
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
        return null;
    }
    $perms = fileperms($real);
    if ($perms === false || ($perms & 0007) !== 0) {
        return null;
    }

    return $real;
}

/** Fail-closed: geen bestand, geen geldige lijst, of het adres staat er niet in. */
function elpis_maillog_reader_allowed(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $path = elpis_maillog_readers_path();
    if ($path === null) {
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
    $value = preg_replace('/\bauthorization\s+bearer\s+\S+/i', 'authorization bearer [redacted]', $value) ?? $value;
    $value = preg_replace('/\bbearer\s+\S+/i', 'bearer [redacted]', $value) ?? $value;
    $value = preg_replace('/\b(access_token|api[_-]?key|token|password)\b\s*[:=]?\s*\S+/i', '$1=[redacted]', $value) ?? $value;

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

/** null = geen bestand, false = pad valt buiten de maillogmap. */
function elpis_maillog_day_path(string $date): string|false|null
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return null;
    }
    $dir = elpis_maillog_existing_dir();
    if ($dir === null) {
        return null;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $date . '.jsonl';
    if (!file_exists($path)) {
        return null;
    }
    if (!is_file($path)) {
        return false;
    }
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
        return false;
    }

    return $real;
}

function elpis_maillog_export(array $filter): array
{
    if (elpis_maillog_existing_dir() === null) {
        return ['complete' => false];
    }
    $mails = [];
    $count = 0;
    $bytes = 0;
    foreach ($filter['dates'] as $date) {
        $path = elpis_maillog_day_path($date);
        if ($path === false) {
            return ['complete' => false];
        }
        if ($path === null) {
            continue;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['complete' => false];
        }
        while (!feof($handle)) {
            $row = fgets($handle, ELPIS_MAILLOG_EXPORT_MAX_LINE_BYTES);
            if ($row === false) {
                break;
            }
            $bytes += strlen($row);
            $tooLong = strlen($row) >= ELPIS_MAILLOG_EXPORT_MAX_LINE_BYTES - 1 && !str_ends_with($row, "\n");
            if ($tooLong || $bytes > ELPIS_MAILLOG_EXPORT_MAX_BYTES || $count >= ELPIS_MAILLOG_EXPORT_MAX_LINES) {
                fclose($handle);

                return ['complete' => false];
            }
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
                if ($count >= ELPIS_MAILLOG_EXPORT_MAX_LINES) {
                    fclose($handle);

                    return ['complete' => false];
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
    }

    return [
        'complete' => true,
        'from' => $filter['dates'][0],
        'to' => $filter['dates'][count($filter['dates']) - 1],
        'mails' => $mails,
    ];
}

/**
 * Echte login: e-mail, api_key en oid, gecontroleerd met verify_rotating_api_key.
 * Een trusted-request of een sessie met alleen een gezet adres telt niet.
 * Daarna moet het adres op de serverlijst staan.
 */
function elpis_maillog_export_response(array $sessionUser, array $query, bool $trustedRequest): array
{
    if ($trustedRequest) {
        return [403, ['error' => 'forbidden']];
    }
    $email = strtolower(trim((string) ($sessionUser['email'] ?? '')));
    $apiKey = trim((string) ($sessionUser['api_key'] ?? ''));
    $oid = strtolower(trim((string) ($sessionUser['oid'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $apiKey === '' || $oid === '') {
        return [403, ['error' => 'forbidden']];
    }
    if (!function_exists('verify_rotating_api_key') || !verify_rotating_api_key($oid, $apiKey)) {
        return [403, ['error' => 'forbidden']];
    }
    if (!elpis_maillog_reader_allowed($email)) {
        return [403, ['error' => 'forbidden']];
    }
    $filter = elpis_maillog_export_filter($query);
    if ($filter === null) {
        return [400, ['error' => 'bad_request']];
    }
    $export = elpis_maillog_export($filter);
    if (empty($export['complete'])) {
        return [413, ['error' => 'incomplete', 'complete' => false]];
    }

    return [200, $export];
}
