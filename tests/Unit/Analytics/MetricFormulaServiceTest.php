<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Services\MetricFormulaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MetricFormulaServiceTest extends TestCase
{
    private MetricFormulaService $formulas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formulas = new MetricFormulaService;
    }

    public function test_canonical_rates_and_stock_forecast(): void
    {
        $this->assertEqualsWithDelta(79.85884908, $this->formulas->buyoutRate(1_471, 1_842), 0.00000001);
        $this->assertSame(371, $this->formulas->notBought(1_842, 1_471));

        $average = $this->formulas->averageDailySales(217, 31);

        $this->assertSame(7.0, $average);
        $this->assertSame(4.0, $this->formulas->stockCoverage(28, $average));
        $this->assertSame(182, $this->formulas->recommendedSupply(28, $average));
    }

    #[DataProvider('edgeCases')]
    public function test_edge_cases(string $formula, array $arguments, mixed $expected): void
    {
        $this->assertSame($expected, $this->formulas->{$formula}(...$arguments));
    }

    /** @return list<array{string, list<int|float>, int|float|null}> */
    public static function edgeCases(): array
    {
        return [
            ['buyoutRate', [0, 0], null],
            ['returnsRate', [0, 0], null],
            ['notBought', [5, 8], 0],
            ['stockCoverage', [30, 0.0], null],
            ['recommendedSupply', [30, 0.0], 0],
            ['estimatedMissedSales', [3.5, 4], 14.0],
        ];
    }
}
