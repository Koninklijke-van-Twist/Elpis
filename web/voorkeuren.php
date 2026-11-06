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

function elpis_prefs_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Page load
 */

$email = elpis_current_user_email();
$saved = false;
$statusError = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $frequency = (string) ($_POST['frequency'] ?? ELPIS_MAIL_FREQ_DAILY);
    $content = (string) ($_POST['content'] ?? ELPIS_MAIL_CONTENT_PROBLEMS);
    $statuses = is_array($_POST['statuses'] ?? null) ? $_POST['statuses'] : [];
    $weekdays = $_POST['weekdays'] ?? [];
    if (!is_array($weekdays)) {
        $weekdays = [];
    }
    // Aangepast vraagt minstens één status; anders niet opslaan.
    if ($content === ELPIS_MAIL_CONTENT_CUSTOM && elpis_mail_normalize_content($content, $statuses)[0] !== ELPIS_MAIL_CONTENT_CUSTOM) {
        $statusError = true;
    } else {
        elpis_save_mail_prefs($email, [
            'frequency' => $frequency,
            'content' => $content,
            'statuses' => $statuses,
            'weekdays' => $weekdays,
            'hour' => $_POST['hour'] ?? null,
        ]);
        $saved = true;
    }
}

$prefs = elpis_mail_prefs_for_email($email);
$weekdayLabels = [];
for ($day = 1; $day <= 7; $day++) {
    $weekdayLabels[$day] = LOC('elpis.weekday.' . $day);
}

