<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Data\PrimarySignalData;

final readonly class PrimarySignalSelector
{
    public function __construct(private MetricFormulaService $formulas) {}

    public function forStock(int $stock, float $averageDailySales): ?PrimarySignalData
    {
        $coverage = $this->formulas->stockCoverage($stock, $averageDailySales);

        if ($stock === 0 && $averageDailySales > 0) {
            return $this->signal('out_of_stock', 1, 'danger', $stock, 0, $averageDailySales);
        }

        if ($coverage !== null && $coverage <= 3) {
            return $this->signal('stock_critical', 2, 'danger', $stock, $coverage, $averageDailySales);
        }

        if ($coverage !== null && $coverage <= 7) {
            return $this->signal('stock_low', 3, 'warning', $stock, $coverage, $averageDailySales);
        }

        return null;
    }

    public function forPerformance(
        int $currentRevenueKopecks,
        int $comparisonRevenueKopecks,
        ?float $returnsRate,
        ?float $buyoutRate,
        ?float $comparisonBuyoutRate,
    ): ?PrimarySignalData {
        if (($returnsRate !== null && $returnsRate >= 10)
            || ($buyoutRate !== null && $comparisonBuyoutRate !== null && $comparisonBuyoutRate - $buyoutRate >= 5)) {
            $fact = $returnsRate !== null && $returnsRate >= 10
                ? 'Возвраты '.number_format($returnsRate, 1, ',', ' ').'%'
                : 'Выкуп снизился на '.number_format($comparisonBuyoutRate - $buyoutRate, 1, ',', ' ').' п.п.';

            return new PrimarySignalData(
                type: 'buyout_returns_risk',
                priority: 4,
                severity: 'warning',
                fact: $fact,
                risk: 'показатели выкупа требуют проверки',
                recommendation: 'Проверьте карточку товара, ожидания покупателей и причины возвратов.',
                evidence: [
                    'returns_rate' => $returnsRate,
                    'buyout_rate' => $buyoutRate,
                    'comparison_buyout_rate' => $comparisonBuyoutRate,
                ],
            );
        }

        if ($comparisonRevenueKopecks <= 0 || $currentRevenueKopecks >= $comparisonRevenueKopecks) {
            return null;
        }

        $decline = (($comparisonRevenueKopecks - $currentRevenueKopecks) / $comparisonRevenueKopecks) * 100;

        return new PrimarySignalData(
            type: 'revenue_decline',
            priority: 5,
            severity: 'warning',
            fact: 'Выручка снизилась на '.number_format($decline, 1, ',', ' ').'%',
            risk: 'товар теряет выручку к прошлому периоду',
            recommendation: 'Проверьте трафик, цену, остатки и позицию товара в выдаче.',
            evidence: [
                'current_revenue_kopecks' => $currentRevenueKopecks,
                'comparison_revenue_kopecks' => $comparisonRevenueKopecks,
                'revenue_decline_percent' => $decline,
            ],
        );
    }

    private function signal(
        string $type,
        int $priority,
        string $severity,
        int $stock,
        float $coverage,
        float $averageDailySales,
    ): PrimarySignalData {
        $coverageDays = (int) round($coverage);

        return new PrimarySignalData(
            type: $type,
            priority: $priority,
            severity: $severity,
            fact: "{$stock} шт. в наличии",
            risk: $stock === 0
                ? 'товар закончился'
                : "хватит примерно на {$coverageDays} {$this->dayNoun($coverageDays)}",
            recommendation: 'Запланируйте поставку с учётом времени приёмки.',
            evidence: [
                'current_stock' => $stock,
                'average_daily_sales' => $averageDailySales,
                'stock_coverage_days' => $coverage,
                'recommended_supply' => $this->formulas->recommendedSupply($stock, $averageDailySales),
            ],
        );
    }

    private function dayNoun(int $days): string
    {
        $mod100 = $days % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'дней';
        }

        return match ($days % 10) {
            1 => 'день',
            2, 3, 4 => 'дня',
            default => 'дней',
        };
    }
}
