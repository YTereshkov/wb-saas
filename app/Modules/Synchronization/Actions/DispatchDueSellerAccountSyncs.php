<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class DispatchDueSellerAccountSyncs
{
    public function __construct(private StartInitialSync $startInitialSync) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $cutoff = ($now ?? CarbonImmutable::now('UTC'))->subMinutes(
            (int) config('sellerscope.synchronization.cadence_minutes', 30),
        );
        $dispatched = 0;

        SellerAccount::query()
            ->where('source', SellerAccountSource::Wildberries)
            ->whereIn('status', [
                SellerAccountStatus::Active,
                SellerAccountStatus::Partial,
            ])
            ->whereHas('credential', fn ($query) => $query
                ->whereNotNull('verified_at')
                ->whereNull('invalidated_at'))
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->whereNull('last_sync_completed_at')
                    ->orWhere('last_sync_completed_at', '<=', $cutoff);
            })
            ->whereDoesntHave('syncRuns', fn ($query) => $query->whereIn('status', [
                SyncRunStatus::Pending,
                SyncRunStatus::Running,
            ]))
            ->orderBy('id')
            ->chunkById(100, function (Collection $accounts) use (&$dispatched): void {
                foreach ($accounts as $account) {
                    $this->startInitialSync->handle($account);
                    $dispatched++;
                }
            });

        return $dispatched;
    }
}
