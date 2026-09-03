<?php

namespace Tests\Unit\Wildberries;

use App\Modules\Wildberries\Clients\WildberriesHttpClient;
use App\Modules\Wildberries\Clients\WildberriesHttpClientFactory;
use App\Modules\Wildberries\Clients\WildberriesSandboxClient;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\Normalizers\HistoricalStockCsvNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class WildberriesHttpClientTest extends TestCase
{
    public function test_connection_check_discovers_seller_and_required_scopes_without_leaking_token(): void
    {
        $this->fakeConnection();

        $connection = $this->client()->checkConnection();

        $this->assertTrue($connection->successful);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $connection->externalAccountId);
        $this->assertSame('ООО Северный Дом', $connection->accountName);
        $this->assertSame(['products', 'orders', 'sales', 'stocks'], $connection->availableResources);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer real-test-token'));
    }

    public function test_connection_check_uses_token_account_id_when_seller_info_is_rate_limited(): void
    {
        $accountId = '11111111-2222-4333-8444-555555555555';
        $token = $this->token(['sid' => $accountId, 'acc' => 1]);
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([], 429, [
                'X-Ratelimit-Retry' => '84182',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $connection = app(WildberriesHttpClientFactory::class)
            ->forToken($token)
            ->checkConnection();

        $this->assertSame($accountId, $connection->externalAccountId);
        $this->assertNull($connection->accountName);
        $this->assertSame(['products', 'orders', 'sales'], $connection->availableResources);
    }

    public function test_sandbox_connection_uses_only_sandbox_hosts_and_excludes_stocks(): void
    {
        $accountId = '11111111-2222-4333-8444-555555555555';
        $token = $this->token(['sid' => $accountId, 'acc' => 2, 's' => 0, 't' => true]);
        Http::fake([
            'https://content-api-sandbox.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api-sandbox.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api-sandbox.wildberries.ru/api/v1/supplier/orders*' => Http::response([]),
        ]);

        $client = app(WildberriesHttpClientFactory::class)->forSandboxToken($token);
        $connection = $client->checkConnection();

        $this->assertInstanceOf(WildberriesSandboxClient::class, $client);
        $this->assertTrue($connection->successful);
        $this->assertSame("sandbox:{$accountId}", $connection->externalAccountId);
        $this->assertSame('Тестовый кабинет WB', $connection->accountName);
        $this->assertSame(['products', 'orders', 'sales'], $connection->availableResources);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), '-sandbox.wildberries.ru'));

        $client->fetchOrders(new ImportPeriodData(
            CarbonImmutable::parse('2026-05-27', 'Europe/Moscow'),
            CarbonImmutable::parse('2026-08-24', 'Europe/Moscow'),
        ));
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['dateFrom'] ?? null) === '2026-05-27';
        });

        try {
            $client->fetchStocks();
            $this->fail('Expected sandbox stocks to be unsupported.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('resource_not_supported_by_sandbox', $exception->errorCode);
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_product_cursor_and_normalizer_follow_content_contract(): void
    {
        config()->set('sellerscope.wildberries.products_page_limit', 2);
        Http::fakeSequence('https://content-api.wildberries.ru/*')
            ->push($this->fixture('products-page.json'))
            ->push($this->fixture('products-empty-page.json'));

        $first = $this->client()->fetchProducts();
        $second = $this->client()->fetchProducts($first->nextCursor);

        $this->assertCount(2, $first->items);
        $this->assertSame('70010001', $first->items[0]->nmId);
        $this->assertSame('Пледы', $first->items[0]->categoryName);
        $this->assertNotNull($first->checkpoint);
        $this->assertNull($second->nextCursor);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://content-api.wildberries.ru/content/v2/get/cards/list'
            && $request['settings']['sort']['ascending'] === true);
    }

    public function test_orders_sales_returns_and_stock_rows_are_normalized(): void
    {
        Http::fake([
            'https://statistics-api.wildberries.ru/api/v1/supplier/orders*' => Http::response($this->fixture('orders-page.json')),
            'https://statistics-api.wildberries.ru/api/v1/supplier/sales*' => Http::response($this->fixture('sales-page.json')),
            'https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses' => Http::response(
                $this->fixture('stocks-page.json'),
                headers: ['Date' => 'Sat, 22 Aug 2026 12:00:00 GMT'],
            ),
        ]);
        $period = new ImportPeriodData(
            CarbonImmutable::parse('2026-08-01', 'Europe/Moscow'),
            CarbonImmutable::parse('2026-08-22', 'Europe/Moscow')->endOfDay(),
        );

        $orders = $this->client()->fetchOrders($period);
        $sales = $this->client()->fetchSales($period);
        $stocks = $this->client()->fetchStocks();

        $this->assertCount(2, $orders->items);
        $this->assertSame(249_950, $orders->items[0]->amountKopecks);
        $this->assertTrue($orders->items[1]->cancelled);
        $this->assertCount(1, $sales->sales);
        $this->assertCount(1, $sales->returns);
        $this->assertSame('fixture-order-001', $sales->returns[0]->srid);
        $this->assertSame(249_950, $sales->returns[0]->amountKopecks);
        $this->assertCount(3, $stocks->items);
        $this->assertSame('900002', $stocks->items[1]->variantExternalId);
        $this->assertSame('Центральный', $stocks->items[1]->warehouseRegion);
    }

    public function test_empty_stock_report_is_a_successful_zero_stock_result(): void
    {
        Http::fake([
            'https://seller-analytics-api.wildberries.ru/api/analytics/v1/stocks-report/wb-warehouses' => Http::response(
                null,
                204,
                ['Date' => 'Sat, 22 Aug 2026 12:00:00 GMT'],
            ),
        ]);

        $stocks = $this->client()->fetchStocks();

        $this->assertSame([], $stocks->items);
        $this->assertNull($stocks->nextCursor);
        $this->assertNull($stocks->checkpoint);
        $this->assertSame([], $stocks->rawPayload);
    }

    public function test_statistics_cursor_removes_inclusive_boundary_record(): void
    {
        config()->set('sellerscope.wildberries.statistics_page_limit', 2);
        $firstPayload = json_decode($this->fixture('orders-page.json'), true, flags: JSON_THROW_ON_ERROR);
        $secondPayload = [$firstPayload[1], [
            'date' => '2026-08-20T12:02:00',
            'lastChangeDate' => '2026-08-20T12:12:00',
            'nmId' => 70010001,
            'finishedPrice' => 1200,
            'isCancel' => false,
            'srid' => 'fixture-order-003',
        ]];
        Http::fakeSequence('https://statistics-api.wildberries.ru/api/v1/supplier/orders*')
            ->push($firstPayload)
            ->push($secondPayload);
        $period = new ImportPeriodData(
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-22')->endOfDay(),
        );

        $first = $this->client()->fetchOrders($period);
        $second = $this->client()->fetchOrders($period, $first->nextCursor);

        $this->assertSame(['fixture-order-003'], array_column($second->items, 'srid'));
    }

    public function test_historical_stock_report_lifecycle_and_csv_normalization(): void
    {
        $reportId = '06eae887-9d9f-491f-b16a-bb1766fcb8d2';
        $archive = $this->stockHistoryArchive();
        Http::fake(function (Request $request) use ($reportId, $archive) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/nm-report/downloads')) {
                return Http::response(['data' => 'Started']);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/api/v2/nm-report/downloads?')) {
                return Http::response(['data' => [[
                    'id' => $reportId,
                    'status' => 'SUCCESS',
                ]]]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/v2/nm-report/downloads/retry')) {
                return Http::response(['data' => 'Retry']);
            }
            if ($request->method() === 'GET' && str_ends_with($request->url(), "/file/{$reportId}")) {
                return Http::response($archive, 200, ['Content-Type' => 'application/zip']);
            }

            return Http::response([], 404);
        });
        $period = new ImportPeriodData(
            CarbonImmutable::parse('2026-05-24', 'Europe/Moscow'),
            CarbonImmutable::parse('2026-08-21', 'Europe/Moscow')->endOfDay(),
        );
        $client = $this->client();

        $client->createHistoricalStocksReport($reportId, $period);
        $this->assertSame('SUCCESS', $client->historicalStocksReportStatus($reportId));
        $client->retryHistoricalStocksReport($reportId);
        $stocks = $client->downloadHistoricalStocksReport($reportId);

        $this->assertInstanceOf(\Traversable::class, $stocks);
        $stocks = iterator_to_array($stocks, false);

        $this->assertCount(4, $stocks);
        $this->assertSame('70010001', $stocks[0]->productNmId);
        $this->assertSame('900001', $stocks[0]->variantExternalId);
        $this->assertSame('2026-08-20', $stocks[0]->snapshotAt->setTimezone('Europe/Moscow')->toDateString());
        $this->assertSame(42, $stocks[1]->quantity);
        $this->assertSame('marketplace', $stocks[2]->warehouseType);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v2/nm-report/downloads')
            && $request['reportType'] === 'STOCK_HISTORY_DAILY_CSV'
            && $request['params']['currentPeriod']['end'] === '2026-08-21');
    }

    public function test_historical_stock_report_skips_empty_date_cells(): void
    {
        $csv = str_replace(',40,42,RUB', ',40,,RUB', $this->fixture('stock-history.csv'));
        $stocks = app(HistoricalStockCsvNormalizer::class)->normalize(
            $this->stockHistoryArchive($csv),
            'Europe/Moscow',
        );

        $this->assertInstanceOf(\Traversable::class, $stocks);
        $stocks = iterator_to_array($stocks, false);

        $this->assertCount(3, $stocks);
        $this->assertSame(40, $stocks[0]->quantity);
        $this->assertSame('2026-08-20', $stocks[0]->snapshotAt->setTimezone('Europe/Moscow')->toDateString());
    }

    public function test_historical_report_download_is_lazy_when_not_iterated(): void
    {
        Http::fake();

        $stocks = $this->client()->downloadHistoricalStocksReport('report-id');

        $this->assertInstanceOf(\Traversable::class, $stocks);
        Http::assertNothingSent();
    }

    public function test_historical_stock_report_rejects_negative_quantities(): void
    {
        $csv = str_replace(',40,42,RUB', ',-1,42,RUB', $this->fixture('stock-history.csv'));
        $stocks = app(HistoricalStockCsvNormalizer::class)->normalize(
            $this->stockHistoryArchive($csv),
            'Europe/Moscow',
        );

        $this->expectExceptionObject(WildberriesApiException::schemaDrift());

        iterator_to_array($stocks, false);
    }

    public function test_rate_limit_uses_retry_after_header(): void
    {
        Http::fake(['https://content-api.wildberries.ru/*' => Http::response([], 429, ['X-Ratelimit-Retry' => '37'])]);

        try {
            $this->client()->fetchProducts();
            $this->fail('Expected rate-limit exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('rate_limited', $exception->errorCode);
            $this->assertTrue($exception->retryable);
            $this->assertSame(37, $exception->retryAfterSeconds);
        }

    }

    public function test_rate_limit_keeps_a_full_day_retry_header(): void
    {
        Http::fake(['https://content-api.wildberries.ru/*' => Http::response([], 429, ['X-Ratelimit-Retry' => '84182'])]);

        try {
            $this->client()->fetchProducts();
            $this->fail('Expected rate-limit exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame(84182, $exception->retryAfterSeconds);
        }
    }

    public function test_upstream_error_is_retryable(): void
    {
        Http::fake(['https://content-api.wildberries.ru/*' => Http::response([], 503)]);
        try {
            $this->client()->fetchProducts();
            $this->fail('Expected upstream exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('upstream_unavailable', $exception->errorCode);
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_timeout_is_retryable(): void
    {
        Http::fake(['https://content-api.wildberries.ru/*' => Http::failedConnection()]);
        try {
            $this->client()->fetchProducts();
            $this->fail('Expected connection exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('connection_failed', $exception->errorCode);
            $this->assertTrue($exception->retryable);
        }

    }

    public function test_schema_drift_is_permanent(): void
    {
        Http::fake(['https://content-api.wildberries.ru/*' => Http::response(['unexpected' => true])]);
        $this->expectExceptionObject(WildberriesApiException::schemaDrift());
        $this->client()->fetchProducts();
    }

    public function test_forbidden_response_is_permanent_access_denied(): void
    {
        Http::fake(['https://common-api.wildberries.ru/*' => Http::response([], 403)]);

        try {
            $this->client()->checkConnection();
            $this->fail('Expected authorization exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('access_denied', $exception->errorCode);
            $this->assertTrue($exception->isAuthorizationFailure());
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_unauthorized_response_is_permanent_invalid_credential(): void
    {
        Http::fake(['https://common-api.wildberries.ru/*' => Http::response([], 401)]);

        try {
            $this->client()->checkConnection();
            $this->fail('Expected authentication exception.');
        } catch (WildberriesApiException $exception) {
            $this->assertSame('invalid_credentials', $exception->errorCode);
            $this->assertTrue($exception->isAuthorizationFailure());
            $this->assertFalse($exception->retryable);
        }
    }

    private function client(): WildberriesHttpClient
    {
        return app(WildberriesHttpClientFactory::class)->forToken('real-test-token');
    }

    private function fakeConnection(): void
    {
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response($this->fixture('seller-info.json')),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(
            base64_encode(json_encode($value, JSON_THROW_ON_ERROR)),
            '+/',
            '-_',
        ), '=');

        return $encode(['alg' => 'ES256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(base_path("tests/Fixtures/Wildberries/{$name}"));
        $this->assertNotFalse($contents);

        return $contents;
    }

    private function stockHistoryArchive(?string $csv = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wb-history-test-');
        $this->assertNotFalse($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('stock-history.csv', $csv ?? $this->fixture('stock-history.csv')));
        $this->assertTrue($zip->close());
        $archive = file_get_contents($path);
        @unlink($path);
        $this->assertNotFalse($archive);

        return $archive;
    }
}
