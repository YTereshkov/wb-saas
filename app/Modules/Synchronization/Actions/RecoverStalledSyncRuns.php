<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RecoverStalledSyncRuns
{
    public function handle(?CarbonImmutable $now = null): int
    {
        $cutoff = ($now ?? CarbonImmutable::now('UTC'))->subMinutes(
            (int) config('sellerscope.synchronization.stalled_after_minutes', 15),
        );
        $recovered = 0;

        SyncRun::query()
            ->whereIn('status', [SyncRunStatus::Pending, SyncRunStatus::Running])
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function (Collection $runs) use ($cutoff, &$recovered): void {
                foreach ($runs as $candidate) {
                    if ($this->recover($candidate->id, $cutoff)) {
                        $recovered++;
                    }
                }
            });

        return $recovered;
    }

    private function recover(int $syncRunId, CarbonImmutable $cutoff): bool
    {
        return DB::transaction(function () use ($syncRunId, $cutoff): bool {
            $run = SyncRun::query()->with('sellerAccount')->lockForUpdate()->find($syncRunId);

            if ($run === null
                || ! in_array($run->status, [SyncRunStatus::Pending, SyncRunStatus::Running], true)
                || $run->updated_at->gt($cutoff)) {
                return false;
            }

            $run->update([
                'status' => SyncRunStatus::Failed,
                'finished_at' => now(),
                'error_code' => 'sync_stalled',
                'error_summary' => 'Синхронизация остановилась и будет запущена повторно автоматически.',
            ]);
            $run->sellerAccount->syncResourceStates()
                ->whereIn('status', [SyncResourceStatus::Pending, SyncResourceStatus::Running])
                ->update([
                    'status' => SyncResourceStatus::Failed,
                    'error_code' => 'sync_stalled',
                    'updated_at' => now(),
                ]);

            if (! in_array($run->sellerAccount->status, [
                SellerAccountStatus::InvalidCredentials,
                SellerAccountStatus::Disconnected,
            ], true)) {
                $run->sellerAccount->update(['status' => SellerAccountStatus::Partial]);
            }

            Log::warning('Stalled synchronization recovered.', [
                'seller_account_id' => $run->seller_account_id,
                'sync_run_id' => $run->id,
                'error_code' => 'sync_stalled',
            ]);

            return true;
        });
    }
}
