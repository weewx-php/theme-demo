<?php

declare(strict_types=1);

use WeewxPhp\DemoTheme\View;
use WeewxPhp\Frontend\Theme;
use WeewxPhp\Frontend\Weather;

require_once __DIR__ . '/View.php';

return static function (Weather $wx, Theme $theme, array $query): array {
    $range = $query['range'] ?? $theme->extras['default_range'] ?? '24h';
    return (new View($wx, $range === '7d' ? '7d' : '24h', $theme))->snapshot();
};
