<?php

namespace App\Modules\Analytics\Services;

final class MetricFormulaService
{
    public function buyoutRate(int $sales, int $orders): ?float
    {
        return $orders === 0 ? null : ($sales / $orders) * 100;
    }

    public function returnsRate(int $returns, int $sales): ?float
    {
        return $sales === 0 ? null : ($returns / $sales) * 100;
    }

    public function notBought(int $orders, int $sales): int
    {
        return max($orders - $sales, 0);
    }

    public function averageDailySales(int $sales, int $days): float
    {
        return $days === 0 ? 0.0 : $sales / $days;
    }

    public function stockCoverage(int $stock, float $averageDailySales): ?float
    {
        return $averageDailySales <= 0 ? null : $stock / $averageDailySales;
    }

    public function recommendedSupply(int $stock, float $averageDailySales, int $horizonDays = 30): int
    {
        return (int) ceil(max(0, ($averageDailySales * $horizonDays) - $stock));
    }

    public function estimatedMissedSales(float $averageDailySales, int $outOfStockDays): float
    {
        return $averageDailySales * $outOfStockDays;
    }
}
