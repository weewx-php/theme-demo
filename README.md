# Atmos demo theme

An optional weewx-php theme with current readings, temperature and rainfall
charts, sunrise/sunset, monthly comparisons and records. The package includes
its PHP views, SVG icons, CSS, JavaScript and animated landscape. Forecast and
climate sections use optional extension tags when those extensions are enabled.

## Installation

Requires PHP 8.1+ and theme API 1. In **Admin → Themes → Shop**, install
**Atmos demo** and activate it. For a manual installation, copy this repository
into `themes/demo` or configure an external directory:

```ini
[Themes]
    active = demo
    [[demo]]
        directory = /srv/weather/themes/theme-demo
        default_range = 24h
```

Relative paths resolve against the configuration file. The package keeps the
ID `demo`, so
existing settings and analytics ownership remain valid. Assets are served by
the core; no files need to be copied into `public/`.

The theme uses the first configured archive, which must exist. After installing
into `themes/demo`, run from the core directory:

```sh
php bin/weewx-php analytics sync demo themes/demo/data.php
php bin/weewx-php analytics run
```

The regular tick maintains prepared queries. Missing results display a dash;
data gaps remain gaps. Larger archives may require multiple worker runs.
Readings use °C, km/h, hPa and mm regardless of archive units. Archive periods
follow the latest archived reading, while sunrise and sunset use the current
local calendar day. English is the default language; select German in the theme
settings or set `language = de` in `[Themes][[demo]]`.

## Package files

| File | Purpose |
|---|---|
| `theme.php` | HTML renderer |
| `snapshot.php` | Prepared JSON snapshot |
| `data.php` | Output profile and analytics recipes |
| `View.php`, `Forecast.php` | Presentation helpers and optional forecast tags |
| `template.php`, `icons.php` | HTML and SVG markup |
| `assets/` | Theme CSS, polling and landscape animation |
| `settings.json`, `locales/` | Declarative settings and translations |

The landscape and weather preview controls are illustrative. Optional forecast
and climate output includes source attribution. The core handles HTTP routing,
cache headers, asset containment and the visitor tick.

## Tests

Always use Docker; start it if necessary. Build the test image in the core:

```sh
docker compose -f tests/docker/compose.yml build unit
```

Then run from this theme repository:

```sh
WEEWX_PHP_ROOT=/path/to/weewx-php docker compose -f tests/docker/compose.yml run --rm unit
```

For PowerShell, set `$env:WEEWX_PHP_ROOT` before invoking Docker Compose.
