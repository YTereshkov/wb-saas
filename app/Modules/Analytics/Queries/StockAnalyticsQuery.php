<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\MetricFormulaService;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class StockAnalyticsQuery
{
    public const NO_SALES_DAYS = 30;

    public const EXCESS_COVERAGE_DAYS = 90;

    public const VIEWS = ['all', 'supply', 'out_of_stock', 'no_movement'];

    private const MAX_FORECAST_DAYS = 365;

    private const SORTS = ['title', 'stock', 'sales', 'coverage', 'supply', 'out_days'];

    public function __construct(
        private AnalyticsContextQuery $context,
        private AnalyticsDataCoverageQuery $coverage,
        private ProductAnalyticsQuery $products,
        private MetricFormulaService $formulas,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user, Request $request, string $view): array
    {
        abort_unless(in_array($view, self::VIEWS, true), 404);
        ['account' => $account, 'period' => $period] = $this->context->resolveForUser($user);

        if (! $account instanceof SellerAccount) {
            return ['state' => 'empty', 'view' => $view];
        }

        $stockCoverage = $this->coverage->forAccount($account)['resources']['stocks'];
        if ($stockCoverage['start'] === null
            || $stockCoverage['end'] === null
            || $period->end->toDateString() < $stockCoverage['start']
            || $period->start->toDateString() > $stockCoverage['end']) {
            return [
                'state' => 'unavailable',
                'view' => $view,
                'coverage' => $stockCoverage,
            ];
        }

        $search = trim((string) $this->scalarQuery($request, 'search', ''));
        $category = $this->positiveIntegerQuery($request, 'category');
        $warehouse = $this->positiveIntegerQuery($request, 'warehouse');
        $sortValue = (string) $this->scalarQuery($request, 'sort', $this->defaultSort($view));
        $sort = in_array($sortValue, self::SORTS, true) ? $sortValue : $this->defaultSort($view);
        $direction = $this->scalarQuery($request, 'direction', $this->defaultDirection($view)) === 'asc' ? 'asc' : 'desc';
        $perPageValue = (int) $this->scalarQuery($request, 'per_page', 25);
        $perPage = in_array($perPageValue, [25, 50, 100], true) ? $perPageValue : 25;
        $base = $this->base($account, $period, $warehouse);
        $summary = $this->summary($base, $period);
        $counts = array_intersect_key($summary, array_flip(['all', 'supply', 'outOfStock', 'noMovement']));
        $query = $this->applyView(clone $base, $view, $period);
        $this->applyFilters($query, $search, $category);
        $this->applySort($query, $sort, $direction, $period);
        $paginator = $query->paginate($perPage, page: max(1, $request->integer('page', 1)))->withQueryString();
        $rows = collect($paginator->items())->map(fn (object $row): array => $this->row($row, $period))->all();

        return [
            'state' => 'ready',
            'view' => $view,
            'counts' => $counts,
            'insight' => $this->insight($view, $summary),
            'notice' => $this->notice($view),
            'rows' => $rows,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => compact('search', 'category', 'warehouse', 'sort', 'direction'),
            'categories' => $account->categories()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])->all(),
            'warehouses' => $account->warehouses()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])->all(),
        ];
    }

    private function base(SellerAccount $account, AnalyticsPeriod $period, ?int $warehouse): Builder
    {
        $recentStart = $period->end->subDays(self::NO_SALES_DAYS - 1)->toDateString();
        $recentSales = DB::table('product_daily_metrics')
            ->where('seller_account_id', $account->id)
            ->whereBetween('metric_date', [$recentStart, $period->end->toDateString()])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(sales) AS recent_sales');
        $lastSale = DB::table('product_daily_metrics')
            ->where('seller_account_id', $account->id)
            ->where('metric_date', '<=', $period->end->toDateString())
            ->groupBy('product_id')
            ->selectRaw('product_id, MAX(CASE WHEN sales > 0 THEN metric_date ELSE NULL END) AS last_sale_date');
        $dailyStock = DB::table('stock_snapshots')
            ->where('seller_account_id', $account->id)
            ->whereBetween('snapshot_date', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($warehouse !== null, fn (Builder $query) => $query->where('warehouse_id', $warehouse))
            ->groupBy(['product_id', 'snapshot_date'])
            ->selectRaw('product_id, snapshot_date, SUM(quantity) AS daily_quantity');
        $outDays = DB::query()->fromSub($dailyStock, 'daily_stock')
            ->where('daily_quantity', '<=', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, COUNT(*) AS out_of_stock_days');

        return $this->products->base($account, $period, $warehouse)
            ->leftJoinSub($recentSales, 'recent_sales', 'recent_sales.product_id', '=', 'products.id')
            ->leftJoinSub($lastSale, 'last_sale', 'last_sale.product_id', '=', 'products.id')
            ->leftJoinSub($outDays, 'out_days', 'out_days.product_id', '=', 'products.id')
            ->addSelect([
                'last_sale.last_sale_date',
            ])
            ->selectRaw('COALESCE(recent_sales.recent_sales, 0) AS recent_sales')
            ->selectRaw('COALESCE(out_days.out_of_stock_days, 0) AS out_of_stock_days');
    }

    /** @return array{all: int, supply: int, outOfStock: int, noMovement: int, supplyUnits: int, missedSales: float, noMovementStock: int} */
    private function summary(Builder $base, AnalyticsPeriod $period): array
    {
        $row = DB::query()->fromSub($base, 'stock_rows')->selectRaw(
            'COUNT(*) AS all_count,
            COALESCE(SUM(CASE WHEN sales > 0 AND coverage <= 7 THEN 1 ELSE 0 END), 0) AS supply_count,
            COALESCE(SUM(CASE WHEN stock = 0 AND sales > 0 THEN 1 ELSE 0 END), 0) AS out_count,
            COALESCE(SUM(CASE WHEN recent_sales = 0 OR coverage > ? THEN 1 ELSE 0 END), 0) AS no_movement_count,
            COALESCE(SUM(CASE WHEN sales > 0 AND coverage <= 7 THEN CASE WHEN ((sales * 30.0 / ?) - stock) > 0 THEN CEIL((sales * 30.0 / ?) - stock) ELSE 0 END ELSE 0 END), 0) AS supply_units,
            COALESCE(SUM(CASE WHEN stock = 0 AND sales > 0 THEN sales * out_of_stock_days * 1.0 / ? ELSE 0 END), 0) AS missed_sales,
            COALESCE(SUM(CASE WHEN recent_sales = 0 OR coverage > ? THEN stock ELSE 0 END), 0) AS no_movement_stock',
            [self::EXCESS_COVERAGE_DAYS, $period->days(), $period->days(), $period->days(), self::EXCESS_COVERAGE_DAYS],
        )->first();
        $data = $row === null ? [] : get_object_vars($row);

        return [
            'all' => (int) ($data['all_count'] ?? 0),
            'supply' => (int) ($data['supply_count'] ?? 0),
            'outOfStock' => (int) ($data['out_count'] ?? 0),
            'noMovement' => (int) ($data['no_movement_count'] ?? 0),
            'supplyUnits' => (int) ($data['supply_units'] ?? 0),
            'missedSales' => (float) ($data['missed_sales'] ?? 0),
            'noMovementStock' => (int) ($data['no_movement_stock'] ?? 0),
        ];
    }

    private function applyView(Builder $query, string $view, AnalyticsPeriod $period): Builder
    {
        return match ($view) {
            'supply' => $query->where('current_metrics.sales', '>', 0)
                ->whereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' <= 7', [$period->days()]),
            'out_of_stock' => $query->whereRaw('COALESCE(latest_stock.quantity, 0) = 0')
                ->where('current_metrics.sales', '>', 0),
            'no_movement' => $query->where(function (Builder $nested) use ($period): void {
                $nested->whereRaw('COALESCE(recent_sales.recent_sales, 0) = 0')
                    ->orWhereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' > ?', [$period->days(), self::EXCESS_COVERAGE_DAYS]);
            }),
            default => $query,
        };
    }

    private function applyFilters(Builder $query, string $search, ?int $category): void
    {
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $nested) use ($needle): void {
                $nested->whereLike('products.title', $needle, caseSensitive: false)
                    ->orWhereLike('products.vendor_code', $needle, caseSensitive: false)
                    ->orWhereLike('products.nm_id', $needle, caseSensitive: false);
            });
        }

        if ($category !== null) {
            $query->where('products.category_id', $category);
        }
    }

    /** @param 'asc'|'desc' $direction */
    private function applySort(Builder $query, string $sort, string $direction, AnalyticsPeriod $period): void
    {
        if ($sort === 'supply') {
            if ($direction === 'asc') {
                $query->orderByRaw('(COALESCE(current_metrics.sales, 0) * 30.0 / ?) - COALESCE(latest_stock.quantity, 0) ASC', [$period->days()]);
            } else {
                $query->orderByRaw('(COALESCE(current_metrics.sales, 0) * 30.0 / ?) - COALESCE(latest_stock.quantity, 0) DESC', [$period->days()]);
            }

            $query->orderBy('products.id');

            return;
        }

        $column = match ($sort) {
            'title' => 'products.title',
            'stock' => 'stock',
            'sales' => 'sales',
            'coverage' => 'coverage',
            'out_days' => 'out_of_stock_days',
            default => 'coverage',
        };

        $query->orderBy($column, $direction)->orderBy('products.id');
    }

    /** @return array<string, mixed> */
    private function row(object $row, AnalyticsPeriod $period): array
    {
        $base = $this->products->toRow($row);
        $data = get_object_vars($row);
        $average = $this->formulas->averageDailySales((int) $data['sales'], $period->days());
        $coverage = $this->formulas->stockCoverage((int) $data['stock'], $average);
        $outDays = (int) $data['out_of_stock_days'];
        $daysWithoutSales = $data['last_sale_date'] === null
            ? self::NO_SALES_DAYS
            : max(0, (int) CarbonImmutable::parse($data['last_sale_date'])->diffInDays($period->end, false));

        return [
            ...$base,
            'averageDailySales' => round($average, 1),
            'coverage' => $coverage === null ? null : round($coverage, 1),
            'outDate' => $coverage === null || $coverage > self::MAX_FORECAST_DAYS || (int) $data['stock'] === 0 ? null : $this->translatedDate($period->end->addDays((int) ceil($coverage))),
            'recommendedSupply' => $this->formulas->recommendedSupply((int) $data['stock'], $average),
            'outOfStockDays' => $outDays,
            'estimatedMissedSales' => round($this->formulas->estimatedMissedSales($average, $outDays), 1),
            'daysWithoutSales' => $daysWithoutSales,
            'recentSales' => (int) $data['recent_sales'],
            'noMovementReason' => (int) $data['recent_sales'] === 0 ? 'Нет продаж 30 дней' : ($coverage !== null && $coverage > self::EXCESS_COVERAGE_DAYS ? 'Запас больше 90 дней' : 'Продажи есть'),
            'action' => $this->action($coverage, (int) $data['stock'], (int) $data['recent_sales']),
            'signalTone' => $this->signalTone($coverage, (int) $data['stock']),
        ];
    }

    /**
     * @param  array{all: int, supply: int, outOfStock: int, noMovement: int, supplyUnits: int, missedSales: float, noMovementStock: int}  $summary
     * @return array{tone: string, title: string, detail: string}
     */
    private function insight(string $view, array $summary): array
    {
        if ($view === 'supply') {
            $count = $summary['supply'];
            $total = $summary['supplyUnits'];

            return ['tone' => 'warning', 'title' => $count.' '.$this->productNoun($count).' '.($count === 1 ? 'требует' : 'требуют').' поставки — суммарно рекомендуется '.$total.' шт.', 'detail' => 'Расчёт покрывает спрос на 30 дней с учётом текущего остатка.'];
        }

        if ($view === 'out_of_stock') {
            $count = $summary['outOfStock'];
            $missed = $summary['missedSales'];

            return ['tone' => 'danger', 'title' => $count.' '.$this->productNoun($count).' '.($count === 1 ? 'закончился' : 'закончились').' — ориентировочно упущено '.(int) round($missed).' продаж', 'detail' => 'Оценка основана на средней скорости продаж и времени без остатка.'];
        }

        if ($view === 'no_movement') {
            $count = $summary['noMovement'];
            $stock = $summary['noMovementStock'];

            return ['tone' => 'info', 'title' => $count.' '.$this->productNoun($count).' без движения — '.$stock.' шт. требуют проверки', 'detail' => 'Без движения: нет продаж 30 дней или запас превышает 90 дней.'];
        }

        $supplyCount = $summary['supply'];
        $outCount = $summary['outOfStock'];

        return ['tone' => 'warning', 'title' => $supplyCount.' '.$this->productNoun($supplyCount).' '.($supplyCount === 1 ? 'требует' : 'требуют').' поставки, '.$outCount.' уже '.($outCount === 1 ? 'закончился' : 'закончились'), 'detail' => 'Прогноз использует средние продажи за выбранный период.'];
    }

    private function notice(string $view): string
    {
        return match ($view) {
            'out_of_stock' => 'Упущенные продажи — оценка, а не фактические отмены или потери.',
            'no_movement' => 'Деньги не показаны: закупочная себестоимость товаров в SellerScope не загружена.',
            default => 'Прогноз использует средние продажи за выбранный период.',
        };
    }

    private function translatedDate(CarbonImmutable $date): string
    {
        $localized = $date->locale('ru');

        if ($localized instanceof CarbonImmutable) {
            return $localized->translatedFormat('j F');
        }

        return $date->format('j F');
    }

    private function action(?float $coverage, int $stock, int $recentSales): string
    {
        if ($stock === 0) {
            return 'Пополнить остаток';
        }

        if ($recentSales === 0 || ($coverage !== null && $coverage > self::EXCESS_COVERAGE_DAYS)) {
            return 'Проверить спрос';
        }

        return $coverage !== null && $coverage <= 7 ? 'Запланировать поставку' : '—';
    }

    /** @return 'danger'|'warning'|'primary' */
    private function signalTone(?float $coverage, int $stock): string
    {
        if ($stock === 0 || ($coverage !== null && $coverage <= 3)) {
            return 'danger';
        }

        return $coverage !== null && $coverage <= 7 ? 'warning' : 'primary';
    }

    private function scalarQuery(Request $request, string $key, int|string|null $default = null): int|string|null
    {
        $value = $request->query($key, $default);

        return is_int($value) || is_string($value) ? $value : $default;
    }

    private function positiveIntegerQuery(Request $request, string $key): ?int
    {
        $value = $this->scalarQuery($request, $key);

        return (is_int($value) || (is_string($value) && ctype_digit($value)))
            && (int) $value > 0 ? (int) $value : null;
    }

    private function defaultSort(string $view): string
    {
        return match ($view) {
            'out_of_stock' => 'out_days',
            'no_movement' => 'coverage',
            default => 'coverage',
        };
    }

    private function defaultDirection(string $view): string
    {
        return in_array($view, ['out_of_stock', 'no_movement'], true) ? 'desc' : 'asc';
    }

    private function productNoun(int $count): string
    {
        $mod100 = $count % 100;
        $mod10 = $count % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'товаров';
        }

        return match ($mod10) {
            1 => 'товар',
            2, 3, 4 => 'товара',
            default => 'товаров',
        };
    }
}
