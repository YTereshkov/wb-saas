<?php

namespace Tests\Feature\Synchronization;

use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\BackfillHistoricalStocks;
use App\Modules\Synchronization\Actions\CanonicalDataImporter;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Jobs\BackfillHistoricalStocksJob;
use App\Modules\Synchronization\Models\RawImportPage;
use App\Modules\Synchronization\Models\SyncResourceState;
use App\Modules\Synchronization\Queries\InitialSyncStatusQuery;
use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\StockData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class WildberriesSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_sync_imports_and_replays_sanitized_contract_idempotently(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00', 'Europe/Moscow'));
        $this->fakeApi();
        $account = SellerAccount::factory()->create([
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::Verified,
            'wb_account_id' => '11111111-2222-4333-8444-555555555555',
        ]);
        $account->credential()->create([
            'token' => 'real-test-token',
            'fingerprint' => hash('sha256', 'real-test-token'),
            'permissions' => ['products', 'orders', 'sales', 'stocks'],
            'verified_at' => now(),
        ]);

        $firstRun = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($firstRun);

        Queue::assertPushed(BackfillHistoricalStocksJob::class, fn (BackfillHistoricalStocksJob $job): bool => $job->sellerAccountId === $account->id);

        $this->assertSame(2, $account->products()->count());
        $this->assertSame(2, $account->orders()->count());
        $this->assertSame(1, $account->sales()->count());
        $this->assertSame(1, $account->returnFacts()->count());
        $this->assertSame(3, $account->stockSnapshots()->count());
        $this->assertSame(69, (int) $account->stockSnapshots()->sum('quantity'));
        $this->assertNotNull($account->returnFacts()->firstOrFail()->sale_id);
        $this->assertSame(4, RawImportPage::query()->where('seller_account_id', $account->id)->count());
        $this->assertSame(3, SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->whereNotNull('checkpoint')
            ->count());
        $this->assertFalse(SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->whereNotNull('cursor')
            ->exists());

        $account->user->preference()->create([
            'active_seller_account_id' => $account->id,
            'period_preset' => 'last_30_days',
        ]);
        $this->actingAs($account->user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('overview/index')
                ->where('overview.state', 'ready')
                ->where('overview.kpis.0.key', 'revenue')
                ->where('overview.kpis.0.value', 249950)
                ->where('overview.kpis.1.value', 2)
                ->where('overview.kpis.2.value', 1)
                ->where('analyticsContext.period.preset', 'last_30_days')
                ->where('analyticsContext.period.end', '2026-08-22'));

        $this->fakeApi();
        $secondRun = app(StartInitialSync::class)->handle($account->fresh());
        app(RunInitialSync::class)->handle($secondRun);

        $this->assertSame(2, $account->products()->count());
        $this->assertSame(2, $account->orders()->count());
        $this->assertSame(1, $account->sales()->count());
        $this->assertSame(1, $account->returnFacts()->count());
        $this->assertSame(3, $account->stockSnapshots()->count());
        $this->assertSame(2, $account->fresh()->data_revision);
    }

    public function test_real_stock_sync_keeps_daily_snapshots_and_replays_each_day_idempotently(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        $this->fakeApiSnapshots([
            'Sat, 22 Aug 2026 12:00:00 GMT',
            'Sun, 23 Aug 2026 12:00:00 GMT',
            'Sun, 23 Aug 2026 12:00:00 GMT',
        ]);
        app(RunInitialSync::class)->handle(app(StartInitialSync::class)->handle($account));

        app(RunInitialSync::class)->handle(app(StartInitialSync::class)->handle($account->fresh()));
        $this->assertSame(
            ['2026-08-22 12:00:00', '2026-08-23 12:00:00'],
            $account->stockSnapshots()->orderBy('snapshot_at')->pluck('snapshot_at')->map->format('Y-m-d H:i:s')->unique()->values()->all(),
        );
        $this->assertSame(6, $account->stockSnapshots()->count());

        app(RunInitialSync::class)->handle(app(StartInitialSync::class)->handle($account->fresh()));
        $this->assertSame(6, $account->stockSnapshots()->count());
        $this->assertSame(69, (int) $account->stockSnapshots()->whereDate('snapshot_at', '2026-08-23')->sum('quantity'));
    }

    public function test_sync_completes_partially_when_stocks_are_not_supported_by_token(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        $account->credential()->update(['permissions' => ['products', 'orders', 'sales']]);
        $this->fakeApi();

        $run = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($run);

        $this->assertSame(SyncRunStatus::Completed, $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->progress);
        $this->assertSame(SellerAccountStatus::Partial, $account->fresh()->status);
        $this->assertNotNull($account->fresh()->last_sync_completed_at);
        $this->assertNull($account->credential()->firstOrFail()->invalidated_at);
        $stockState = $account->syncResourceStates()
            ->where('resource', SyncResource::Stocks)
            ->firstOrFail();
        $this->assertSame(SyncResourceStatus::Skipped, $stockState->status);
        $this->assertSame(ResourceAvailability::Unavailable, $stockState->availability);
        $this->assertSame('resource_not_supported_by_token', $stockState->error_code);
        $this->assertSame(0, $account->stockSnapshots()->count());
        Queue::assertNotPushed(BackfillHistoricalStocksJob::class);
        Http::assertNotSent(fn ($request): bool => str_contains(
            $request->url(),
            '/api/analytics/v1/stocks-report/wb-warehouses',
        ));

        $status = app(InitialSyncStatusQuery::class)->forAccount($account->fresh());
        $this->assertTrue($status['completed']);
        $this->assertSame('skipped', $status['resources'][3]['status']);
        $this->assertSame('unavailable', $status['resources'][3]['availability']);
    }

    public function test_sandbox_sync_imports_supported_resources_without_production_requests(): void
    {
        Queue::fake();
        $token = $this->sandboxToken();
        $account = SellerAccount::factory()->create([
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::Verified,
            'wb_account_id' => 'sandbox:11111111-2222-4333-8444-555555555555',
        ]);
        $account->credential()->create([
            'token' => $token,
            'fingerprint' => hash('sha256', $token),
            'permissions' => ['products', 'orders', 'sales'],
            'verified_at' => now(),
        ]);
        Http::fake(function ($request) {
            if ($request->url() === 'https://content-api-sandbox.wildberries.ru/content/v2/get/cards/list') {
                return Http::response($this->fixture('products-page.json'));
            }
            if (str_starts_with($request->url(), 'https://statistics-api-sandbox.wildberries.ru/api/v1/supplier/orders')) {
                return Http::response($this->fixture('orders-page.json'));
            }
            if (str_starts_with($request->url(), 'https://statistics-api-sandbox.wildberries.ru/api/v1/supplier/sales')) {
                return Http::response($this->fixture('sales-page.json'));
            }

            return Http::response([], 404);
        });

        $run = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($run);

        $this->assertSame(SyncRunStatus::Completed, $run->fresh()->status);
        $this->assertSame(100, $run->fresh()->progress);
        $this->assertSame(SellerAccountStatus::Partial, $account->fresh()->status);
        $this->assertSame(2, $account->products()->count());
        $this->assertSame(2, $account->orders()->count());
        $this->assertSame(1, $account->sales()->count());
        $this->assertSame(1, $account->returnFacts()->count());
        $this->assertSame(0, $account->stockSnapshots()->count());
        $this->assertSame(SyncResourceStatus::Skipped, $account->syncResourceStates()
            ->where('resource', SyncResource::Stocks)
            ->firstOrFail()
            ->status);
        $status = app(InitialSyncStatusQuery::class)->forAccount($account->fresh());
        $this->assertSame('sandbox', $status['account']['environment']);
        $this->assertSame(
            '11111111-2222-4333-8444-555555555555',
            $status['account']['wbAccountId'],
        );
        Http::assertNotSent(fn ($request): bool => ! str_contains($request->url(), '-sandbox.wildberries.ru'));
        Queue::assertNotPushed(BackfillHistoricalStocksJob::class);
    }

    public function test_sandbox_import_creates_a_placeholder_for_a_fact_with_no_catalog_product(): void
    {
        $account = SellerAccount::factory()->create([
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::Verified,
            'wb_account_id' => 'sandbox:11111111-2222-4333-8444-555555555555',
        ]);
        $orderedAt = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');

        $processed = app(CanonicalDataImporter::class)->orders($account, [
            new OrderData(
                'sandbox-order-without-card',
                '99900001',
                $orderedAt,
                1,
                199_900,
                'new',
                false,
                null,
                $orderedAt,
            ),
        ]);

        $product = $account->products()->firstOrFail();
        $this->assertSame(1, $processed);
        $this->assertSame('99900001', $product->nm_id);
        $this->assertSame('Товар WB 99900001', $product->title);
        $this->assertSame('Товары песочницы WB', $product->category?->name);
        $this->assertSame($product->id, $account->orders()->firstOrFail()->product_id);
    }

    public function test_production_import_rejects_a_fact_with_no_catalog_product(): void
    {
        $account = $this->realAccount();
        $orderedAt = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Canonical fact references an unknown product.');

        app(CanonicalDataImporter::class)->orders($account, [
            new OrderData(
                'production-order-without-card',
                '99900001',
                $orderedAt,
                1,
                199_900,
                'new',
                false,
                null,
                $orderedAt,
            ),
        ]);
    }

    public function test_unsupported_stocks_are_marked_unavailable_before_another_resource_retries(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        $account->credential()->update(['permissions' => ['products', 'orders', 'sales']]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/content/v2/get/cards/list')) {
                return Http::response($this->fixture('products-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/orders')) {
                return Http::response([], 429, ['X-Ratelimit-Retry' => '60']);
            }

            return Http::response([], 404);
        });

        $run = app(StartInitialSync::class)->handle($account);

        try {
            app(RunInitialSync::class)->handle($run);
            $this->fail('Expected a retryable Wildberries exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('rate_limited', $exception->errorCode);
        }

        $this->assertSame(SyncRunStatus::Pending, $run->fresh()->status);
        $stockState = $account->syncResourceStates()
            ->where('resource', SyncResource::Stocks)
            ->firstOrFail();
        $this->assertSame(SyncResourceStatus::Skipped, $stockState->status);
        $this->assertSame(ResourceAvailability::Unavailable, $stockState->availability);
        $this->assertSame('resource_not_supported_by_token', $stockState->error_code);
    }

    public function test_stock_access_denied_marks_only_resource_unavailable(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/content/v2/get/cards/list')) {
                return Http::response($this->fixture('products-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/orders')) {
                return Http::response($this->fixture('orders-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/sales')) {
                return Http::response($this->fixture('sales-page.json'));
            }
            if (str_contains($request->url(), '/api/analytics/v1/stocks-report/wb-warehouses')) {
                return Http::response([
                    'title' => 'Forbidden',
                    'detail' => 'token does not satisfy additional requirements',
                ], 403);
            }

            return Http::response([], 404);
        });

        $run = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($run);

        $this->assertSame(SyncRunStatus::Completed, $run->fresh()->status);
        $this->assertSame(SellerAccountStatus::Partial, $account->fresh()->status);
        $this->assertNull($account->credential()->firstOrFail()->invalidated_at);
        $stockState = $account->syncResourceStates()
            ->where('resource', SyncResource::Stocks)
            ->firstOrFail();
        $this->assertSame(SyncResourceStatus::Skipped, $stockState->status);
        $this->assertSame(ResourceAvailability::Unavailable, $stockState->availability);
        $this->assertSame('resource_access_denied', $stockState->error_code);
    }

    public function test_historical_stock_report_is_backfilled_asynchronously(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        $job = new BackfillHistoricalStocksJob($account->id);
        $this->assertSame(720, $job->tries);
        $this->assertSame(900, $job->timeout);
        $this->assertSame(18_000, $job->uniqueFor);
        Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => '70010001',
            'vendor_code' => 'fixture-plaid',
            'title' => 'Плед фактурный',
            'brand' => 'Текстиль Маркет',
            'is_active' => true,
        ]);
        Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => '70010002',
            'vendor_code' => 'fixture-cover',
            'title' => 'Комплект наволочек',
            'brand' => 'Текстиль Маркет',
            'is_active' => true,
        ]);

        Http::fake([
            'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/downloads' => Http::response(['data' => 'Started']),
        ]);
        try {
            app(BackfillHistoricalStocks::class)->handle($account->fresh());
            $this->fail('Expected asynchronous report wait.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('historical_report_pending', $exception->errorCode);
        }

        $reportId = SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->where('resource', SyncResource::StockHistory)
            ->value('checkpoint');
        $this->assertIsString($reportId);
        Http::fake(function ($request) use ($reportId) {
            if (str_contains($request->url(), '/api/v2/nm-report/downloads?')) {
                return Http::response(['data' => [['id' => $reportId, 'status' => 'SUCCESS']]]);
            }
            if (str_ends_with($request->url(), "/file/{$reportId}")) {
                return Http::response($this->stockHistoryArchive(), 200, ['Content-Type' => 'application/zip']);
            }

            return Http::response([], 404);
        });

        app(BackfillHistoricalStocks::class)->handle($account->fresh());

        $this->assertSame(4, $account->stockSnapshots()->count());
        $this->assertSame(55, (int) $account->stockSnapshots()->where('snapshot_date', '2026-08-20')->sum('quantity'));
        $this->assertSame(54, (int) $account->stockSnapshots()->where('snapshot_date', '2026-08-21')->sum('quantity'));
        $this->assertSame(SyncResourceStatus::Completed, SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->where('resource', SyncResource::StockHistory)
            ->firstOrFail()
            ->status);
        $this->assertSame(1, $account->fresh()->data_revision);
    }

    public function test_historical_report_checkpoint_is_saved_before_create_request(): void
    {
        $account = $this->realAccount();
        Http::fake([
            'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/downloads' => Http::failedConnection(),
        ]);

        try {
            app(BackfillHistoricalStocks::class)->handle($account);
            $this->fail('Expected failed report creation request.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('connection_failed', $exception->errorCode);
        }

        $this->assertIsString(SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->where('resource', SyncResource::StockHistory)
            ->value('checkpoint'));
    }

    public function test_stock_import_accepts_a_generator_and_deduplicates_conflict_keys(): void
    {
        $account = $this->realAccount();
        $product = Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => '70010001',
            'vendor_code' => 'fixture-plaid',
            'title' => 'Плед фактурный',
            'brand' => 'Текстиль Маркет',
            'is_active' => true,
        ]);
        $snapshotAt = CarbonImmutable::parse('2026-08-20 20:59:59', 'UTC');
        $items = (function () use ($snapshotAt): \Generator {
            yield new StockData('70010001', 'history:test', 'Коледино', 'wb', null, '900001', $snapshotAt, 40);
            yield new StockData('70010001', 'history:test', 'Коледино', 'wb', null, '900001', $snapshotAt, 42);
        })();

        app(CanonicalDataImporter::class)->stocks($account, $items);

        $this->assertSame(1, $account->stockSnapshots()->where('product_id', $product->id)->count());
        $this->assertSame(42, (int) $account->stockSnapshots()->where('product_id', $product->id)->value('quantity'));
    }

    public function test_product_import_deduplicates_conflict_keys_and_keeps_last_item(): void
    {
        $account = $this->realAccount();
        $updatedAt = CarbonImmutable::parse('2026-08-20 12:00:00', 'UTC');
        $items = [
            new ProductData('70010001', 'old-code', 'Старое название', 'Бренд', '10', 'Текстиль', null, true, $updatedAt),
            new ProductData('70010001', 'new-code', 'Новое название', 'Бренд', '10', 'Текстиль', null, true, $updatedAt->addMinute()),
        ];

        $processed = app(CanonicalDataImporter::class)->products($account, $items);

        $this->assertSame(2, $processed);
        $this->assertSame(1, $account->products()->count());
        $this->assertSame('new-code', $account->products()->firstOrFail()->vendor_code);
    }

    public function test_completed_sync_does_not_reactivate_a_disconnected_account(): void
    {
        Queue::fake();
        $account = $this->realAccount();
        Http::fake(function ($request) use ($account) {
            if (str_contains($request->url(), '/content/v2/get/cards/list')) {
                $account->update(['status' => SellerAccountStatus::Disconnected]);

                return Http::response($this->fixture('products-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/orders')) {
                return Http::response($this->fixture('orders-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/sales')) {
                return Http::response($this->fixture('sales-page.json'));
            }
            if (str_contains($request->url(), '/api/analytics/v1/stocks-report/wb-warehouses')) {
                return Http::response($this->fixture('stocks-page.json'), headers: ['Date' => 'Sat, 22 Aug 2026 12:00:00 GMT']);
            }

            return Http::response([], 404);
        });

        app(RunInitialSync::class)->handle(app(StartInitialSync::class)->handle($account));

        $this->assertSame(SellerAccountStatus::Disconnected, $account->fresh()->status);
    }

    private function fakeApi(string $snapshotDate = 'Sat, 22 Aug 2026 12:00:00 GMT'): void
    {
        $this->fakeApiSnapshots([$snapshotDate]);
    }

    /** @param non-empty-list<string> $snapshotDates */
    private function fakeApiSnapshots(array $snapshotDates): void
    {
        $stockRequest = 0;
        Http::fake(function ($request) use ($snapshotDates, &$stockRequest) {
            if (str_contains($request->url(), '/content/v2/get/cards/list')) {
                return Http::response($this->fixture('products-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/orders')) {
                return Http::response($this->fixture('orders-page.json'));
            }
            if (str_contains($request->url(), '/api/v1/supplier/sales')) {
                return Http::response($this->fixture('sales-page.json'));
            }
            if (str_contains($request->url(), '/api/analytics/v1/stocks-report/wb-warehouses')) {
                $date = $snapshotDates[min($stockRequest, count($snapshotDates) - 1)];
                $stockRequest++;

                return Http::response($this->fixture('stocks-page.json'), headers: ['Date' => $date]);
            }

            return Http::response([], 404);
        });
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(base_path("tests/Fixtures/Wildberries/{$name}"));
        $this->assertNotFalse($contents);

        return $contents;
    }

    private function realAccount(): SellerAccount
    {
        $account = SellerAccount::factory()->create([
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::Verified,
            'wb_account_id' => '11111111-2222-4333-8444-555555555555',
        ]);
        $account->credential()->create([
            'token' => 'real-test-token',
            'fingerprint' => hash('sha256', 'real-test-token'),
            'permissions' => ['products', 'orders', 'sales', 'stocks'],
            'verified_at' => now(),
        ]);

        return $account;
    }

    private function sandboxToken(): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(
            base64_encode(json_encode($value, JSON_THROW_ON_ERROR)),
            '+/',
            '-_',
        ), '=');

        return $encode(['alg' => 'ES256', 'typ' => 'JWT']).'.'.$encode([
            'acc' => 2,
            's' => 0,
            'sid' => '11111111-2222-4333-8444-555555555555',
            't' => true,
        ]).'.signature';
    }

    private function stockHistoryArchive(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wb-history-test-');
        $this->assertNotFalse($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('stock-history.csv', $this->fixture('stock-history.csv')));
        $this->assertTrue($zip->close());
        $archive = file_get_contents($path);
        @unlink($path);
        $this->assertNotFalse($archive);

        return $archive;
    }
}
