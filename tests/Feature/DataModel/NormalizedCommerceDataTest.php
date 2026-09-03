<?php

namespace Tests\Feature\DataModel;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizedCommerceDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_catalog_sales_and_stock_graph_is_persisted(): void
    {
        $sellerAccount = SellerAccount::factory()->create();
        $category = $this->createCategory($sellerAccount, '10');
        $product = $this->createProduct($sellerAccount, '00123456789012345678', $category);
        $warehouse = $this->createWarehouse($sellerAccount, '00077');
        $orderedAt = now()->setTimezone('UTC')->startOfSecond();

        $order = Order::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'product_id' => $product->id,
            'srid' => 'order-srid-001',
            'ordered_at' => $orderedAt,
            'quantity' => 2,
            'amount_kopecks' => 259_800,
            'status' => 'ordered',
        ]);
        $sale = Sale::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'external_id' => 'sale-001',
            'srid' => $order->srid,
            'sold_at' => $orderedAt->copy()->addDay(),
            'amount_kopecks' => 129_900,
        ]);
        $return = ReturnFact::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'product_id' => $product->id,
            'sale_id' => $sale->id,
            'external_id' => 'return-001',
            'returned_at' => $orderedAt->copy()->addDays(3),
            'amount_kopecks' => 129_900,
        ]);
        $snapshot = StockSnapshot::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'snapshot_at' => $orderedAt,
            'snapshot_date' => $orderedAt->toDateString(),
            'quantity' => 18,
        ]);

        $this->assertSame('00123456789012345678', $product->fresh()->nm_id);
        $this->assertTrue($product->fresh()->category->is($category));
        $this->assertSame('00077', $warehouse->fresh()->external_id);
        $this->assertTrue($sale->order->is($order));
        $this->assertTrue($return->sale->is($sale));
        $this->assertTrue($snapshot->product->is($product));
        $this->assertTrue($snapshot->warehouse->is($warehouse));
        $this->assertSame(259_800, $order->amount_kopecks);
    }

    public function test_external_identifiers_are_unique_only_inside_an_account(): void
    {
        $firstAccount = SellerAccount::factory()->create();
        $secondAccount = SellerAccount::factory()->create();

        $this->createProduct($firstAccount, '123456');
        $this->createProduct($secondAccount, '123456');

        $this->expectException(QueryException::class);

        $this->createProduct($firstAccount, '123456');
    }

    public function test_sales_fact_cannot_reference_a_product_from_another_account(): void
    {
        $firstAccount = SellerAccount::factory()->create();
        $secondAccount = SellerAccount::factory()->create();
        $foreignProduct = $this->createProduct($secondAccount, '7654321');

        $this->expectException(QueryException::class);

        Order::query()->create([
            'seller_account_id' => $firstAccount->id,
            'product_id' => $foreignProduct->id,
            'srid' => 'cross-tenant-order',
            'ordered_at' => now(),
            'amount_kopecks' => 10_000,
            'status' => 'ordered',
        ]);
    }

    public function test_stock_snapshot_requires_product_and_warehouse_from_same_account(): void
    {
        $firstAccount = SellerAccount::factory()->create();
        $secondAccount = SellerAccount::factory()->create();
        $product = $this->createProduct($firstAccount, '111');
        $foreignWarehouse = $this->createWarehouse($secondAccount, '222');

        $this->expectException(QueryException::class);

        StockSnapshot::query()->create([
            'seller_account_id' => $firstAccount->id,
            'product_id' => $product->id,
            'warehouse_id' => $foreignWarehouse->id,
            'snapshot_at' => now(),
            'snapshot_date' => now()->toDateString(),
            'quantity' => 5,
        ]);
    }

    private function createCategory(SellerAccount $sellerAccount, string $externalId): Category
    {
        return Category::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'external_id' => $externalId,
            'name' => 'Дом и спальня',
        ]);
    }

    private function createProduct(
        SellerAccount $sellerAccount,
        string $nmId,
        ?Category $category = null,
    ): Product {
        return Product::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'category_id' => $category?->id,
            'nm_id' => $nmId,
            'vendor_code' => "SKU-{$nmId}",
            'title' => 'Подушка Classic',
            'brand' => 'SellerScope Demo',
        ]);
    }

    private function createWarehouse(SellerAccount $sellerAccount, string $externalId): Warehouse
    {
        return Warehouse::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'external_id' => $externalId,
            'name' => 'Коледино',
            'type' => 'wb',
        ]);
    }
}
