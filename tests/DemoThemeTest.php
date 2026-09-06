<?php

declare(strict_types=1);

namespace WeewxPhp\Tests\Frontend;

use PHPUnit\Framework\TestCase;
use WeewxPhp\Archive\ArchiveDb;
use WeewxPhp\Config\Config;
use WeewxPhp\Config\JournalMode;
use WeewxPhp\DemoTheme\View;
use WeewxPhp\Frontend\ReadBudget;
use WeewxPhp\Frontend\Span;
use WeewxPhp\Frontend\Theme;
use WeewxPhp\Frontend\Weather;
use WeewxPhp\Tests\Support\Archives;
use WeewxPhp\Tests\Support\TempDir;
use WeewxPhp\Time\FixedClock;
use WeewxPhp\Weewx\Policy;
use WeewxPhp\Weewx\UnitSystem;

require_once dirname(__DIR__) . '/View.php';

final class DemoThemeTest extends TestCase
{
    public function testUsArchiveConvertsUnitsAndPreservesMissingReadingsAndChartGaps(): void
    {
        $dir = TempDir::create('demo-theme');
        $archive = Archives::config(database: $dir . '/weather.sdb', unitSystem: UnitSystem::US);
        $config = new Config(Archives::settings($dir), [], [$archive->id => $archive], []);
        $db = ArchiveDb::open($archive->database, JournalMode::Wal, new Policy(), $archive->timezone, create: true);
        $start = Span::timestamp('2026-08-26 12:00:00', $archive->timezone);
        foreach ([68.0, null, 86.0] as $i => $temperature) {
            $db->addRecord(['dateTime' => $start + $i * 900, 'usUnits' => 1, 'interval' => 15,
                'outTemp' => $temperature, 'rain' => 0.0, 'rainRate' => 0.1, 'windSpeed' => 10.0, 'barometer' => 29.92126]);
        }
        $live = \WeewxPhp\Live\LiveDb::open($config->settings->liveDbPath(), JournalMode::Wal);
        $live->add(new \WeewxPhp\Live\Packet($start + 1800, UnitSystem::US, ['outTemp' => 50.0], 'ecowitt'), [], 300);
        $live->close();
        $wx = new Weather($config, clock: new FixedClock($start + 1800), budget: new ReadBudget(maxStatements: 2048, milliseconds: 5000));
        try {
            $view = new View($wx, '../../invalid');
            self::assertSame('24h', $view->range);
            self::assertSame('30.0', $view->number('temperature'));
            self::assertSame('16.1', $view->number('wind'));
            self::assertSame('1,013', $view->number('pressure'));
            self::assertSame('0.0', $view->number('rainDay'));
            self::assertSame('2.5', $view->number('rainRate'));
            self::assertSame('—', $view->number('humidity'));
            $chart = $view->chart();
            self::assertSame([20.0, null, 30.0], array_column(array_slice($chart['points'], -3), 'value'));
            self::assertCount(2, $chart['paths']);
            // The full page must also render sparse archives and preserve the chart gaps.
            ob_start();
            try {
                require dirname(__DIR__) . '/template.php';
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertStringContainsString('data-value="temperature">30.0', $html);
            self::assertSame(2, substr_count($html, 'class="temperature-line"'));
            self::assertStringContainsString('class="scene-dialog"', $html);
            self::assertSame('rain', $view->atmosphere($start + 1800)['kind']);
            self::assertStringContainsString('<html lang="en">', $html);
            self::assertStringContainsString('Current readings', $html);
            $de = new Theme(language: 'de', texts: json_decode(file_get_contents(dirname(__DIR__) . '/locales/de.json'), true, flags: JSON_THROW_ON_ERROR));
            $translated = new View($wx, theme: $de);
            self::assertSame('30,0', $translated->number('temperature'));
            self::assertSame('Regen', $translated->atmosphere($start + 1800)['label']);
            $usTheme = new Theme(language: 'de', texts: $de->texts,
                units: new \WeewxPhp\Frontend\UnitPreferences('us', 'us'));
            $us = new View($wx, theme: $usTheme);
            self::assertSame('86,0', $us->number('temperature'));
            self::assertSame('10,0', $us->number('wind'));
            self::assertSame('29,92', $us->number('pressure'));
            self::assertSame('0,10', $us->number('rainRate'));
            self::assertSame('°F', $us->unitLabel('temperature'));
            self::assertSame([68.0, null, 86.0], array_column(array_slice($us->chart()['points'], -3), 'value'));
            self::assertCount(2, $us->chart()['paths']);
            self::assertSame($view->atmosphere($start + 1800)['wind'], $us->atmosphere($start + 1800)['wind']);
            self::assertSame(30.0, $us->atmosphere($start + 1800)['temperature']);
            self::assertSame('degree_F', $us->snapshot()['live']->unit);
            self::assertSame(50.0, $us->liveTemperature->raw);
            self::assertSame(10.0, $view->liveTemperature->raw);
            self::assertSame('us', $us->snapshot()['unitProfile']);
            $render = require dirname(__DIR__) . '/theme.php';
            $usHtml = $render($wx, $usTheme, ['range' => '24h']);
            self::assertStringContainsString('data-chart data-unit="°F"', $usHtml);
            self::assertStringContainsString('<th scope="col">°F</th>', $usHtml);
            self::assertStringContainsString('<span class="unit">inHg</span>', $usHtml);
            self::assertStringContainsString('<span class="unit">in</span>', $usHtml);
            self::assertStringContainsString('value="us" selected', $usHtml);
            self::assertStringContainsString('?units=us&amp;range=7d', $usHtml);
            self::assertStringContainsString('Einheiten', $usHtml);
            self::assertSame('30.0', $view->number('temperature'));

            // Providers expose measurements; their display must follow the same visitor profile.
            $entry = $dir . '/extension.php';
            file_put_contents($entry, <<<'PHP'
<?php
use WeewxPhp\Frontend\{Report, Series, Value};
return static function (\WeewxPhp\Extension\Registration $registration): void {
    $registration->tag('status', static fn($context) => new Report(new Series([]), [], ['days' => 1, 'fetched_at' => $context->now]));
    $values = [
        'outTemp' => new Value(10, 'degree_C', 'group_temperature'),
        'outTempMin' => new Value(0, 'degree_C', 'group_temperature'),
        'outTempMax' => new Value(20, 'degree_C', 'group_temperature'),
        'rain' => new Value(0.254, 'mm', 'group_rain'),
        'windGust' => new Value(36, 'km_per_hour', 'group_speed'),
    ];
    $registration->tag('day', static fn($context) => new Report(new Series([]), $values, ['date' => '2026-08-26', 'start' => $context->now]));
    $registration->tag('normal', static fn() => new Report(new Series([]), $values, []));
};
PHP);
            $extensions = [];
            foreach (['forecast', 'climate'] as $id) {
                $extensions[$id] = new \WeewxPhp\Extension\Definition($id, $entry, new \WeewxPhp\Config\Section($id, 2));
            }
            $extended = new Weather(new Config($config->settings, [], $config->archives, [], extensions: $extensions),
                clock: new FixedClock($start + 1800), budget: new ReadBudget(maxStatements: 2048, milliseconds: 5000));
            try {
                $forecastView = new View($extended, theme: $usTheme);
                self::assertSame('32 °F', $forecastView->forecast->daily()[0]->value('outTempMin')->format());
                self::assertSame('0,01 in', $forecastView->forecast->daily()[0]->value('rain')->format());
                self::assertSame('50,0 °F', $forecastView->climate->value('outTemp')->format());
                $forecastHtml = $render($extended, $usTheme, []);
                self::assertStringContainsString('0,01 <small>in</small>', $forecastHtml);
                self::assertStringContainsString('32 °F', $forecastHtml);
                self::assertStringContainsString('50,0 °F', $forecastHtml);
            } finally {
                $extended->close();
            }
        } finally {
            $wx->close();
            $db->close();
            TempDir::remove($dir);
        }
    }

    public function testStationTextIsSafeForHtmlAndAttributes(): void
    {
        self::assertSame('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#039;Ort&#039;', View::escape('<img src=x onerror="alert(1)"> & \'Ort\''));
    }

    public function testMissingConfigurationRendersWithoutInternalPaths(): void
    {
        $view = null;
        ob_start();
        try {
            require dirname(__DIR__) . '/template.php';
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertStringContainsString('No weather data', $html);
        self::assertStringNotContainsString('weewx-php.conf', $html);
        self::assertStringNotContainsString(dirname(__DIR__), $html);
    }
}
