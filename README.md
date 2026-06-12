# Elpis

Webapp om per projectmanager projecten en inkoopplanningsregels (ontvangstmonitor) uit Business Central te tonen.

## Structuur

- `web/index.php` — hoofdpagina
- `web/elpis_data.php` — OData-queries en data-opbouw
- `web/odata.php` — OData-client en cache-widget
- `web/localization.php` — meertalige UI-teksten
- `web/auth.php` — credentials (niet in git, lokaal aanwezig)

## Lokaal draaien

Via XAMPP: `http://localhost/Elpis/web/index.php`

Productie: `https://sleutels.kvt.nl/elpis/`

Dev-hulpmiddel voor BC-probes: `php web/bc_probe.php`
