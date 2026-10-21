<?php

/**
 * E-mail naar projectmanagers. Zelfde pad als Ponos: $reportMail uit auth.php
 * (SMTP als dat gezet is, anders PHP mail()). Testmodus stuurt alles naar
 * tfalken@kvt.nl totdat web/data/mail_config.json test_mode op false zet.
 */

const ELPIS_MAIL_FREQ_DAILY = 'daily';
const ELPIS_MAIL_FREQ_WEEKLY = 'weekly';
const ELPIS_MAIL_FREQ_CUSTOM = 'custom';
const ELPIS_MAIL_FREQ_NEVER = 'never';
const ELPIS_MAIL_CONTENT_LATE = 'late';
const ELPIS_MAIL_CONTENT_UNCONFIRMED = 'unconfirmed';
const ELPIS_MAIL_CONTENT_BOTH = 'both';

function elpis_mail_config_path(): string
{
    return elpis_data_dir() . '/mail_config.json';
}

function elpis_mail_config(): array
{
    $defaults = [
        'test_mode' => true,
        'test_recipient' => 'tfalken@kvt.nl',
    ];
    $path = elpis_mail_config_path();
    if (!is_file($path)) {
        elpis_write_json_file($path, $defaults);

        return $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $recipient = strtolower(trim((string) ($decoded['test_recipient'] ?? '')));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = $defaults['test_recipient'];
    }

    return [
        'test_mode' => array_key_exists('test_mode', $decoded) ? (bool) $decoded['test_mode'] : true,
        'test_recipient' => $recipient,
    ];
}

function elpis_write_json_file(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return;
    }

    $tmp = $path . '.tmp';
    file_put_contents($tmp, $json, LOCK_EX);
    rename($tmp, $path);
}

function elpis_default_mail_prefs(): array
{
    return [
        'frequency' => ELPIS_MAIL_FREQ_DAILY,
        'weekdays' => [1],
        'content' => ELPIS_MAIL_CONTENT_BOTH,
    ];
}

function elpis_normalize_mail_prefs(array $raw): array
{
    $defaults = elpis_default_mail_prefs();
    $frequency = (string) ($raw['frequency'] ?? $defaults['frequency']);
    if (!in_array($frequency, [ELPIS_MAIL_FREQ_DAILY, ELPIS_MAIL_FREQ_WEEKLY, ELPIS_MAIL_FREQ_CUSTOM, ELPIS_MAIL_FREQ_NEVER], true)) {
        $frequency = $defaults['frequency'];
    }

    $content = (string) ($raw['content'] ?? $defaults['content']);
    if (!in_array($content, [ELPIS_MAIL_CONTENT_LATE, ELPIS_MAIL_CONTENT_UNCONFIRMED, ELPIS_MAIL_CONTENT_BOTH], true)) {
        $content = $defaults['content'];
    }

    $weekdays = [];
    $rawDays = $raw['weekdays'] ?? $defaults['weekdays'];
    if (is_string($rawDays)) {
        $decoded = json_decode($rawDays, true);
        $rawDays = is_array($decoded) ? $decoded : [];
    }
    if (is_array($rawDays)) {
        foreach ($rawDays as $day) {
            $day = (int) $day;
            if ($day >= 1 && $day <= 7) {
                $weekdays[$day] = $day;
            }
        }
    }
    if ($weekdays === []) {
        $weekdays = [1 => 1];
    }
    $weekdays = array_values($weekdays);
    sort($weekdays);

    return [
        'frequency' => $frequency,
        'weekdays' => $weekdays,
        'content' => $content,
    ];
}

function elpis_mail_prefs_for_email(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '' || !function_exists('loadUserPrefs')) {
        return elpis_default_mail_prefs();
    }

    $stored = loadUserPrefs($email);
    $weekdays = $stored['elpis_mail_weekdays'] ?? null;

    return elpis_normalize_mail_prefs([
        'frequency' => $stored['elpis_mail_frequency'] ?? null,
        'content' => $stored['elpis_mail_content'] ?? null,
        'weekdays' => $weekdays,
    ]);
}

function elpis_save_mail_prefs(string $email, array $prefs): void
{
    if (!function_exists('saveUserPref')) {
        return;
    }

    $prefs = elpis_normalize_mail_prefs($prefs);
    saveUserPref($email, 'elpis_mail_frequency', $prefs['frequency']);
    saveUserPref($email, 'elpis_mail_content', $prefs['content']);
    saveUserPref($email, 'elpis_mail_weekdays', json_encode($prefs['weekdays']));
}

