<?php

namespace App\Modules\Wildberries\Demo;

use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\ReturnData;
use App\Modules\Wildberries\Data\SaleData;
use App\Modules\Wildberries\Data\StockData;
use Carbon\CarbonImmutable;

final class DemoWildberriesDataset
{
    private const PRODUCTS = [
        ['100000001', 'PILLOW-MEMORY-5070', 'Подушка Memory 50×70', 'Подушки', 'cat-pillows', 'pillow-memory'],
        ['100000002', 'PILLOW-CLASSIC-5070', 'Подушка Classic 50×70', 'Подушки', 'cat-pillows', 'pillow-classic'],
        ['100000003', 'DUVET-AIR-172205', 'Одеяло Air 172×205', 'Одеяла', 'cat-duvets', 'duvet-air'],
        ['100000004', 'MATTRESS-BALANCE-160200', 'Матрас Balance 160×200', 'Матрасы', 'cat-mattresses', 'mattress-balance'],
        ['100000005', 'TOPPER-CLOUD-160200', 'Топпер Cloud 160×200', 'Матрасы', 'cat-mattresses', 'mattress-topper-cloud'],
        ['100000006', 'BED-LINEN-GRAY', 'Комплект постельного белья Gray', 'Постельное бельё', 'cat-bed-linen', 'bed-linen-gray'],
        ['100000007', 'THROW-SOFT-TOUCH', 'Плед Soft Touch', 'Пледы', 'cat-throws', 'throw-soft-touch'],
    ];

    /** @return list<ProductData> */
    public function products(): array
    {
        $updatedAt = CarbonImmutable::parse('2026-07-31 18:00:00', 'UTC');

        return array_map(
            static fn (array $product): ProductData => new ProductData(
                nmId: $product[0],
                vendorCode: $product[1],
                title: $product[2],
                brand: 'SellerScope Demo',
                categoryExternalId: $product[4],
                categoryName: $product[3],
                imageUrl: "/images/demo/products/{$product[5]}.webp",
                active: true,
                updatedAt: $updatedAt,
            ),
            self::PRODUCTS,
        );
    }

    /** @return list<OrderData> */
    public function orders(): array
    {
        return [
            ...$this->monthlyOrders('2026-06', [245, 280, 255, 230, 215, 240, 230]),
            ...$this->monthlyOrders('2026-07', [280, 310, 280, 250, 240, 260, 222]),
        ];
    }

    /** @return list<SaleData> */
    public function sales(): array
    {
        return [
            ...$this->monthlySales('2026-06', [190, 220, 200, 180, 170, 190, 185], [
                17_000_000, 14_000_000, 12_000_000, 15_000_000, 14_288_300, 22_000_000, 20_000_000,
            ]),
            ...$this->monthlySales('2026-07', [217, 250, 220, 200, 190, 210, 184], [
                16_000_000, 22_000_000, 18_000_000, 20_000_000, 17_000_000, 18_000_000, 17_460_000,
            ]),
        ];
    }

    /** @return list<ReturnData> */
    public function returns(): array
    {
        return [
            ...$this->monthlyReturns('2026-06', [5, 4, 5, 3, 4, 5, 4]),
            ...$this->monthlyReturns('2026-07', [6, 5, 6, 4, 5, 5, 5]),
        ];
    }

    /** @return list<StockData> */
    public function stocks(): array
    {
        $warehouseStocks = [
            ['wb-01', 'Коледино', [18, 72, 45, 0, 31, 68, 350]],
            ['wb-02', 'Казань', [10, 39, 28, 0, 20, 44, 250]],
        ];
        $julySales = [217, 250, 220, 200, 190, 210, 184];
        $stocks = [];

        for ($day = 1; $day <= 31; $day++) {
            $snapshotAt = CarbonImmutable::parse(sprintf('2026-07-%02d 18:00:00', $day), 'UTC');

            foreach (self::PRODUCTS as $index => $product) {
                $currentTotal = $warehouseStocks[0][2][$index] + $warehouseStocks[1][2][$index];
                $historicalTotal = $currentTotal + (int) round(($julySales[$index] / 31) * (31 - $day));
                $firstShare = $currentTotal > 0 ? $warehouseStocks[0][2][$index] / $currentTotal : 0.5;
                $firstQuantity = $day === 31
                    ? $warehouseStocks[0][2][$index]
                    : (int) round($historicalTotal * $firstShare);
                $quantities = [$firstQuantity, $historicalTotal - $firstQuantity];

                foreach ($warehouseStocks as $warehouseIndex => [$externalId, $name]) {
                    $stocks[] = new StockData(
                        productNmId: $product[0],
                        warehouseExternalId: $externalId,
                        warehouseName: $name,
                        warehouseType: 'wildberries',
                        warehouseRegion: null,
                        variantExternalId: '0',
                        snapshotAt: $snapshotAt,
                        quantity: $quantities[$warehouseIndex],
                    );
                }
            }
        }

        return $stocks;
    }

