<?php

namespace App\Modules\Analytics\Queries;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\Analytics\Services\AnalyticsTimeBucketBuilder;
use App\Modules\Analytics\Services\MetricFormulaService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class ProductDetailsQuery
{
    private const MAX_FORECAST_DAYS = 365;

    public function __construct(
        private ProductAnalyticsQuery $products,
        private MetricFormulaService $formulas,
        private AnalyticsTimeBucketBuilder $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function get(SellerAccount $account, AnalyticsPeriod $period, Product $product): array
    {
        $product->load(['category:id,name', 'signals' => fn ($query) => $query->orderBy('priority')]);
        $row = $this->products->base($account, $period)->where('products.id', $product->id)->firstOrFail();
        $summary = $this->products->toRow($row);
        $daily = $this->dailyMetrics($account, $product, $period);
        $current = $this->totals($daily, $period->start, $period->end);
        $comparison = $this->totals($daily, $period->comparisonStart, $period->comparisonEnd);
        $stock = (int) $summary['stock'];
        $averageDailySales = $this->formulas->averageDailySales($current['sales'], $period->days());
        $coverage = $this->formulas->stockCoverage($stock, $averageDailySales);
        $warehouses = $this->warehouses($account, $product, $stock, $averageDailySales);
        $signal = $summary['signal'];

        return [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'vendorCode' => $product->vendor_code,
                'nmId' => $product->nm_id,
                'imageUrl' => $product->image_url,
                'category' => $product->category?->name,
            ],
            'signal' => $signal,
            'insights' => [
                'overview' => $coverage !== null && $coverage <= 7
                    ? 'Продажи идут, но запаса хватит примерно на '.max(0, (int) floor($coverage)).' дн.'
                    : ($signal['fact'] ?? 'Критичных сигналов по товару нет'),
                'sales' => $this->salesInsight($current, $comparison),
                'stocks' => $coverage === null
                    ? 'Недостаточно продаж для расчёта покрытия запаса'
                    : 'Запаса хватит примерно на '.max(0, (int) floor($coverage)).' дн. — '.($coverage <= 7 ? 'поставка нужна сейчас' : 'срочная поставка не требуется'),
            ],
            'kpis' => $this->kpis($current, $comparison),
            'salesKpis' => $this->salesKpis($current, $comparison),
            'series' => $this->series($daily, $period),
            'weekly' => $this->weekly($daily, $period),
            'funnel' => [
                'orders' => $current['orders'],
                'sales' => $current['sales'],
                'notBought' => $this->formulas->notBought($current['orders'], $current['sales']),
                'buyout' => $this->round($this->formulas->buyoutRate($current['sales'], $current['orders'])),
                'returnsRate' => $this->round($this->formulas->returnsRate($current['returns'], $current['sales'])),
            ],
            'stock' => [
                'total' => $stock,
                'averageDailySales' => round($averageDailySales, 1),
                'coverage' => $coverage === null ? null : round($coverage, 1),
                'outDate' => $coverage === null || $coverage > self::MAX_FORECAST_DAYS ? null : $this->translatedDate($period->end->addDays((int) ceil($coverage))),
                'recommendedSupply' => $this->formulas->recommendedSupply($stock, $averageDailySales),
                'series' => $this->stockSeries($account, $product, $period, $stock, $averageDailySales),
                'warehouses' => $warehouses,
            ],
        ];
    }

    /** @return Collection<int, ProductDailyMetric> */
    private function dailyMetrics(SellerAccount $account, Product $product, AnalyticsPeriod $period): Collection
    {
        return ProductDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->where('product_id', $product->id)
            ->whereBetween('metric_date', [$period->comparisonStart->toDateString(), $period->end->toDateString()])
            ->orderBy('metric_date')
            ->get();
    }

    /**
     * @param  Collection<int, ProductDailyMetric>  $daily
     * @return array{revenue: int, orders: int, sales: int, returns: int}
     */
    private function totals(Collection $daily, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $range = $daily->filter(fn (ProductDailyMetric $metric): bool => $metric->metric_date->toDateString() >= $start->toDateString()
            && $metric->metric_date->toDateString() <= $end->toDateString());

        return [
            'revenue' => (int) $range->sum('revenue_kopecks'),
            'orders' => (int) $range->sum('orders'),
            'sales' => (int) $range->sum('sales'),
            'returns' => (int) $range->sum('returns'),
        ];
    }

    /**
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $current
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $comparison
     * @return list<array<string, mixed>>
     */
    private function kpis(array $current, array $comparison): array
    {
        $buyout = $this->formulas->buyoutRate($current['sales'], $current['orders']);
        $comparisonBuyout = $this->formulas->buyoutRate($comparison['sales'], $comparison['orders']);

        return [
            $this->kpi('revenue', 'Выручка', $current['revenue'], $this->change($current['revenue'], $comparison['revenue']), 'money'),
            $this->kpi('orders', 'Заказы', $current['orders'], $this->change($current['orders'], $comparison['orders']), 'number'),
            $this->kpi('sales', 'Продажи', $current['sales'], $this->change($current['sales'], $comparison['sales']), 'number'),
            $this->kpi('buyout', 'Выкуп', $this->round($buyout), $buyout !== null && $comparisonBuyout !== null ? round($buyout - $comparisonBuyout, 1) : null, 'percent', 'pp'),
        ];
    }

    /**
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $current
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $comparison
     * @return list<array<string, mixed>>
     */
    private function salesKpis(array $current, array $comparison): array
    {
        $buyout = $this->formulas->buyoutRate($current['sales'], $current['orders']);
        $comparisonBuyout = $this->formulas->buyoutRate($comparison['sales'], $comparison['orders']);
        $returns = $this->formulas->returnsRate($current['returns'], $current['sales']);
        $comparisonReturns = $this->formulas->returnsRate($comparison['returns'], $comparison['sales']);
        $notBought = $this->formulas->notBought($current['orders'], $current['sales']);
        $comparisonNotBought = $this->formulas->notBought($comparison['orders'], $comparison['sales']);

        return [
            $this->kpi('orders', 'Заказы', $current['orders'], $this->change($current['orders'], $comparison['orders']), 'number'),
            $this->kpi('sales', 'Продажи', $current['sales'], $this->change($current['sales'], $comparison['sales']), 'number'),
            $this->kpi('not-bought', 'Не выкуплено', $notBought, $this->change($notBought, $comparisonNotBought), 'number'),
            $this->kpi('buyout', 'Выкуп', $this->round($buyout), $buyout !== null && $comparisonBuyout !== null ? round($buyout - $comparisonBuyout, 1) : null, 'percent', 'pp'),
            $this->kpi('returns', 'Возвраты', $this->round($returns), $returns !== null && $comparisonReturns !== null ? round($returns - $comparisonReturns, 1) : null, 'percent', 'pp'),
        ];
    }

    /** @return array<string, mixed> */
    private function kpi(string $key, string $label, int|float|null $value, ?float $change, string $format, string $changeFormat = 'percent'): array
    {
        return compact('key', 'label', 'value', 'change', 'format', 'changeFormat');
    }

    /**
     * @param  Collection<int, ProductDailyMetric>  $daily
     * @return array{granularity: string, revenue: list<array<string, mixed>>, sales: list<array<string, mixed>>}
     */
    private function series(Collection $daily, AnalyticsPeriod $period): array
    {
        $revenue = [];
        $sales = [];

        foreach ($this->buckets->forPeriod($period) as $bucket) {
            $current = $this->totals($daily, $bucket['start'], $bucket['end']);
            $previous = $this->totals($daily, $bucket['comparisonStart'], $bucket['comparisonEnd']);
            $revenue[] = [
                'label' => $bucket['label'],
                'current' => $current['revenue'],
                'comparison' => $previous['revenue'],
            ];
            $sales[] = [
                'label' => $bucket['label'],
                'orders' => $current['orders'],
                'sales' => $current['sales'],
                'buyout' => $this->round($this->formulas->buyoutRate($current['sales'], $current['orders'])),
                'returnsRate' => $this->round($this->formulas->returnsRate($current['returns'], $current['sales'])),
            ];
        }

        return [
            'granularity' => $period->granularity,
            'revenue' => $revenue,
            'sales' => $sales,
        ];
    }

    /**
     * @param  Collection<int, ProductDailyMetric>  $daily
     * @return list<array<string, int|float|string|null>>
     */
    private function weekly(Collection $daily, AnalyticsPeriod $period): array
    {
        $ranges = $period->granularity === 'day'
            ? collect(range(0, (int) ceil($period->days() / 7) - 1))->map(function (int $week) use ($period): array {
                $start = $period->start->addDays($week * 7);

                return [
                    'start' => $start,
                    'end' => $start->addDays(6)->min($period->end),
                    'label' => ($week + 1).' неделя',
                ];
            })->all()
            : array_map(fn (array $bucket): array => [
                'start' => $bucket['start'],
                'end' => $bucket['end'],
                'label' => $bucket['label'],
            ], $this->buckets->forPeriod($period));

        return array_values(array_map(function (array $range) use ($daily): array {
            $totals = $this->totals($daily, $range['start'], $range['end']);

            return [
                'label' => $range['label'],
                'orders' => $totals['orders'],
                'sales' => $totals['sales'],
                'buyout' => $this->round($this->formulas->buyoutRate($totals['sales'], $totals['orders'])),
                'returnsRate' => $this->round($this->formulas->returnsRate($totals['returns'], $totals['sales'])),
            ];
        }, $ranges));
    }

    /** @return list<array{label: string, actual: int|null, forecast: int|null}> */
    private function stockSeries(SellerAccount $account, Product $product, AnalyticsPeriod $period, int $stock, float $averageDailySales): array
    {
        $history = StockSnapshot::query()
            ->where('seller_account_id', $account->id)
            ->where('product_id', $product->id)
            ->whereBetween('snapshot_date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->selectRaw('snapshot_date, SUM(quantity) AS quantity')
            ->get();
        $series = $history->map(fn (object $point): array => [
            'label' => CarbonImmutable::parse($point->snapshot_date)->format('d.m'),
            'actual' => (int) $point->quantity,
            'forecast' => null,
        ])->all();
        $forecastDays = min(14, max(1, (int) ceil($stock / max($averageDailySales, 0.01)) + 2));
        $endLabel = $period->end->format('d.m');
        $lastIndex = array_key_last($series);

        if ($lastIndex !== null && $series[$lastIndex]['label'] === $endLabel) {
            $series[$lastIndex]['forecast'] = $stock;
        } else {
            $series[] = [
                'label' => $endLabel,
                'actual' => $stock,
                'forecast' => $stock,
            ];
        }

        for ($day = 1; $day <= $forecastDays; $day++) {
            $series[] = [
                'label' => $period->end->addDays($day)->format('d.m'),
                'actual' => null,
                'forecast' => max(0, (int) round($stock - ($averageDailySales * $day))),
            ];
        }

        return array_values($series);
    }

    /** @return list<array<string, int|float|string|null>> */
    private function warehouses(SellerAccount $account, Product $product, int $stock, float $averageDailySales): array
    {
        $latestSnapshot = StockSnapshot::query()
            ->where('seller_account_id', $account->id)
            ->where('product_id', $product->id)
            ->max('snapshot_at');

        if ($latestSnapshot === null) {
            return [];
        }

        return array_values(StockSnapshot::query()
            ->with('warehouse:id,name')
            ->where('seller_account_id', $account->id)
            ->where('product_id', $product->id)
            ->where('snapshot_at', $latestSnapshot)
            ->groupBy('warehouse_id')
            ->selectRaw('warehouse_id, SUM(quantity) AS quantity')
            ->orderByRaw('SUM(quantity) DESC')
            ->get()
            ->map(function (StockSnapshot $snapshot) use ($stock, $averageDailySales): array {
                $coverage = $averageDailySales > 0 ? $snapshot->quantity / $averageDailySales : null;

                return [
                    'name' => $snapshot->warehouse->name,
                    'quantity' => $snapshot->quantity,
                    'share' => $stock > 0 ? round(($snapshot->quantity / $stock) * 100) : 0,
                    'coverage' => $coverage === null ? null : round($coverage, 1),
                    'urgency' => $coverage === null || $coverage > 3 ? 'Нормальная' : ($coverage <= 1 ? 'Критичная' : 'Высокая'),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $current
     * @param  array{revenue: int, orders: int, sales: int, returns: int}  $comparison
     */
    private function salesInsight(array $current, array $comparison): string
    {
        $buyout = $this->formulas->buyoutRate($current['sales'], $current['orders']);
        $previous = $this->formulas->buyoutRate($comparison['sales'], $comparison['orders']);
        $change = $buyout !== null && $previous !== null ? round($buyout - $previous, 1) : null;

        return $change === null
            ? 'Недостаточно данных для сравнения выкупа'
            : 'Выкуп изменился на '.($change >= 0 ? '+' : '').number_format($change, 1, ',', ' ').' п.п., разница между заказами и продажами '.($current['orders'] - $current['sales']);
    }

    private function translatedDate(CarbonImmutable $date): string
    {
        $localized = $date->locale('ru');

        if ($localized instanceof CarbonImmutable) {
            return $localized->translatedFormat('j F');
        }

        return $date->format('j F');
    }

    private function change(int $current, int $comparison): ?float
    {
        return $comparison === 0 ? null : round((($current - $comparison) / $comparison) * 100, 1);
    }

    private function round(?float $value): ?float
    {
        return $value === null ? null : round($value, 1);
    }
}