function elpis_mail_should_send_on(array $prefs, string $dateYmd): bool
{
    $prefs = elpis_normalize_mail_prefs($prefs);
    if ($prefs['frequency'] === ELPIS_MAIL_FREQ_NEVER) {
        return false;
    }
    if ($prefs['frequency'] === ELPIS_MAIL_FREQ_DAILY) {
        return true;
    }

    $weekday = (int) (new DateTimeImmutable($dateYmd))->format('N');
    if ($prefs['frequency'] === ELPIS_MAIL_FREQ_WEEKLY) {
        return $weekday === 1;
    }

    return in_array($weekday, $prefs['weekdays'], true);
}

function elpis_mail_line_matches_content(array $line, string $content): bool
{
    $state = (string) ($line['receipt_state'] ?? '');
    if ($content === ELPIS_MAIL_CONTENT_LATE) {
        return $state === 'late';
    }
    if ($content === ELPIS_MAIL_CONTENT_UNCONFIRMED) {
        return $state === 'unconfirmed';
    }

    return $state === 'late' || $state === 'unconfirmed';
}

function elpis_preferences_url(): string
{
    global $elpisBaseUrl;
    if (isset($elpisBaseUrl) && is_string($elpisBaseUrl) && trim($elpisBaseUrl) !== '') {
        return rtrim(trim($elpisBaseUrl), '/') . '/voorkeuren.php';
    }

    $env = getenv('ELPIS_BASE_URL');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/') . '/voorkeuren.php';
    }

    return 'https://sleutels.kvt.nl/elpis/voorkeuren.php';
}

function elpis_fetch_user_emails(string $company, array $userIds, int $ttl = ELPI_NIGHTLY_MAX_AGE): array
{
    $wanted = [];
    foreach ($userIds as $userId) {
        $normalized = elpis_normalize_bc_username((string) $userId);
        if ($normalized !== '') {
            $wanted[$normalized] = $normalized;
        }
    }
    if ($wanted === [] || trim($company) === '') {
        return [];
    }

    $found = [];
    foreach (array_chunk(array_values($wanted), ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_USER_SETUP_ENTITY, [
            '$select' => 'User_ID,Email',
            '$filter' => elpis_odata_eq_list('User_ID', $chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = elpis_normalize_bc_username((string) ($row['User_ID'] ?? ''));
            $email = strtolower(trim((string) ($row['Email'] ?? '')));
            if ($id !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $found[$id] = $email;
            }
        }
    }

    $missing = [];
    foreach ($wanted as $id) {
        if (!isset($found[$id])) {
            $missing[] = $id;
        }
    }

    foreach (array_chunk($missing, ELPI_PLANNING_LINES_CHUNK_SIZE) as $chunk) {
        $rows = elpis_try_fetch_rows($company, ELPI_USERS_ENTITY, [
            '$select' => 'User_Name,Authentication_Email',
            '$filter' => elpis_odata_eq_list('User_Name', $chunk),
        ], $ttl);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = elpis_normalize_bc_username((string) ($row['User_Name'] ?? ''));
            $email = strtolower(trim((string) ($row['Authentication_Email'] ?? '')));
            if ($id !== '' && !isset($found[$id]) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $found[$id] = $email;
            }
        }
    }

    return $found;
}

function elpis_mail_smtp_settings(): ?array
{
    global $reportMail;
    if (!is_array($reportMail ?? null)) {
        return null;
    }

    $smtp = $reportMail['smtp'] ?? null;
    if (!is_array($smtp)) {
        return null;
    }

    $host = trim((string) ($smtp['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    return [
        'host' => $host,
        'port' => (int) ($smtp['port'] ?? 587),
        'encryption' => strtolower(trim((string) ($smtp['encryption'] ?? 'tls'))),
        'username' => trim((string) ($smtp['username'] ?? '')),
        'password' => (string) ($smtp['password'] ?? ''),
        'timeout' => max(5, (int) ($smtp['timeout'] ?? 20)),
    ];
}

function elpis_mail_smtp_read($socket): string
{
    $data = '';
    while (is_resource($socket) && !feof($socket)) {
        $line = fgets($socket, 8192);
        if ($line === false) {
            break;
        }
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function elpis_mail_smtp_expect(string $response, array $codes): bool
{
    if ($response === '') {
        return false;
    }

    return in_array((int) substr($response, 0, 3), $codes, true);
}

function elpis_mail_smtp_dot_stuff(string $body): string
{
    $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
    $stuffed = [];
    foreach ($lines as $line) {
        if ($line !== '' && $line[0] === '.') {
            $line = '.' . $line;
        }
        $stuffed[] = $line;
    }

    return implode("\r\n", $stuffed);
}

function elpis_mail_send_smtp(
    string $to,
    string $fromEmail,
    string $fromName,
    string $encodedSubject,
    string $mimeHeaders,
    string $body
): bool {
    $smtp = elpis_mail_smtp_settings();
    if ($smtp === null) {
        return false;
    }

    $remote = ($smtp['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $smtp['host'] . ':' . $smtp['port'];
    $socket = @stream_socket_client($remote, $errno, $errstr, $smtp['timeout'], STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, $smtp['timeout']);
    $fail = static function ($socket): bool {
        fclose($socket);

        return false;
    };

    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [220])) {
        return $fail($socket);
    }

    fwrite($socket, "EHLO elpis.local\r\n");
    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }

    if ($smtp['encryption'] === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [220])) {
            return $fail($socket);
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            return $fail($socket);
        }

        fwrite($socket, "EHLO elpis.local\r\n");
        if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [250])) {
            return $fail($socket);
        }
    }

    if ($smtp['username'] !== '' && $smtp['password'] !== '') {
        fwrite($socket, "AUTH LOGIN\r\n");
        if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [334])) {
            return $fail($socket);
        }
        fwrite($socket, base64_encode($smtp['username']) . "\r\n");
        if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [334])) {
            return $fail($socket);
        }
        fwrite($socket, base64_encode($smtp['password']) . "\r\n");
        if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [235])) {
            return $fail($socket);
        }
    }

    fwrite($socket, 'MAIL FROM:<' . $fromEmail . ">\r\n");
    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }
    fwrite($socket, 'RCPT TO:<' . $to . ">\r\n");
    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [250, 251])) {
        return $fail($socket);
    }
    fwrite($socket, "DATA\r\n");
    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [354])) {
        return $fail($socket);
    }

    $payload = 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail) . "\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $encodedSubject . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n\r\n"
        . elpis_mail_smtp_dot_stuff($body);
    fwrite($socket, $payload . "\r\n.\r\n");
    if (!elpis_mail_smtp_expect(elpis_mail_smtp_read($socket), [250])) {
        return $fail($socket);
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}

