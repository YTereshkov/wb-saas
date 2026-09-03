<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Services\AnalyticsPeriodResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalyticsPeriodResolverTest extends TestCase
{
    #[DataProvider('granularities')]
    public function test_custom_period_uses_automatic_granularity(
        string $start,
        string $end,
        string $expected,
    ): void {
        $period = (new AnalyticsPeriodResolver)->forCustom(null, $start, $end);

        $this->assertSame($expected, $period->granularity);
        $this->assertSame($period->days(), (int) $period->comparisonStart->diffInDays($period->comparisonEnd) + 1);
        $this->assertSame($period->start->subDay()->toDateString(), $period->comparisonEnd->toDateString());
    }

    /** @return list<array{string, string, string}> */
    public static function granularities(): array
    {
        return [
            ['2026-01-01', '2026-03-31', 'day'],
            ['2026-01-01', '2026-04-01', 'week'],
            ['2024-01-01', '2026-01-01', 'month'],
            ['2016-01-01', '2026-01-01', 'month'],
        ];
    }
}
