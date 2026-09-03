<?php

namespace App\Modules\Analytics\Queries;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ProductAnalyticsQuery
{
    public const DYNAMICS_EXPRESSION = '(COALESCE(current_metrics.revenue_kopecks, 0) - COALESCE(comparison_metrics.revenue_kopecks, 0)) * 100.0 / NULLIF(comparison_metrics.revenue_kopecks, 0)';

    public const BUYOUT_EXPRESSION = 'COALESCE(current_metrics.sales, 0) * 100.0 / NULLIF(current_metrics.orders, 0)';

    public const RETURNS_EXPRESSION = 'COALESCE(current_metrics.returns, 0) * 100.0 / NULLIF(current_metrics.sales, 0)';

    public const COVERAGE_EXPRESSION = 'COALESCE(latest_stock.quantity, 0) * ? * 1.0 / NULLIF(current_metrics.sales, 0)';

    public function base(SellerAccount $account, AnalyticsPeriod $period, ?int $warehouseId = null): Builder
    {
        $current = $this->metricSubquery(
            $account->id,
            $period->start->toDateString(),
            $period->end->toDateString(),
        );
        $comparison = $this->metricSubquery(
            $account->id,
            $period->comparisonStart->toDateString(),
            $period->comparisonEnd->toDateString(),
        );
        $stock = DB::table('stock_snapshots')
            ->where('seller_account_id', $account->id)
            ->when($warehouseId !== null, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->where('snapshot_at', '=', function (Builder $query) use ($account, $warehouseId): void {
                $query->from('stock_snapshots')
                    ->where('seller_account_id', $account->id)
                    ->when($warehouseId !== null, fn (Builder $nested) => $nested->where('warehouse_id', $warehouseId))
                    ->selectRaw('MAX(snapshot_at)');
            })
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) AS quantity');

        return DB::table('products')
            ->leftJoin('categories', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                    ->on('categories.seller_account_id', '=', 'products.seller_account_id');
            })
            ->leftJoinSub($current, 'current_metrics', 'current_metrics.product_id', '=', 'products.id')
            ->leftJoinSub($comparison, 'comparison_metrics', 'comparison_metrics.product_id', '=', 'products.id')
            ->leftJoinSub($stock, 'latest_stock', 'latest_stock.product_id', '=', 'products.id')
            ->leftJoin('product_signals', function ($join) {
                $join->on('product_signals.product_id', '=', 'products.id')
                    ->on('product_signals.seller_account_id', '=', 'products.seller_account_id');
            })
            ->where('products.seller_account_id', $account->id)
            ->select([
                'products.id',
                'products.title',
                'products.vendor_code',
                'products.nm_id',
                'products.image_url',
                'products.is_active',
                'products.category_id',
                'categories.name as category_name',
                'product_signals.type as signal_type',
                'product_signals.priority as signal_priority',
                'product_signals.severity as signal_severity',
                'product_signals.fact as signal_fact',
                'product_signals.risk as signal_risk',
                'product_signals.recommendation as signal_recommendation',
            ])
            ->selectRaw('COALESCE(current_metrics.revenue_kopecks, 0) AS revenue_kopecks')
            ->selectRaw('COALESCE(current_metrics.orders, 0) AS orders')
            ->selectRaw('COALESCE(current_metrics.sales, 0) AS sales')
            ->selectRaw('COALESCE(current_metrics.returns, 0) AS returns')
            ->selectRaw('COALESCE(comparison_metrics.revenue_kopecks, 0) AS comparison_revenue_kopecks')
            ->selectRaw('COALESCE(comparison_metrics.orders, 0) AS comparison_orders')
            ->selectRaw('COALESCE(comparison_metrics.sales, 0) AS comparison_sales')
            ->selectRaw('COALESCE(comparison_metrics.returns, 0) AS comparison_returns')
            ->selectRaw('COALESCE(latest_stock.quantity, 0) AS stock')
            ->selectRaw(self::DYNAMICS_EXPRESSION.' AS dynamics')
            ->selectRaw(self::BUYOUT_EXPRESSION.' AS buyout')
            ->selectRaw(self::RETURNS_EXPRESSION.' AS returns_rate')
            ->selectRaw(self::COVERAGE_EXPRESSION.' AS coverage', [$period->days()]);
    }

    /** @return array<string, mixed> */
    public function toRow(object $row): array
    {
        $data = get_object_vars($row);

        return [
            'id' => (int) $data['id'],
            'title' => (string) $data['title'],
            'vendorCode' => (string) $data['vendor_code'],
            'nmId' => (string) $data['nm_id'],
            'imageUrl' => $data['image_url'] ? (string) $data['image_url'] : null,
            'category' => $data['category_name'] ? (string) $data['category_name'] : null,
            'active' => (bool) $data['is_active'],
            'revenueKopecks' => (int) $data['revenue_kopecks'],
            'orders' => (int) $data['orders'],
            'sales' => (int) $data['sales'],
            'dynamics' => $data['dynamics'] !== null ? round((float) $data['dynamics'], 1) : null,
            'buyout' => $data['buyout'] !== null ? round((float) $data['buyout'], 1) : null,
            'returnsRate' => $data['returns_rate'] !== null ? round((float) $data['returns_rate'], 1) : null,
            'stock' => (int) $data['stock'],
            'coverage' => $data['coverage'] !== null ? round((float) $data['coverage'], 1) : null,
            'signal' => $data['signal_type'] ? [
                'type' => (string) $data['signal_type'],
                'priority' => (int) $data['signal_priority'],
                'severity' => (string) $data['signal_severity'],
                'fact' => (string) $data['signal_fact'],
                'risk' => (string) $data['signal_risk'],
                'recommendation' => (string) $data['signal_recommendation'],
            ] : null,
        ];
    }

    private function metricSubquery(int $accountId, string $start, string $end): Builder
    {
        return DB::table('product_daily_metrics')
            ->where('seller_account_id', $accountId)
            ->whereBetween('metric_date', [$start, $end])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(revenue_kopecks) AS revenue_kopecks, SUM(orders) AS orders, SUM(sales) AS sales, SUM(returns) AS returns');
    }
}
