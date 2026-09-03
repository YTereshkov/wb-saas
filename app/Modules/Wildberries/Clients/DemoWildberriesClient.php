<?php

namespace App\Modules\Wildberries\Clients;

use App\Modules\Wildberries\Contracts\WildberriesClientInterface;
use App\Modules\Wildberries\Data\ConnectionCheckData;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\ReturnData;
use App\Modules\Wildberries\Data\SaleData;
use App\Modules\Wildberries\Data\SalesPageData;
use App\Modules\Wildberries\Data\StockData;
use App\Modules\Wildberries\Demo\DemoWildberriesDataset;
use Carbon\CarbonImmutable;

final readonly class DemoWildberriesClient implements WildberriesClientInterface
{
    private const PAGE_SIZE = 80;

    public function __construct(private DemoWildberriesDataset $dataset) {}

    public function checkConnection(): ConnectionCheckData
    {
        return new ConnectionCheckData(true, '218547392', [
            'products', 'orders', 'sales', 'stocks',
        ], 'Текстиль Маркет');
    }

    /** @return FetchPageData<ProductData> */
    public function fetchProducts(?string $cursor = null): FetchPageData
    {
        return $this->page($this->dataset->products(), $cursor, 'products');
    }

    /** @return FetchPageData<OrderData> */
    public function fetchOrders(ImportPeriodData $period, ?string $cursor = null): FetchPageData
    {
        $items = array_values(array_filter(
            $this->dataset->orders(),
            fn (OrderData $order): bool => $this->insidePeriod($order->orderedAt, $period),
        ));

        return $this->page($items, $cursor, 'orders');
    }

    public function fetchSales(ImportPeriodData $period, ?string $cursor = null): SalesPageData
    {
        $sales = array_values(array_filter(
            $this->dataset->sales(),
            fn (SaleData $sale): bool => $this->insidePeriod($sale->soldAt, $period),
        ));
        $offset = $this->offset($cursor, 'sales');
        $pageSales = array_slice($sales, $offset, self::PAGE_SIZE);
        $nextOffset = $offset + count($pageSales);
        $nextCursor = $nextOffset < count($sales) ? "sales:{$nextOffset}" : null;

        $returns = $nextCursor === null
            ? array_values(array_filter(
                $this->dataset->returns(),
                fn (ReturnData $return): bool => $this->insidePeriod($return->returnedAt, $period),
            ))
            : [];

        return new SalesPageData($pageSales, $returns, $nextCursor);
    }

    /** @return FetchPageData<StockData> */
    public function fetchStocks(?string $cursor = null): FetchPageData
    {
        return $this->page($this->dataset->stocks(), $cursor, 'stocks');
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return FetchPageData<T>
     */
    private function page(array $items, ?string $cursor, string $resource): FetchPageData
    {
        $offset = $this->offset($cursor, $resource);
        $pageItems = array_slice($items, $offset, self::PAGE_SIZE);
        $nextOffset = $offset + count($pageItems);

        return new FetchPageData(
            $pageItems,
            $nextOffset < count($items) ? "{$resource}:{$nextOffset}" : null,
        );
    }

    private function offset(?string $cursor, string $resource): int
    {
        if ($cursor === null) {
            return 0;
        }

        if (! preg_match('/^'.preg_quote($resource, '/').':(\d+)$/', $cursor, $matches)) {
            throw new \InvalidArgumentException("Invalid {$resource} cursor.");
        }

        return (int) $matches[1];
    }

    private function insidePeriod(CarbonImmutable $date, ImportPeriodData $period): bool
    {
        return $date->betweenIncluded($period->start->startOfDay(), $period->end->endOfDay());
    }
}
