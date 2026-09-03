<?php

namespace App\Modules\Wildberries\Contracts;

use App\Modules\Wildberries\Data\ConnectionCheckData;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\SalesPageData;
use App\Modules\Wildberries\Data\StockData;

interface WildberriesClientInterface
{
    public function checkConnection(): ConnectionCheckData;

    /** @return FetchPageData<ProductData> */
    public function fetchProducts(?string $cursor = null): FetchPageData;

    /** @return FetchPageData<OrderData> */
    public function fetchOrders(ImportPeriodData $period, ?string $cursor = null): FetchPageData;

    public function fetchSales(ImportPeriodData $period, ?string $cursor = null): SalesPageData;

    /** @return FetchPageData<StockData> */
    public function fetchStocks(?string $cursor = null): FetchPageData;
}
