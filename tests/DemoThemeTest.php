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