function elpis_mail_send(string $to, string $subject, string $plain, string $html): bool
{
    $to = strtolower(trim($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (isset($GLOBALS['ELPIS_MAIL_SENDER']) && is_callable($GLOBALS['ELPIS_MAIL_SENDER'])) {
        return (bool) $GLOBALS['ELPIS_MAIL_SENDER']($to, $subject, $plain, $html);
    }

    global $reportMail;
    $fromEmail = 'kvtbot@kvt.nl';
    $fromName = 'Elpis';
    if (is_array($reportMail ?? null)) {
        $fromEmail = (string) ($reportMail['from_email'] ?? $fromEmail);
        $fromName = (string) ($reportMail['from_name'] ?? $fromName);
    }

    $boundary = 'elpis_' . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $mimeHeaders = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $plain . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html . "\r\n"
        . '--' . $boundary . '--';

    if (elpis_mail_smtp_settings() !== null && elpis_mail_send_smtp($to, $fromEmail, $fromName, $encodedSubject, $mimeHeaders, $body)) {
        return true;
    }

    $headers = "MIME-Version: 1.0\r\n"
        . $mimeHeaders . "\r\n"
        . 'From: ' . sprintf('%s <%s>', $fromName, $fromEmail);

    return @mail($to, $encodedSubject, $body, $headers);
}

function elpis_digest_rows_for_manager(array $projects, array $linesByProject, string $manager): array
{
    $manager = elpis_normalize_bc_username($manager);
    $rows = [];
    foreach ($projects as $project) {
        if (!is_array($project)) {
            continue;
        }
        if (strcasecmp((string) ($project['project_manager'] ?? ''), $manager) !== 0) {
            continue;
        }
        $projectNo = (string) ($project['no'] ?? '');
        foreach ($linesByProject[$projectNo] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $line['project_no'] = $projectNo;
            $line['project_description'] = (string) ($project['description'] ?? '');
            $rows[] = $line;
        }
    }

    return $rows;
}

function elpis_mail_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function elpis_mail_late_day_count(string $expectedYmd, string $todayYmd): int
{
    $expected = DateTimeImmutable::createFromFormat('!Y-m-d', substr($expectedYmd, 0, 10));
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', substr($todayYmd, 0, 10));
    if (!$expected instanceof DateTimeImmutable || !$today instanceof DateTimeImmutable || $today <= $expected) {
        return 0;
    }

    return (int) $expected->diff($today)->days;
}

function elpis_mail_lateness_text(int $count, string $unit): string
{
    if ($count < 1) {
        $count = 1;
    }
    $key = $count === 1 ? 'elpis.mail.late.' . $unit : 'elpis.mail.late.' . $unit . 's';

    return LOC($key, $count);
}

/**
 * Grootste passende eenheid: t/m 14 dagen in dagen, daarna weken tot
 * ongeveer 2 maanden (60 dagen), daarna maanden van 30 dagen.
 */
function elpis_mail_lateness_for_days(int $days): string
{
    if ($days < 1) {
        $days = 1;
    }
    if ($days <= 14) {
        return elpis_mail_lateness_text($days, 'day');
    }
    if ($days < 60) {
        return elpis_mail_lateness_text(max(1, intdiv($days, 7)), 'week');
    }

    return elpis_mail_lateness_text(max(1, intdiv($days, 30)), 'month');
}

function elpis_mail_line_reasons(array $line, ?string $today = null): array
{
    $today = $today ?? elpis_today();
    $line = elpis_refresh_receipt_state($line);
    $reasons = [];
    $date = elpis_normalize_bc_date((string) ($line['expected_receipt_date'] ?? ''));
    $date = $date === '' ? '' : substr($date, 0, 10);

    if ($date === '') {
        $reasons[] = LOC('elpis.mail.reason.missing_date');
    }

    if ($date !== '' && elpis_line_is_late($line, $today)) {
        $days = elpis_mail_late_day_count($date, $today);
        $reasons[] = elpis_mail_lateness_for_days($days > 0 ? $days : 1);
    }

    return $reasons;
}

function elpis_mail_join_reasons(array $reasons): string
{
    $clean = [];
    foreach ($reasons as $reason) {
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $clean[] = $reason;
        }
    }

    return implode('; ', $clean);
}

function elpis_mail_reasons_html(array $reasons, string $cellStyle): string
{
    $clean = [];
    foreach ($reasons as $reason) {
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $clean[] = $reason;
        }
    }
    if ($clean === []) {
        return '';
    }
    if (count($clean) === 1) {
        return elpis_mail_h($clean[0]);
    }

    $rows = '';
    foreach ($clean as $index => $reason) {
        $gap = $index > 0 ? 'padding-top:6px;' : '';
        $rows .= '<tr><td style="' . $gap . $cellStyle . '">' . elpis_mail_h($reason) . '</td></tr>';
    }

    return '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . $rows . '</table>';
}