    /**
     * @param  list<int>  $totals
     * @return list<OrderData>
     */
    private function monthlyOrders(string $month, array $totals): array
    {
        $days = CarbonImmutable::parse("{$month}-01", 'UTC')->daysInMonth;
        $orders = [];

        foreach (self::PRODUCTS as $productIndex => $product) {
            foreach ($this->dailyDistribution($totals[$productIndex], $days) as $dayIndex => $quantity) {
                $date = CarbonImmutable::parse(sprintf('%s-%02d 08:00:00', $month, $dayIndex + 1), 'UTC');
                $srid = "demo-order-{$date->format('Y-m-d')}-{$product[0]}";
                $orders[] = new OrderData(
                    srid: $srid,
                    productNmId: $product[0],
                    orderedAt: $date,
                    quantity: $quantity,
                    amountKopecks: $quantity * (6_900 + ($productIndex * 1_100)),
                    status: 'ordered',
                    cancelled: false,
                    cancelledAt: null,
                    updatedAt: $date->addHours(2),
                );
            }
        }

        return $orders;
    }

    /**
     * @param  list<int>  $totals
     * @param  list<int>  $revenues
     * @return list<SaleData>
     */
    private function monthlySales(string $month, array $totals, array $revenues): array
    {
        $days = CarbonImmutable::parse("{$month}-01", 'UTC')->daysInMonth;
        $sales = [];

        foreach (self::PRODUCTS as $productIndex => $product) {
            $quantities = $this->dailyDistribution($totals[$productIndex], $days);
            $amounts = $this->dailyDistribution($revenues[$productIndex], $days);

            foreach ($quantities as $dayIndex => $quantity) {
                $date = CarbonImmutable::parse(sprintf('%s-%02d 15:00:00', $month, $dayIndex + 1), 'UTC');
                $datePart = $date->format('Y-m-d');
                $sales[] = new SaleData(
                    externalId: "demo-sale-{$datePart}-{$product[0]}",
                    srid: "demo-order-{$datePart}-{$product[0]}",
                    productNmId: $product[0],
                    soldAt: $date,
                    quantity: $quantity,
                    amountKopecks: $amounts[$dayIndex],
                    updatedAt: $date->addHour(),
                );
            }
        }

        return $sales;
    }

    /**
     * @param  list<int>  $totals
     * @return list<ReturnData>
     */
    private function monthlyReturns(string $month, array $totals): array
    {
        $returns = [];

        foreach (self::PRODUCTS as $productIndex => $product) {
            $day = 20 + $productIndex;
            $date = CarbonImmutable::parse(sprintf('%s-%02d 12:00:00', $month, $day), 'UTC');
            $saleDate = $date->subDays(5)->format('Y-m-d');
            $returns[] = new ReturnData(
                externalId: "demo-return-{$date->format('Y-m-d')}-{$product[0]}",
                saleExternalId: "demo-sale-{$saleDate}-{$product[0]}",
                srid: "demo-order-{$saleDate}-{$product[0]}",
                productNmId: $product[0],
                returnedAt: $date,
                quantity: $totals[$productIndex],
                amountKopecks: $totals[$productIndex] * (6_900 + ($productIndex * 1_100)),
                updatedAt: $date->addHour(),
            );
        }

        return $returns;
    }

    /** @return list<int> */
    private function dailyDistribution(int $total, int $days): array
    {
        $pattern = [0.78, 0.92, 1.16, 1.34, 1.08, 0.86, 0.98, 1.12, 0.88, 1.24, 1.40, 1.02, 0.82, 1.10];
        $weights = array_map(
            static fn (int $day): float => $pattern[$day % count($pattern)] * (1 + (($day / max(1, $days - 1)) * 0.08)),
            range(0, $days - 1),
        );
        $weightTotal = array_sum($weights);
        $distribution = [];
        $fractions = [];

        foreach ($weights as $day => $weight) {
            $exact = $total * $weight / $weightTotal;
            $distribution[$day] = (int) floor($exact);
            $fractions[$day] = $exact - $distribution[$day];
        }

        arsort($fractions);

        foreach (array_slice(array_keys($fractions), 0, $total - array_sum($distribution)) as $day) {
            $distribution[$day]++;
        }

        ksort($distribution);

        return array_values($distribution);
    }
}
