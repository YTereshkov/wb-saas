<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAnalyticsContextRequest;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Queries\AnalyticsDataCoverageQuery;
use App\Modules\Analytics\Services\AnalyticsPeriodResolver;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AnalyticsContextController extends Controller
{
    public function __construct(
        private readonly AnalyticsPeriodResolver $periodResolver,
        private readonly AnalyticsDataCoverageQuery $coverageQuery,
    ) {}

    public function update(UpdateAnalyticsContextRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $preference = $request->user()->preference()->firstOrCreate();
        $account = null;
        if ($data['seller_account_id'] !== null) {
            $account = SellerAccount::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($data['seller_account_id'])
                ->firstOrFail();
        }
        $preset = $this->periodResolver->normalizePreset($account, $data['period_preset']);
        $period = $preset === 'custom'
            ? $this->customPeriod($account, $data['period_start'], $data['period_end'])
            : $this->periodResolver->forPreset($account, $preset);

        $preference->update([
            'active_seller_account_id' => $data['seller_account_id'],
            'period_preset' => $preset,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'comparison_start' => $period->comparisonStart->toDateString(),
            'comparison_end' => $period->comparisonEnd->toDateString(),
            'granularity' => $period->granularity,
        ]);

        return redirect()->to($this->returnUrl(
            $data['return_to'],
            $data['seller_account_id'],
            $preset,
            $period->start->toDateString(),
            $period->end->toDateString(),
        ));
    }

    private function customPeriod(?SellerAccount $account, string $start, string $end): AnalyticsPeriod
    {
        $coverage = $this->coverageQuery->forAccount($account)['overall'];
        if ($coverage['start'] === null || $coverage['end'] === null) {
            throw ValidationException::withMessages([
                'period_start' => 'Для кабинета ещё нет загруженных аналитических данных.',
            ]);
        }
        $timezone = $account->timezone;
        $periodStart = CarbonImmutable::parse($start, $timezone)->startOfDay();
        $periodEnd = CarbonImmutable::parse($end, $timezone)->startOfDay();

        if ($periodStart->lt(CarbonImmutable::parse($coverage['start'], $timezone))) {
            throw ValidationException::withMessages([
                'period_start' => 'Дата начала раньше первой доступной даты кабинета.',
            ]);
        }
        if ($periodEnd->gt(CarbonImmutable::parse($coverage['end'], $timezone))) {
            throw ValidationException::withMessages([
                'period_end' => 'Дата окончания позже последней доступной даты кабинета.',
            ]);
        }

        return $this->periodResolver->forCustom($account, $periodStart, $periodEnd);
    }

    private function returnUrl(
        string $returnTo,
        ?int $accountId,
        string $preset,
        string $start,
        string $end,
    ): string {
        $parts = parse_url($returnTo);
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/overview';
        $query = [];
        parse_str(is_string($parts['query'] ?? null) ? $parts['query'] : '', $query);
        if ($accountId === null) {
            unset($query['cabinet']);
        } else {
            $query['cabinet'] = $accountId;
        }
        $query['period'] = $preset;
        if ($preset === 'custom') {
            $query['from'] = $start;
            $query['to'] = $end;
        } else {
            unset($query['from'], $query['to']);
        }

        $encoded = http_build_query($query);

        return $path.($encoded === '' ? '' : '?'.$encoded);
    }
}
