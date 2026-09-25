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

With `$mimirApi` set, `$auth_list`, `$environment`, `$baseUrl` and `$auth` are unused for Business Central — OData fetches and company discovery go through Mímir (`max_age` from Elpis TTLs). User company/manager preferences (`elpis_company`, `elpis_managers_by_company`) are unchanged. The local odata file-cache widget is shown only on the non-Mímir path. Without `$mimirApi` the existing BC path remains unchanged.

## Lokaal draaien

Via XAMPP: `http://localhost/Elpis/web/index.php`

Productie: `https://sleutels.kvt.nl/elpis/`

Dev-hulpmiddel voor BC-probes: `php web/bc_probe.php`
