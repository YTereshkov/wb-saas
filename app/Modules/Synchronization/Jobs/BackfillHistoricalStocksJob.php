<?php

namespace App\Modules\Synchronization\Jobs;

use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\BackfillHistoricalStocks;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Models\SyncResourceState;
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

final class BackfillHistoricalStocksJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 720;

    public int $timeout = 900;

    public int $uniqueFor = 18_000;

    public function __construct(public readonly int $sellerAccountId) {}

    public function uniqueId(): string
    {
        return (string) $this->sellerAccountId;
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

    public function handle(BackfillHistoricalStocks $action, SyncRetryDelay $retryDelay): void
    {
        $account = SellerAccount::query()->find($this->sellerAccountId);
        if ($account === null) {
            return;
        }

        try {
            $action->handle($account);
        } catch (WildberriesApiException $exception) {
            if ($exception->isAuthorizationFailure()) {
                $currentAccount = SellerAccount::query()->find($this->sellerAccountId);
                $currentAccount?->credential?->update(['invalidated_at' => now()]);
                $currentAccount?->update(['status' => SellerAccountStatus::InvalidCredentials]);
            }

            if ($exception->retryable && $this->attempts() < $this->tries) {
                $this->release($retryDelay->forException($exception, $this->attempts(), $this->uniqueId()));

                return;
            }

            $this->markFailed($exception->errorCode);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception instanceof WildberriesApiException
            ? $exception->errorCode
            : 'historical_stock_backfill_failed');
    }

    private function markFailed(string $errorCode): void
    {
        SyncResourceState::query()
            ->where('seller_account_id', $this->sellerAccountId)
            ->where('resource', SyncResource::StockHistory)
            ->update([
                'status' => SyncResourceStatus::Failed,
                'error_code' => $errorCode,
                'updated_at' => now(),
            ]);

        Log::warning('Historical stock backfill failed.', [
            'seller_account_id' => $this->sellerAccountId,
            'resource' => SyncResource::StockHistory->value,
            'error_code' => $errorCode,
        ]);
    }
}
