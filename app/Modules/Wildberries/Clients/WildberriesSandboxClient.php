<?php

namespace App\Modules\Wildberries\Clients;

use App\Modules\Wildberries\Data\ConnectionCheckData;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\StockData;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;

final readonly class WildberriesSandboxClient extends WildberriesHttpClient
{
    public function checkConnection(): ConnectionCheckData
    {
        $sellerAccountId = $this->tokenInspector->sellerAccountId($this->token);
        if ($sellerAccountId === null) {
            throw new WildberriesApiException('invalid_credentials', false, httpStatus: 401);
        }

        $this->send('content', 'GET', '/ping');
        $this->send('statistics', 'GET', '/ping');

        return new ConnectionCheckData(
            true,
            "sandbox:{$sellerAccountId}",
            ['products', 'orders', 'sales'],
            'Тестовый кабинет WB',
        );
    }

    /** @return FetchPageData<StockData> */
    public function fetchStocks(?string $cursor = null): FetchPageData
    {
        throw new WildberriesApiException(
            'resource_not_supported_by_sandbox',
            false,
            httpStatus: 403,
        );
    }

    protected function host(string $service): ?string
    {
        $host = config("sellerscope.wildberries.sandbox_hosts.{$service}");

        return is_string($host) ? $host : null;
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        return [];
    }

    protected function statisticsDateFrom(ImportPeriodData $period): string
    {
        return $period->start->toDateString();
    }
}
