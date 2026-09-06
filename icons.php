<?php

declare(strict_types=1);

namespace WeewxPhp\DemoTheme;

// One shared stroke language. All identifiers are fixed by the template.
final class Icons
{
    public static function render(string $name, string $class = ''): string
    {
        $paths = [
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
            'moon' => '<path d="M20.8 14A9 9 0 0 1 10 3.2 9 9 0 1 0 20.8 14Z"/><path d="M18 2v4m-2-2h4"/>',
            'cloud' => '<path d="M7 18a5 5 0 1 1 .9-9.9A6 6 0 0 1 19.5 10 4 4 0 0 1 19 18Z"/>',
            'partly' => '<path d="M9 4V2M4 9H2m2-5 1.5 1.5M14 4l1-1"/><path d="M5 11a4 4 0 1 1 6-5"/><path d="M9 20a4 4 0 1 1 .5-8A5 5 0 0 1 19 12a4 4 0 0 1 0 8Z"/>',
            'rain' => '<path d="M6 15a4 4 0 1 1 1-7.9A5.5 5.5 0 0 1 18 9a3 3 0 0 1 1 6M8 18l-1 3m6-3-1 3m6-3-1 3"/>',
            'snow' => '<path d="M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7M9 4l3 3 3-3M9 20l3-3 3 3M3.6 10l4-1-1-4m11.8 14-1-4 4-1M6.6 19l1-4-4-1m17.8-4-4-1 1-4"/>',
            'storm' => '<path d="M6 15a4 4 0 1 1 1-7.9A5.5 5.5 0 0 1 18 9a3 3 0 0 1 1 6M13 12l-4 6h5l-3 5"/>',
            'fog' => '<path d="M6 12a4 4 0 1 1 1-7.9A5.5 5.5 0 0 1 18 6a3 3 0 0 1 1 6M3 16h18M5 20h14"/>',
            'temperature' => '<path d="M9 14.8V5a3 3 0 0 1 6 0v9.8a5 5 0 1 1-6 0Z"/><path d="M12 9v9m6-12h2m-2 4h2"/><circle cx="12" cy="18" r="1"/>',
            'drop' => '<path d="M12 2S5 10 5 15a7 7 0 0 0 14 0c0-5-7-13-7-13Z"/><path d="M8 15a4 4 0 0 0 4 4"/>',
            'wind' => '<path d="M3 7h12a3 3 0 1 0-3-3M2 12h17a3 3 0 1 0-3-3M4 17h9a3 3 0 1 1-3 3"/>',
            'pressure' => '<path d="M4 18a10 10 0 1 1 16 0M12 4v2M5 8l2 1m12-1-2 1M3 14h2m14 0h2M12 14l4-5"/><circle cx="12" cy="14" r="2"/><path d="M8 21h8"/>',
            'chart' => '<path d="M3 3v18h18M6 15l5-5 4 2 6-7"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2m-8 3h2"/>',
            'station' => '<path d="M12 13v9m-4 0h8M8 5a6 6 0 0 0 0 8m8-8a6 6 0 0 1 0 8M5 2a10 10 0 0 0 0 14M19 2a10 10 0 0 1 0 14"/><circle cx="12" cy="9" r="2"/>',
            'location' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
            'up' => '<path d="M12 20V4m-6 6 6-6 6 6"/>',
            'down' => '<path d="M12 4v16m-6-6 6 6 6-6"/>',
            'sunrise' => '<path d="M2 18h20M4 22h16M6 14a6 6 0 0 1 12 0M12 2v7m-3-4 3-3 3 3M3 9l2 2m14 0 2-2"/>',
            'sunset' => '<path d="M2 18h20M4 22h16M6 14a6 6 0 0 1 12 0M12 2v7m-3-3 3 3 3-3M3 9l2 2m14 0 2-2"/>',
            'play' => '<path d="m9 5 11 7-11 7Z"/>',
            'pause' => '<path d="M8 5v14M16 5v14"/>',
            'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
            'record' => '<path d="M8 3h8v8a4 4 0 0 1-8 0ZM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 3v5m-4 1h8"/>',
        ];
        return '<svg class="icon ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? $paths['cloud']) . '</svg>';
    }
}