function elpis_digest_message(string $manager, string $intendedEmail, array $lines, bool $testMode): array
{
    $count = count($lines);
    $subject = LOC('elpis.mail.subject', $count, elpis_format_manager_label($manager));
    if ($testMode) {
        $subject = '[TEST] ' . $subject;
    }

    $prefsUrl = elpis_preferences_url();
    $font = "font-family:Montserrat,'Segoe UI',Arial,sans-serif;";
    $lineColor = '#c9d7eb';
    $cell = $font . 'padding:11px 10px;border-bottom:1px solid ' . $lineColor . ';text-align:left;vertical-align:top;font-size:14px;';
    $head = $font . 'background-color:#00529B;color:#ffffff;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;text-align:left;padding:11px 10px;border-bottom:0;';
    $headers = [
        LOC('elpis.mail.col.project'),
        LOC('elpis.col.workorder'),
        LOC('elpis.col.item'),
        LOC('elpis.col.description'),
        LOC('elpis.mail.col.receipt'),
        LOC('elpis.mail.col.note'),
    ];
    $headHtml = '';
    foreach ($headers as $header) {
        $headHtml .= '<td bgcolor="#00529B" style="' . $head . '">' . elpis_mail_h($header) . '</td>';
    }

    $plainLines = [];
    $htmlRows = '';
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $line = elpis_refresh_receipt_state($line);
        $projectNo = (string) ($line['project_no'] ?? '');
        $item = (string) ($line['item_no'] ?? '');
        $description = (string) ($line['description'] ?? '');
        $workOrder = (string) ($line['work_order_no'] ?? '');
        $state = (string) ($line['receipt_state'] ?? '');
        $late = $state === 'late' || elpis_line_is_late($line);
        $reasons = elpis_mail_line_reasons($line);
        $note = elpis_mail_join_reasons($reasons);
        if ($late) {
            $when = LOC('elpis.receipt.late');
            $rowBg = '#fdebec';
            $textColor = 'color:#111827;';
            $whenColor = 'color:#b42318;font-weight:700;';
            $noteColor = 'color:#b42318;font-weight:700;';
        } elseif ($state === 'unconfirmed') {
            $when = LOC('elpis.receipt.missing');
            $rowBg = '#ffffff';
            $textColor = 'color:#111827;';
            $whenColor = 'color:#8a4b08;font-weight:700;';
            $noteColor = 'color:#111827;';
        } else {
            $when = elpis_format_display_date((string) ($line['expected_receipt_date'] ?? ''));
            $rowBg = '#ffffff';
            $textColor = 'color:#111827;';
            $whenColor = 'color:#111827;';
            $noteColor = 'color:#111827;';
        }
        $plainLines[] = $projectNo . ' | ' . $workOrder . ' | ' . $item . ' | ' . $description . ' | ' . $when . ' | ' . $note;
        $values = [$projectNo, $workOrder, $item, $description, $when];
        $colors = [$textColor, $textColor, $textColor, $textColor, $whenColor];
        $htmlRows .= '<tr>';
        foreach ($values as $index => $value) {
            $htmlRows .= '<td bgcolor="' . $rowBg . '" style="' . $cell . 'background-color:' . $rowBg . ';' . $colors[$index] . '">'
                . elpis_mail_h($value) . '</td>';
        }
        $htmlRows .= '<td bgcolor="' . $rowBg . '" style="' . $cell . 'background-color:' . $rowBg . ';' . $noteColor . '">'
            . elpis_mail_reasons_html($reasons, $font . 'font-size:14px;' . $noteColor) . '</td>';
        $htmlRows .= '</tr>';
    }

    $intro = LOC('elpis.mail.intro', elpis_format_manager_label($manager), $count);
    if ($testMode) {
        $intro = LOC('elpis.mail.test_banner', $intendedEmail) . "\n" . $intro;
    }

    $plain = $intro . "\n\n" . implode("\n", $plainLines) . "\n\n" . LOC('elpis.mail.prefs_link') . ': ' . $prefsUrl;
    $htmlIntro = nl2br(elpis_mail_h($intro), false);
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#eef6fc" style="background-color:#eef6fc;margin:0;padding:0;width:100%;">'
        . '<tr><td align="center" bgcolor="#eef6fc" style="' . $font . 'background-color:#eef6fc;padding:16px 12px 32px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:100%;max-width:760px;background-color:#ffffff;border:1px solid ' . $lineColor . ';border-top:4px solid #00529B;border-radius:14px;">'
        . '<tr><td style="' . $font . 'padding:18px 20px 8px;color:#111827;font-size:15px;line-height:1.45;">' . $htmlIntro . '</td></tr>'
        . '<tr><td style="padding:8px 12px 16px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="' . $font . 'width:100%;border-collapse:collapse;border:1px solid ' . $lineColor . ';background-color:#ffffff;">'
        . '<tr>' . $headHtml . '</tr>'
        . $htmlRows
        . '</table></td></tr>'
        . '<tr><td style="' . $font . 'padding:4px 20px 20px;font-size:14px;">'
        . '<a href="' . elpis_mail_h($prefsUrl) . '" style="color:#0099cc;font-weight:700;text-decoration:underline;">'
        . elpis_mail_h(LOC('elpis.mail.prefs_link')) . '</a></td></tr>'
        . '</table></td></tr></table>';

    return [$subject, $plain, $html];
}

