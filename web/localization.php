<?php

/**
 * Constants
 */

const FLAG_SVGS = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#AE1C28"/><rect width="900" height="400" fill="#fff"/><rect width="900" height="200" fill="#fff"/><rect width="900" height="200" y="0" fill="#AE1C28"/><rect width="900" height="200" y="200" fill="#fff"/><rect width="900" height="200" y="400" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>',
];

const SUPPORTED_LANGUAGES = [
    'nl' => ['flag' => '🇳🇱', 'label' => 'Nederlands'],
    'en' => ['flag' => '🇬🇧', 'label' => 'English'],
    'de' => ['flag' => '🇩🇪', 'label' => 'Deutsch'],
    'fr' => ['flag' => '🇫🇷', 'label' => 'Français'],
];

const LOCALE_BY_LANG = [
    'nl' => 'nl-NL',
    'en' => 'en-GB',
    'de' => 'de-DE',
    'fr' => 'fr-FR',
];

const TRANSLATIONS = [
    'nl' => [
        'lang.menu_aria' => 'Taal kiezen',
        'lang.switch_to' => 'Schakel naar %s',
        'app.title' => 'Monitor Ontvangstrapport',
        'elpis.hero.title' => 'Monitor Ontvangstrapport',
        'elpis.hero.subtitle' => 'Bekijk inkoopplanningsregels per project en projectmanager.',
        'elpis.label.company' => 'Bedrijf',
        'elpis.label.manager' => 'Projectmanager',
        'elpis.manager.all' => 'Iedereen',
        'elpis.section.projects' => 'Projecten',
        'elpis.label.search' => 'Zoeken',
        'elpis.placeholder.search' => 'Projectnr., werkorder, item of omschrijving',
        'elpis.label.line_search' => 'Zoeken in regels',
        'elpis.placeholder.line_search' => 'Werkorder, item of omschrijving',
        'elpis.empty.search' => 'Geen projecten gevonden voor deze zoekopdracht.',
        'elpis.meta.manager' => 'Projectmanager',
        'elpis.col.workorder' => 'Werkorder',
        'elpis.col.item' => 'Item',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'Te bestellen',
        'elpis.col.ordered' => 'Besteld',
        'elpis.col.outstanding' => 'Openstaand',
        'elpis.col.received' => 'Ontvangen',
        'elpis.empty.managers' => 'Geen projectmanagers gevonden',
        'elpis.empty.projects' => 'Geen projecten gevonden voor deze projectmanager.',
        'elpis.empty.lines' => 'Geen inkoopplanningsregels voor dit project.',
        'elpis.error.load_failed' => 'Gegevens ophalen mislukt. Probeer het later opnieuw.',
        'elpis.loader.wait' => 'Even geduld...',
        'elpis.loader.loading' => 'Gegevens ophalen uit Business Central',
    ],

    'en' => [
        'lang.menu_aria' => 'Choose language',
        'lang.switch_to' => 'Switch to %s',
        'app.title' => 'Receipt Monitor',
        'elpis.hero.title' => 'Receipt Monitor',
        'elpis.hero.subtitle' => 'View purchase planning lines per project and project manager.',
        'elpis.label.company' => 'Company',
        'elpis.label.manager' => 'Project manager',
        'elpis.manager.all' => 'Everyone',
        'elpis.section.projects' => 'Projects',
        'elpis.label.search' => 'Search',
        'elpis.placeholder.search' => 'Project no., work order, item or description',
        'elpis.label.line_search' => 'Search lines',
        'elpis.placeholder.line_search' => 'Work order, item or description',
        'elpis.empty.search' => 'No projects found for this search.',
        'elpis.meta.manager' => 'Project manager',
        'elpis.col.workorder' => 'Work order',
        'elpis.col.item' => 'Item',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'To order',
        'elpis.col.ordered' => 'Ordered',
        'elpis.col.outstanding' => 'Outstanding',
        'elpis.col.received' => 'Received',
        'elpis.empty.managers' => 'No project managers found',
        'elpis.empty.projects' => 'No projects found for this project manager.',
        'elpis.empty.lines' => 'No purchase planning lines for this project.',
        'elpis.error.load_failed' => 'Failed to load data. Please try again later.',
        'elpis.loader.wait' => 'Please wait...',
        'elpis.loader.loading' => 'Fetching data from Business Central',
    ],

    'de' => [
        'lang.menu_aria' => 'Sprache wählen',
        'lang.switch_to' => 'Wechseln zu %s',
        'app.title' => 'Wareneingangsmonitor',
        'elpis.hero.title' => 'Wareneingangsmonitor',
        'elpis.hero.subtitle' => 'Einkaufsplanungszeilen pro Projekt und Projektmanager anzeigen.',
        'elpis.label.company' => 'Unternehmen',
        'elpis.label.manager' => 'Projektmanager',
        'elpis.manager.all' => 'Alle',
        'elpis.section.projects' => 'Projekte',
        'elpis.label.search' => 'Suchen',
        'elpis.placeholder.search' => 'Projektnr., Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.label.line_search' => 'Zeilen suchen',
        'elpis.placeholder.line_search' => 'Arbeitsauftrag, Artikel oder Beschreibung',
        'elpis.empty.search' => 'Keine Projekte für diese Suche gefunden.',
        'elpis.meta.manager' => 'Projektmanager',
        'elpis.col.workorder' => 'Arbeitsauftrag',
        'elpis.col.item' => 'Artikel',
        'elpis.col.description' => 'Beschreibung',
        'elpis.col.to_order' => 'Zu bestellen',
        'elpis.col.ordered' => 'Bestellt',
        'elpis.col.outstanding' => 'Offen',
        'elpis.col.received' => 'Erhalten',
        'elpis.empty.managers' => 'Keine Projektmanager gefunden',
        'elpis.empty.projects' => 'Keine Projekte für diesen Projektmanager gefunden.',
        'elpis.empty.lines' => 'Keine Einkaufsplanungszeilen für dieses Projekt.',
        'elpis.error.load_failed' => 'Daten konnten nicht geladen werden. Bitte später erneut versuchen.',
        'elpis.loader.wait' => 'Bitte warten...',
        'elpis.loader.loading' => 'Daten werden aus Business Central geladen',
    ],

    'fr' => [
        'lang.menu_aria' => 'Choisir la langue',
        'lang.switch_to' => 'Passer en %s',
        'app.title' => 'Moniteur de réception',
        'elpis.hero.title' => 'Moniteur de réception',
        'elpis.hero.subtitle' => 'Consultez les lignes de planification d\'achat par projet et chef de projet.',
        'elpis.label.company' => 'Société',
        'elpis.label.manager' => 'Chef de projet',
        'elpis.manager.all' => 'Tous',
        'elpis.section.projects' => 'Projets',
        'elpis.label.search' => 'Rechercher',
        'elpis.placeholder.search' => 'N° projet, ordre de travail, article ou description',
        'elpis.label.line_search' => 'Rechercher dans les lignes',
        'elpis.placeholder.line_search' => 'Ordre de travail, article ou description',
        'elpis.empty.search' => 'Aucun projet trouvé pour cette recherche.',
        'elpis.meta.manager' => 'Chef de projet',
        'elpis.col.workorder' => 'Ordre de travail',
        'elpis.col.item' => 'Article',
        'elpis.col.description' => 'Description',
        'elpis.col.to_order' => 'À commander',
        'elpis.col.ordered' => 'Commandé',
        'elpis.col.outstanding' => 'En cours',
        'elpis.col.received' => 'Reçu',
        'elpis.empty.managers' => 'Aucun chef de projet trouvé',
        'elpis.empty.projects' => 'Aucun projet trouvé pour ce chef de projet.',
        'elpis.empty.lines' => 'Aucune ligne de planification d\'achat pour ce projet.',
        'elpis.error.load_failed' => 'Échec du chargement des données. Réessayez plus tard.',
        'elpis.loader.wait' => 'Veuillez patienter...',
        'elpis.loader.loading' => 'Récupération des données depuis Business Central',
    ],
];

