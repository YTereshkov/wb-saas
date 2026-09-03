<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\AnalyticsTimeBucketBuilder;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AnalyticsTimeBucketBuilderTest extends TestCase
{
    public function test_week_buckets_follow_calendar_weeks_and_keep_comparison_offsets(): void
    {
        $period = $this->period('2026-01-07', '2026-04-07', 'week');

        $buckets = (new AnalyticsTimeBucketBuilder)->forPeriod($period);

        $this->assertSame('2026-01-07', $buckets[0]['start']->toDateString());
        $this->assertSame('2026-01-11', $buckets[0]['end']->toDateString());
        $this->assertSame('07.01–11.01', $buckets[0]['label']);
        $this->assertSame('2025-10-08', $buckets[0]['comparisonStart']->toDateString());
        $this->assertSame('2025-10-12', $buckets[0]['comparisonEnd']->toDateString());
        $this->assertSame('2026-04-07', $buckets[array_key_last($buckets)]['end']->toDateString());
    }

    public function test_multi_year_period_is_grouped_by_calendar_month(): void
    {
        $period = $this->period('2024-01-15', '2026-01-15', 'month');

        $buckets = (new AnalyticsTimeBucketBuilder)->forPeriod($period);

        $this->assertCount(25, $buckets);
        $this->assertSame('15–31 января 2024', $buckets[0]['label']);
        $this->assertSame('1–15 января 2026', $buckets[24]['label']);
        $this->assertSame('2026-01-15', $buckets[24]['end']->toDateString());
    }

    private function period(string $start, string $end, string $granularity): AnalyticsPeriod
    {
        $currentStart = CarbonImmutable::parse($start, 'Europe/Moscow');
        $currentEnd = CarbonImmutable::parse($end, 'Europe/Moscow');
        $days = (int) $currentStart->diffInDays($currentEnd) + 1;
        $comparisonEnd = $currentStart->subDay();

        return new AnalyticsPeriod(
            start: $currentStart,
            end: $currentEnd,
            comparisonStart: $comparisonEnd->subDays($days - 1),
            comparisonEnd: $comparisonEnd,
            timezone: 'Europe/Moscow',
            granularity: $granularity,
        );
    }
}
