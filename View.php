<?php

declare(strict_types=1);

namespace WeewxPhp\DemoTheme;

use DateTimeZone;
use Throwable;
use WeewxPhp\Frontend\Query;
use WeewxPhp\Frontend\Report;
use WeewxPhp\Frontend\Series;
use WeewxPhp\Frontend\Span;
use WeewxPhp\Frontend\Theme;
use WeewxPhp\Frontend\Value;
use WeewxPhp\Frontend\Weather;

/** Presentation helpers for this theme. All weather data comes through PHP tags. */
require_once __DIR__ . '/Forecast.php';

final class View
{
    /** @var array<string, Value|Series|Report> */
    public readonly array $data;
    public readonly string $name;
    public readonly DateTimeZone $zone;
    public readonly string $range;
    public readonly ?int $updated;
    public readonly string $status;
    public readonly Value $liveTemperature;
    public readonly Forecast $forecast;
    public readonly ?Report $climate;
    public readonly ?float $altitude;
    public readonly ?float $latitude;
    public readonly ?float $longitude;

    public function __construct(Weather $wx, string $range = '24h', public readonly Theme $theme = new Theme())
    {
        $this->range = $range === '7d' ? '7d' : '24h';
        $this->name = $wx->configuration()->name;
        $this->zone = $wx->configuration()->timezone;
        $this->altitude = $wx->configuration()->altitude?->in('meter');
        $this->latitude = $wx->configuration()->latitude;
        $this->longitude = $wx->configuration()->longitude;
        $language = $theme->language;
        /** @var array<string, Query> $queries */
        $queries = require __DIR__ . '/data.php';
        $wx->syncTheme('demo', $queries);
        $data = [];
        foreach ($queries as $key => $query) {
            // The CLI can register both ranges; the page loads only its selection.
            if (in_array($key, ['temperature24h', 'temperature7d'], true) && $key !== 'temperature' . $this->range) {
                continue;
            }
            try {
                $result = $query->get();
            } catch (Throwable) {
                error_log('Demo theme query unavailable: ' . $key);
                $result = new Value(null, status: 'unavailable');
            }
            $data[$key] = $result;
        }
        $this->data = $data;
        $updated = $this->value('updated')->raw;
        $this->updated = is_int($updated) || is_float($updated) ? (int) $updated : null;
        // A refresh deadline is normal cache lifecycle, not a station outage.
        $temperature = $this->value('temperature');
        $this->status = $temperature->raw === null
            ? ($temperature->status === 'pending' ? $this->theme->text('Calculating readings') : $this->theme->text('Readings unavailable')) : '';
        $this->liveTemperature = $wx->live('outTemp')->to('degree_C');
        $this->forecast = new Forecast($wx->output(new \WeewxPhp\Frontend\Output($theme->language, decimals: [
            'group_temperature' => 0, 'group_speed' => 0, 'group_percent' => 0,
        ])));
        $climate = $wx->hasTag('climate.normal') ? $wx->tag('climate.normal') : null;
        $this->climate = $climate instanceof Report && $climate->status !== 'disabled' ? $climate : null;
    }

    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function value(string $key): Value
    {
        $value = $this->data[$key] ?? null;
        return $value instanceof Value ? $value : new Value(null);
    }

    public function number(string $key): string
    {
        return $this->value($key)->html(label: false);
    }

    public function date(?int $timestamp, string $format = 'd.m.Y · H:i'): string
    {
        if ($this->theme->language !== 'de') {
            $format = match ($format) {
                'd.m.Y · H:i' => 'Y-m-d · H:i', 'd.m.Y' => 'Y-m-d', 'd.m.' => 'm-d', 'm.Y' => 'Y-m', default => $format,
            };
        }
        return $timestamp === null ? '—' : Span::date($timestamp, $this->zone)->format($format);
    }

    public function solarTime(string $key): string
    {
        $value = $this->value($key)->raw;
        return $this->date(is_int($value) || is_float($value) ? (int) $value : null, 'H:i');
    }

    /** @return list<array{start: int, end: int, value: float|null}> */
    public function points(string $key, string $unit): array
    {
        $series = $this->data[$key] ?? null;
        if (!$series instanceof Series || $series->unit === null) {
            return [];
        }
        $points = [];
        foreach ($series->to($unit)->points as $point) {
            $raw = $point['value'];
            $points[] = ['start' => $point['start'], 'end' => $point['end'], 'value' => is_int($raw) || is_float($raw) ? (float) $raw : null];
        }
        return $points;
    }

    public function report(string $key): Report
    {
        $value = $this->data[$key] ?? null;
        return $value instanceof Report ? $value : new Report(new Series([]), [], [], 'pending');
    }

    /** The same prepared values used by the HTML view.
     * @return array<string, mixed> */
    public function snapshot(): array
    {
        $formatted = [];
        foreach ($this->data as $name => $value) {
            if ($value instanceof Value) {
                $formatted[$name] = $value->format(label: false);
            }
        }
        return ['data' => $this->data, 'formatted' => $formatted, 'updated' => $this->updated,
            'updatedLabel' => $this->date($this->updated), 'live' => $this->liveTemperature,
            'liveLabel' => $this->liveTemperature->format(), 'status' => $this->status,
            'atmosphere' => $this->atmosphere(),
            'climate' => $this->climate === null ? null : ['status' => $this->climate->status, 'date' => $this->climate->meta['date'] ?? null, 'computed_at' => $this->climate->meta['computed_at'] ?? null, 'window_days' => $this->climate->meta['window_days'] ?? null],
            'forecast' => ['fetched_at' => $this->forecast->fetchedAt, 'status' => $this->forecast->status,
                'dates' => array_map(static fn(Report $day): mixed => $day->meta['date'], $this->forecast->daily())]];
    }

