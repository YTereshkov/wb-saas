<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\Analytics\Services\AnalyticsTimeBucketBuilder;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;

final readonly class OverviewQuery
{
    public function __construct(
        private AnalyticsContextQuery $contextQuery,
        private ProductAnalyticsQuery $products,
        private AnalyticsTimeBucketBuilder $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        ['account' => $account, 'period' => $period] = $this->contextQuery->resolveForUser($user);

        if (! $account instanceof SellerAccount) {
            return ['state' => 'empty'];
        }

        $current = $this->totals($account, $period->start, $period->end);
        $comparison = $this->totals($account, $period->comparisonStart, $period->comparisonEnd);
        $buyout = $this->rate($current['sales'], $current['orders']);
        $comparisonBuyout = $this->rate($comparison['sales'], $comparison['orders']);
        $attentionCount = ProductSignal::query()
            ->where('seller_account_id', $account->id)
            ->count();
        $signals = ProductSignal::query()
            ->with('product:id,title,vendor_code,image_url')
            ->where('seller_account_id', $account->id)
            ->orderBy('priority')
            ->orderByDesc('calculated_at')
            ->limit(3)
            ->get()
            ->map(fn (ProductSignal $signal): array => [
                'productId' => $signal->product_id,
                'productTitle' => $signal->product->title,
                'imageUrl' => $signal->product->image_url,
                'type' => $signal->type,
                'severity' => $signal->severity,
                'fact' => $signal->fact,
                'risk' => $signal->risk,
                'recommendation' => $signal->recommendation,
                'href' => $this->signalHref($signal),
            ])
            ->values()
            ->all();

        $base = $this->products->base($account, $period);
        $leaders = (clone $base)->orderByDesc('revenue_kopecks')->limit(5)->get();
        $decline = (clone $base)
            ->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0')
            ->orderBy('dynamics')
            ->limit(5)
            ->get();
        $risk = (clone $base)
            ->where('current_metrics.sales', '>', 0)
            ->whereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' <= 7', [$period->days()])
            ->orderBy('coverage')
            ->limit(5)
            ->get();

        $selectedSeries = $this->series($account, $period);

        return [
            'state' => $current['orders'] === 0 && $current['sales'] === 0 ? 'empty' : 'ready',
            'insight' => $attentionCount > 0
                ? "Выручка изменилась на {$this->signed($this->change($current['revenueKopecks'], $comparison['revenueKopecks']))}, но {$attentionCount} {$this->productWord($attentionCount)} требуют внимания"
                : "Выручка изменилась на {$this->signed($this->change($current['revenueKopecks'], $comparison['revenueKopecks']))}, критичных сигналов нет",
            'kpis' => [
                $this->kpi('revenue', 'Выручка', $current['revenueKopecks'], $this->change($current['revenueKopecks'], $comparison['revenueKopecks']), 'money'),
                $this->kpi('orders', 'Заказы', $current['orders'], $this->change($current['orders'], $comparison['orders']), 'number'),
                $this->kpi('sales', 'Продажи', $current['sales'], $this->change($current['sales'], $comparison['sales']), 'number'),
                $this->kpi('buyout', 'Выкуп', $buyout, $buyout !== null && $comparisonBuyout !== null ? $buyout - $comparisonBuyout : null, 'percent', 'pp'),
            ],
            'series' => [
                'granularity' => $period->granularity,
                'points' => $selectedSeries,
                'day' => $period->granularity === 'day' ? $selectedSeries : [],
                'week' => $period->granularity === 'day'
                    ? $this->weeklySeries($selectedSeries)
                    : ($period->granularity === 'week' ? $selectedSeries : []),
            ],
            'attention' => [
                'count' => $attentionCount,
                'signals' => $signals,
            ],
            'products' => [
                'leaders' => $leaders->map(fn (object $row) => $this->products->toRow($row))->all(),
                'decline' => $decline->map(fn (object $row) => $this->products->toRow($row))->all(),
                'risk' => $risk->map(fn (object $row) => $this->products->toRow($row))->all(),
            ],
        ];
    }

    /** @return array{revenueKopecks: int, orders: int, sales: int, returns: int} */
    private function totals(SellerAccount $account, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('metric_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(revenue_kopecks), 0) AS revenue, COALESCE(SUM(orders), 0) AS orders, COALESCE(SUM(sales), 0) AS sales, COALESCE(SUM(returns), 0) AS returns')
            ->firstOrFail();

        return [
            'revenueKopecks' => (int) $row->getAttribute('revenue'),
            'orders' => (int) $row->orders,
            'sales' => (int) $row->sales,
            'returns' => (int) $row->returns,
        ];
    }

    /** @return array<string, mixed> */
    private function kpi(string $key, string $label, int|float|null $value, ?float $change, string $format, string $changeFormat = 'percent'): array
    {
        return compact('key', 'label', 'value', 'change', 'format', 'changeFormat');
    }

    /** @return list<array{label: string, current: int, comparison: int|null}> */
    private function series(SellerAccount $account, AnalyticsPeriod $period): array
    {
        $metrics = AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->where(function ($query) use ($period) {
                $query->whereBetween('metric_date', [$period->start->toDateString(), $period->end->toDateString()])
                    ->orWhereBetween('metric_date', [$period->comparisonStart->toDateString(), $period->comparisonEnd->toDateString()]);
            })
            ->get(['metric_date', 'revenue_kopecks'])
            ->keyBy(fn (AccountDailyMetric $metric): string => $metric->metric_date->toDateString());

        return array_map(function (array $bucket) use ($metrics): array {
            $current = $metrics->filter(fn (AccountDailyMetric $metric): bool => $this->dateIsBetween(
                $metric->metric_date->toDateString(),
                $bucket['start'],
                $bucket['end'],
            ));
            if ($bucket['comparisonStart']->gt($bucket['comparisonEnd'])) {
                $comparison = null;
            } else {
                $comparison = (int) $metrics
                    ->filter(fn (AccountDailyMetric $metric): bool => $this->dateIsBetween(
                        $metric->metric_date->toDateString(),
                        $bucket['comparisonStart'],
                        $bucket['comparisonEnd'],
                    ))
                    ->sum('revenue_kopecks');
            }

            return [
                'label' => $bucket['label'],
                'current' => (int) $current->sum('revenue_kopecks'),
                'comparison' => $comparison,
            ];
        }, $this->buckets->forPeriod($period));
    }

    private function dateIsBetween(string $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $date >= $start->toDateString() && $date <= $end->toDateString();
    }

    /**
     * @param  list<array{label: string, current: int, comparison: int|null}>  $daily
     * @return list<array{label: string, current: int, comparison: int}>
     */
    private function weeklySeries(array $daily): array
    {
        $result = [];

        foreach (array_chunk($daily, 7) as $index => $week) {
            $result[] = [
                'label' => ($index + 1).' нед.',
                'current' => array_sum(array_column($week, 'current')),
                'comparison' => array_sum(array_column($week, 'comparison')),
            ];
        }

        return $result;
    }

    private function change(int|float $current, int|float $comparison): ?float
    {
        return $comparison == 0 ? null : round((($current - $comparison) / $comparison) * 100, 1);
    }

    private function rate(int $part, int $total): ?float
    {
        return $total === 0 ? null : round(($part / $total) * 100, 1);
    }

    private function signed(?float $value): string
    {
        return $value === null ? '—' : ($value >= 0 ? '+' : '').number_format($value, 1, ',', ' ').'%';
    }

    private function productWord(int $count): string
    {
        $lastTwo = $count % 100;
        $last = $count % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return 'товаров';
        }

        return match ($last) {
            1 => 'товар',
            2, 3, 4 => 'товара',
            default => 'товаров',
        };
    }

    private function signalHref(ProductSignal $signal): string
    {
        $path = str_starts_with($signal->type, 'stock_') || $signal->type === 'out_of_stock'
            ? '/products/low-stock'
            : (str_contains($signal->type, 'decline') ? '/products/decline' : '/products/attention');

        return $path.'?focus='.$signal->product_id;
    }
}
