<?php

namespace App\Modules\Synchronization\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Data\OrderData;
use App\Modules\Wildberries\Data\ProductData;
use App\Modules\Wildberries\Data\ReturnData;
use App\Modules\Wildberries\Data\SaleData;
use App\Modules\Wildberries\Data\StockData;
use Illuminate\Support\Facades\DB;

final class CanonicalDataImporter
{
    private const string SANDBOX_PLACEHOLDER_CATEGORY_ID = 'sandbox-imported';

    /** @param list<ProductData> $items */
    public function products(SellerAccount $account, array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $processed = count($items);
        $items = $this->deduplicate($items, static fn (ProductData $item): string => $item->nmId);

        DB::transaction(function () use ($account, $items): void {
            $now = now();
            $categories = [];

            foreach ($items as $item) {
                $categories[$item->categoryExternalId] = [
                    'seller_account_id' => $account->id,
                    'external_id' => $item->categoryExternalId,
                    'name' => $item->categoryName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Category::query()->upsert(
                array_values($categories),
                ['seller_account_id', 'external_id'],
                ['name', 'updated_at'],
            );

            $categoryIds = Category::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('external_id', array_keys($categories))
                ->pluck('id', 'external_id');
            if ($categoryIds->count() !== count($categories)) {
                throw new \DomainException('Canonical product references an unknown category.');
            }

            $products = array_map(static fn (ProductData $item): array => [
                'seller_account_id' => $account->id,
                'category_id' => $categoryIds[$item->categoryExternalId],
                'nm_id' => $item->nmId,
                'vendor_code' => $item->vendorCode,
                'title' => $item->title,
                'brand' => $item->brand,
                'image_url' => $item->imageUrl,
                'is_active' => $item->active,
                'external_updated_at' => $item->updatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items);

            Product::query()->upsert(
                $products,
                ['seller_account_id', 'nm_id'],
                [
                    'category_id', 'vendor_code', 'title', 'brand', 'image_url',
                    'is_active', 'external_updated_at', 'updated_at',
                ],
            );
        });

        return $processed;
    }

    /** @param list<OrderData> $items */
    public function orders(SellerAccount $account, array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $processed = count($items);
        $items = $this->deduplicate($items, static fn (OrderData $item): string => $item->srid);

        DB::transaction(function () use ($account, $items): void {
            $productIds = $this->productIds($account, array_column($items, 'productNmId'));
            $now = now();
            $rows = array_map(static fn (OrderData $item): array => [
                'seller_account_id' => $account->id,
                'product_id' => $productIds[$item->productNmId],
                'srid' => $item->srid,
                'ordered_at' => $item->orderedAt,
                'quantity' => $item->quantity,
                'amount_kopecks' => $item->amountKopecks,
                'status' => $item->status,
                'is_cancelled' => $item->cancelled,
                'cancelled_at' => $item->cancelledAt,
                'external_updated_at' => $item->updatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items);

            Order::query()->upsert($rows, ['seller_account_id', 'srid'], [
                'product_id', 'ordered_at', 'quantity', 'amount_kopecks', 'status',
                'is_cancelled', 'cancelled_at', 'external_updated_at', 'updated_at',
            ]);
        });

        return $processed;
    }

    /** @param list<SaleData> $items */
    public function sales(SellerAccount $account, array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $processed = count($items);
        $items = $this->deduplicate($items, static fn (SaleData $item): string => $item->externalId);

        DB::transaction(function () use ($account, $items): void {
            $productIds = $this->productIds($account, array_column($items, 'productNmId'));
            $orderIds = Order::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('srid', array_column($items, 'srid'))
                ->pluck('id', 'srid');
            $now = now();
            $rows = array_map(static fn (SaleData $item): array => [
                'seller_account_id' => $account->id,
                'product_id' => $productIds[$item->productNmId],
                'order_id' => $orderIds[$item->srid] ?? null,
                'external_id' => $item->externalId,
                'srid' => $item->srid,
                'sold_at' => $item->soldAt,
                'quantity' => $item->quantity,
                'amount_kopecks' => $item->amountKopecks,
                'external_updated_at' => $item->updatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items);

            Sale::query()->upsert($rows, ['seller_account_id', 'external_id'], [
                'product_id', 'order_id', 'srid', 'sold_at', 'quantity',
                'amount_kopecks', 'external_updated_at', 'updated_at',
            ]);
        });

        return $processed;
    }

    /** @param list<ReturnData> $items */
    public function returns(SellerAccount $account, array $items): int
    {
        if ($items === []) {
            return 0;
        }
        $processed = count($items);
        $items = $this->deduplicate($items, static fn (ReturnData $item): string => $item->externalId);

        DB::transaction(function () use ($account, $items): void {
            $productIds = $this->productIds($account, array_column($items, 'productNmId'));
            $saleIdsByExternalId = Sale::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('external_id', array_filter(array_column($items, 'saleExternalId')))
                ->pluck('id', 'external_id');
            $saleIdsBySrid = Sale::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('srid', array_column($items, 'srid'))
                ->pluck('id', 'srid');
            $now = now();
            $rows = array_map(static fn (ReturnData $item): array => [
                'seller_account_id' => $account->id,
                'product_id' => $productIds[$item->productNmId],
                'sale_id' => ($item->saleExternalId !== null
                    ? ($saleIdsByExternalId[$item->saleExternalId] ?? null)
                    : null) ?? ($saleIdsBySrid[$item->srid] ?? null),
                'external_id' => $item->externalId,
                'returned_at' => $item->returnedAt,
                'quantity' => $item->quantity,
                'amount_kopecks' => $item->amountKopecks,
                'external_updated_at' => $item->updatedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items);

            ReturnFact::query()->upsert($rows, ['seller_account_id', 'external_id'], [
                'product_id', 'sale_id', 'returned_at', 'quantity', 'amount_kopecks',
                'external_updated_at', 'updated_at',
            ]);
        });

        return $processed;
    }

    /** @param iterable<StockData> $items */
    public function stocks(SellerAccount $account, iterable $items): int
    {
        $processed = 0;
        $chunk = [];

        foreach ($items as $item) {
            $chunk[] = $item;
            $processed++;

            if (count($chunk) === 1000) {
                $this->importStockChunk($account, $chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->importStockChunk($account, $chunk);
        }

        return $processed;
    }

    /** @param list<StockData> $items */
    private function importStockChunk(SellerAccount $account, array $items): void
    {
        $items = $this->deduplicate(
            $items,
            static fn (StockData $item): string => implode("\0", [
                $item->productNmId,
                $item->warehouseExternalId,
                $item->variantExternalId,
                $item->snapshotAt->format('Y-m-d\TH:i:s.uP'),
            ]),
        );

        DB::transaction(function () use ($account, $items): void {
            $now = now();
            $warehouses = [];

            foreach ($items as $item) {
                $warehouses[$item->warehouseExternalId] = [
                    'seller_account_id' => $account->id,
                    'external_id' => $item->warehouseExternalId,
                    'name' => $item->warehouseName,
                    'type' => $item->warehouseType,
                    'region_name' => $item->warehouseRegion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            Warehouse::query()->upsert(array_values($warehouses), [
                'seller_account_id', 'external_id',
            ], ['name', 'type', 'region_name', 'updated_at']);

            $warehouseIds = Warehouse::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('external_id', array_keys($warehouses))
                ->pluck('id', 'external_id');
            if ($warehouseIds->count() !== count($warehouses)) {
                throw new \DomainException('Canonical stock references an unknown warehouse.');
            }
            $productIds = $this->productIds($account, array_column($items, 'productNmId'));
            $rows = array_map(static fn (StockData $item): array => [
                'seller_account_id' => $account->id,
                'product_id' => $productIds[$item->productNmId],
                'warehouse_id' => $warehouseIds[$item->warehouseExternalId],
                'variant_external_id' => $item->variantExternalId,
                'snapshot_at' => $item->snapshotAt,
                'snapshot_date' => $item->snapshotAt->setTimezone($account->timezone)->toDateString(),
                'quantity' => $item->quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ], $items);

            StockSnapshot::query()->upsert($rows, [
                'seller_account_id', 'product_id', 'warehouse_id',
                'variant_external_id', 'snapshot_at',
            ], ['snapshot_date', 'quantity', 'updated_at']);
        });
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): string  $key
     * @return list<T>
     */
    private function deduplicate(array $items, callable $key): array
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[$key($item)] = $item;
        }

        return array_values($unique);
    }

    /**
     * @param  list<string>  $nmIds
     * @return array<string, int>
     */
    private function productIds(SellerAccount $account, array $nmIds): array
    {
        $nmIds = array_values(array_unique($nmIds));

        /** @var array<string, int> $ids */
        $ids = Product::query()
            ->where('seller_account_id', $account->id)
            ->whereIn('nm_id', $nmIds)
            ->pluck('id', 'nm_id')
            ->all();

        if (count($ids) !== count($nmIds) && $account->environment() === 'sandbox') {
            $this->createSandboxPlaceholderProducts($account, array_values(array_diff($nmIds, array_keys($ids))));

            /** @var array<string, int> $ids */
            $ids = Product::query()
                ->where('seller_account_id', $account->id)
                ->whereIn('nm_id', $nmIds)
                ->pluck('id', 'nm_id')
                ->all();
        }

        if (count($ids) !== count($nmIds)) {
            throw new \DomainException('Canonical fact references an unknown product.');
        }

        return $ids;
    }

    /** @param list<string> $nmIds */
    private function createSandboxPlaceholderProducts(SellerAccount $account, array $nmIds): void
    {
        if ($nmIds === []) {
            return;
        }

        $now = now();
        Category::query()->upsert([
            [
                'seller_account_id' => $account->id,
                'external_id' => self::SANDBOX_PLACEHOLDER_CATEGORY_ID,
                'name' => 'Товары песочницы WB',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['seller_account_id', 'external_id'], ['name', 'updated_at']);

        $categoryId = Category::query()
            ->where('seller_account_id', $account->id)
            ->where('external_id', self::SANDBOX_PLACEHOLDER_CATEGORY_ID)
            ->value('id');
        if (! is_int($categoryId)) {
            throw new \DomainException('Sandbox placeholder category was not created.');
        }

        Product::query()->insertOrIgnore(array_map(static fn (string $nmId): array => [
            'seller_account_id' => $account->id,
            'category_id' => $categoryId,
            'nm_id' => $nmId,
            'vendor_code' => "sandbox-{$nmId}",
            'title' => "Товар WB {$nmId}",
            'brand' => 'Wildberries Sandbox',
            'image_url' => null,
            'is_active' => true,
            'external_updated_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $nmIds));
    }
}
