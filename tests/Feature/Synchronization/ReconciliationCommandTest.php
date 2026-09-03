<?php

namespace Tests\Feature\Synchronization;

use App\Modules\Analytics\Actions\RebuildAnalytics;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_passes_then_detects_aggregate_drift(): void
    {
        $account = SellerAccount::factory()->active()->create([
            'source' => SellerAccountSource::Wildberries,
            'timezone' => 'Europe/Moscow',
        ]);
        $product = Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => '70010001',
            'vendor_code' => 'fixture-plaid',
            'title' => 'Плед фактурный',
            'brand' => 'Текстиль Маркет',
            'is_active' => true,
        ]);
        $order = $account->orders()->create([
            'product_id' => $product->id,
            'srid' => 'order-1',
            'ordered_at' => '2026-08-10 09:00:00+00:00',
            'quantity' => 2,
            'amount_kopecks' => 500000,
            'status' => 'new',
        ]);
        $sale = $account->sales()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'external_id' => 'sale-1',
            'srid' => 'order-1',
            'sold_at' => '2026-08-12 09:00:00+00:00',
            'quantity' => 1,
            'amount_kopecks' => 250000,
        ]);
        $account->returnFacts()->create([
            'product_id' => $product->id,
            'sale_id' => $sale->id,
            'external_id' => 'return-1',
            'returned_at' => '2026-08-18 09:00:00+00:00',
            'quantity' => 1,
            'amount_kopecks' => 250000,
        ]);
        app(RebuildAnalytics::class)->handle($account);

        $arguments = [
            'sellerAccount' => $account->id,
            '--start' => '2026-08-01',
            '--end' => '2026-08-31',
            '--fail-on-drift' => true,
        ];
        $this->assertSame(0, Artisan::call('sellerscope:reconcile', $arguments));
        $this->assertStringContainsString('PASS', Artisan::output());

        AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->where('metric_date', '2026-08-10')
            ->increment('orders');

        $this->assertSame(1, Artisan::call('sellerscope:reconcile', $arguments));
        $this->assertStringContainsString('DRIFT', Artisan::output());
    }

    public function test_reconciliation_rejects_a_normalized_invalid_date(): void
    {
        $account = SellerAccount::factory()->active()->create(['timezone' => 'Europe/Moscow']);

        $this->assertSame(2, Artisan::call('sellerscope:reconcile', [
            'sellerAccount' => $account->id,
            '--start' => '2026-02-30',
        ]));
        $this->assertStringContainsString('YYYY-MM-DD', Artisan::output());
    }
}