/**
 * Functies
 */

function getUserPrefsPath(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $dir = __DIR__ . '/data/user_prefs';
    $filename = preg_replace('/[^a-z0-9._\-]/', '_', $email) . '.json';
    return $dir . '/' . $filename;
}

function loadUserPrefs(string $email): array
{
    $path = getUserPrefsPath($email);
    if ($path === null || !is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function saveUserPref(string $email, string $key, mixed $value): void
{
    $path = getUserPrefsPath($email);
    if ($path === null) {
        return;
    }
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $prefs = loadUserPrefs($email);
    $prefs[$key] = $value;
    file_put_contents($path, json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function getCurrentLanguage(): string
{
    $lang = (string) ($_SESSION['lang'] ?? 'nl');
    return array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';
}

function getHtmlLang(): string
{
    return getCurrentLanguage();
}

function getDateLocale(): string
{
    $lang = getCurrentLanguage();
    return LOCALE_BY_LANG[$lang] ?? 'nl-NL';
}

/**
 * Geeft de vertaling voor $key in de actieve taal.
 * Extra $args worden via sprintf ingevoegd (voor %d, %s, etc.).
 */
function LOC(string $key, mixed ...$args): string
{
    $lang = getCurrentLanguage();
    $translations = TRANSLATIONS[$lang] ?? TRANSLATIONS['nl'];
    $string = $translations[$key] ?? (TRANSLATIONS['nl'][$key] ?? $key);

    return $args !== [] ? sprintf($string, ...$args) : $string;
}

function localizationFlagSvg(string $lang): string
{
    $svg = FLAG_SVGS[$lang] ?? '';
    if ($svg === '') {
        return '';
    }

    $safeLang = preg_replace('/[^a-z0-9]/', '', $lang) ?? $lang;
    return str_replace(
        ['id="a"', 'url(#a)', 'id="b"', 'url(#b)'],
        ['id="flag-' . $safeLang . '-a"', 'url(#flag-' . $safeLang . '-a)', 'id="flag-' . $safeLang . '-b"', 'url(#flag-' . $safeLang . '-b)'],
        $svg
    );
}

function localizationUrlWithLang(string $lang): string
{
    $params = $_GET;
    unset($params['lang']);
    $params['lang'] = $lang;
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

function localizationJsTranslations(array $keys): string
{
    $payload = [];
    foreach ($keys as $key) {
        $payload[$key] = LOC($key);
    }

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function renderLanguageSwitcherStyles(): void
{
    echo <<<'CSS'
<style>
.lang-switcher {
    position: fixed;
    top: 12px;
    right: 12px;
    z-index: 5000;
    font-family: inherit;
}
.lang-switcher-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 30px;
    padding: 0;
    border: 1px solid rgba(0, 82, 155, 0.25);
    border-radius: 6px;
    background: #ffffff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
    cursor: pointer;
}
.lang-switcher-toggle:hover {
    background: #f2f9ff;
}
.lang-switcher-toggle svg {
    width: 28px;
    height: auto;
    display: block;
    border-radius: 2px;
    overflow: hidden;
}
.lang-switcher-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    min-width: 160px;
    margin: 0;
    padding: 6px;
    list-style: none;
    background: #ffffff;
    border: 1px solid #c9d7eb;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
    display: none;
}
.lang-switcher.is-open .lang-switcher-menu {
    display: block;
}
.lang-switcher-item a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    color: var(--kvt-text, #1f2937);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}
.lang-switcher-item a:hover {
    background: #edf7ff;
}
.lang-switcher-item.is-active a {
    background: #e6f4ff;
}
.lang-switcher-item svg {
    width: 24px;
    height: auto;
    flex-shrink: 0;
    border-radius: 2px;
    overflow: hidden;
}
@media print {
    .lang-switcher {
        display: none !important;
    }
}
</style>
CSS;
}

function renderLanguageSwitcher(): void
{
    $current = getCurrentLanguage();
    $menuAria = htmlspecialchars(LOC('lang.menu_aria'), ENT_QUOTES);

    echo '<div class="lang-switcher" data-lang-switcher>';
    echo '<button type="button" class="lang-switcher-toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . $menuAria . '">';
    echo localizationFlagSvg($current);
    echo '</button>';
    echo '<ul class="lang-switcher-menu" role="menu">';

    foreach (SUPPORTED_LANGUAGES as $code => $meta) {
        if ($code === $current) {
            continue;
        }

        $label = (string) ($meta['label'] ?? $code);
        $href = htmlspecialchars(localizationUrlWithLang($code), ENT_QUOTES);
        $title = htmlspecialchars(LOC('lang.switch_to', $label), ENT_QUOTES);

        echo '<li class="lang-switcher-item" role="none">';
        echo '<a role="menuitem" href="' . $href . '" title="' . $title . '">';
        echo localizationFlagSvg($code);
        echo '<span>' . htmlspecialchars($label) . '</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

function renderLanguageSwitcherScript(): void
{
    echo <<<'JS'
<script>
(function () {
    document.querySelectorAll('[data-lang-switcher]').forEach(function (root) {
        var toggle = root.querySelector('.lang-switcher-toggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = root.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function () {
            root.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
})();
</script>
JS;
}

/**
 * Page load
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!isset($_SESSION['lang'])) {
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '') {
        $savedPrefs = loadUserPrefs($prefEmail);
        if (isset($savedPrefs['lang']) && array_key_exists($savedPrefs['lang'], SUPPORTED_LANGUAGES)) {
            $_SESSION['lang'] = $savedPrefs['lang'];
        }
    }
}

if (!isset($_SESSION['lang']) || !array_key_exists((string) $_SESSION['lang'], SUPPORTED_LANGUAGES)) {
    $_SESSION['lang'] = 'nl';
}

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
    $requestedLang = (string) $_GET['lang'];
    $langChanged = $requestedLang !== getCurrentLanguage();
    $_SESSION['lang'] = $requestedLang;
    $prefEmail = strtolower(trim((string) ($_SESSION['user']['email'] ?? '')));
    if ($prefEmail !== '' && $langChanged) {
        saveUserPref($prefEmail, 'lang', $requestedLang);
    }

    $isApiAction = isset($_GET['action']) && trim((string) $_GET['action']) !== '';
    if (!$isApiAction && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        $params = $_GET;
        unset($params['lang']);
        $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $query = http_build_query($params);
        header('Location: ' . $path . ($query !== '' ? '?' . $query : ''));
        exit;
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
