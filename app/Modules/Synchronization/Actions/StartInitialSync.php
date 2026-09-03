<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use App\Modules\Synchronization\Jobs\RunInitialSyncJob;
use App\Modules\Synchronization\Models\SyncRun;
use Illuminate\Support\Facades\DB;

final class StartInitialSync
{
    public function handle(SellerAccount $sellerAccount): SyncRun
    {
        $created = false;

        $syncRun = DB::transaction(function () use ($sellerAccount, &$created): SyncRun {
            $account = SellerAccount::query()->lockForUpdate()->findOrFail($sellerAccount->id);

            if ($account->status === SellerAccountStatus::Disconnected) {
                throw new \DomainException('A disconnected seller account cannot be synchronized.');
            }

            $existing = $account->syncRuns()
                ->whereIn('status', [SyncRunStatus::Pending, SyncRunStatus::Running])
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $type = in_array($account->status, [SellerAccountStatus::Active, SellerAccountStatus::Partial], true)
                ? SyncRunType::Incremental
                : SyncRunType::Initial;
            $syncRun = $account->syncRuns()->create([
                'type' => $type,
                'status' => SyncRunStatus::Pending,
                'progress' => 0,
            ]);
            $account->update(array_filter([
                'status' => $type === SyncRunType::Initial ? SellerAccountStatus::InitialSync : null,
                'last_sync_started_at' => now(),
            ], static fn ($value): bool => $value !== null));
            $created = true;

            return $syncRun;
        });

        if ($created) {
            RunInitialSyncJob::dispatch($syncRun->id, $syncRun->seller_account_id)->onQueue('sync');
        }

        return $syncRun;
    }
}