    public function condition(Value $code): string
    {
        return match (is_int($code->raw) || is_float($code->raw) ? (int) $code->raw : null) {
            0 => $this->theme->text('Clear'), 1 => $this->theme->text('Mostly clear'), 2 => $this->theme->text('Partly cloudy'), 3 => $this->theme->text('Overcast'),
            45, 48 => $this->theme->text('Fog'), 51, 53, 55 => $this->theme->text('Drizzle'), 56, 57 => $this->theme->text('Freezing drizzle'),
            61, 63, 65 => $this->theme->text('Rain'), 66, 67 => $this->theme->text('Freezing rain'),
            71, 73, 75, 77 => $this->theme->text('Snow'), 80, 81, 82 => $this->theme->text('Rain showers'),
            85, 86 => $this->theme->text('Snow showers'), 95 => $this->theme->text('Thunderstorm'), 96, 99 => $this->theme->text('Thunderstorm with hail'), default => '—',
        };
    }

    public static function weatherKind(?int $code): string
    {
        return match ($code) {
            0, 1 => 'clear', 2 => 'partly', 3 => 'cloudy', 45, 48 => 'fog',
            51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82 => 'rain',
            71, 73, 75, 77, 85, 86 => 'snow', 95, 96, 99 => 'storm',
            default => 'unknown',
        };
    }

    /** The scene uses current forecast conditions, never a whole day's weather code.
     * @return array{kind: string, phase: string, label: string, source: string, sunrise: int|null, sunset: int|null, wind: float}
     */
    public function atmosphere(?int $now = null): array
    {
        $now ??= time();
        $rise = $this->value('sunrise')->raw;
        $set = $this->value('sunset')->raw;
        $rise = is_numeric($rise) ? (int) $rise : null;
        $set = is_numeric($set) ? (int) $set : null;
        $hour = (int) $this->date($now, 'G');
        $phase = $hour >= 7 && $hour < 19 ? 'day' : 'night';
        if ($rise !== null && $set !== null && $this->date($rise, 'Y-m-d') === $this->date($now, 'Y-m-d')) {
            $phase = $now >= $rise && $now < $set ? 'day' : 'night';
            if (abs($now - $rise) < 2700 || abs($now - $set) < 2700) {
                $phase = 'twilight';
            }
        }
        $code = null;
        if ($this->forecast->status === 'ready') {
            foreach ($this->forecast->hourly('weatherCode', 2)->points as $point) {
                if (abs($point['end'] - $now) <= 3600 && is_numeric($point['value'])) {
                    $code = (int) $point['value'];
                    break;
                }
            }
        }
        $kind = self::weatherKind($code);
        $label = $this->condition(new Value($code));
        $source = $code === null ? '' : 'Prognose jetzt';
        $rate = $this->value('rainRate')->raw;
        if (is_numeric($rate) && $rate > 0 && $this->updated !== null && abs($now - $this->updated) < 900) {
            $kind = 'rain';
            $label = 'Regen';
            $source = 'Gemessen';
        }
        if ($kind === 'unknown') {
            $label = match ($phase) {
                'day' => $this->theme->text('Daytime'), 'twilight' => $this->theme->text('Twilight'), default => $this->theme->text('Nighttime')
            };
        }
        $wind = $this->value('wind')->raw;
        return ['kind' => $kind, 'phase' => $phase, 'label' => $label, 'source' => $source,
            'sunrise' => $rise, 'sunset' => $set, 'wind' => is_numeric($wind) ? (float) $wind : 0.0];
    }

    /**
     * Separate paths preserve gaps. SVG coordinates are generated only from numeric values.
     * @return array{paths: list<string>, low: float, high: float, start: int|null, end: int|null, points: list<array{start: int, end: int, value: float|null}>}
     */
    public function chart(): array
    {
        $points = $this->points('temperature' . $this->range, 'degree_C');
        $values = [];
        foreach ($points as $point) {
            if ($point['value'] !== null) {
                $values[] = $point['value'];
            }
        }
        $low = $values === [] ? 0.0 : floor(min($values) / 5) * 5;
        $high = $values === [] ? 10.0 : max($low + 5, ceil(max($values) / 5) * 5);
        $start = $points[0]['end'] ?? null;
        $end = $points === [] ? null : $points[count($points) - 1]['end'];
        $paths = [];
        $path = '';
        foreach ($points as $point) {
            if ($point['value'] === null) {
                if ($path !== '') {
                    $paths[] = $path;
                    $path = '';
                }
                continue;
            }
            $x = 48 + ($point['end'] - ($start ?? 0)) / max(1, ($end ?? 0) - ($start ?? 0)) * 704;
            $y = 210 - ($point['value'] - $low) / ($high - $low) * 180;
            $path .= ($path === '' ? 'M' : ' L') . sprintf('%.2F %.2F', $x, $y);
        }
        if ($path !== '') {
            $paths[] = $path;
        }
        return ['paths' => $paths, 'low' => $low, 'high' => $high, 'start' => $start, 'end' => $end, 'points' => $points];
    }
}