function elpis_dispatch_pm_digests(array $byCompany, ?string $today = null): array
{
    $today = $today ?? elpis_today();
    $config = elpis_mail_config();
    $testMode = !empty($config['test_mode']);
    $sent = 0;
    $skipped = 0;
    $details = [];

    foreach ($byCompany as $company => $bundle) {
        if (!is_array($bundle)) {
            continue;
        }
        $projects = is_array($bundle['projects'] ?? null) ? $bundle['projects'] : [];
        $linesByProject = is_array($bundle['lines'] ?? null) ? $bundle['lines'] : [];
        $managers = [];
        foreach ($projects as $project) {
            if (!is_array($project)) {
                continue;
            }
            $manager = elpis_normalize_bc_username((string) ($project['project_manager'] ?? ''));
            if ($manager !== '') {
                $managers[$manager] = $manager;
            }
        }
        $emails = elpis_fetch_user_emails((string) $company, array_values($managers), ELPI_NIGHTLY_MAX_AGE);

        foreach ($managers as $manager) {
            $intended = $emails[$manager] ?? '';
            $prefs = $intended !== '' ? elpis_mail_prefs_for_email($intended) : elpis_default_mail_prefs();
            if (!elpis_mail_should_send_on($prefs, $today)) {
                $skipped++;
                $details[] = ['manager' => $manager, 'status' => 'schedule'];
                continue;
            }

            $matched = [];
            foreach (elpis_digest_rows_for_manager($projects, $linesByProject, $manager) as $line) {
                if (elpis_mail_line_matches_content($line, $prefs['content'])) {
                    $matched[] = $line;
                }
            }
            if ($matched === []) {
                $skipped++;
                $details[] = ['manager' => $manager, 'status' => 'empty'];
                continue;
            }
            if ($intended === '' && !$testMode) {
                $skipped++;
                $details[] = ['manager' => $manager, 'status' => 'no-email'];
                continue;
            }

            $recipient = $testMode ? (string) $config['test_recipient'] : $intended;
            [$subject, $plain, $html] = elpis_digest_message($manager, $intended, $matched, $testMode);
            $ok = elpis_mail_send($recipient, $subject, $plain, $html);
            if ($ok) {
                $sent++;
            }
            $details[] = [
                'manager' => $manager,
                'status' => $ok ? 'sent' : 'failed',
                'to' => $recipient,
                'intended' => $intended,
                'lines' => count($matched),
            ];
        }
    }

    return [
        'sent' => $sent,
        'skipped' => $skipped,
        'test_mode' => $testMode,
        'details' => $details,
    ];
}

