<?php

declare(strict_types=1);

use WeewxPhp\Frontend\Theme;

use WeewxPhp\DemoTheme\Icons;
use WeewxPhp\DemoTheme\View;
use WeewxPhp\Frontend\Value;

/** @var View|null $view */
$theme = isset($theme) && $theme instanceof Theme ? $theme : ($view->theme ?? new Theme());
$title = $view === null ? $theme->text('Weather station') : $view->name;
require_once __DIR__ . '/icons.php';
$icon = Icons::render(...);
$weatherIcon = static fn(string $kind, string $phase = 'day'): string => match ($kind) {
    'clear', 'unknown' => $phase === 'night' ? 'moon' : 'sun',
    'cloudy' => 'cloud', default => $kind,
};
$decimal = static fn(float $value): string => number_format($value, 1, $theme->language === 'de' ? ',' : '.', $theme->language === 'de' ? '.' : ',');
$forecastNumber = static fn(Value $value, int $places = 0): string => is_numeric($value->raw) ? number_format((float) $value->raw, $places, $theme->language === 'de' ? ',' : '.', $theme->language === 'de' ? '.' : ',') : '—';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($theme->language, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#111312">
    <title><?= View::escape($title) ?> · Atmos</title>
    <link rel="stylesheet" href="theme-assets.php/demo/demo.css">
    <script src="theme-assets.php/demo/landscape.js" defer></script>
    <script src="theme-assets.php/demo/demo.js" defer></script>
</head>
<body data-units="<?= View::escape($theme->units->profile) ?>" data-texts="<?= htmlspecialchars(json_encode($theme->texts, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">
<a class="skip" href="#weather"><?= $theme->html('Skip to weather') ?></a>
<div class="page">
    <header class="site-header">
        <a class="brand" href="?units=<?= View::escape($theme->units->selection) ?>&amp;range=24h" aria-label="<?= $theme->html('Atmos weather overview') ?>">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 36 36"><path d="M3 27 17 5l15 22M9 27l8-13 9 13M4 32h28"/><circle cx="28" cy="9" r="3"/></svg></span>
            <span>atmos<span class="brand-dot">.</span></span>
            <span class="brand-sub"><?= $theme->html('WEATHER STATION') ?></span>
        </a>
        <div class="header-actions">
            <form class="unit-selector" method="get">
                <input type="hidden" name="range" value="<?= View::escape($view->range ?? '24h') ?>">
                <label for="units"><?= $theme->html('Units') ?></label>
                <select id="units" name="units">
                    <?php foreach ($theme->unitOptions() as $id => $label): ?>
                    <option value="<?= View::escape($id) ?>"<?= $id === $theme->units->selection ? ' selected' : '' ?>><?= View::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><?= $theme->html('Apply') ?></button>
            </form>
            <span class="header-date"><?= View::escape($view?->date(time(), 'd.m.Y') ?? '') ?></span>
            <button class="icon-button" type="button" data-motion aria-label="<?= $theme->html('Pause animations') ?>" aria-pressed="false" hidden><?= $icon('pause') ?></button>
            <button class="scene-button" type="button" data-scenes aria-haspopup="dialog" hidden><?= $icon('sun') ?><span><?= $theme->html('Scene') ?></span></button>
        </div>
    </header>
    <main id="weather">
    <?php if ($view === null): ?>
        <section class="empty-page"><p class="eyebrow"><?= $theme->html('Weather station') ?></p><h1><?= $theme->html('No weather data') ?></h1><p><?= $theme->html('Configuration unavailable.') ?></p></section>
    <?php else:
        $scene = $view->atmosphere();
        $chart = $view->chart();
        $rain = $view->points('rain7d');
        $rainMax = max([1.0, ...array_map(static fn(array $point): float => $point['value'] ?? 0.0, $rain)]);
        $day = $view->date($view->updated, 'd.m.Y');
        $fresh = $view->updated !== null && abs(time() - $view->updated) < 900;
        $forecastDays = $view->forecast->daily();
        $rise = $scene['sunrise'];
        $set = $scene['sunset'];
        $daylight = $rise !== null && $set !== null ? max(0, $set - $rise) : null;
        $sunProgress = $daylight !== null && $daylight > 0 && time() >= $rise && time() <= $set ? (time() - $rise) / $daylight : null;
        $low = $view->value('low')->raw;
        $high = $view->value('high')->raw;
        $temperature = $view->value('temperature')->raw;
        $position = is_numeric($low) && is_numeric($high) && is_numeric($temperature) ? max(0, min(100, ($temperature - $low) / max(1, $high - $low) * 100)) : null;
        ?>
        <div class="page-title" id="now">
            <div><p class="eyebrow location-label"><?= $icon('location') ?> <?= $theme->html('Local weather') ?></p><h1><?= View::escape($title) ?></h1></div>
            <div class="station-status"><span class="status-dot<?= $fresh ? ' is-live' : '' ?>"></span><span><?= $fresh ? $theme->html('Current') : $theme->html('Last reading') ?><time data-updated><?= View::escape($view->date($view->updated)) ?></time></span></div>
        </div>
        <p class="notice" data-refresh-status role="status"<?= $view->status === '' ? ' hidden' : '' ?>><?= View::escape($view->status) ?></p>
        <section class="overview" aria-label="<?= $theme->html('Current readings') ?>">
            <article class="hero panel" data-weather="<?= View::escape($scene['kind']) ?>" data-phase="<?= View::escape($scene['phase']) ?>" data-wind="<?= $scene['wind'] ?>" data-temperature="<?= $scene['temperature'] === null ? '' : sprintf('%.2F', $scene['temperature']) ?>" data-zone="<?= View::escape($view->zone->getName()) ?>" data-latitude="<?= $view->latitude === null ? '' : sprintf('%.4F', $view->latitude) ?>">
                <div class="weather-scene" aria-hidden="true">
                    <div class="sky-glow"></div><div class="orbital orbital-one"></div><div class="orbital orbital-two"></div>
                    <div class="celestial"><div class="celestial-core"></div></div>
                    <div class="cloud cloud-one"></div><div class="cloud cloud-two"></div><div class="cloud cloud-three"></div>
                    <canvas class="atmosphere-canvas"></canvas>
                    <svg class="terrain" viewBox="0 0 900 250" preserveAspectRatio="none"><defs><linearGradient id="terrain-fill" x2="0" y2="1"><stop stop-color="currentColor" stop-opacity=".09"/><stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs><path class="terrain-fill" d="M0 165Q130 80 280 128T510 100T710 94T900 40V250H0Z"/><?php for ($i = 0; $i < 10; ++$i): ?><path d="M0 <?= 165 + $i * 13 ?>Q130 <?= 80 + $i * 20 ?> 280 <?= 128 + $i * 15 ?>T510 <?= 100 + $i * 17 ?>T710 <?= 94 + $i * 20 ?>T900 <?= 40 + $i * 21 ?>"/><?php endfor; ?></svg>
                    <div class="scene-vignette"></div>
                </div>
                <div class="hero-top"><span class="eyebrow"><?= $theme->html('Air temperature') ?> <span>/ <?= $view->sensorHeight()->html() ?></span></span><span class="hero-tag" data-scene-tag<?= $scene['source'] === '' ? ' hidden' : '' ?>><?= View::escape($scene['source']) ?></span></div>
                <div class="hero-reading"><span class="temperature" data-value="temperature"><?= $view->number('temperature') ?></span><span class="temperature-unit"><?= $view->unitLabel('temperature') ?></span></div>
                <div class="hero-condition"><span class="condition-icon"><?= $icon($weatherIcon($scene['kind'], $scene['phase'])) ?></span><span data-condition><?= View::escape($scene['label']) ?></span></div>
                <div class="hero-secondary"><?php if ($view->value('feelsLike')->raw !== null): ?><span><?= $theme->html('Feels like') ?> <strong><span data-value="feelsLike"><?= $view->number('feelsLike') ?></span>°</strong></span><?php endif; ?><span data-live<?= $view->liveTemperature->raw === null ? ' hidden' : '' ?>>Live: <?= $view->liveTemperature->html() ?><?= $view->liveTemperature->status === 'stale' ? $theme->html(' · stale') : '' ?></span></div>
                <div class="hero-bottom">
                    <div class="hero-range-label"><span><?= $theme->html('Daily range') ?></span><span><?= View::escape($day) ?></span></div>
                    <div class="hero-range"><span class="range-low"><?= $icon('down') ?><strong data-value="low"><?= $view->number('low') ?></strong>°</span><svg viewBox="0 0 300 20" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="day-range"><stop stop-color="#93c8bd"/><stop offset=".52" stop-color="#d4f783"/><stop offset="1" stop-color="#ff9257"/></linearGradient></defs><rect x="5" y="7" width="290" height="6" rx="3" fill="url(#day-range)"/><?php if ($position !== null): ?><line x1="<?= 5 + $position * 2.9 ?>" x2="<?= 5 + $position * 2.9 ?>" y1="4" y2="16" stroke="#f3f7ed" stroke-width="3" stroke-linecap="round" vector-effect="non-scaling-stroke"/><?php endif; ?></svg><span class="range-high"><?= $icon('up') ?><strong data-value="high"><?= $view->number('high') ?></strong>°</span></div>
                    <div class="hero-foot"><span><span class="tiny-cross">+</span> <span data-phase-label><?= $scene['phase'] === 'night' ? $theme->html('Night') : ($scene['phase'] === 'twilight' ? $theme->html('Twilight') : $theme->html('Day')) ?></span><span class="hero-foot-divider">/</span><?= View::escape($view->zone->getName()) ?></span><span><?= $view->altitude->raw === null ? '' : $view->altitude->html('%.0f') . ' ' . $theme->html('above sea level') ?></span></div>
                </div>
            </article>
            <div class="metric-grid">
                <article class="metric-card humidity-card" aria-labelledby="humidity-title">
                    <div class="metric-heading"><h2 id="humidity-title"><?= $theme->html('Humidity') ?></h2><?= $icon('drop') ?></div>
                    <div class="metric-value"><span data-value="humidity"><?= $view->number('humidity') ?></span><span class="unit">%</span></div>
                    <svg class="humidity-wave" viewBox="0 0 240 50" preserveAspectRatio="none" aria-hidden="true"><path d="M-120 30Q-90 0-60 30T0 30T60 30T120 30T180 30T240 30T300 30T360 30V60H-120Z"/><path d="M-120 35Q-90 15-60 35T0 35T60 35T120 35T180 35T240 35T300 35T360 35V60H-120Z"/></svg>
                    <div class="metric-foot"><?= $theme->html('Dew point') ?> <strong><span data-value="dewpoint"><?= $view->number('dewpoint') ?></span> <?= $view->unitLabel('dewpoint') ?></strong></div>
                </article>
                <article class="metric-card wind-card" aria-labelledby="wind-title">
                    <div class="metric-heading"><h2 id="wind-title"><?= $theme->html('Wind') ?></h2><?= $icon('wind') ?></div>
                    <div class="metric-value"><span data-value="wind"><?= $view->number('wind') ?></span><span class="unit"><?= $view->unitLabel('wind') ?></span></div>
                    <svg class="wind-stream" viewBox="0 0 240 80" aria-hidden="true"><path d="M0 18H140Q165 18 165 40T195 62H240M-30 35H115Q140 35 140 52T172 74H240M30 0H165Q190 0 190 20T220 42H260"/></svg>
                    <div class="metric-foot"><?= $theme->html('Gusts') ?> <strong><span data-value="gust"><?= $view->number('gust') ?></span> <?= $view->unitLabel('gust') ?></strong></div>
                </article>
                <article class="metric-card pressure-card" aria-labelledby="pressure-title">
                    <div class="metric-heading"><h2 id="pressure-title"><?= $theme->html('Pressure') ?></h2><?= $icon('pressure') ?></div>
                    <div class="metric-value"><span data-value="pressure"><?= $view->number('pressure') ?></span><span class="unit"><?= $view->unitLabel('pressure') ?></span></div>
                    <div class="pressure-rings" aria-hidden="true"></div><div class="metric-foot"><?= $theme->html('At sea level') ?></div>
                </article>
                <article class="metric-card rain-mini-card" aria-labelledby="rain-mini-title">
                    <div class="metric-heading"><h2 id="rain-mini-title"><?= $theme->html('Rainfall') ?></h2><?= $icon('rain') ?></div>
                    <div class="metric-value"><span data-value="rainDay"><?= $view->number('rainDay') ?></span><span class="unit"><?= $view->unitLabel('rainDay') ?></span></div>
                    <div class="rain-texture" aria-hidden="true"></div><div class="metric-foot"><?= $theme->html('Daily total') ?> <strong><?= View::escape($view->date($view->updated, 'd.m.')) ?></strong></div>
                </article>
            </div>
        </section>
        <div class="section-heading" id="analysis"><h2><?= $theme->html('Weather history') ?><span class="section-dot">.</span></h2><span><?= $theme->html('Readings') ?></span></div>
        <div class="content-grid">
            <section class="panel temperature-panel" aria-labelledby="temperature-title">
                <div class="panel-heading"><div class="panel-label"><?= $icon('chart') ?><h3 id="temperature-title"><?= $theme->html('Temperature') ?></h3></div><nav class="range" aria-label="<?= $theme->html('Range') ?>"><a href="?units=<?= View::escape($theme->units->selection) ?>&amp;range=24h#analysis"<?= $view->range === '24h' ? ' aria-current="true"' : '' ?>>24 h</a><a href="?units=<?= View::escape($theme->units->selection) ?>&amp;range=7d#analysis"<?= $view->range === '7d' ? ' aria-current="true"' : '' ?>><?= $theme->html('7 days') ?></a></nav></div>
                <div class="chart-headline"><span class="chart-reading"><span data-value="temperature"><?= $view->number('temperature') ?></span><span class="unit"><?= $view->unitLabel('temperature') ?></span></span><span class="legend"><i></i><?= $view->range === '24h' ? $theme->html('15-minute average') : $theme->html('Hourly average') ?></span></div>
                <?php if ($view->climate !== null): $climate = $view->climate; ?>
                <div class="climate-reference">
                    <?php if (is_int($climate->meta['window_days'] ?? null) && $climate->meta['window_days'] > 0): ?><span>±<?= $climate->meta['window_days'] ?> <?= $theme->html('days') ?></span><?php endif; ?>
                    <?php if ($climate->status === 'pending'): ?><?= $theme->html('Loading climate average') ?><?php else: ?>
                    <span><?= $theme->html('Daily average') ?> <?= View::escape(is_string($climate->meta['date'] ?? null) ? substr($climate->meta['date'], 8, 2) . '.' . substr($climate->meta['date'], 5, 2) . '.' : '') ?> · <?= is_int($climate->meta['start_year'] ?? null) ? $climate->meta['start_year'] : '' ?>–<?= is_int($climate->meta['end_year'] ?? null) ? $climate->meta['end_year'] : '' ?>: <strong><?= $climate->value('outTemp')->html() ?></strong></span>
                    <span><?= $theme->html('Average minimum') ?> <?= $climate->value('outTempMin')->html() ?> <?= $theme->html('· Average maximum') ?> <?= $climate->value('outTempMax')->html() ?></span>
                    <span><a href="https://open-meteo.com/">Open-Meteo / ERA5</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($chart['paths'] === []): ?><div class="chart-empty" role="status"><?= ($view->data['temperature' . $view->range]->status ?? '') === 'pending' ? $theme->html('Calculating history …') : $theme->html('No historical data') ?></div><?php else: ?>
                    <div class="chart-wrap" data-chart data-unit="<?= $view->unitLabel('temperature') ?>" tabindex="0" role="group" aria-label="<?= $theme->html('Temperature chart. Use arrow keys to select readings.') ?>">
                    <output class="chart-tooltip" hidden></output>
                    <svg class="temperature-chart" viewBox="0 0 780 256" role="img" aria-labelledby="chart-title chart-description">
                        <title id="chart-title"><?= $theme->html('Temperature') . ' · ' . $view->unitLabel('temperature') ?></title><desc id="chart-description"><?= View::escape($view->date($chart['start']) . $theme->html(' to ') . $view->date($chart['end'])) ?><?= $theme->html('. Readings are available in the table below.') ?></desc>
                        <defs><linearGradient id="plot-fill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#c8f566" stop-opacity=".2"/><stop offset="1" stop-color="#c8f566" stop-opacity="0"/></linearGradient></defs>
                        <?php for ($i = 0; $i < 4; ++$i): $y = 30 + $i * 60; ?><line class="grid-line" x1="48" x2="752" y1="<?= $y ?>" y2="<?= $y ?>"/><text class="axis-label" x="34" y="<?= $y + 5 ?>" text-anchor="end"><?= number_format($chart['high'] - $i * ($chart['high'] - $chart['low']) / 3, 0, $theme->language === 'de' ? ',' : '.', $theme->language === 'de' ? '.' : ',') ?>°</text><?php endfor; ?>
                        <?php $endpoint = null;
                    foreach ($chart['paths'] as $path):
                        if (preg_match('/^M([\d.]+) /', $path, $firstX) !== 1 || preg_match('/([\d.]+) ([\d.]+)$/', $path, $lastPoint) !== 1) {
                            continue;
                        }
                        $endpoint = ['x' => $lastPoint[1], 'y' => $lastPoint[2]];
                        ?><path class="temperature-area" d="<?= View::escape($path) ?> L<?= $lastPoint[1] ?> 210 L<?= $firstX[1] ?> 210Z"/><path class="temperature-line" d="<?= View::escape($path) ?>" pathLength="1"/><?php endforeach; ?>
                        <?php if ($endpoint !== null): ?><circle class="plot-beacon" cx="<?= $endpoint['x'] ?>" cy="<?= $endpoint['y'] ?>" r="9"/><circle class="plot-endpoint" cx="<?= $endpoint['x'] ?>" cy="<?= $endpoint['y'] ?>" r="4.5"/><?php endif; ?>
                        <?php for ($i = 0; $i < 5; ++$i): $stamp = (int) (($chart['start'] ?? 0) + (($chart['end'] ?? 0) - ($chart['start'] ?? 0)) * $i / 4); ?><text class="axis-label" x="<?= 48 + $i * 176 ?>" y="247" text-anchor="<?= $i === 0 ? 'start' : ($i === 4 ? 'end' : 'middle') ?>"><?= View::escape($view->date($stamp, $view->range === '7d' ? 'd.m.' : 'H:i')) ?></text><?php endfor; ?>
                        <line class="chart-crosshair" x1="48" x2="48" y1="20" y2="215" visibility="hidden"/><circle class="chart-cursor" cx="48" cy="30" r="5" visibility="hidden"/>
                    </svg></div>
                    <details class="chart-data"><summary><?= $theme->html('Readings') ?> <?= $icon('arrow') ?></summary><div class="table-scroll"><table><caption><?= $theme->html('Temperature ·') ?> <?= $view->range === '24h' ? $theme->html('15-minute average') : $theme->html('Hourly average') ?></caption><thead><tr><th scope="col"><?= $theme->html('Time') ?></th><th scope="col"><?= $view->unitLabel('temperature') ?></th></tr></thead><tbody>
                    <?php foreach ($chart['points'] as $point):
                        $pointX = 48 + ($point['end'] - ($chart['start'] ?? 0)) / max(1, ($chart['end'] ?? 0) - ($chart['start'] ?? 0)) * 704;
                        $pointY = $point['value'] === null ? null : 210 - ($point['value'] - $chart['low']) / ($chart['high'] - $chart['low']) * 180;
                        ?><tr data-point-x="<?= sprintf('%.2F', $pointX) ?>" data-point-y="<?= $pointY === null ? '' : sprintf('%.2F', $pointY) ?>"><td><?= View::escape($view->date($point['end'])) ?></td><td><?= $point['value'] === null ? '—' : $decimal($point['value']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div></details>
                <?php endif; ?>
            </section>
            <section class="panel sun-panel" id="sky" aria-labelledby="sun-title" data-sunrise="<?= $rise ?>" data-sunset="<?= $set ?>">
                <div class="panel-heading"><div class="panel-label"><?= $icon('sun') ?><h3 id="sun-title"><?= $theme->html('Sun path') ?></h3></div><span class="quiet"><?= View::escape($view->date(time(), 'd.m.')) ?></span></div>
                <div class="sun-duration"><?= $daylight === null ? '—' : floor($daylight / 3600) . '<span>h</span> ' . floor($daylight % 3600 / 60) . '<span>min</span>' ?></div><p class="eyebrow sun-caption"><?= $theme->html('Daylight') ?></p>
                <svg class="solar-chart" viewBox="0 0 340 145" role="img" aria-label="<?= $theme->html('Sun path from sunrise to sunset') ?>"><defs><linearGradient id="sun-fill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#ff985c" stop-opacity=".2"/><stop offset="1" stop-color="#ff985c" stop-opacity="0"/></linearGradient></defs><path class="solar-area" d="M25 123Q170-85 315 123Z"/><path class="solar-orbit" d="M25 123Q170-85 315 123"/><line class="solar-horizon" x1="10" x2="330" y1="123" y2="123"/><?php if ($sunProgress !== null): $sunX = 25 + 290 * $sunProgress;
                    $sunY = 123 - 416 * $sunProgress * (1 - $sunProgress); ?><circle class="sun-position-halo" cx="<?= $sunX ?>" cy="<?= $sunY ?>" r="14"/><circle class="sun-position" cx="<?= $sunX ?>" cy="<?= $sunY ?>" r="6"/><?php endif; ?><circle class="solar-edge" cx="25" cy="123" r="3"/><circle class="solar-edge" cx="315" cy="123" r="3"/></svg>
                <div class="sun-times"><div><?= $icon('sunrise') ?><span><?= $theme->html('Sunrise') ?><strong><?= View::escape($view->solarTime('sunrise')) ?></strong></span></div><div><span><?= $theme->html('Sunset') ?><strong><?= View::escape($view->solarTime('sunset')) ?></strong></span><?= $icon('sunset') ?></div></div>
            </section>
        </div>
        <?php if ($view->forecast->status !== 'disabled'): ?>
        <div class="section-heading" id="forecast"><h2><?= $theme->html('Coming days') ?><span class="section-dot">.</span></h2><span><?= $view->forecast->status === 'stale' ? $theme->html('Stale') : $theme->html('Forecast') ?></span></div>
        <section class="panel forecast-panel" aria-label="<?= $theme->html('Weather forecast') ?>">
            <?php if ($forecastDays === []): ?><div class="chart-empty"><?= $theme->html('No forecast available') ?></div><?php else:
                $forecastMin = 0.0;
                $forecastMax = 1.0;
                $bounds = [];
                foreach ($forecastDays as $forecastDay) {
                    foreach (['outTempMin', 'outTempMax'] as $field) {
                        $bound = $forecastDay->value($field)->raw;
                        if (is_numeric($bound)) {
                            $bounds[] = (float) $bound;
                        }
                    }
                }
                if ($bounds !== []) {
                    $forecastMin = min($bounds);
                    $forecastMax = max($forecastMin + 1, max($bounds));
                }
                ?><div class="forecast-days">
                <?php foreach ($forecastDays as $i => $forecastDay):
                    $start = is_int($forecastDay->meta['start']) ? $forecastDay->meta['start'] : null;
                    $code = $forecastDay->value('weatherCode')->raw;
                    $kind = View::weatherKind(is_numeric($code) ? (int) $code : null);
                    $min = $forecastDay->value('outTempMin');
                    $max = $forecastDay->value('outTempMax');
                    $rainValue = $forecastDay->value('rain');
                    ?><article class="forecast-day" data-forecast-weather="<?= $kind ?>">
                    <div class="forecast-date"><h3><?= $start !== null && $view->date($start, 'Y-m-d') === $view->date(time(), 'Y-m-d') ? $theme->html('Today') : [$theme->html('weekday.sun'), $theme->html('weekday.mon'), $theme->html('weekday.tue'), $theme->html('weekday.wed'), $theme->html('weekday.thu'), $theme->html('weekday.fri'), $theme->html('weekday.sat')][(int) $view->date($start, 'w')] ?></h3><span><?= View::escape($view->date($start, 'd.m.')) ?></span></div>
                    <div class="forecast-condition" title="<?= View::escape($view->condition($forecastDay->value('weatherCode'))) ?>"><?= $icon($weatherIcon($kind)) ?><span><?= View::escape($view->condition($forecastDay->value('weatherCode'))) ?></span></div>
                    <div class="forecast-temperature"><span><?= $forecastNumber($min) ?> <?= View::escape($min->unitLabel()) ?></span><svg viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><rect class="forecast-track" x="0" y="2" width="100" height="6" rx="3"/><?php if (is_numeric($min->raw) && is_numeric($max->raw)): ?><rect x="<?= max(0, ($min->raw - $forecastMin) / ($forecastMax - $forecastMin) * 90) ?>" y="2" width="<?= max(6, ($max->raw - $min->raw) / ($forecastMax - $forecastMin) * 90) ?>" height="6" rx="3" fill="url(#day-range)"/><?php endif; ?></svg><strong><?= $forecastNumber($max) ?> <?= View::escape($max->unitLabel()) ?></strong></div>
                    <div class="forecast-details"><span><?= $icon('drop') ?><?= $rainValue->html(label: false) ?> <small><?= View::escape($rainValue->unitLabel()) ?></small></span><span><?= $icon('wind') ?><?= $forecastNumber($forecastDay->value('windGust')) ?> <small><?= View::escape($forecastDay->value('windGust')->unitLabel()) ?></small></span></div>
                    <div class="forecast-probability"><?= $theme->html('Rain') ?> <?= $forecastNumber($forecastDay->value('rainProbability')) ?> %</div>
                </article><?php endforeach; ?>
            </div><?php endif; ?>
            <div class="forecast-source"><span><a href="https://open-meteo.com/">Open-Meteo</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a></span><span><?= $theme->html('Updated') ?> <?= View::escape($view->date($view->forecast->fetchedAt)) ?></span></div>
        </section><?php endif; ?>
        <div class="section-heading" id="rain"><h2><?= $theme->html('Rain & archive') ?><span class="section-dot">.</span></h2><span><?= View::escape($day) ?></span></div>
        <div class="rain-records-grid">
            <section class="panel rain-panel" aria-labelledby="rain-title">
                <div class="panel-heading"><div class="panel-label"><?= $icon('rain') ?><h3 id="rain-title"><?= $theme->html('Rainfall') ?></h3></div><span class="quiet"><?= $theme->html('7 days') ?></span></div>
                <div class="rain-summary"><div class="rain-total"><span data-value="rainDay"><?= $view->number('rainDay') ?></span><span class="unit"><?= $view->unitLabel('rainDay') ?></span><small><?= $theme->html('Day') ?></small></div><dl class="rain-totals"><div><dt><?= $theme->html('Month') ?></dt><dd><span data-value="rainMonth"><?= $view->number('rainMonth') ?></span> <span><?= $view->unitLabel('rainDay') ?></span></dd></div><div><dt><?= $theme->html('Year') ?></dt><dd><span data-value="rainYear"><?= $view->number('rainYear') ?></span> <span><?= $view->unitLabel('rainDay') ?></span></dd></div></dl></div>
                <?php if ($rain === []): ?><div class="chart-empty" role="status"><?= ($view->data['rain7d']->status ?? '') === 'pending' ? $theme->html('Calculating history …') : $theme->html('No historical data') ?></div><?php else: ?>
                <svg class="rain-chart" viewBox="0 0 420 150" role="img" aria-labelledby="rain-chart-title"><title id="rain-chart-title"><?= $theme->html('Daily total') . ' · ' . $view->unitLabel('rainDay') ?></title><defs><linearGradient id="rain-fill" x1="0" x2="0" y2="1"><stop stop-color="#a3cfc7"/><stop offset="1" stop-color="#527c75" stop-opacity=".25"/></linearGradient></defs><line class="grid-line" x1="10" x2="410" y1="115" y2="115"/>
                    <?php foreach ($rain as $i => $point): $width = 400 / count($rain);
                        $height = $point['value'] === null ? 0 : max(2, $point['value'] / $rainMax * 75); ?><g class="rain-column"><rect class="rain-bar" x="<?= sprintf('%.2F', 10 + $i * $width + 9) ?>" y="<?= sprintf('%.2F', 115 - $height) ?>" width="<?= sprintf('%.2F', $width - 18) ?>" height="<?= sprintf('%.2F', $height) ?>" rx="4"/><text class="rain-label" x="<?= sprintf('%.2F', 10 + ($i + 0.5) * $width) ?>" y="<?= sprintf('%.2F', 105 - $height) ?>" text-anchor="middle"><?= $point['value'] === null ? '—' : $decimal($point['value']) ?></text><text class="axis-label" x="<?= sprintf('%.2F', 10 + ($i + 0.5) * $width) ?>" y="142" text-anchor="middle"><?= View::escape($view->date($point['start'], 'd.m.')) ?></text></g><?php endforeach; ?>
                </svg><?php endif; ?>
            </section>
            <?php $comparison = $view->report('rainComparison');
$wettest = $view->report('wettestMonth');
$dry = $view->report('drySpell');
$record = $wettest->periods->points[0] ?? null; ?>
            <div class="records-grid">
                <article class="panel record-panel comparison-card"><div class="panel-label"><?= $icon('chart') ?><h3><?= $theme->html('Monthly comparison') ?></h3></div><div class="record-value"><?= $comparison->value('percentOfMean')->html() ?></div><p><?= $theme->html('Of the average of previous years') ?></p><details><summary><?= $theme->html('Reference years') ?></summary><p><?= $comparison->referenceYears() === [] ? '—' : View::escape(implode(', ', $comparison->referenceYears())) ?></p></details></article>
                <article class="panel record-panel wettest-card"><div class="panel-label"><?= $icon('record') ?><h3><?= $theme->html('Wettest month') ?></h3></div><div class="record-value"><?= $wettest->periods->value(0)->html() ?></div><p><?= View::escape($view->date($record['start'] ?? null, 'm.Y')) ?></p><span class="record-note"><?= $theme->html('Coverage ≥ 95 %') ?></span></article>
                <article class="panel record-panel dry-card"><div class="panel-label"><?= $icon('sun') ?><h3><?= $theme->html('Longest dry spell') ?></h3></div><div class="record-value"><?= $dry->value('intervals')->html() ?></div><p><?= $theme->html('Recorded dry days') ?></p><span class="record-note"><?= $theme->html('Since') ?> <?= $dry->value('start')->html('d.m.Y') ?></span></article>
            </div>
        </div>
        <section class="station-strip" id="station" aria-label="<?= $theme->html('Station information') ?>"><div class="station-strip-title"><?= $icon('station') ?><span><?= View::escape($title) ?><small>weewx-php</small></span></div><div class="station-coordinates"><?php if ($view->latitude !== null && $view->longitude !== null): ?><span><?= number_format(abs($view->latitude), 4) ?>° <?= $view->latitude < 0 ? 'S' : 'N' ?></span><span><?= number_format(abs($view->longitude), 4) ?>° <?= $view->longitude < 0 ? 'W' : 'E' ?></span><?php endif; ?><span><?= $view->altitude->raw === null ? '' : $view->altitude->html('%.0f') . ' ' . $theme->html('above sea level') ?></span></div></section>
        <nav class="dock" aria-label="<?= $theme->html('Main navigation') ?>"><a href="#now" aria-current="location"><?= $icon('grid') ?><span><?= $theme->html('Now') ?></span></a><a href="#analysis"><?= $icon('chart') ?><span><?= $theme->html('History') ?></span></a><?php if ($view->forecast->status !== 'disabled'): ?><a href="#forecast"><?= $icon('calendar') ?><span><?= $theme->html('Outlook') ?></span></a><?php endif; ?><a href="#sky"><?= $icon('sun') ?><span><?= $theme->html('Sun') ?></span></a><a href="#rain"><?= $icon('rain') ?><span><?= $theme->html('Rain') ?></span></a><a href="#station"><?= $icon('station') ?><span><?= $theme->html('Station') ?></span></a></nav>
    <?php endif; ?>
    </main>
    <footer><span>atmos<span class="brand-dot">.</span> <span class="footer-divider">/</span> weewx-php</span><span><?= View::escape($view?->zone->getName() ?? 'Europe/Berlin') ?></span></footer>
</div>
<dialog class="scene-dialog" aria-labelledby="scene-title"><div class="dialog-heading"><h2 id="scene-title"><?= $theme->html('Weather effects') ?></h2><button class="icon-button" type="button" data-close-scenes aria-label="<?= $theme->html('Close') ?>"><?= $icon('close') ?></button></div><div class="scene-options"><button type="button" data-scene="auto" aria-pressed="true"><?= $icon('station') ?><span><?= $theme->html('Automatic') ?></span></button><button type="button" data-scene="clear-day" aria-pressed="false"><?= $icon('sun') ?><span><?= $theme->html('Sun') ?></span></button><button type="button" data-scene="clear-twilight" aria-pressed="false"><?= $icon('sunset') ?><span><?= $theme->html('Sunset glow') ?></span></button><button type="button" data-scene="clear-night" aria-pressed="false"><?= $icon('moon') ?><span><?= $theme->html('Night') ?></span></button><button type="button" data-scene="rain-day" aria-pressed="false"><?= $icon('rain') ?><span><?= $theme->html('Rain') ?></span></button><button type="button" data-scene="snow-night" aria-pressed="false"><?= $icon('snow') ?><span><?= $theme->html('Snow') ?></span></button><button type="button" data-scene="storm-night" aria-pressed="false"><?= $icon('storm') ?><span><?= $theme->html('Thunderstorm') ?></span></button><button type="button" data-scene="fog-day" aria-pressed="false"><?= $icon('fog') ?><span><?= $theme->html('Fog') ?></span></button></div></dialog>
<script src="assets/visit.js" defer></script>
</body>
</html>
