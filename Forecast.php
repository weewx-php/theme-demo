<?php

declare(strict_types=1);

namespace WeewxPhp\DemoTheme;

use WeewxPhp\Frontend\{QueryError, Report, Series, Weather};

/** Theme presentation of optional extension tags. No provider or cache access. */
final class Forecast
{
    public readonly string $status;
    public readonly ?int $fetchedAt;
    private readonly int $days;
    /** @var array<int, Report> */
    private array $daily = [];

    public function __construct(private readonly Weather $weather)
    {
        $status = $weather->hasTag('forecast.status') ? $weather->tag('forecast.status') : null;
        $this->status = $status->status ?? 'disabled';
        $at = $status instanceof Report ? ($status->meta['fetched_at'] ?? null) : null;
        $days = $status instanceof Report ? ($status->meta['days'] ?? null) : null;
        $this->fetchedAt = is_int($at) ? $at : null;
        $this->days = is_int($days) ? max(0, min(16, $days)) : 0;
    }

    /** @return list<Report> */
    public function daily(int $days = 7): array
    {
        if ($days < 1 || $days > 16) {
            throw new QueryError('Forecast days must be 1..16');
        }
        $result = [];
        for ($index = 0; $index < min($days, $this->days); ++$index) {
            $day = $this->daily[$index] ?? $this->weather->tag('forecast.day', ['index' => $index]);
            if ($day instanceof Report && isset($day->meta['date'])) {
                $result[] = $this->daily[$index] = $day;
            }
        }
        return $result;
    }

    public function hourly(string $observation = 'outTemp', int $hours = 48): Series
    {
        $series = $this->weather->hasTag('forecast.hourly')
            ? $this->weather->tag('forecast.hourly', ['observation' => $observation, 'hours' => $hours]) : null;
        return $series instanceof Series ? $series : new Series([], status: $this->status);
    }
}
