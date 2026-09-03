<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\AnalyticsPeriodResolver;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\SellerAccounts\Models\UserPreference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class AnalyticsContextQuery
{
    public function __construct(
        private readonly AnalyticsPeriodResolver $periodResolver,
        private readonly AnalyticsDataCoverageQuery $coverageQuery,
    ) {}

    /** @return array{account: SellerAccount|null, period: AnalyticsPeriod} */
    public function resolveForUser(User $user): array
    {
        $accounts = $user->sellerAccounts()
            ->with(['credential', 'syncResourceStates'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $preference = $user->preference()->first();
        $activeAccount = $this->activeAccount($accounts, $preference);
        ['period' => $period] = $this->resolvedPeriod($activeAccount, $preference);

        return [
            'account' => $activeAccount,
            'period' => $period,
        ];
    }

    /** @return array<string, mixed>|null */
    public function forUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $accounts = $user->sellerAccounts()
            ->with(['credential', 'syncResourceStates'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
        $preference = $user->preference()->first();
        $activeAccount = $this->activeAccount($accounts, $preference);
        ['period' => $period, 'preset' => $preset] = $this->resolvedPeriod($activeAccount, $preference);
        $coverage = $this->coverageQuery->forAccount($activeAccount);

        return [
            'accounts' => $accounts->map(fn (SellerAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'status' => $account->status->value,
            ])->values()->all(),
            'activeAccount' => $activeAccount ? [
                'id' => $activeAccount->id,
                'name' => $activeAccount->name,
                'status' => $activeAccount->status->value,
                'statusLabel' => $this->statusLabel($activeAccount->status->value),
            ] : null,
            'period' => [
                'preset' => $preset,
                'start' => $period->start->toDateString(),
                'end' => $period->end->toDateString(),
                'label' => $this->dateRangeLabel($period->start, $period->end),
                'granularity' => $period->granularity,
            ],
            'comparison' => [
                'start' => $period->comparisonStart->toDateString(),
                'end' => $period->comparisonEnd->toDateString(),
                'label' => $this->dateRangeLabel($period->comparisonStart, $period->comparisonEnd),
                'isComplete' => $coverage['overall']['start'] !== null
                    && $coverage['overall']['end'] !== null
                    && $period->comparisonStart->gte(CarbonImmutable::parse($coverage['overall']['start'], $period->timezone))
                    && $period->comparisonEnd->lte(CarbonImmutable::parse($coverage['overall']['end'], $period->timezone)),
            ],
            'coverage' => $coverage,
            'sync' => $activeAccount ? [
                'status' => $activeAccount->status->value,
                'lastCompletedAt' => $activeAccount->last_sync_completed_at?->toISOString(),
                'label' => $this->syncLabel($activeAccount),
                'isStale' => $activeAccount->last_sync_completed_at !== null
                    && $activeAccount->last_sync_completed_at->lt(now()->subHours(6)),
                'missingPermissions' => array_values(array_diff(
                    $this->requiredPermissions($activeAccount),
                    $this->credentialPermissions($activeAccount),
                )),
                'unavailableResources' => $activeAccount->syncResourceStates
                    ->filter(fn ($state): bool => $state->availability->value === 'unavailable' || $state->status->value === 'failed')
                    ->map(fn ($state): string => $state->resource->value)
                    ->values()
                    ->all(),
            ] : null,
            'periodOptions' => $this->periodResolver->options($activeAccount),
        ];
    }

    /** @return list<string> */
    private function credentialPermissions(SellerAccount $account): array
    {
        $credential = $account->getRelation('credential');

        return $credential instanceof SellerAccountCredential
            ? $credential->permissions
            : [];
    }

    /** @return list<string> */
    private function requiredPermissions(SellerAccount $account): array
    {
        return $account->environment() === 'sandbox'
            ? ['products', 'orders', 'sales']
            : ['products', 'orders', 'sales', 'stocks'];
    }

    /** @param Collection<int, SellerAccount> $accounts */
    private function activeAccount(Collection $accounts, ?UserPreference $preference): ?SellerAccount
    {
        if ($preference?->active_seller_account_id !== null) {
            $preferred = $accounts->firstWhere('id', $preference->active_seller_account_id);

            if ($preferred instanceof SellerAccount) {
                return $preferred;
            }
        }

        $first = $accounts->first();

        return $first instanceof SellerAccount ? $first : null;
    }

    /** @return array{period: AnalyticsPeriod, preset: string} */
    private function resolvedPeriod(?SellerAccount $account, ?UserPreference $preference): array
    {
        $requestedPreset = request()->query('period');

        if (is_string($requestedPreset)
            && $this->periodResolver->normalizePreset($account, $requestedPreset) === $requestedPreset) {
            if ($requestedPreset !== 'custom') {
                return [
                    'period' => $this->periodResolver->forPreset($account, $requestedPreset),
                    'preset' => $requestedPreset,
                ];
            }

            $requested = $this->requestedCustomPeriod($account);
            if ($requested instanceof AnalyticsPeriod) {
                return ['period' => $requested, 'preset' => 'custom'];
            }
        }

        return [
            'period' => $this->periodResolver->resolve($account, $preference),
            'preset' => $this->periodResolver->normalizePreset($account, $preference?->period_preset),
        ];
    }

    private function requestedCustomPeriod(?SellerAccount $account): ?AnalyticsPeriod
    {
        if (! $account instanceof SellerAccount) {
            return null;
        }

        $from = request()->query('from');
        $to = request()->query('to');
        if (! is_string($from) || ! is_string($to)
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $from) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $to) !== 1) {
            return null;
        }

        try {
            $start = CarbonImmutable::parse($from, $account->timezone)->startOfDay();
            $end = CarbonImmutable::parse($to, $account->timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($start->toDateString() !== $from || $end->toDateString() !== $to || $start->gt($end)) {
            return null;
        }

        $coverage = $this->coverageQuery->forAccount($account)['overall'];
        if ($coverage['start'] === null || $coverage['end'] === null
            || $from < $coverage['start'] || $to > $coverage['end']) {
            return null;
        }

        return $this->periodResolver->forCustom($account, $start, $end);
    }

    private function dateRangeLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        if ($start->month === $end->month && $start->year === $end->year) {
            return "{$start->day}–{$end->day} {$months[$end->month]} {$end->year}";
        }

        return "{$start->format('d.m.Y')}–{$end->format('d.m.Y')}";
    }

    private function syncLabel(SellerAccount $account): string
    {
        if ($account->last_sync_completed_at === null) {
            return match ($account->status->value) {
                'initial_sync' => 'Выполняется первичная загрузка',
                'verified' => 'Готов к первичной загрузке',
                default => 'Данные ещё не загружены',
            };
        }

        return 'Обновлено '.$account->last_sync_completed_at
            ->setTimezone($account->timezone)
            ->format('d.m.Y, H:i');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Подключён',
            'partial' => 'Данные загружены частично',
            'invalid_credentials' => 'Нужен новый токен',
            'disconnected' => 'Кабинет отключён',
            'initial_sync' => 'Выполняется загрузка',
            'verified' => 'Готов к загрузке',
            default => 'Ожидает подключения',
        };
    }
}
