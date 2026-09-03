<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Services\AnalyticsTimeBucketBuilder;
use App\Modules\Analytics\Services\MetricFormulaService;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

final readonly class SalesAnalyticsQuery
{
    public function __construct(
        private AnalyticsContextQuery $contextQuery,
        private ProductAnalyticsQuery $products,
        private MetricFormulaService $formulas,
        private AnalyticsTimeBucketBuilder $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        ['account' => $account, 'period' => $period] = $this->contextQuery->resolveForUser($user);

        if (! $account instanceof SellerAccount) {
            return ['state' => 'empty'];
        }

        $daily = AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('metric_date', [$period->comparisonStart->toDateString(), $period->end->toDateString()])
            ->orderBy('metric_date')
            ->get();
        $current = $this->totals($daily, $period, false);
        $comparison = $this->totals($daily, $period, true);
        $buyout = $this->formulas->buyoutRate($current['sales'], $current['orders']);
        $comparisonBuyout = $this->formulas->buyoutRate($comparison['sales'], $comparison['orders']);
        $returnsRate = $this->formulas->returnsRate($current['returns'], $current['sales']);
        $comparisonReturnsRate = $this->formulas->returnsRate($comparison['returns'], $comparison['sales']);
        $productBase = $this->products->base($account, $period);
        $positiveContributionCount = (clone $productBase)
            ->whereRaw('COALESCE(current_metrics.revenue_kopecks, 0) > COALESCE(comparison_metrics.revenue_kopecks, 0)')
            ->count('products.id');
        $declineCount = (clone $productBase)
            ->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0')
            ->count('products.id');
        $contribution = $this->topProducts(
            clone $productBase,
            'ABS(COALESCE(current_metrics.revenue_kopecks, 0) - COALESCE(comparison_metrics.revenue_kopecks, 0)) DESC',
        );
        $gapRows = $this->topProducts(
            clone $productBase,
            '(COALESCE(current_metrics.orders, 0) - COALESCE(current_metrics.sales, 0)) DESC',
        );
        $qualityRows = $this->topProducts(
            clone $productBase,
            '(COALESCE('.ProductAnalyticsQuery::RETURNS_EXPRESSION.', 0) + (100 - COALESCE('.ProductAnalyticsQuery::BUYOUT_EXPRESSION.', 100))) DESC',
        );
        $revenueChange = $this->change($current['revenue'], $comparison['revenue']);
        $gap = $this->formulas->notBought($current['orders'], $current['sales']);
        $comparisonGap = $this->formulas->notBought($comparison['orders'], $comparison['sales']);
        $series = $this->series($daily, $period);
        $salesChange = $this->change($current['sales'], $comparison['sales']);
        $ordersChange = $this->change($current['orders'], $comparison['orders']);
        $gapChange = $this->change($gap, $comparisonGap);
        $ordersSalesInsight = $salesChange === null || $ordersChange === null
            ? 'Недостаточно данных для сравнения заказов и продаж'
            : 'Продажи растут '.($salesChange >= $ordersChange ? 'быстрее' : 'медленнее')
                .' заказов — разница изменилась на '.$this->signed($gapChange);
        $buyoutReturnsInsight = $buyout === null || $comparisonBuyout === null
            ? 'Недостаточно данных для сравнения выкупа'
            : 'Выкуп '.($buyout >= $comparisonBuyout ? 'улучшился' : 'снизился')
                .' на '.$this->signedPoints($buyout, $comparisonBuyout)
                .', возвраты изменились на '.$this->signedPoints($returnsRate, $comparisonReturnsRate);

        return [
            'state' => $current['orders'] === 0 && $current['sales'] === 0 ? 'empty' : 'ready',
            'insights' => [
                'dynamics' => 'Выручка изменилась на '.$this->signed($revenueChange).' — основной вклад дали '.$positiveContributionCount.' '.$this->productNoun($positiveContributionCount),
                'ordersSales' => $ordersSalesInsight,
                'buyoutReturns' => $buyoutReturnsInsight,
                'products' => $positiveContributionCount.' '.$this->productNoun($positiveContributionCount).' дали основной рост выручки',
            ],
            'kpis' => [
                $this->kpi('revenue', 'Выручка', $current['revenue'], $revenueChange, 'money'),
                $this->kpi('orders', 'Заказы', $current['orders'], $this->change($current['orders'], $comparison['orders']), 'number'),
                $this->kpi('sales', 'Продажи', $current['sales'], $this->change($current['sales'], $comparison['sales']), 'number'),
                $this->kpi('gap', 'Разница', $gap, $this->change($gap, $comparisonGap), 'number'),
                $this->kpi('buyout', 'Выкуп', $this->round($buyout), $buyout !== null && $comparisonBuyout !== null ? round($buyout - $comparisonBuyout, 1) : null, 'percent', 'pp'),
                $this->kpi('returns-rate', 'Возвраты', $this->round($returnsRate), $returnsRate !== null && $comparisonReturnsRate !== null ? round($returnsRate - $comparisonReturnsRate, 1) : null, 'percent', 'pp', true),
                $this->kpi('returned', 'Возвращено', $current['returns'], $this->change($current['returns'], $comparison['returns']), 'number', inverse: true),
                $this->kpi('average-sale', 'Средняя выручка с продажи', $current['sales'] === 0 ? null : (int) round($current['revenue'] / $current['sales']), $comparison['sales'] === 0 ? null : $this->change((int) round($current['revenue'] / max(1, $current['sales'])), (int) round($comparison['revenue'] / $comparison['sales'])), 'money'),
                $this->kpi('decline-count', 'Товаров со снижением', $declineCount, null, 'number'),
            ],
            'series' => $series,
            'products' => [
                'contribution' => $contribution->map(function (object $row): array {
                    $data = get_object_vars($row);

                    return [
                        ...$this->products->toRow($row),
                        'comparisonRevenueKopecks' => (int) $data['comparison_revenue_kopecks'],
                        'deltaKopecks' => (int) $data['revenue_kopecks'] - (int) $data['comparison_revenue_kopecks'],
                    ];
                })->values()->all(),
                'gap' => $gapRows->map(function (object $row): array {
                    $data = get_object_vars($row);

                    return [
                        ...$this->products->toRow($row),
                        'gap' => max(0, (int) $data['orders'] - (int) $data['sales']),
                    ];
                })->values()->all(),
                'quality' => $qualityRows->map(fn (object $row): array => $this->qualityRow($row))->values()->all(),
            ],
        ];
    }

    /**
     * @param  Collection<int, AccountDailyMetric>  $daily
     * @return array{revenue: int, orders: int, sales: int, returns: int}
     */
    private function totals(Collection $daily, AnalyticsPeriod $period, bool $comparison): array
    {
        $start = $comparison ? $period->comparisonStart : $period->start;
        $end = $comparison ? $period->comparisonEnd : $period->end;
        $range = $daily->filter(fn (AccountDailyMetric $metric): bool => $metric->metric_date->toDateString() >= $start->toDateString()
            && $metric->metric_date->toDateString() <= $end->toDateString());

        return [
            'revenue' => (int) $range->sum('revenue_kopecks'),
            'orders' => (int) $range->sum('orders'),
            'sales' => (int) $range->sum('sales'),
            'returns' => (int) $range->sum('returns'),
        ];
    }

    /**
     * @param  Collection<int, AccountDailyMetric>  $daily
     * @return array<string, mixed>
     */
    private function series(Collection $daily, AnalyticsPeriod $period): array
    {
        $revenue = [];
        $ordersSales = [];
        $quality = [];

        foreach ($this->buckets->forPeriod($period) as $bucket) {
            $current = $this->rangeTotals($daily, $bucket['start'], $bucket['end']);
            $previous = $this->rangeTotals($daily, $bucket['comparisonStart'], $bucket['comparisonEnd']);
            $revenue[] = [
                'label' => $bucket['label'],
                'current' => $current['revenue'],
                'comparison' => $previous['revenue'],
            ];
            $ordersSales[] = [
                'label' => $bucket['label'],
                'orders' => $current['orders'],
                'sales' => $current['sales'],
            ];
            $quality[] = [
                'label' => $bucket['label'],
                'buyout' => $this->round($this->formulas->buyoutRate($current['sales'], $current['orders'])),
                'comparisonBuyout' => $this->round($this->formulas->buyoutRate($previous['sales'], $previous['orders'])),
                'returnsRate' => $this->round($this->formulas->returnsRate($current['returns'], $current['sales'])),
                'comparisonReturnsRate' => $this->round($this->formulas->returnsRate($previous['returns'], $previous['sales'])),
            ];
        }

        return [
            'granularity' => $period->granularity,
            'revenuePoints' => $revenue,
            'ordersSalesPoints' => $ordersSales,
            'qualityPoints' => $quality,
            'revenueDay' => $period->granularity === 'day' ? $revenue : [],
            'revenueWeek' => $period->granularity === 'day'
                ? $this->weekly($revenue, ['current', 'comparison'])
                : ($period->granularity === 'week' ? $revenue : []),
            'ordersSalesDay' => $period->granularity === 'day' ? $ordersSales : [],
            'ordersSalesWeek' => $period->granularity === 'day'
                ? $this->weekly($ordersSales, ['orders', 'sales'])
                : ($period->granularity === 'week' ? $ordersSales : []),
            'qualityDay' => $period->granularity === 'day' ? $quality : [],
            'qualityWeek' => $period->granularity === 'day'
                ? $this->qualityWeekly($daily, $period)
                : ($period->granularity === 'week' ? $quality : []),
        ];
    }

    /**
     * @param  Collection<int, AccountDailyMetric>  $daily
     * @return list<array{label: string, buyout: float|null, comparisonBuyout: float|null, returnsRate: float|null, comparisonReturnsRate: float|null}>
     */
    private function qualityWeekly(Collection $daily, AnalyticsPeriod $period): array
    {
        $points = [];

        for ($offset = 0, $week = 1; $offset < $period->days(); $offset += 7, $week++) {
            $length = min(7, $period->days() - $offset);
            $current = $this->rangeTotals($daily, $period->start->addDays($offset), $period->start->addDays($offset + $length - 1));
            $comparison = $this->rangeTotals($daily, $period->comparisonStart->addDays($offset), $period->comparisonStart->addDays($offset + $length - 1)->min($period->comparisonEnd));
            $points[] = [
                'label' => $week.' нед.',
                'buyout' => $this->round($this->formulas->buyoutRate($current['sales'], $current['orders'])),
                'comparisonBuyout' => $this->round($this->formulas->buyoutRate($comparison['sales'], $comparison['orders'])),
                'returnsRate' => $this->round($this->formulas->returnsRate($current['returns'], $current['sales'])),
                'comparisonReturnsRate' => $this->round($this->formulas->returnsRate($comparison['returns'], $comparison['sales'])),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, AccountDailyMetric>  $daily
     * @return array{revenue: int, orders: int, sales: int, returns: int}
     */
    private function rangeTotals(Collection $daily, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $range = $daily->filter(fn (AccountDailyMetric $metric): bool => $metric->metric_date->toDateString() >= $start->toDateString()
            && $metric->metric_date->toDateString() <= $end->toDateString());

        return [
            'revenue' => (int) $range->sum('revenue_kopecks'),
            'orders' => (int) $range->sum('orders'),
            'sales' => (int) $range->sum('sales'),
            'returns' => (int) $range->sum('returns'),
        ];
    }

    /** @return array<string, mixed> */
    private function qualityRow(object $row): array
    {
        $data = get_object_vars($row);
        $comparisonBuyout = $this->formulas->buyoutRate((int) $data['comparison_sales'], (int) $data['comparison_orders']);
        $comparisonReturns = $this->formulas->returnsRate((int) $data['comparison_returns'], (int) $data['comparison_sales']);

        return [
            ...$this->products->toRow($row),
            'comparisonRevenueKopecks' => (int) $data['comparison_revenue_kopecks'],
            'deltaKopecks' => (int) $data['revenue_kopecks'] - (int) $data['comparison_revenue_kopecks'],
            'buyoutChange' => $data['buyout'] !== null && $comparisonBuyout !== null ? round((float) $data['buyout'] - $comparisonBuyout, 1) : null,
            'returnsChange' => $data['returns_rate'] !== null && $comparisonReturns !== null ? round((float) $data['returns_rate'] - $comparisonReturns, 1) : null,
        ];
    }

    /**
     * @param  list<array<string, int|string|null>>  $points
     * @param  list<string>  $keys
     * @return list<array<string, int|string|null>>
     */
    private function weekly(array $points, array $keys): array
    {
        return array_values(collect($points)->chunk(7)->values()->map(function (Collection $week, int $index) use ($keys): array {
            $point = ['label' => ($index + 1).' нед.'];

            foreach ($keys as $key) {
                $point[$key] = (int) $week->sum($key);
            }

            return $point;
        })->all());
    }

    /** @return array<string, mixed> */
    private function kpi(string $key, string $label, int|float|null $value, ?float $change, string $format, string $changeFormat = 'percent', bool $inverse = false): array
    {
        return compact('key', 'label', 'value', 'change', 'format', 'changeFormat', 'inverse');
    }

    private function change(int $current, int $comparison): ?float
    {
        return $comparison === 0 ? null : round((($current - $comparison) / $comparison) * 100, 1);
    }

    private function signed(?float $value): string
    {
        return $value === null ? '—' : ($value >= 0 ? '+' : '').number_format($value, 1, ',', ' ').'%';
    }

    private function signedPoints(?float $current, ?float $comparison): string
    {
        if ($current === null || $comparison === null) {
            return '—';
        }

        $change = round($current - $comparison, 1);

        return ($change >= 0 ? '+' : '').number_format($change, 1, ',', ' ').' п.п.';
    }

    private function round(?float $value): ?float
    {
        return $value === null ? null : round($value, 1);
    }

    /**
     * @param  literal-string  $order
     * @return Collection<int, \stdClass>
     */
    private function topProducts(Builder $query, string $order): Collection
    {
        return $query->orderByRaw($order)->orderBy('products.id')->limit(5)->get();
    }

    private function productNoun(int $count): string
    {
        $mod100 = $count % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'товаров';
        }

        return match ($count % 10) {
            1 => 'товар',
            2, 3, 4 => 'товара',
            default => 'товаров',
        };
    }
}
