<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_details_routes_use_consistent_backend_metrics(): void
    {
        [$user, $account] = $this->syncedAccount();
        $product = Product::query()
            ->where('seller_account_id', $account->id)
            ->where('nm_id', '100000001')
            ->firstOrFail();

        $this->actingAs($user)->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/show/overview')
                ->where('details.product.title', 'Подушка Memory 50×70')
                ->where('details.kpis.0.value', 16_000_000)
                ->where('details.funnel.orders', 280)
                ->where('details.funnel.sales', 217)
                ->where('details.stock.total', 28)
                ->has('details.stock.warehouses', 2));

        $this->actingAs($user)->get(route('products.show.sales', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/show/sales')
                ->has('details.series.sales', 31)
                ->has('details.weekly', 5));

        $this->actingAs($user)->get(route('products.show.stocks', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/show/stocks')
                ->where('details.stock.recommendedSupply', 182)
                ->has('details.stock.series', 37)
                ->where('details.stock.series', function ($series): bool {
                    $labels = $series->pluck('label')->all();

                    return count($labels) === count(array_unique($labels));
                }));
    }

    public function test_user_cannot_open_product_from_another_seller_account(): void
    {
        [$user] = $this->syncedAccount();
        [, $otherAccount] = $this->syncedAccount();
        $otherProduct = Product::query()->where('seller_account_id', $otherAccount->id)->firstOrFail();

        $this->actingAs($user)->get(route('products.show', $otherProduct))->assertNotFound();
        $this->actingAs($user)->get(route('products.show.sales', $otherProduct))->assertNotFound();
        $this->actingAs($user)->get(route('products.show.stocks', $otherProduct))->assertNotFound();
    }

    public function test_multi_year_product_series_use_monthly_points(): void
    {
        [$user, $account] = $this->syncedAccount();
        $product = Product::query()
            ->where('seller_account_id', $account->id)
            ->firstOrFail();
        $user->preference()->update([
            'period_preset' => 'custom',
            'period_start' => '2024-01-15',
            'period_end' => '2026-01-15',
            'comparison_start' => '2022-01-14',
            'comparison_end' => '2024-01-14',
        ]);

        $this->actingAs($user)
            ->get(route('products.show.sales', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('details.series.granularity', 'month')
                ->has('details.series.revenue', 25)
                ->has('details.series.sales', 25)
                ->has('details.weekly', 25));
    }

    /** @return array{User, SellerAccount} */
    private function syncedAccount(): array
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

        return [$user, $account->fresh()];
    }
}
