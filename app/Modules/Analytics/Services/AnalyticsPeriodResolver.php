<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\UserPreference;
use Carbon\CarbonImmutable;

final class AnalyticsPeriodResolver
{
    public function resolve(?SellerAccount $account, ?UserPreference $preference): AnalyticsPeriod
    {
        $preset = $this->normalizePreset($account, $preference?->period_preset);
        $period = $preset === 'custom' && $preference?->period_start !== null && $preference->period_end !== null
            ? $this->forCustom($account, $preference->period_start, $preference->period_end)
            : $this->forPreset($account, $preset);

        return new AnalyticsPeriod(
            start: $period->start,
            end: $period->end,
            comparisonStart: $period->comparisonStart,
            comparisonEnd: $period->comparisonEnd,
            timezone: $period->timezone,
            granularity: $period->granularity,
        );
    }

    public function forCustom(
        ?SellerAccount $account,
        CarbonImmutable|string $start,
        CarbonImmutable|string $end,
    ): AnalyticsPeriod {
        $timezone = $account instanceof SellerAccount ? $account->timezone : 'Europe/Moscow';
        $periodStart = $start instanceof CarbonImmutable
            ? $start->setTimezone($timezone)->startOfDay()
            : CarbonImmutable::parse($start, $timezone)->startOfDay();
        $periodEnd = $end instanceof CarbonImmutable
            ? $end->setTimezone($timezone)->startOfDay()
            : CarbonImmutable::parse($end, $timezone)->startOfDay();

        return $this->periodWithPrecedingComparison($periodStart, $periodEnd, $timezone);
    }

    public function forPreset(
        ?SellerAccount $account,
        string $preset,
        ?CarbonImmutable $now = null,
    ): AnalyticsPeriod {
        $timezone = $account instanceof SellerAccount
            ? $account->timezone
            : 'Europe/Moscow';

        if ($account?->source !== SellerAccountSource::Wildberries) {
            return $this->demoPeriod($preset, $timezone);
        }

        $today = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();

        if ($preset === 'previous_month') {
            return AnalyticsPeriod::calendarMonth($today->subMonthNoOverflow(), $timezone);
        }

        if ($preset === 'current_month') {
            return $this->periodWithPrecedingComparison($today->startOfMonth(), $today, $timezone);
        }

        return $this->periodWithPrecedingComparison($today->subDays(29), $today, $timezone);
    }

    public function normalizePreset(?SellerAccount $account, ?string $preset): string
    {
        $allowed = $account?->source === SellerAccountSource::Wildberries
            ? ['last_30_days', 'current_month', 'previous_month', 'custom']
            : ['july_2026', 'june_2026', 'last_30_days', 'custom'];

        if ($preset !== null && in_array($preset, $allowed, true)) {
            return $preset;
        }

        return $account?->source === SellerAccountSource::Wildberries
            ? 'last_30_days'
            : 'july_2026';
    }

    /** @return list<array{value: string, label: string}> */
    public function options(?SellerAccount $account): array
    {
        if ($account?->source === SellerAccountSource::Wildberries) {
            return [
                ['value' => 'last_30_days', 'label' => 'Последние 30 дней'],
                ['value' => 'current_month', 'label' => 'Текущий месяц'],
                ['value' => 'previous_month', 'label' => 'Предыдущий месяц'],
                ['value' => 'custom', 'label' => 'Выбрать даты'],
            ];
        }

        return [
            ['value' => 'july_2026', 'label' => '1–31 июля 2026'],
            ['value' => 'june_2026', 'label' => '1–30 июня 2026'],
            ['value' => 'last_30_days', 'label' => 'Последние 30 дней'],
            ['value' => 'custom', 'label' => 'Выбрать даты'],
        ];
    }

    private function demoPeriod(string $preset, string $timezone): AnalyticsPeriod
    {
        if ($preset === 'june_2026') {
            return AnalyticsPeriod::calendarMonth(CarbonImmutable::parse('2026-06-15', $timezone), $timezone);
        }

        if ($preset === 'last_30_days') {
            $end = CarbonImmutable::parse('2026-07-31', $timezone)->startOfDay();

            return $this->periodWithPrecedingComparison($end->subDays(29), $end, $timezone);
        }

        return AnalyticsPeriod::calendarMonth(CarbonImmutable::parse('2026-07-15', $timezone), $timezone);
    }

    private function periodWithPrecedingComparison(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $timezone,
    ): AnalyticsPeriod {
        $comparisonEnd = $start->subDay();
        $days = (int) $start->diffInDays($end) + 1;

        return new AnalyticsPeriod(
            start: $start,
            end: $end,
            comparisonStart: $comparisonEnd->subDays($days - 1),
            comparisonEnd: $comparisonEnd,
            timezone: $timezone,
            granularity: $days <= 90 ? 'day' : ($days <= 730 ? 'week' : 'month'),
        );
    }
}
