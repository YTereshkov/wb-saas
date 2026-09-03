<?php

namespace App\Modules\Wildberries\Clients;

use App\Modules\Wildberries\Contracts\HistoricalStocksClientInterface;
use App\Modules\Wildberries\Contracts\WildberriesClientInterface;
use App\Modules\Wildberries\Data\ConnectionCheckData;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\ReturnData;
use App\Modules\Wildberries\Data\SalesPageData;
use App\Modules\Wildberries\Data\StockData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use App\Modules\Wildberries\Normalizers\HistoricalStockCsvNormalizer;
use App\Modules\Wildberries\Normalizers\OrderNormalizer;
use App\Modules\Wildberries\Normalizers\ProductNormalizer;
use App\Modules\Wildberries\Normalizers\SalesNormalizer;
use App\Modules\Wildberries\Normalizers\StockNormalizer;
use App\Modules\Wildberries\Support\WildberriesCursor;
use App\Modules\Wildberries\Support\WildberriesTokenInspector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

readonly class WildberriesHttpClient implements HistoricalStocksClientInterface, WildberriesClientInterface
{
    public function __construct(
        protected string $token,
        private ProductNormalizer $productNormalizer,
        private OrderNormalizer $orderNormalizer,
        private SalesNormalizer $salesNormalizer,
        private StockNormalizer $stockNormalizer,
        private HistoricalStockCsvNormalizer $historicalStockCsvNormalizer,
        protected WildberriesTokenInspector $tokenInspector,
        private string $timezone,
    ) {}

    public function checkConnection(): ConnectionCheckData
    {
        [$externalAccountId, $accountName] = $this->sellerIdentity();
        $resources = [];

        if ($this->pingAvailable('content')) {
            $resources[] = 'products';
        }
        if ($this->pingAvailable('statistics')) {
            $resources[] = 'orders';
            $resources[] = 'sales';
        }
        if ($this->pingAvailable('analytics') && $this->tokenInspector->supportsCurrentStocks($this->token)) {
            $resources[] = 'stocks';
        }

        return new ConnectionCheckData(true, $externalAccountId, $resources, $accountName);
    }

    /** @return FetchPageData<ProductData> */
    public function fetchProducts(?string $cursor = null): FetchPageData
    {
        $cursorData = WildberriesCursor::decode($cursor, 'products');
        $limit = max(1, min(100, (int) config('sellerscope.wildberries.products_page_limit', 100)));
        $requestCursor = ['limit' => $limit];
        if (isset($cursorData['updated_at'], $cursorData['nm_id'])) {
            $requestCursor['updatedAt'] = $this->cursorString($cursorData, 'updated_at');
            $requestCursor['nmID'] = $this->cursorInteger($cursorData, 'nm_id');
        }

        $response = $this->send('content', 'POST', '/content/v2/get/cards/list', body: [
            'settings' => [
                'sort' => ['ascending' => true],
                'cursor' => $requestCursor,
                'filter' => ['withPhoto' => -1],
            ],
        ]);
        $payload = $this->object($response);
        $cards = $this->list($payload['cards'] ?? null);
        $responseCursor = $payload['cursor'] ?? null;
        if (! is_array($responseCursor)) {
            throw WildberriesApiException::schemaDrift();
        }

        $checkpoint = $cursor;
        if ($cards !== []) {
            $checkpoint = WildberriesCursor::encode('products', [
                'updated_at' => $this->requiredString($responseCursor, 'updatedAt'),
                'nm_id' => $this->requiredInteger($responseCursor, 'nmID'),
            ]);
        }
        $total = $this->requiredInteger($responseCursor, 'total');

        return new FetchPageData(
            array_map(fn (array $card): ProductData => $this->productNormalizer->normalize($card), $cards),
            $total >= $limit && $cards !== [] ? $checkpoint : null,
            $checkpoint,
            $payload,
        );
    }

    /** @return FetchPageData<OrderData> */
    public function fetchOrders(ImportPeriodData $period, ?string $cursor = null): FetchPageData
    {
        [$records, $nextCursor, $checkpoint, $raw] = $this->statisticsPage(
            'orders',
            '/api/v1/supplier/orders',
            'srid',
            $period,
            $cursor,
        );

        return new FetchPageData(
            array_map(fn (array $record): OrderData => $this->orderNormalizer->normalize($record), $records),
            $nextCursor,
            $checkpoint,
            $raw,
        );
    }

    public function fetchSales(ImportPeriodData $period, ?string $cursor = null): SalesPageData
    {
        [$records, $nextCursor, $checkpoint, $raw] = $this->statisticsPage(
            'sales',
            '/api/v1/supplier/sales',
            'saleID',
            $period,
            $cursor,
        );
        $sales = [];
        $returns = [];

        foreach ($records as $record) {
            $item = $this->salesNormalizer->normalize($record);
            if ($item instanceof ReturnData) {
                $returns[] = $item;
            } else {
                $sales[] = $item;
            }
        }

        return new SalesPageData($sales, $returns, $nextCursor, $checkpoint, $raw);
    }

    /** @return FetchPageData<StockData> */
    public function fetchStocks(?string $cursor = null): FetchPageData
    {
        $cursorData = WildberriesCursor::decode($cursor, 'stocks');
        $offset = isset($cursorData['offset']) ? $this->cursorInteger($cursorData, 'offset') : 0;
        $limit = max(1, (int) config('sellerscope.wildberries.stocks_page_limit', 250_000));
        $response = $this->send(
            'analytics',
            'POST',
            '/api/analytics/v1/stocks-report/wb-warehouses',
            body: ['nmIds' => [], 'chrtIds' => [], 'limit' => $limit, 'offset' => $offset],
        );
        if ($response->noContent()) {
            return new FetchPageData([], null, null, []);
        }

        $payload = $this->object($response);
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            throw WildberriesApiException::schemaDrift();
        }
        $items = $this->list($data['items'] ?? null);
        $snapshotAt = isset($cursorData['snapshot_at'])
            ? $this->parseDate($this->cursorString($cursorData, 'snapshot_at'))
            : $this->responseDate($response);
        $nextCursor = count($items) >= $limit
            ? WildberriesCursor::encode('stocks', [
                'offset' => $offset + count($items),
                'snapshot_at' => $snapshotAt->toIso8601String(),
            ])
            : null;

        return new FetchPageData(
            array_map(
                fn (array $item): StockData => $this->stockNormalizer->normalize($item, $snapshotAt),
                $items,
            ),
            $nextCursor,
            null,
            $payload,
        );
    }

    public function createHistoricalStocksReport(string $reportId, ImportPeriodData $period): void
    {
        $payload = $this->object($this->send(
            'analytics',
            'POST',
            '/api/v2/nm-report/downloads',
            body: [
                'id' => $reportId,
                'reportType' => 'STOCK_HISTORY_DAILY_CSV',
                'userReportName' => 'SellerScope stock history',
                'params' => [
                    'nmIds' => [],
                    'subjectIds' => [],
                    'brandNames' => [],
                    'tagIds' => [],
                    'currentPeriod' => [
                        'start' => $period->start->toDateString(),
                        'end' => $period->end->toDateString(),
                    ],
                    'stockType' => '',
                    'skipDeletedNm' => true,
                ],
            ],
        ));
        $this->requiredString($payload, 'data');
    }

    public function historicalStocksReportStatus(string $reportId): string
    {
        $payload = $this->object($this->send(
            'analytics',
            'GET',
            '/api/v2/nm-report/downloads',
            ['filter[downloadIds]' => $reportId],
        ));

        foreach ($this->list($payload['data'] ?? null) as $report) {
            if ($this->requiredString($report, 'id') === $reportId) {
                return strtoupper($this->requiredString($report, 'status'));
            }
        }

        throw new WildberriesApiException('historical_report_not_visible', true, 20);
    }

    public function retryHistoricalStocksReport(string $reportId): void
    {
        $payload = $this->object($this->send(
            'analytics',
            'POST',
            '/api/v2/nm-report/downloads/retry',
            body: ['downloadId' => $reportId],
        ));
        $this->requiredString($payload, 'data');
    }

    /** @return iterable<StockData> */
    public function downloadHistoricalStocksReport(string $reportId): iterable
    {
        $path = tempnam(sys_get_temp_dir(), 'wb-stock-history-download-');
        if ($path === false) {
            throw new WildberriesApiException('historical_report_unreadable', false);
        }

        try {
            $this->send(
                'analytics',
                'GET',
                "/api/v2/nm-report/downloads/file/{$reportId}",
                expectsJson: false,
                sink: $path,
            );
            yield from $this->historicalStockCsvNormalizer->normalizeFile($path, $this->timezone);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        } finally {
            @unlink($path);
        }
    }

    private function pingAvailable(string $service): bool
    {
        try {
            $this->send($service, 'GET', '/ping');

            return true;
        } catch (WildberriesApiException $exception) {
            if ($exception->isAuthorizationFailure()) {
                return false;
            }

            throw $exception;
        }
    }

    /** @return array{0: string, 1: ?string} */
    private function sellerIdentity(): array
    {
        try {
            $seller = $this->object($this->send('common', 'GET', '/api/v1/seller-info'));

            return [
                $this->requiredString($seller, 'sid'),
                $this->requiredString($seller, 'name'),
            ];
        } catch (WildberriesApiException $exception) {
            $accountId = $this->tokenInspector->sellerAccountId($this->token);
            if ($exception->errorCode !== 'rate_limited' || $accountId === null) {
                throw $exception;
            }

            return [$accountId, null];
        }
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: ?string, 2: ?string, 3: array<mixed>}
     */
    private function statisticsPage(
        string $resource,
        string $path,
        string $identifier,
        ImportPeriodData $period,
        ?string $cursor,
    ): array {
        $cursorData = WildberriesCursor::decode($cursor, $resource);
        $dateFrom = isset($cursorData['date_from'])
            ? $this->cursorString($cursorData, 'date_from')
            : $this->statisticsDateFrom($period);
        $response = $this->send('statistics', 'GET', $path, [
            'dateFrom' => $dateFrom,
            'flag' => 0,
        ]);
        $raw = $this->list($response->json());
        $knownBoundaryIds = $this->cursorStringList($cursorData, 'boundary_ids');
        $records = array_values(array_filter(
            $raw,
            fn (array $record): bool => ! (
                ($record['lastChangeDate'] ?? null) === $dateFrom
                && in_array((string) ($record[$identifier] ?? ''), $knownBoundaryIds, true)
            ),
        ));

        if ($raw === []) {
            return [$records, null, $cursor, $raw];
        }

        $last = $raw[array_key_last($raw)];
        $lastChangeDate = $this->requiredString($last, 'lastChangeDate');
        $boundaryIds = [];
        foreach ($raw as $record) {
            if (($record['lastChangeDate'] ?? null) === $lastChangeDate) {
                $boundaryIds[] = $this->requiredString($record, $identifier);
            }
        }
        $checkpoint = WildberriesCursor::encode($resource, [
            'date_from' => $lastChangeDate,
            'boundary_ids' => array_values(array_unique($boundaryIds)),
        ]);
        $limit = max(1, (int) config('sellerscope.wildberries.statistics_page_limit', 80_000));
        $nextCursor = count($raw) >= $limit ? $checkpoint : null;

        if ($nextCursor !== null && $records === [] && $checkpoint === $cursor) {
            throw new WildberriesApiException('pagination_stalled', false);
        }

        return [$records, $nextCursor, $checkpoint, $raw];
    }

    protected function statisticsDateFrom(ImportPeriodData $period): string
    {
        return $period->start->setTimezone($this->timezone)->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    protected function send(
        string $service,
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        bool $expectsJson = true,
        ?string $sink = null,
    ): Response {
        $host = $this->host($service);
        if (! is_string($host) || ! str_starts_with($host, 'https://')) {
            throw new WildberriesApiException('invalid_host_configuration', false);
        }

        try {
            $client = Http::baseUrl(rtrim($host, '/'))
                ->withToken($this->token)
                ->withHeaders($this->headers())
                ->connectTimeout((int) config('sellerscope.wildberries.connect_timeout', 5))
                ->timeout((int) config('sellerscope.wildberries.timeout', 20));
            $client = $expectsJson
                ? $client->acceptJson()->asJson()
                : $client->accept('application/zip');
            if ($sink !== null) {
                $client = $client->sink($sink);
            }
            $response = $client->send($method, $path, array_filter([
                'query' => $query === [] ? null : $query,
                'json' => $body === [] ? null : $body,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (ConnectionException $exception) {
            throw new WildberriesApiException('connection_failed', true, previous: $exception);
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        if ($status === 401) {
            throw new WildberriesApiException('invalid_credentials', false, httpStatus: $status);
        }
        if ($status === 403) {
            throw new WildberriesApiException('access_denied', false, httpStatus: $status);
        }
        if ($status === 429) {
            throw new WildberriesApiException(
                'rate_limited',
                true,
                $this->retryAfter($response),
                $status,
            );
        }
        if ($status >= 500) {
            throw new WildberriesApiException('upstream_unavailable', true, httpStatus: $status);
        }

        throw new WildberriesApiException('request_rejected', false, httpStatus: $status);
    }

    protected function host(string $service): ?string
    {
        $host = config("sellerscope.wildberries.hosts.{$service}");

        return is_string($host) ? $host : null;
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $clientSecret = config('sellerscope.wildberries.client_secret');

        return is_string($clientSecret) && $clientSecret !== ''
            ? ['X-Client-Secret' => $clientSecret]
            : [];
    }

    /** @return array<string, mixed> */
    private function object(Response $response): array
    {
        $payload = $response->json();
        if (! is_array($payload) || array_is_list($payload)) {
            throw WildberriesApiException::schemaDrift();
        }

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function list(mixed $payload): array
    {
        if (! is_array($payload) || ! array_is_list($payload)) {
            throw WildberriesApiException::schemaDrift();
        }

        foreach ($payload as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw WildberriesApiException::schemaDrift();
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if ((! is_string($value) && ! is_int($value)) || (string) $value === '') {
            throw WildberriesApiException::schemaDrift();
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $payload */
    private function requiredInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw WildberriesApiException::schemaDrift();
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    private function cursorString(array $payload, string $key): string
    {
        return $this->requiredString($payload, $key);
    }

    /** @param array<string, mixed> $payload */
    private function cursorInteger(array $payload, string $key): int
    {
        return $this->requiredInteger($payload, $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function cursorStringList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (! is_array($value) || ! array_is_list($value)) {
            throw WildberriesApiException::schemaDrift();
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw WildberriesApiException::schemaDrift();
            }
        }

        return $value;
    }

    private function responseDate(Response $response): CarbonImmutable
    {
        $date = $response->header('Date');

        return $date !== '' ? $this->parseDate($date) : CarbonImmutable::now('UTC');
    }

    private function parseDate(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable $exception) {
            throw new WildberriesApiException('schema_drift', false, previous: $exception);
        }
    }

    private function retryAfter(Response $response): int
    {
        $maximum = max(1, (int) config('sellerscope.synchronization.retry_after_max_seconds', 86_400));
        $header = $response->header('X-Ratelimit-Retry');
        if ($header === '') {
            $header = $response->header('Retry-After');
        }
        if ($header === '') {
            $header = $response->header('X-Ratelimit-Reset');
        }
        if (ctype_digit($header)) {
            return max(1, min($maximum, (int) $header));
        }

        if ($header !== '') {
            try {
                return (int) max(1, min($maximum, CarbonImmutable::parse($header)->diffInSeconds(now(), true)));
            } catch (Throwable) {
                // Use conservative fallback below.
            }
        }

        return 60;
    }
}
