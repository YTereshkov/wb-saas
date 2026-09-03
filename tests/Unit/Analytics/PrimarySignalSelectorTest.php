<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Services\MetricFormulaService;
use App\Modules\Analytics\Services\PrimarySignalSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PrimarySignalSelectorTest extends TestCase
{
    #[DataProvider('stockPriorities')]
    public function test_approved_stock_priorities(
        int $stock,
        float $averageDailySales,
        ?int $priority,
        ?string $type,
    ): void {
        $signal = (new PrimarySignalSelector(new MetricFormulaService))
            ->forStock($stock, $averageDailySales);

        $this->assertSame($priority, $signal?->priority);
        $this->assertSame($type, $signal?->type);
    }

    /** @return list<array{int, float, int|null, string|null}> */
    public static function stockPriorities(): array
    {
        return [
            [0, 7.0, 1, 'out_of_stock'],
            [14, 7.0, 2, 'stock_critical'],
            [28, 7.0, 3, 'stock_low'],
            [56, 7.0, null, null],
            [28, 0.0, null, null],
        ];
    }

    public function test_performance_signal_uses_decline_after_stock_priorities(): void
    {
        $signal = (new PrimarySignalSelector(new MetricFormulaService))->forPerformance(
            currentRevenueKopecks: 18_000_000,
            comparisonRevenueKopecks: 22_000_000,
            returnsRate: 2.4,
            buyoutRate: 80.0,
            comparisonBuyoutRate: 79.0,
        );

        $this->assertNotNull($signal);
        $this->assertSame('revenue_decline', $signal->type);
        $this->assertSame(5, $signal->priority);
    }

    #[DataProvider('dayNouns')]
    public function test_stock_signal_uses_russian_day_pluralization(int $days, string $noun): void
    {
        $signal = (new PrimarySignalSelector(new MetricFormulaService))->forStock($days, 1.0);

        $this->assertNotNull($signal);
        $this->assertStringContainsString("{$days} {$noun}", $signal->risk);
    }

    /** @return list<array{int, string}> */
    public static function dayNouns(): array
    {
        return [[1, 'день'], [2, 'дня'], [5, 'дней']];
    }
}
