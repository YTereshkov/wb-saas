<?php

namespace App\Modules\Analytics\Actions;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\Analytics\Models\ProductSignal;
use App\Modules\Analytics\Services\MetricFormulaService;
use App\Modules\Analytics\Services\PrimarySignalSelector;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RebuildAnalytics
{
    public function __construct(
        private MetricFormulaService $formulas,
        private PrimarySignalSelector $signalSelector,
    ) {}

    public function handle(SellerAccount $sellerAccount): int
    {
        /** @var array<string, array{revenue_kopecks: int, orders: int, sales: int, returns: int}> $accountDays */
        $accountDays = [];
        $productDays = [];

        $this->collectFacts(
            $sellerAccount->orders()->getQuery()->select(['id', 'product_id', 'ordered_at', 'quantity']),
            'ordered_at',
            'orders',
            $sellerAccount->timezone,
            $accountDays,
            $productDays,
            false,
        );
        $this->collectFacts(
            $sellerAccount->sales()->getQuery()->select(['id', 'product_id', 'sold_at', 'quantity', 'amount_kopecks']),
            'sold_at',
            'sales',
            $sellerAccount->timezone,
            $accountDays,
            $productDays,
            false,
        );
        $this->collectFacts(
            $sellerAccount->returnFacts()->getQuery()->select(['id', 'product_id', 'returned_at', 'quantity']),
            'returned_at',
            'returns',
            $sellerAccount->timezone,
            $accountDays,
            $productDays,
            false,
        );

        $dates = array_keys($accountDays);
        sort($dates);

        return DB::transaction(function () use (
            $sellerAccount,
            $accountDays,
            $dates,
        ): int {
            $lockedAccount = SellerAccount::query()->lockForUpdate()->findOrFail($sellerAccount->id);
            $dataRevision = $lockedAccount->data_revision + 1;

            AccountDailyMetric::query()->where('seller_account_id', $sellerAccount->id)->delete();
            ProductDailyMetric::query()->where('seller_account_id', $sellerAccount->id)->delete();
            ProductSignal::query()->where('seller_account_id', $sellerAccount->id)->delete();

            if ($dates !== []) {
                $this->storeAccountDailyMetrics(
                    $sellerAccount,
                    $accountDays,
                    $dates[0],
                    $dates[array_key_last($dates)],
                );
            }
            $this->storeProductDailyMetrics($sellerAccount);

            $this->storeSignals($sellerAccount, $dataRevision);
            $lockedAccount->update(['data_revision' => $dataRevision]);
            $sellerAccount->setAttribute('data_revision', $dataRevision);

            return $dataRevision;
        });
    }

    /**
     * @template TFact of Model
     *
     * @param  Builder<TFact>  $query
     * @param  array<string, array{revenue_kopecks: int, orders: int, sales: int, returns: int}>  $accountDays
     * @param  array<int, array<string, array{revenue_kopecks: int, orders: int, sales: int, returns: int}>>  $productDays
     */
    private function collectFacts(
        Builder $query,
        string $dateField,
        string $metric,
        string $timezone,
        array &$accountDays,
        array &$productDays,
        bool $collectProducts = true,
    ): void {
        $query->orderBy('id')->chunkById(500, function (EloquentCollection $facts) use (
            $dateField,
            $metric,
            $timezone,
            &$accountDays,
            &$productDays,
            $collectProducts,
        ): void {
            foreach ($facts as $fact) {
                $date = CarbonImmutable::parse($fact->getAttribute($dateField))
                    ->setTimezone($timezone)
                    ->toDateString();
                $accountDays[$date] ??= $this->emptyMetric();
                $productId = (int) $fact->getAttribute('product_id');
                $quantity = (int) $fact->getAttribute('quantity');

                $accountDays[$date][$metric] += $quantity;
                if ($collectProducts) {
                    $productDays[$productId][$date] ??= $this->emptyMetric();
                    $productDays[$productId][$date][$metric] += $quantity;
                }

                if ($metric === 'sales') {
                    $amount = (int) $fact->getAttribute('amount_kopecks');
                    $accountDays[$date]['revenue_kopecks'] += $amount;
                    if ($collectProducts) {
                        $productDays[$productId][$date]['revenue_kopecks'] += $amount;
                    }
                }
            }
        });
    }

    /**
     * @param  array<string, array{revenue_kopecks: int, orders: int, sales: int, returns: int}>  $accountDays
     */
    private function storeAccountDailyMetrics(
        SellerAccount $sellerAccount,
        array $accountDays,
        string $firstDate,
        string $lastDate,
    ): void {
        $now = now();
        $accountRows = [];
        $date = CarbonImmutable::parse($firstDate, $sellerAccount->timezone);
        $end = CarbonImmutable::parse($lastDate, $sellerAccount->timezone);

        while ($date->lessThanOrEqualTo($end)) {
            $dateString = $date->toDateString();
            $accountRows[] = [
                'seller_account_id' => $sellerAccount->id,
                'metric_date' => $dateString,
                ...($accountDays[$dateString] ?? $this->emptyMetric()),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $date = $date->addDay();
        }

        AccountDailyMetric::query()->insert($accountRows);
    }

    private function storeProductDailyMetrics(SellerAccount $sellerAccount): void
    {
        $sellerAccount->products()->select('id')->orderBy('id')->chunkById(100, function (EloquentCollection $products) use ($sellerAccount): void {
            $productIds = $products->modelKeys();
            $accountDays = [];
            $productDays = [];
            $this->collectFacts(
                $sellerAccount->orders()->getQuery()->whereIn('product_id', $productIds)->select(['id', 'product_id', 'ordered_at', 'quantity']),
                'ordered_at',
                'orders',
                $sellerAccount->timezone,
                $accountDays,
                $productDays,
            );
            $this->collectFacts(
                $sellerAccount->sales()->getQuery()->whereIn('product_id', $productIds)->select(['id', 'product_id', 'sold_at', 'quantity', 'amount_kopecks']),
                'sold_at',
                'sales',
                $sellerAccount->timezone,
                $accountDays,
                $productDays,
            );
            $this->collectFacts(
                $sellerAccount->returnFacts()->getQuery()->whereIn('product_id', $productIds)->select(['id', 'product_id', 'returned_at', 'quantity']),
                'returned_at',
                'returns',
                $sellerAccount->timezone,
                $accountDays,
                $productDays,
            );

            $now = now();
            $rows = [];
            foreach ($productDays as $productId => $days) {
                foreach ($days as $date => $metric) {
                    $rows[] = [
                        'seller_account_id' => $sellerAccount->id,
                        'product_id' => $productId,
                        'metric_date' => $date,
                        ...$metric,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                ProductDailyMetric::query()->insert($chunk);
            }
        });
    }

    private function storeSignals(SellerAccount $sellerAccount, int $dataRevision): void
    {
        $latestSnapshotAt = $sellerAccount->stockSnapshots()->max('snapshot_at');

        if ($latestSnapshotAt === null) {
            return;
        }

        $snapshotAt = CarbonImmutable::parse($latestSnapshotAt);
        $period = $this->signalPeriod($snapshotAt, $sellerAccount->timezone);
        $stockByProduct = StockSnapshot::query()
            ->where('seller_account_id', $sellerAccount->id)
            ->where('snapshot_at', $latestSnapshotAt)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) AS total')
            ->pluck('total', 'product_id');
        $salesByProduct = ProductDailyMetric::query()
            ->where('seller_account_id', $sellerAccount->id)
            ->whereBetween('metric_date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(sales) AS total')
            ->pluck('total', 'product_id');
        $currentMetrics = $this->productMetricTotals(
            $sellerAccount,
            $period->start->toDateString(),
            $period->end->toDateString(),
        );
        $comparisonMetrics = $this->productMetricTotals(
            $sellerAccount,
            $period->comparisonStart->toDateString(),
            $period->comparisonEnd->toDateString(),
        );
        $now = now();
        $rows = [];

        foreach ($sellerAccount->products()->pluck('id') as $productId) {
            $stock = (int) ($stockByProduct[$productId] ?? 0);
            $sales = (int) ($salesByProduct[$productId] ?? 0);
            $averageDailySales = $this->formulas->averageDailySales($sales, $period->days());
            $signal = $this->signalSelector->forStock($stock, $averageDailySales);

            if ($signal === null) {
                $current = $this->productMetric($currentMetrics, $productId);
                $comparison = $this->productMetric($comparisonMetrics, $productId);
                $signal = $this->signalSelector->forPerformance(
                    currentRevenueKopecks: (int) ($current->revenue_kopecks ?? 0),
                    comparisonRevenueKopecks: (int) ($comparison->revenue_kopecks ?? 0),
                    returnsRate: $this->formulas->returnsRate(
                        (int) ($current->returns ?? 0),
                        (int) ($current->sales ?? 0),
                    ),
                    buyoutRate: $this->formulas->buyoutRate(
                        (int) ($current->sales ?? 0),
                        (int) ($current->orders ?? 0),
                    ),
                    comparisonBuyoutRate: $this->formulas->buyoutRate(
                        (int) ($comparison->sales ?? 0),
                        (int) ($comparison->orders ?? 0),
                    ),
                );
            }

            if ($signal === null) {
                continue;
            }

            $rows[] = [
                'seller_account_id' => $sellerAccount->id,
                'product_id' => $productId,
                'type' => $signal->type,
                'priority' => $signal->priority,
                'severity' => $signal->severity,
                'evidence' => json_encode($signal->evidence, JSON_THROW_ON_ERROR),
                'fact' => $signal->fact,
                'risk' => $signal->risk,
                'recommendation' => $signal->recommendation,
                'calculated_at' => $now,
                'data_revision' => $dataRevision,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            ProductSignal::query()->insert($rows);
        }
    }

    /** @return Collection<int, ProductDailyMetric> */
    private function productMetricTotals(SellerAccount $sellerAccount, string $start, string $end): Collection
    {
        return ProductDailyMetric::query()
            ->where('seller_account_id', $sellerAccount->id)
            ->whereBetween('metric_date', [$start, $end])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(revenue_kopecks) AS revenue_kopecks, SUM(orders) AS orders, SUM(sales) AS sales, SUM(returns) AS returns')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * @param  Collection<int, ProductDailyMetric>  $metrics
     */
    private function productMetric(Collection $metrics, int $productId): ?ProductDailyMetric
    {
        $metric = $metrics->get($productId);

        return $metric instanceof ProductDailyMetric ? $metric : null;
    }

    private function signalPeriod(CarbonImmutable $snapshotAt, string $timezone): AnalyticsPeriod
    {
        $localDate = $snapshotAt->setTimezone($timezone)->startOfDay();
        if ($localDate->isLastOfMonth()) {
            return AnalyticsPeriod::calendarMonth($localDate, $timezone);
        }

        $start = $localDate->startOfMonth();
        $comparisonEnd = $start->subDay();

        return new AnalyticsPeriod(
            start: $start,
            end: $localDate,
            comparisonStart: $comparisonEnd->subDays($start->diffInDays($localDate)),
            comparisonEnd: $comparisonEnd,
            timezone: $timezone,
        );
    }

    /** @return array{revenue_kopecks: int, orders: int, sales: int, returns: int} */
    private function emptyMetric(): array
    {
        return [
            'revenue_kopecks' => 0,
            'orders' => 0,
            'sales' => 0,
            'returns' => 0,
        ];
    }
}
