<?php

namespace App\Modules\Analytics\Data;

use Carbon\CarbonImmutable;

final readonly class AnalyticsPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $comparisonStart,
        public CarbonImmutable $comparisonEnd,
        public string $timezone,
        public string $granularity = 'day',
    ) {}

    public static function calendarMonth(CarbonImmutable $date, string $timezone): self
    {
        $current = $date->setTimezone($timezone);
        $start = $current->startOfMonth();
        $end = $current->endOfMonth();
        $previous = $start->subMonthNoOverflow();

        return new self(
            start: $start,
            end: $end,
            comparisonStart: $previous->startOfMonth(),
            comparisonEnd: $previous->endOfMonth(),
            timezone: $timezone,
        );
    }

    public function days(): int
    {
        return (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }
}
