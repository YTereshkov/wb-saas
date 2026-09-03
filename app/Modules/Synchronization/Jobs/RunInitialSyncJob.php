<?php

namespace App\Modules\Synchronization\Jobs;

use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Models\SyncRun;
use App\Modules\Synchronization\Support\SyncRetryDelay;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RunInitialSyncJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $syncRunId,
        public readonly int $sellerAccountId,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->sellerAccountId}:{$this->syncRunId}";
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("seller-account:{$this->sellerAccountId}:sync"))
                ->expireAfter($this->timeout + 30)
                ->releaseAfter(30),
        ];
    }

    public function handle(RunInitialSync $action, SyncRetryDelay $retryDelay): void
    {
        $syncRun = SyncRun::query()->find($this->syncRunId);
        if ($syncRun === null) {
            return;
        }

        try {
            $action->handle($syncRun);
        } catch (WildberriesApiException $exception) {
            if ($exception->retryable && $this->attempts() < $this->tries) {
                $this->release($retryDelay->forException($exception, $this->attempts(), $this->uniqueId()));

                return;
            }

            $this->fail($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $syncRun = SyncRun::query()->with('sellerAccount')->find($this->syncRunId);

        if ($syncRun === null || in_array($syncRun->status, [
            SyncRunStatus::Completed,
            SyncRunStatus::Failed,
        ], true)) {
            return;
        }

        $errorCode = $exception instanceof WildberriesApiException
            ? $exception->errorCode
            : 'sync_failed';
        $syncRun->update([
            'status' => SyncRunStatus::Failed,
            'finished_at' => now(),
            'error_code' => $errorCode,
            'error_summary' => 'Не удалось завершить синхронизацию. Повторите попытку позже.',
        ]);

        $account = $syncRun->sellerAccount->fresh();
        if ($account instanceof SellerAccount && ! in_array($account->status, [
            SellerAccountStatus::InvalidCredentials,
            SellerAccountStatus::Disconnected,
        ], true)) {
            $account->update(['status' => SellerAccountStatus::Partial]);
        }

        Log::warning('Synchronization job exhausted its retries.', [
            'seller_account_id' => $syncRun->seller_account_id,
            'sync_run_id' => $syncRun->id,
            'error_code' => $errorCode,
        ]);
    }
}
