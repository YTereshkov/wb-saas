<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_views_use_forecasts_and_saved_snapshots(): void
    {
        $user = $this->syncedUser();

        $this->actingAs($user)->get(route('stocks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('stocks/index')
                ->where('stocks.state', 'ready')
                ->where('stocks.view', 'all')
                ->where('stocks.counts.all', 7)
                ->where('stocks.counts.supply', 2)
                ->where('stocks.counts.outOfStock', 1)
                ->where('stocks.counts.noMovement', 1)
                ->where('stocks.pagination.total', 7));

        $this->actingAs($user)->get(route('stocks.supply'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.view', 'supply')
                ->where('stocks.pagination.total', 2)
                ->where('stocks.rows.0.title', 'Матрас Balance 160×200')
                ->where('stocks.rows.0.stock', 0)
                ->where('stocks.rows.0.recommendedSupply', 194)
                ->where('stocks.rows.1.title', 'Подушка Memory 50×70')
                ->where('stocks.rows.1.recommendedSupply', 182));

        $this->actingAs($user)->get(route('stocks.out-of-stock'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.view', 'out_of_stock')
                ->where('stocks.insight.title', '1 товар закончился — ориентировочно упущено 6 продаж')
                ->where('stocks.pagination.total', 1)
                ->where('stocks.rows.0.title', 'Матрас Balance 160×200')
                ->where('stocks.rows.0.outOfStockDays', 1)
                ->where('stocks.rows.0.estimatedMissedSales', 6.5));

        $this->actingAs($user)->get(route('stocks.no-movement'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.view', 'no_movement')
                ->where('stocks.insight.title', '1 товар без движения — 600 шт. требуют проверки')
                ->where('stocks.pagination.total', 1)
                ->where('stocks.rows.0.title', 'Плед Soft Touch')
                ->where('stocks.rows.0.stock', 600)
                ->where('stocks.rows.0.noMovementReason', 'Запас больше 90 дней'));
    }

    public function test_stock_table_filters_by_search_and_warehouse(): void
    {
        $user = $this->syncedUser();
        $account = $user->sellerAccounts()->firstOrFail();
        $warehouse = $account->warehouses()->where('name', 'Казань')->firstOrFail();
        $otherWarehouse = $account->warehouses()->where('id', '!=', $warehouse->id)->firstOrFail();
        $product = $account->products()->where('title', 'Подушка Memory 50×70')->firstOrFail();
        $latest = CarbonImmutable::parse($account->stockSnapshots()->max('snapshot_at'));
        $account->stockSnapshots()->create([
            'product_id' => $product->id,
            'warehouse_id' => $otherWarehouse->id,
            'variant_external_id' => 'later-other-warehouse',
            'snapshot_at' => $latest->addDay(),
            'snapshot_date' => $latest->addDay()->toDateString(),
            'quantity' => 999,
        ]);

        $this->actingAs($user)->get(route('stocks.index', [
            'search' => 'Memory',
            'warehouse' => $warehouse->id,
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.pagination.total', 1)
                ->where('stocks.rows.0.title', 'Подушка Memory 50×70')
                ->where('stocks.rows.0.stock', 10)
                ->where('stocks.rows.0.signalTone', 'danger')
                ->where('stocks.filters.warehouse', $warehouse->id));
    }

    public function test_stock_page_does_not_show_current_stock_for_period_without_snapshots(): void
    {
        $user = $this->syncedUser();
        $account = $user->sellerAccounts()->firstOrFail();
        $coverageStart = $account->stockSnapshots()->min('snapshot_date');
        $this->assertNotNull($coverageStart);
        $periodEnd = CarbonImmutable::parse($coverageStart)->subDay();
        $user->preference()->update([
            'period_preset' => 'custom',
            'period_start' => $periodEnd->subDays(6)->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'comparison_start' => $periodEnd->subDays(13)->toDateString(),
            'comparison_end' => $periodEnd->subDays(7)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('stocks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stocks.state', 'unavailable')
                ->where('stocks.view', 'all')
                ->where('stocks.coverage.start', (string) $coverageStart)
                ->missing('stocks.rows'));
    }

    private function syncedUser(): User
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = SellerAccount::factory()->for($user)->create(['name' => 'Дом и уют']);
        $user->preference()->create([
            'active_seller_account_id' => $account->id,
            'period_preset' => 'july_2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'comparison_start' => '2026-06-01',
            'comparison_end' => '2026-06-30',
        ]);
        $run = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($run);

        return $user;
    }
}
