<?php

namespace App\Modules\Analytics\Queries;

use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AnalyticsDataCoverageQuery
{
    /** @return array{overall: array{start: string|null, end: string|null}, resources: array<string, array{start: string|null, end: string|null}>} */
    public function forAccount(?SellerAccount $account): array
    {
        if (! $account instanceof SellerAccount) {
            return $this->empty();
        }

        $resources = [
            'orders' => $this->timestampRange($account, 'orders', 'ordered_at'),
            'sales' => $this->timestampRange($account, 'sales', 'sold_at'),
            'returns' => $this->timestampRange($account, 'return_facts', 'returned_at'),
            'stocks' => $this->dateRange($account, 'stock_snapshots', 'snapshot_date'),
        ];
        $starts = array_values(array_filter(array_column($resources, 'start'), is_string(...)));
        $ends = array_values(array_filter(array_column($resources, 'end'), is_string(...)));

        return [
            'overall' => [
                'start' => $starts === [] ? null : min($starts),
                'end' => $ends === [] ? null : max($ends),
            ],
            'resources' => $resources,
        ];
    }

    /** @return array{start: string|null, end: string|null} */
    private function timestampRange(SellerAccount $account, string $table, string $column): array
    {
        $query = DB::table($table)->where('seller_account_id', $account->id);

        return [
            'start' => $this->businessDate($query->min($column), $account->timezone),
            'end' => $this->businessDate($query->max($column), $account->timezone),
        ];
    }

    /** @return array{start: string|null, end: string|null} */
    private function dateRange(SellerAccount $account, string $table, string $column): array
    {
        $query = DB::table($table)->where('seller_account_id', $account->id);
        $start = $query->min($column);
        $end = $query->max($column);

        return [
            'start' => is_string($start) ? CarbonImmutable::parse($start)->toDateString() : null,
            'end' => is_string($end) ? CarbonImmutable::parse($end)->toDateString() : null,
        ];
    }

    private function businessDate(mixed $value, string $timezone): ?string
    {
        return is_string($value)
            ? CarbonImmutable::parse($value, 'UTC')->setTimezone($timezone)->toDateString()
            : null;
    }

    /** @return array{overall: array{start: null, end: null}, resources: array<string, array{start: null, end: null}>} */
    private function empty(): array
    {
        $empty = ['start' => null, 'end' => null];

        return [
            'overall' => $empty,
            'resources' => [
                'orders' => $empty,
                'sales' => $empty,
                'returns' => $empty,
                'stocks' => $empty,
            ],
        ];
    }
}
