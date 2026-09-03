<?php

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Actions\RebuildAnalytics;
use App\Modules\Analytics\Models\AccountDailyMetric;
use App\Modules\Analytics\Models\ProductDailyMetric;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_sync_builds_canonical_daily_metrics_and_primary_signal(): void
    {
        Queue::fake();
        $account = SellerAccount::factory()->create();
        $run = app(StartInitialSync::class)->handle($account);

        app(RunInitialSync::class)->handle($run);

        $july = AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('metric_date', ['2026-07-01', '2026-07-31']);
        $june = AccountDailyMetric::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('metric_date', ['2026-06-01', '2026-06-30']);

        $this->assertSame(61, $account->accountDailyMetrics()->count());
        $this->assertSame(427, $account->productDailyMetrics()->count());
        $this->assertSame(128_460_000, (int) (clone $july)->sum('revenue_kopecks'));
        $this->assertSame(1_842, (int) (clone $july)->sum('orders'));
        $this->assertSame(1_471, (int) (clone $july)->sum('sales'));
        $this->assertSame(114_288_300, (int) (clone $june)->sum('revenue_kopecks'));
        $this->assertSame(1_695, (int) (clone $june)->sum('orders'));
        $this->assertSame(1_335, (int) (clone $june)->sum('sales'));

        $product = $account->products()->where('nm_id', '100000001')->firstOrFail();
        $this->assertSame(217, (int) ProductDailyMetric::query()
            ->where('product_id', $product->id)
            ->whereBetween('metric_date', ['2026-07-01', '2026-07-31'])
            ->sum('sales'));

        $signal = $account->productSignals()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(3, $signal->priority);
        $this->assertSame('stock_low', $signal->type);
        $this->assertSame(28, $signal->evidence['current_stock']);
        $this->assertSame(4, (int) $signal->evidence['stock_coverage_days']);
        $this->assertSame(182, $signal->evidence['recommended_supply']);
        $this->assertSame(1, $signal->data_revision);
    }

    public function test_rebuild_replaces_metrics_without_duplicates(): void
    {
        Queue::fake();
        $account = SellerAccount::factory()->create();

        foreach (range(1, 2) as $iteration) {
            $run = app(StartInitialSync::class)->handle($account->fresh());
            app(RunInitialSync::class)->handle($run);
        }

        $this->assertSame(61, $account->accountDailyMetrics()->count());
        $this->assertSame(427, $account->productDailyMetrics()->count());
        $this->assertSame(2, $account->fresh()->data_revision);
        $this->assertFalse($account->productSignals()->where('data_revision', '!=', 2)->exists());
    }

    public function test_signal_period_stops_at_an_unfinished_snapshot_month(): void
    {
        $account = SellerAccount::factory()->create(['timezone' => 'Europe/Moscow']);
        $product = Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => 'unfinished-month',
            'vendor_code' => 'unfinished-month',
            'title' => 'Товар августа',
            'brand' => 'SellerScope',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'seller_account_id' => $account->id,
            'external_id' => 'warehouse-1',
            'name' => 'Коледино',
            'type' => 'wb',
        ]);
        foreach (range(1, 10) as $day) {
            $account->sales()->create([
                'product_id' => $product->id,
                'external_id' => 'sale-'.$day,
                'srid' => 'srid-'.$day,
                'sold_at' => CarbonImmutable::parse("2026-08-{$day} 09:00:00", 'Europe/Moscow')->utc(),
                'quantity' => 10,
                'amount_kopecks' => 100_000,
            ]);
        }
        $snapshotAt = CarbonImmutable::parse('2026-08-10 12:00:00', 'Europe/Moscow')->utc();
        $account->stockSnapshots()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'variant_external_id' => 'variant-1',
            'snapshot_at' => $snapshotAt,
            'snapshot_date' => '2026-08-10',
            'quantity' => 50,
        ]);

        app(RebuildAnalytics::class)->handle($account);

        $signal = $account->productSignals()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('stock_low', $signal->type);
        $this->assertSame(5, $signal->evidence['stock_coverage_days']);
    }
}
