<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_dynamics_and_orders_sales_use_canonical_metrics(): void
    {
        $user = $this->syncedUser();

        $this->actingAs($user)->get(route('sales.dynamics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/dynamics')
                ->where('sales.state', 'ready')
                ->where('sales.kpis.0.value', 128_460_000)
                ->where('sales.kpis.1.value', 1_842)
                ->where('sales.kpis.2.value', 1_471)
                ->has('sales.series.revenueDay', 31)
                ->has('sales.products.contribution', 5));

        $this->actingAs($user)->get(route('sales.orders-sales'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders-sales')
                ->where('sales.kpis.3.value', 371)
                ->has('sales.series.ordersSalesDay', 31)
                ->has('sales.products.gap', 5));
    }

    public function test_buyout_returns_uses_the_same_canonical_metrics(): void
    {
        $user = $this->syncedUser();

        $this->actingAs($user)->get(route('sales.buyout-returns'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/buyout-returns')
                ->where('sales.state', 'ready')
                ->where('sales.kpis.4.value', 79.9)
                ->where('sales.kpis.5.value', 2.4)
                ->where('sales.kpis.5.inverse', true)
                ->where('sales.kpis.6.value', 36)
                ->where('sales.kpis.6.inverse', true)
                ->has('sales.series.qualityDay', 31)
                ->has('sales.series.qualityWeek', 5)
                ->has('sales.products.quality', 5));
    }

    public function test_sales_products_supports_backend_filtering_and_sorting(): void
    {
        $user = $this->syncedUser();

        $this->actingAs($user)->get(route('sales.products'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/products')
                ->where('sales.kpis.0.value', 128_460_000)
                ->where('table.state', 'ready')
                ->where('table.pagination.total', 7)
                ->has('table.rows', 7));

        $this->actingAs($user)->get(route('sales.products', [
            'search' => 'Memory',
            'sort' => 'title',
            'direction' => 'asc',
        ]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.pagination.total', 1)
                ->where('table.rows.0.title', 'Подушка Memory 50×70')
                ->where('table.filters.search', 'Memory'));

        $this->actingAs($user)->get(route('sales.products', ['performance' => 'decline']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.pagination.total', 3)
                ->where('table.filters.performance', 'decline'));
    }

    public function test_multi_year_sales_series_use_monthly_points(): void
    {
        $user = $this->syncedUser();
        $user->preference()->update([
            'period_preset' => 'custom',
            'period_start' => '2024-01-15',
            'period_end' => '2026-01-15',
            'comparison_start' => '2022-01-14',
            'comparison_end' => '2024-01-14',
        ]);

        $this->actingAs($user)
            ->get(route('sales.dynamics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sales.series.granularity', 'month')
                ->has('sales.series.revenuePoints', 25)
                ->has('sales.series.ordersSalesPoints', 25)
                ->has('sales.series.qualityPoints', 25));
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