?><!DOCTYPE html>
<html lang="<?= elpis_prefs_h(getHtmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= elpis_prefs_h(LOC('elpis.prefs.title')) ?></title>
    <link rel="stylesheet" href="brand.css?v=<?= (int) @filemtime(__DIR__ . '/brand.css') ?>">
    <link rel="manifest" href="site.webmanifest">
    <link rel="icon" href="box.svg" type="image/svg+xml">
    <?php renderLanguageSwitcherStyles(); ?>
    <style>
        .elpis-page { max-width: 720px; margin: 0 auto; padding: 20px 16px 32px; }
        .elpis-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 3px solid var(--kvt-main-blue); }
        .elpis-header img { max-height: 48px; width: auto; }
        .elpis-card { background: #fff; border: 1px solid var(--kvt-line); border-radius: 14px; padding: 18px 16px; }
        .elpis-card h1 { margin: 0 0 8px; color: var(--kvt-perkins-blue); font-size: 1.4rem; }
        .elpis-muted { color: var(--kvt-muted); }
        .elpis-field { display: grid; gap: 8px; margin: 16px 0; }
        .elpis-field legend, .elpis-field > span { font-weight: 700; color: var(--kvt-perkins-blue); }
        .elpis-choice { display: flex; gap: 8px; align-items: center; padding: 8px 0; }
        .elpis-status-choice { align-items: flex-start; }
        .elpis-status-choice input { margin-top: 3px; }
        .elpis-status-hint { display: block; margin-top: 2px; font-size: 0.85rem; font-weight: 400; line-height: 1.35; }
        .elpis-days { display: grid; gap: 4px; margin-left: 24px; }
        .elpis-days.is-hidden { display: none; }
        .elpis-btn { font: inherit; font-weight: 700; border-radius: 10px; border: 1px solid var(--kvt-perkins-blue); background: var(--kvt-main-blue); color: #fff; padding: 12px 14px; }
        .elpis-error { background: var(--kvt-row-alert); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        .elpis-ok { background: var(--kvt-row-ok); border-radius: 10px; padding: 10px 12px; margin-bottom: 12px; }
        a.elpis-back { color: var(--kvt-perkins-blue); font-weight: 700; }
    </style>
</head>
<body>
<div class="elpis-page">
    <header class="elpis-header">
        <a href="index.php"><img src="logo-website.png" alt="KVT"></a>
        <?php renderLanguageSwitcher(); ?>
    </header>
    <section class="elpis-card">
        <h1 class="brand-display"><?= elpis_prefs_h(LOC('elpis.prefs.title')) ?></h1>
        <p class="elpis-muted"><?= elpis_prefs_h(LOC('elpis.prefs.intro')) ?></p>
        <?php if ($saved): ?>
            <p class="elpis-ok"><?= elpis_prefs_h(LOC('elpis.prefs.saved')) ?></p>
        <?php endif; ?>
        <?php if ($statusError): ?>
            <p class="elpis-error"><?= elpis_prefs_h(LOC('elpis.prefs.statuses_required')) ?></p>
        <?php endif; ?>
        <form method="post" action="voorkeuren.php">
            <input type="hidden" name="lang" value="<?= elpis_prefs_h(getCurrentLanguage()) ?>">
            <fieldset class="elpis-field">
                <legend><?= elpis_prefs_h(LOC('elpis.prefs.frequency')) ?></legend>
                <?php foreach ([
                    ELPIS_MAIL_FREQ_DAILY => 'elpis.prefs.daily',
                    ELPIS_MAIL_FREQ_WEEKLY => 'elpis.prefs.weekly',
                    ELPIS_MAIL_FREQ_CUSTOM => 'elpis.prefs.custom',
                    ELPIS_MAIL_FREQ_NEVER => 'elpis.prefs.never',
                ] as $value => $key): ?>
                    <label class="elpis-choice">
                        <input type="radio" name="frequency" value="<?= elpis_prefs_h($value) ?>" data-elpis-frequency<?= $prefs['frequency'] === $value ? ' checked' : '' ?>>
                        <span><?= elpis_prefs_h(LOC($key)) ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="elpis-days<?= $prefs['frequency'] === ELPIS_MAIL_FREQ_CUSTOM ? '' : ' is-hidden' ?>" data-elpis-days>
                    <?php foreach ($weekdayLabels as $day => $label): ?>
                        <label class="elpis-choice">
                            <input type="checkbox" name="weekdays[]" value="<?= (int) $day ?>"<?= in_array($day, $prefs['weekdays'], true) ? ' checked' : '' ?>>
                            <span><?= elpis_prefs_h($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset class="elpis-field">
                <legend><?= elpis_prefs_h(LOC('elpis.prefs.content')) ?></legend>
                <?php foreach ([
                    ELPIS_MAIL_CONTENT_ALL => ['elpis.prefs.content_all', ''],
                    ELPIS_MAIL_CONTENT_PROBLEMS => ['elpis.prefs.content_problems', 'elpis.prefs.content_problems_hint'],
                    ELPIS_MAIL_CONTENT_PURCHASER => ['elpis.prefs.content_purchaser', 'elpis.prefs.content_purchaser_hint'],
                    ELPIS_MAIL_CONTENT_CUSTOM => ['elpis.prefs.custom', ''],
                ] as $value => [$key, $hint]): ?>
                    <label class="elpis-choice<?= $hint !== '' ? ' elpis-status-choice' : '' ?>">
                        <input type="radio" name="content" value="<?= elpis_prefs_h($value) ?>" data-elpis-content<?= $prefs['content'] === $value ? ' checked' : '' ?>>
                        <span>
                            <?= elpis_prefs_h(LOC($key)) ?>
                            <?php if ($hint !== ''): ?>
                                <span class="elpis-muted elpis-status-hint"><?= elpis_prefs_h(LOC($hint)) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
                <div class="elpis-days<?= $prefs['content'] === ELPIS_MAIL_CONTENT_CUSTOM ? '' : ' is-hidden' ?>" data-elpis-statuses>
                    <?php foreach (ELPI_LINE_STATUSES as $statusKey => $def): ?>
                        <label class="elpis-choice elpis-status-choice">
                            <input type="checkbox" name="statuses[]" value="<?= elpis_prefs_h($statusKey) ?>"<?= in_array($statusKey, $prefs['statuses'], true) ? ' checked' : '' ?>>
                            <span>
                                <?= elpis_prefs_h(LOC($def['label'])) ?>
                                <span class="elpis-muted elpis-status-hint"><?= elpis_prefs_h(LOC($def['description'])) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label class="elpis-field">
                <span><?= elpis_prefs_h(LOC('elpis.prefs.hour')) ?></span>
                <select name="hour">
                    <?php foreach (ELPIS_MAIL_HOURS as $hourOption): ?>
                        <option value="<?= (int) $hourOption ?>"<?= $prefs['hour'] === $hourOption ? ' selected' : '' ?>><?= sprintf('%02d:00', $hourOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="elpis-btn" type="submit"><?= elpis_prefs_h(LOC('elpis.prefs.save')) ?></button>
        </form>
        <p><a class="elpis-back" href="index.php"><?= elpis_prefs_h(LOC('elpis.prefs.back')) ?></a></p>
    </section>
</div>
<script>
(function () {
    var statuses = document.querySelector('[data-elpis-statuses]');
    document.querySelectorAll('[data-elpis-content]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (statuses && input.checked) {
                statuses.classList.toggle('is-hidden', input.value !== 'custom');
            }
        });
    });
    var form = document.querySelector('form[action="voorkeuren.php"]');
    if (form && statuses) {
        form.addEventListener('submit', function (event) {
            var custom = form.querySelector('[data-elpis-content][value="custom"]');
            if (custom && custom.checked && !statuses.querySelector('input:checked')) {
                event.preventDefault();
                window.alert(<?= json_encode(LOC('elpis.prefs.statuses_required')) ?>);
            }
        });
    }
    var days = document.querySelector('[data-elpis-days]');
    document.querySelectorAll('[data-elpis-frequency]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!days) {
                return;
            }
            days.classList.toggle('is-hidden', input.value !== 'custom' || !input.checked);
        });
    });
})();
</script>
<?php renderLanguageSwitcherScript(); ?>
</body>
</html>
