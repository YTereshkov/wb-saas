<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final class ReconcileSellerAccount
{
    /**
     * @return array{
     *     sellerAccountId: int,
     *     source: string,
     *     start: string,
     *     end: string,
     *     passed: bool,
     *     checks: list<array{
     *         key: string,
     *         expected: int,
     *         actual: int,
     *         difference: int,
     *         passed: bool
     *     }>
     * }
     */
    public function handle(
        SellerAccount $account,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $startLocal = $start->setTimezone($account->timezone)->startOfDay();
        $endLocal = $end->setTimezone($account->timezone)->endOfDay();
        $startUtc = $startLocal->utc();
        $endUtc = $endLocal->utc();
        $startDate = $startLocal->toDateString();
        $endDate = $endLocal->toDateString();

        $facts = [
            'revenue_kopecks' => (int) $account->sales()
                ->whereBetween('sold_at', [$startUtc, $endUtc])
                ->sum('amount_kopecks'),
            'orders' => (int) $account->orders()
                ->whereBetween('ordered_at', [$startUtc, $endUtc])
                ->sum('quantity'),
            'sales' => (int) $account->sales()
                ->whereBetween('sold_at', [$startUtc, $endUtc])
                ->sum('quantity'),
            'returns' => (int) $account->returnFacts()
                ->whereBetween('returned_at', [$startUtc, $endUtc])
                ->sum('quantity'),
        ];
        $accountMetrics = $this->metricTotals(
            AccountDailyMetric::query()
                ->where('seller_account_id', $account->id)
                ->whereBetween('metric_date', [$startDate, $endDate]),
        );
        $productMetrics = $this->metricTotals(
            ProductDailyMetric::query()
                ->where('seller_account_id', $account->id)
                ->whereBetween('metric_date', [$startDate, $endDate]),
        );

        $checks = [];
        foreach (['revenue_kopecks', 'orders', 'sales', 'returns'] as $metric) {
            $checks[] = $this->check(
                "facts_to_account.{$metric}",
                $facts[$metric],
                $accountMetrics[$metric],
            );
            $checks[] = $this->check(
                "account_to_products.{$metric}",
                $accountMetrics[$metric],
                $productMetrics[$metric],
            );
        }

        $passed = collect($checks)->every(
            static fn (array $check): bool => $check['passed'],
        );
        $result = [
            'sellerAccountId' => $account->id,
            'source' => $account->source->value,
            'start' => $startDate,
            'end' => $endDate,
            'passed' => $passed,
            'checks' => $checks,
        ];

        Log::log($passed ? 'info' : 'warning', 'Seller account reconciliation completed.', [
            'seller_account_id' => $account->id,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'passed' => $passed,
            'differences' => collect($checks)
                ->reject(static fn (array $check): bool => $check['passed'])
                ->mapWithKeys(static fn (array $check): array => [
                    $check['key'] => $check['difference'],
                ])
                ->all(),
        ]);

        return $result;
    }

    /**
     * @param  Builder<AccountDailyMetric>|Builder<ProductDailyMetric>  $query
     * @return array{revenue_kopecks: int, orders: int, sales: int, returns: int}
     */
    private function metricTotals(Builder $query): array
    {
        $row = $query->selectRaw(
            'COALESCE(SUM(revenue_kopecks), 0) AS revenue_kopecks, '
            .'COALESCE(SUM(orders), 0) AS orders, '
            .'COALESCE(SUM(sales), 0) AS sales, '
            .'COALESCE(SUM(returns), 0) AS returns',
        )->first();

        return [
            'revenue_kopecks' => (int) ($row?->getAttribute('revenue_kopecks') ?? 0),
            'orders' => (int) ($row?->getAttribute('orders') ?? 0),
            'sales' => (int) ($row?->getAttribute('sales') ?? 0),
            'returns' => (int) ($row?->getAttribute('returns') ?? 0),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     expected: int,
     *     actual: int,
     *     difference: int,
     *     passed: bool
     * }
     */
    private function check(string $key, int $expected, int $actual): array
    {
        return [
            'key' => $key,
            'expected' => $expected,
            'actual' => $actual,
            'difference' => $actual - $expected,
            'passed' => $expected === $actual,
        ];
    }
}