function elpis_nightly_state_path(): string
{
    return elpis_data_dir() . '/nightly_state.json';
}

function elpis_nightly_read_state(): array
{
    $path = elpis_nightly_state_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

function elpis_nightly_write_state(array $state): void
{
    elpis_write_json_file(elpis_nightly_state_path(), $state);
}

function elpis_nightly_needs_run(): bool
{
    $state = elpis_nightly_read_state();
    if (($state['date'] ?? '') === elpis_today() && ($state['status'] ?? '') === 'ok') {
        return false;
    }

    if (($state['status'] ?? '') === 'failed') {
        $finished = strtotime((string) ($state['finished_at'] ?? ''));
        if ($finished !== false && (time() - $finished) < 600) {
            return false;
        }
    }

    return true;
}

function elpis_run_nightly(bool $force = false): array
{
    $lockPath = elpis_data_dir() . '/nightly.lock';
    $handle = fopen($lockPath, 'c');
    if ($handle === false) {
        elpis_nightly_write_state([
            'date' => elpis_today(),
            'status' => 'failed',
            'finished_at' => date('c'),
            'error' => 'lock',
        ]);

        return ['status' => 'failed', 'error' => 'lock'];
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        return ['status' => 'running'];
    }

    try {
        $today = elpis_today();
        $state = elpis_nightly_read_state();
        if (!$force && ($state['date'] ?? '') === $today && ($state['status'] ?? '') === 'ok') {
            return ['status' => 'fresh', 'date' => $today];
        }

        if (isset($GLOBALS['ELPIS_NIGHTLY_WORK']) && is_callable($GLOBALS['ELPIS_NIGHTLY_WORK'])) {
            $summary = $GLOBALS['ELPIS_NIGHTLY_WORK']();
            if (!is_array($summary)) {
                $summary = ['by_company' => []];
            }
        } else {
            elpis_clear_odata_cache();
            $summary = elpis_warm_odata_cache(ELPI_NIGHTLY_MAX_AGE);
        }
        $mail = elpis_dispatch_pm_digests(is_array($summary['by_company'] ?? null) ? $summary['by_company'] : []);
        unset($summary['by_company']);
        elpis_nightly_write_state([
            'date' => $today,
            'status' => 'ok',
            'finished_at' => date('c'),
            'mails_sent' => (int) ($mail['sent'] ?? 0),
        ]);

        return [
            'status' => 'ok',
            'date' => $today,
            'summary' => $summary,
            'mail' => $mail,
        ];
    } catch (Throwable $error) {
        elpis_nightly_write_state([
            'date' => elpis_today(),
            'status' => 'failed',
            'finished_at' => date('c'),
            'error' => $error->getMessage(),
        ]);

        return ['status' => 'failed', 'error' => $error->getMessage()];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
