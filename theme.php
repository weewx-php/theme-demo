<?php

declare(strict_types=1);

use WeewxPhp\DemoTheme\View;
use WeewxPhp\Frontend\Theme;
use WeewxPhp\Frontend\Weather;

require_once __DIR__ . '/View.php';

return static function (Weather $wx, Theme $theme, array $query): string {
    $range = $query['range'] ?? $theme->extras['default_range'] ?? '24h';
    $view = new View($wx, $range === '7d' ? '7d' : '24h', $theme);
    ob_start();
    try {
        require __DIR__ . '/template.php';
        return (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
