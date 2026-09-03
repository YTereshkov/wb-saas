<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\Analytics\Actions\RebuildAnalytics;
use App\Modules\Notifications\Actions\QueueSignalNotifications;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use App\Modules\Synchronization\Jobs\BackfillHistoricalStocksJob;
use App\Modules\Synchronization\Models\ImportBatch;
use App\Modules\Synchronization\Models\RawImportPage;
use App\Modules\Synchronization\Models\SyncResourceState;
use App\Modules\Synchronization\Models\SyncRun;
use App\Modules\Wildberries\Contracts\CanonicalData;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\SalesPageData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\WildberriesClientResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RunInitialSync
{
    public function __construct(
        private WildberriesClientResolver $clientResolver,
        private CanonicalDataImporter $importer,
        private RebuildAnalytics $rebuildAnalytics,
        private QueueSignalNotifications $queueSignalNotifications,
    ) {}

    public function handle(SyncRun $syncRun): void
    {
        $syncRun->refresh()->loadMissing('sellerAccount.credential');
        if ($this->shouldStop($syncRun)) {
            return;
        }

        $account = $syncRun->sellerAccount;
        $client = $this->clientResolver->resolve($account);
        $period = $this->importPeriod($account->source, $account->timezone);
        $currentResource = null;

        $syncRun->update([
            'status' => SyncRunStatus::Running,
            'started_at' => $syncRun->started_at ?? now(),
            'finished_at' => null,
            'error_code' => null,
            'error_summary' => null,
        ]);
        $this->markUnsupportedResources($syncRun);

        try {
            $currentResource = SyncResource::Products;
            if (! $this->syncResource(
                $syncRun,
                $currentResource,
                fn () => $this->importPages(
                    $syncRun,
                    SyncResource::Products,
                    fn (?string $cursor): FetchPageData => $client->fetchProducts($cursor),
                    fn (array $items): int => $this->importer->products($account, $items),
                ),
            )) {
                return;
            }
            $syncRun->update(['progress' => 25]);

            $currentResource = SyncResource::Orders;
            if (! $this->syncResource(
                $syncRun,
                $currentResource,
                fn () => $this->importPages(
                    $syncRun,
                    SyncResource::Orders,
                    fn (?string $cursor): FetchPageData => $client->fetchOrders($period, $cursor),
                    fn (array $items): int => $this->importer->orders($account, $items),
                    $period,
                ),
            )) {
                return;
            }
            $syncRun->update(['progress' => 50]);

            $currentResource = SyncResource::Sales;
            if (! $this->syncResource(
                $syncRun,
                $currentResource,
                fn () => $this->importSalesPages($syncRun, $period),
            )) {
                return;
            }
            $syncRun->update(['progress' => 75]);

            $currentResource = SyncResource::Stocks;
            if (! $this->syncResource(
                $syncRun,
                $currentResource,
                fn () => $this->importPages(
                    $syncRun,
                    SyncResource::Stocks,
                    fn (?string $cursor): FetchPageData => $client->fetchStocks($cursor),
                    fn (array $items): int => $this->importer->stocks($account, $items),
                ),
            )) {
                return;
            }
            $syncRun->update(['progress' => 90]);

            if ($this->shouldStop($syncRun)) {
                return;
            }
            $this->rebuildAnalytics->handle($account);

            $completedAt = now();
            $completed = SyncRun::query()
                ->whereKey($syncRun->id)
                ->where('status', SyncRunStatus::Running)
                ->update([
                    'status' => SyncRunStatus::Completed,
                    'progress' => 100,
                    'finished_at' => $completedAt,
                ]);
            if ($completed === 0) {
                return;
            }
            $syncRun->refresh();
            $expectedStatuses = $syncRun->type === SyncRunType::Initial
                ? [SellerAccountStatus::InitialSync, SellerAccountStatus::Verified]
                : [SellerAccountStatus::Active, SellerAccountStatus::Partial];
            $nextAccountStatus = $this->hasUnavailableResources($account)
                ? SellerAccountStatus::Partial
                : SellerAccountStatus::Active;
            SellerAccount::query()
                ->whereKey($account->id)
                ->whereIn('status', $expectedStatuses)
                ->update([
                    'status' => $nextAccountStatus,
                    'last_sync_completed_at' => $completedAt,
                ]);
            $account->refresh();
            if ($account->status === SellerAccountStatus::Active
                && $syncRun->type === SyncRunType::Initial
                && $account->source === SellerAccountSource::Wildberries) {
                BackfillHistoricalStocksJob::dispatch($account->id)->onQueue('sync');
            }
            if ($account->status === SellerAccountStatus::Active
                && $syncRun->type === SyncRunType::Incremental) {
                $this->queueSignalNotifications->handle($account);
            }
        } catch (WildberriesApiException $exception) {
            if ($exception->isAuthenticationFailure()) {
                $this->markInvalidCredential($syncRun, $currentResource);
            } elseif ($exception->retryable) {
                $this->markRetryable($syncRun, $currentResource, $exception->errorCode);
            } else {
                $this->markFailed($syncRun, $currentResource, $exception->errorCode);
            }

            $this->logFailure($syncRun, $currentResource, $exception->errorCode);

            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($syncRun, $currentResource);
            $this->logFailure($syncRun, $currentResource, 'sync_failed');

            throw $exception;
        }
    }

    /** @param callable(): void $sync */
    private function syncResource(SyncRun $syncRun, SyncResource $resource, callable $sync): bool
    {
        if ($this->shouldStop($syncRun)) {
            return false;
        }

        $account = $syncRun->sellerAccount;
        if ($account->source === SellerAccountSource::Wildberries) {
            $permissions = $account->credential->permissions;
            if (! in_array($resource->value, $permissions, true)) {
                $this->markResourceUnavailable($syncRun, $resource, 'resource_not_supported_by_token');

                return true;
            }
        }

        try {
            $sync();
        } catch (WildberriesApiException $exception) {
            if ($exception->httpStatus !== 403) {
                throw $exception;
            }

            $this->markResourceUnavailable($syncRun, $resource, 'resource_access_denied');
            Log::warning('Seller account synchronization resource is unavailable.', [
                'seller_account_id' => $syncRun->seller_account_id,
                'sync_run_id' => $syncRun->id,
                'resource' => $resource->value,
                'error_code' => $exception->errorCode,
            ]);
        }

        return ! $this->shouldStop($syncRun);
    }

    private function shouldStop(SyncRun $syncRun): bool
    {
        $syncRun->refresh();
        $syncRun->sellerAccount->refresh();

        return ! in_array($syncRun->status, [SyncRunStatus::Pending, SyncRunStatus::Running], true)
            || $syncRun->sellerAccount->status === SellerAccountStatus::Disconnected;
    }

    private function markResourceUnavailable(
        SyncRun $syncRun,
        SyncResource $resource,
        string $errorCode,
    ): void {
        SyncResourceState::query()->updateOrCreate([
            'seller_account_id' => $syncRun->seller_account_id,
            'resource' => $resource,
        ], [
            'status' => SyncResourceStatus::Skipped,
            'availability' => ResourceAvailability::Unavailable,
            'cursor' => null,
            'last_attempt_at' => now(),
            'error_code' => $errorCode,
        ]);
    }

    private function markUnsupportedResources(SyncRun $syncRun): void
    {
        $account = $syncRun->sellerAccount;
        if ($account->source !== SellerAccountSource::Wildberries) {
            return;
        }

        $permissions = $account->credential->permissions ?? [];
        foreach ([
            SyncResource::Products,
            SyncResource::Orders,
            SyncResource::Sales,
            SyncResource::Stocks,
        ] as $resource) {
            if (! in_array($resource->value, $permissions, true)) {
                $this->markResourceUnavailable($syncRun, $resource, 'resource_not_supported_by_token');
            }
        }
    }

    private function hasUnavailableResources(SellerAccount $account): bool
    {
        return SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->whereIn('resource', [
                SyncResource::Products->value,
                SyncResource::Orders->value,
                SyncResource::Sales->value,
                SyncResource::Stocks->value,
            ])
            ->where('availability', ResourceAvailability::Unavailable)
            ->exists();
    }

    /**
     * @template T of CanonicalData
     *
     * @param  callable(?string): FetchPageData<T>  $fetch
     * @param  callable(list<T>): int  $import
     */
    private function importPages(
        SyncRun $syncRun,
        SyncResource $resource,
        callable $fetch,
        callable $import,
        ?ImportPeriodData $period = null,
    ): void {
        $state = $this->startResource($syncRun, $resource);
        $cursor = $state->cursor
            ?? ($syncRun->type === SyncRunType::Incremental ? $state->checkpoint : null);
        $checkpoint = $state->checkpoint;
        $pageNumber = 0;

        do {
            $cursorIn = $cursor;
            $page = $fetch($cursorIn);
            $pageNumber++;
            $this->guardPagination($cursorIn, $page->nextCursor, $pageNumber);
            $checksum = $this->checksum(array_map(
                static fn (CanonicalData $item): array => $item->toArray(),
                $page->items,
            ));
            $batch = $this->startBatch($syncRun, $resource, $cursorIn, $checksum, $period);
            $this->storeRawPage($batch, $page->rawPayload);
            $count = $import($page->items);
            $cursor = $page->nextCursor;
            $checkpoint = $page->checkpoint;
            $this->completeBatchAndAdvance($batch, $state, $cursor, $checkpoint, $count);
        } while ($cursor !== null);

        $this->completeResource($state, $checkpoint);
    }

    private function importSalesPages(SyncRun $syncRun, ImportPeriodData $period): void
    {
        $account = $syncRun->sellerAccount;
        $client = $this->clientResolver->resolve($account);
        $resource = SyncResource::Sales;
        $state = $this->startResource($syncRun, $resource);
        $cursor = $state->cursor
            ?? ($syncRun->type === SyncRunType::Incremental ? $state->checkpoint : null);
        $checkpoint = $state->checkpoint;
        $pageNumber = 0;

        do {
            $cursorIn = $cursor;
            /** @var SalesPageData $page */
            $page = $client->fetchSales($period, $cursorIn);
            $pageNumber++;
            $this->guardPagination($cursorIn, $page->nextCursor, $pageNumber);
            $payload = [
                'sales' => array_map(static fn ($item): array => $item->toArray(), $page->sales),
                'returns' => array_map(static fn ($item): array => $item->toArray(), $page->returns),
            ];
            $batch = $this->startBatch(
                $syncRun,
                $resource,
                $cursorIn,
                $this->checksum($payload),
                $period,
            );
            $this->storeRawPage($batch, $page->rawPayload);
            $count = $this->importer->sales($account, $page->sales);
            $count += $this->importer->returns($account, $page->returns);
            $cursor = $page->nextCursor;
            $checkpoint = $page->checkpoint;
            $this->completeBatchAndAdvance($batch, $state, $cursor, $checkpoint, $count);
        } while ($cursor !== null);

        $this->completeResource($state, $checkpoint);
    }

    private function startResource(SyncRun $syncRun, SyncResource $resource): SyncResourceState
    {
        $state = SyncResourceState::query()->firstOrCreate([
            'seller_account_id' => $syncRun->seller_account_id,
            'resource' => $resource,
        ]);
        $state->update([
            'status' => SyncResourceStatus::Running,
            'last_attempt_at' => now(),
            'error_code' => null,
        ]);

        return $state;
    }

    private function startBatch(
        SyncRun $syncRun,
        SyncResource $resource,
        ?string $cursor,
        string $checksum,
        ?ImportPeriodData $period,
    ): ImportBatch {
        return ImportBatch::query()->updateOrCreate([
            'sync_run_id' => $syncRun->id,
            'resource' => $resource,
            'page_key' => hash('sha256', $resource->value.'|'.($cursor ?? 'start')),
        ], [
            'seller_account_id' => $syncRun->seller_account_id,
            'source' => $syncRun->sellerAccount->source,
            'status' => SyncResourceStatus::Running,
            'period_start' => $period?->start->toDateString(),
            'period_end' => $period?->end->toDateString(),
            'cursor_in' => $cursor,
            'cursor_out' => null,
            'checksum' => $checksum,
            'imported_count' => 0,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
        ]);
    }

    private function completeBatchAndAdvance(
        ImportBatch $batch,
        SyncResourceState $state,
        ?string $nextCursor,
        ?string $checkpoint,
        int $count,
    ): void {
        $batch->update([
            'status' => SyncResourceStatus::Completed,
            'cursor_out' => $nextCursor,
            'imported_count' => $count,
            'finished_at' => now(),
        ]);
        $state->update([
            'cursor' => $nextCursor,
            'checkpoint' => $checkpoint,
            'processed_pages' => $state->processed_pages + 1,
        ]);
    }

    private function completeResource(SyncResourceState $state, ?string $checkpoint): void
    {
        $state->update([
            'status' => SyncResourceStatus::Completed,
            'availability' => ResourceAvailability::Available,
            'cursor' => null,
            'checkpoint' => $checkpoint,
            'last_success_at' => now(),
        ]);
    }

    private function markFailed(
        SyncRun $syncRun,
        ?SyncResource $resource,
        string $errorCode = 'sync_failed',
    ): void {
        $syncRun->update([
            'status' => SyncRunStatus::Failed,
            'finished_at' => now(),
            'error_code' => $errorCode,
            'error_summary' => 'Не удалось завершить синхронизацию. Повторите попытку позже.',
        ]);
        SellerAccount::query()
            ->whereKey($syncRun->seller_account_id)
            ->whereNotIn('status', [
                SellerAccountStatus::Disconnected,
                SellerAccountStatus::InvalidCredentials,
            ])
            ->update(['status' => SellerAccountStatus::Partial]);

        if ($resource !== null) {
            SyncResourceState::query()
                ->where('seller_account_id', $syncRun->seller_account_id)
                ->where('resource', $resource)
                ->update([
                    'status' => SyncResourceStatus::Failed,
                    'error_code' => $errorCode,
                    'updated_at' => now(),
                ]);
        }
    }

    private function markRetryable(
        SyncRun $syncRun,
        ?SyncResource $resource,
        string $errorCode,
    ): void {
        $syncRun->update([
            'status' => SyncRunStatus::Pending,
            'error_code' => $errorCode,
            'error_summary' => 'Wildberries временно недоступен. Синхронизация продолжится автоматически.',
        ]);

        if ($resource !== null) {
            SyncResourceState::query()
                ->where('seller_account_id', $syncRun->seller_account_id)
                ->where('resource', $resource)
                ->update([
                    'status' => SyncResourceStatus::Pending,
                    'error_code' => $errorCode,
                    'updated_at' => now(),
                ]);
        }
    }

    private function markInvalidCredential(SyncRun $syncRun, ?SyncResource $resource): void
    {
        $this->markFailed($syncRun, $resource, 'invalid_credentials');
        $syncRun->sellerAccount->credential?->update(['invalidated_at' => now()]);
        SellerAccount::query()
            ->whereKey($syncRun->seller_account_id)
            ->where('status', '!=', SellerAccountStatus::Disconnected)
            ->update(['status' => SellerAccountStatus::InvalidCredentials]);
    }

    /** @param array<mixed>|null $payload */
    private function storeRawPage(ImportBatch $batch, ?array $payload): void
    {
        if ($payload === null || $batch->getRawOriginal('source') === SellerAccountSource::Demo->value) {
            return;
        }

        RawImportPage::query()->updateOrCreate(
            ['import_batch_id' => $batch->id],
            [
                'seller_account_id' => $batch->seller_account_id,
                'payload' => $payload,
                'retrieved_at' => now(),
                'expires_at' => now()->addDays(
                    (int) config('sellerscope.wildberries.raw_page_retention_days', 7),
                ),
            ],
        );
    }

    private function importPeriod(SellerAccountSource $source, string $timezone): ImportPeriodData
    {
        if ($source === SellerAccountSource::Demo) {
            return new ImportPeriodData(
                CarbonImmutable::parse('2026-06-01', $timezone),
                CarbonImmutable::parse('2026-07-31', $timezone),
            );
        }

        $end = CarbonImmutable::now($timezone)->endOfDay();

        return new ImportPeriodData($end->subDays(89)->startOfDay(), $end);
    }

    private function logFailure(
        SyncRun $syncRun,
        ?SyncResource $resource,
        string $errorCode,
    ): void {
        Log::warning('Seller account synchronization failed.', [
            'seller_account_id' => $syncRun->seller_account_id,
            'sync_run_id' => $syncRun->id,
            'resource' => $resource?->value,
            'error_code' => $errorCode,
        ]);
    }

    private function guardPagination(?string $cursorIn, ?string $nextCursor, int $pageNumber): void
    {
        if ($nextCursor !== null && $nextCursor === $cursorIn) {
            throw new WildberriesApiException('pagination_stalled', false);
        }

        $maximum = max(1, (int) config('sellerscope.synchronization.max_pages_per_resource', 10_000));
        if ($nextCursor !== null && $pageNumber >= $maximum) {
            throw new WildberriesApiException('pagination_limit_exceeded', false);
        }
    }

    /** @param array<mixed> $payload */
    private function checksum(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
