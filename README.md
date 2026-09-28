# Elpis

Webapp om per projectmanager projecten en inkoopplanningsregels (ontvangstmonitor) uit Business Central te tonen.

## Structuur

- `web/index.php` — hoofdpagina
- `web/elpis_data.php` — OData-queries en data-opbouw
- `web/odata.php` — OData-client en cache-widget
- `web/localization.php` — meertalige UI-teksten
- `web/auth.php` — credentials (niet in git, lokaal aanwezig)


## Mímir (optional)

Set in `web/auth.php` (not in git):

```php
$mimirApi  = 'mimir_…';
// optional:
$mimirBase = 'https://sleutels.kvt.nl/mimir/api';
```

With `$mimirApi` set, OData fetches (`odata_get_all`, `elpis_fetch_rows`, nightly `elpis_warm_odata_cache`) and company discovery try Mímir first (`max_age` from Elpis TTLs; nightly warm uses `ELPI_NIGHTLY_MAX_AGE` = 14400, page/UI keeps `ELPI_CACHE_TTL` = 86400). If that call fails (connection/timeout, non-2xx, invalid JSON, or a Mímir error payload), Elpis fetches the same data on the legacy Business Central path (`$baseUrl`, `$auth` / `$auth_list`, `$environment`, local odata file cache) and skips Mímir for the rest of that PHP request — including CLI/cron (`php web/nightly.php`, `php web/bc_probe.php`). Keep those BC credentials in `auth.php` next to `$mimirApi`; if they are absent the original Mímir error is raised. User company/manager preferences (`elpis_company`, `elpis_managers_by_company`) are unchanged. The local odata file-cache widget stays hidden while Mímir serves the page, and is shown again after a fallback in that request. Without `$mimirApi` the existing BC path remains unchanged.

## Lokaal draaien

Via XAMPP: `http://localhost/Elpis/web/index.php`

Productie: `https://sleutels.kvt.nl/elpis/`

Dev-hulpmiddel voor BC-probes: `php web/bc_probe.php`
