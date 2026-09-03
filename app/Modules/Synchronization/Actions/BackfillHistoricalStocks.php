<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\Analytics\Actions\RebuildAnalytics;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Models\SyncResourceState;
use App\Modules\Wildberries\Contracts\HistoricalStocksClientInterface;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\WildberriesClientResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class BackfillHistoricalStocks
{
    public function __construct(
        private WildberriesClientResolver $clientResolver,
        private CanonicalDataImporter $importer,
        private RebuildAnalytics $rebuildAnalytics,
    ) {}

    public function handle(SellerAccount $account): void
    {
        if ($account->source !== SellerAccountSource::Wildberries
            || $account->status === SellerAccountStatus::Disconnected) {
            return;
        }

        $client = $this->clientResolver->resolve($account);
        if (! $client instanceof HistoricalStocksClientInterface) {
            return;
        }

        $state = SyncResourceState::query()->firstOrCreate([
            'seller_account_id' => $account->id,
            'resource' => SyncResource::StockHistory,
        ], [
            'status' => SyncResourceStatus::Pending,
            'availability' => ResourceAvailability::Unknown,
            'processed_pages' => 0,
        ]);
        if ($state->status === SyncResourceStatus::Completed) {
            return;
        }

        $reportId = $state->checkpoint;
        if ($reportId === null) {
            $reportId = (string) Str::uuid();
            $state->update([
                'status' => SyncResourceStatus::Pending,
                'checkpoint' => $reportId,
                'last_attempt_at' => now(),
                'error_code' => null,
            ]);
            $client->createHistoricalStocksReport($reportId, $this->period($account));

            throw new WildberriesApiException('historical_report_pending', true, 20);
        }

        $status = $client->historicalStocksReportStatus($reportId);
        if (in_array($status, ['WAITING', 'PROCESSING', 'RETRY'], true)) {
            $state->update(['last_attempt_at' => now(), 'error_code' => null]);
            throw new WildberriesApiException('historical_report_pending', true, 20);
        }
        if ($status === 'FAILED') {
            $client->retryHistoricalStocksReport($reportId);
            $state->update(['last_attempt_at' => now(), 'error_code' => 'historical_report_retry']);
            throw new WildberriesApiException('historical_report_pending', true, 20);
        }
        if ($status !== 'SUCCESS') {
            throw WildberriesApiException::schemaDrift();
        }

        $state->update(['status' => SyncResourceStatus::Running, 'last_attempt_at' => now()]);
        $this->importer->stocks($account, $client->downloadHistoricalStocksReport($reportId));
        $this->rebuildAnalytics->handle($account);
        $state->update([
            'status' => SyncResourceStatus::Completed,
            'availability' => ResourceAvailability::Available,
            'processed_pages' => $state->processed_pages + 1,
            'last_success_at' => now(),
            'error_code' => null,
        ]);
    }

    private function period(SellerAccount $account): ImportPeriodData
    {
        $end = CarbonImmutable::now($account->timezone)->subDay()->endOfDay();

        return new ImportPeriodData($end->subDays(89)->startOfDay(), $end);
    }
}
