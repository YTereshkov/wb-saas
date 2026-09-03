<?php

namespace Tests\Unit\Wildberries;

use App\Modules\Wildberries\Clients\DemoWildberriesClient;
use App\Modules\Wildberries\Contracts\WildberriesClientInterface;
use App\Modules\Wildberries\Data\FetchPageData;
use App\Modules\Wildberries\Data\ImportPeriodData;
use App\Modules\Wildberries\Data\SalesPageData;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DemoWildberriesClientContractTest extends TestCase
{
    public function test_demo_client_implements_contract_and_is_repeatable(): void
    {
        $client = app(DemoWildberriesClient::class);

        $this->assertInstanceOf(WildberriesClientInterface::class, $client);
        $this->assertTrue($client->checkConnection()->successful);
        $this->assertSame(
            array_map(fn ($item): array => $item->toArray(), $client->fetchProducts()->items),
            array_map(fn ($item): array => $item->toArray(), $client->fetchProducts()->items),
        );
    }

    public function test_demo_pages_produce_approved_july_and_june_control_totals(): void
    {
        $client = app(DemoWildberriesClient::class);
        $july = $this->period('2026-07-01', '2026-07-31');
        $june = $this->period('2026-06-01', '2026-06-30');

        $julyOrders = $this->allItems(fn (?string $cursor): FetchPageData => $client->fetchOrders($july, $cursor));
        $juneOrders = $this->allItems(fn (?string $cursor): FetchPageData => $client->fetchOrders($june, $cursor));
        $julySales = $this->allSales($client, $july);
        $juneSales = $this->allSales($client, $june);

        $this->assertSame(1_842, array_sum(array_column($julyOrders, 'quantity')));
        $this->assertSame(1_695, array_sum(array_column($juneOrders, 'quantity')));
        $this->assertSame(1_471, array_sum(array_column($julySales, 'quantity')));
        $this->assertSame(1_335, array_sum(array_column($juneSales, 'quantity')));
        $this->assertSame(128_460_000, array_sum(array_column($julySales, 'amountKopecks')));
        $this->assertSame(114_288_300, array_sum(array_column($juneSales, 'amountKopecks')));
    }

    public function test_invalid_cursor_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(DemoWildberriesClient::class)->fetchProducts('orders:80');
    }

    private function period(string $start, string $end): ImportPeriodData
    {
        return new ImportPeriodData(
            CarbonImmutable::parse($start, 'Europe/Moscow'),
            CarbonImmutable::parse($end, 'Europe/Moscow'),
        );
    }

    /**
     * @param  callable(?string): FetchPageData<mixed>  $fetch
     * @return list<mixed>
     */
    private function allItems(callable $fetch): array
    {
        $items = [];
        $cursor = null;

        do {
            $page = $fetch($cursor);
            $items = [...$items, ...$page->items];
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $items;
    }

    /** @return list<object> */
    private function allSales(DemoWildberriesClient $client, ImportPeriodData $period): array
    {
        $items = [];
        $cursor = null;

        do {
            /** @var SalesPageData $page */
            $page = $client->fetchSales($period, $cursor);
            $items = [...$items, ...$page->sales];
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $items;
    }
}
