<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockSnapshot;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ReturnFact;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_pages_share_consistent_analytics_context(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'name' => 'Дом и уют',
            'last_sync_completed_at' => '2026-07-31 10:42:00',
        ]);
        $user->preference()->create([
            'active_seller_account_id' => $account->id,
            'period_preset' => 'july_2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'comparison_start' => '2026-06-01',
            'comparison_end' => '2026-06-30',
        ]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('overview/index')
                ->where('analyticsContext.activeAccount.id', $account->id)
                ->where('analyticsContext.period.label', '1–31 июля 2026')
                ->where('analyticsContext.comparison.label', '1–30 июня 2026'));
    }

    public function test_context_handles_an_account_without_credentials(): void
    {
        $user = User::factory()->create();
        SellerAccount::factory()->active()->for($user)->create();

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.sync.missingPermissions', ['products', 'orders', 'sales', 'stocks']));
    }

    public function test_sandbox_account_does_not_require_unsupported_stocks_permission(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
            'wb_account_id' => 'sandbox:11111111-2222-4333-8444-555555555555',
        ]);
        $account->credential()->create([
            'token' => 'sandbox-test-token',
            'fingerprint' => hash('sha256', 'sandbox-test-token'),
            'permissions' => ['products', 'orders', 'sales'],
            'verified_at' => now(),
        ]);
        $user->preference()->create(['active_seller_account_id' => $account->id]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.sync.missingPermissions', []));
    }

    public function test_context_update_persists_only_owned_account_and_redirects_with_url_state(): void
    {
        $user = User::factory()->create();
        $first = SellerAccount::factory()->for($user)->create();
        $second = SellerAccount::factory()->for($user)->create();
        $foreign = SellerAccount::factory()->create();
        $user->preference()->create(['active_seller_account_id' => $first->id]);

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $second->id,
                'period_preset' => 'june_2026',
                'return_to' => '/products',
            ])
            ->assertRedirect("/products?cabinet={$second->id}&period=june_2026");

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'active_seller_account_id' => $second->id,
            'period_start' => '2026-06-01 00:00:00',
        ]);

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $foreign->id,
                'period_preset' => 'july_2026',
                'return_to' => '/overview',
            ])
            ->assertSessionHasErrors('seller_account_id');
    }

    public function test_real_account_uses_rolling_periods_instead_of_demo_dates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00', 'Europe/Moscow'));
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
            'timezone' => 'Europe/Moscow',
        ]);
        $user->preference()->create([
            'active_seller_account_id' => $account->id,
            'period_preset' => 'july_2026',
        ]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.period.preset', 'last_30_days')
                ->where('analyticsContext.period.start', '2026-07-24')
                ->where('analyticsContext.period.end', '2026-08-22')
                ->where('analyticsContext.comparison.start', '2026-06-24')
                ->where('analyticsContext.comparison.end', '2026-07-23')
                ->where('analyticsContext.periodOptions.1.value', 'current_month'));
    }

    public function test_real_current_month_persists_equal_length_comparison(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00', 'Europe/Moscow'));
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
            'timezone' => 'Europe/Moscow',
        ]);

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $account->id,
                'period_preset' => 'current_month',
                'return_to' => '/overview',
            ])
            ->assertRedirect("/overview?cabinet={$account->id}&period=current_month");

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'period_start' => '2026-08-01 00:00:00',
            'period_end' => '2026-08-22 00:00:00',
            'comparison_start' => '2026-07-10 00:00:00',
            'comparison_end' => '2026-07-31 00:00:00',
        ]);
    }

    public function test_context_exposes_union_and_resource_data_coverage(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
        ]);
        $user->preference()->create(['active_seller_account_id' => $account->id]);
        [$product, $warehouse] = $this->catalog($account);
        $order = Order::query()->create([
            'seller_account_id' => $account->id,
            'product_id' => $product->id,
            'srid' => 'coverage-order',
            'ordered_at' => '2024-02-01 07:00:00+00:00',
            'quantity' => 1,
            'amount_kopecks' => 10_000,
            'status' => 'ordered',
        ]);
        $sale = Sale::query()->create([
            'seller_account_id' => $account->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'external_id' => 'coverage-sale',
            'sold_at' => '2025-04-15 12:00:00+00:00',
            'quantity' => 1,
            'amount_kopecks' => 10_000,
        ]);
        ReturnFact::query()->create([
            'seller_account_id' => $account->id,
            'product_id' => $product->id,
            'sale_id' => $sale->id,
            'external_id' => 'coverage-return',
            'returned_at' => '2025-05-02 09:00:00+00:00',
            'quantity' => 1,
            'amount_kopecks' => 10_000,
        ]);
        StockSnapshot::query()->create([
            'seller_account_id' => $account->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'snapshot_at' => '2026-08-20 08:00:00+00:00',
            'snapshot_date' => '2026-08-20',
            'quantity' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.coverage.overall.start', '2024-02-01')
                ->where('analyticsContext.coverage.overall.end', '2026-08-20')
                ->where('analyticsContext.coverage.resources.orders.start', '2024-02-01')
                ->where('analyticsContext.coverage.resources.sales.start', '2025-04-15')
                ->where('analyticsContext.coverage.resources.returns.end', '2025-05-02')
                ->where('analyticsContext.coverage.resources.stocks.start', '2026-08-20'));
    }

    public function test_custom_period_is_persisted_with_equal_length_comparison_and_url_state(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
        ]);
        $user->preference()->create(['active_seller_account_id' => $account->id]);
        [$product] = $this->catalog($account);
        foreach (['2024-01-01', '2024-01-31'] as $index => $date) {
            Order::query()->create([
                'seller_account_id' => $account->id,
                'product_id' => $product->id,
                'srid' => 'custom-order-'.$index,
                'ordered_at' => $date.' 12:00:00+00:00',
                'quantity' => 1,
                'amount_kopecks' => 10_000,
                'status' => 'ordered',
            ]);
        }

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $account->id,
                'period_preset' => 'custom',
                'period_start' => '2024-01-10',
                'period_end' => '2024-01-20',
                'return_to' => '/overview?view=summary&period=previous_month',
            ])
            ->assertRedirect("/overview?view=summary&period=custom&cabinet={$account->id}&from=2024-01-10&to=2024-01-20");

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'period_preset' => 'custom',
            'period_start' => '2024-01-10 00:00:00',
            'period_end' => '2024-01-20 00:00:00',
            'comparison_start' => '2023-12-30 00:00:00',
            'comparison_end' => '2024-01-09 00:00:00',
            'granularity' => 'day',
        ]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.period.preset', 'custom')
                ->where('analyticsContext.period.start', '2024-01-10')
                ->where('analyticsContext.period.end', '2024-01-20')
                ->where('analyticsContext.period.granularity', 'day')
                ->where('analyticsContext.comparison.isComplete', false));
    }

    public function test_custom_period_must_be_ordered_and_inside_available_coverage(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
        ]);
        $user->preference()->create(['active_seller_account_id' => $account->id]);
        [$product] = $this->catalog($account);
        foreach (['2024-01-01', '2024-01-31'] as $index => $date) {
            Order::query()->create([
                'seller_account_id' => $account->id,
                'product_id' => $product->id,
                'srid' => 'validation-order-'.$index,
                'ordered_at' => $date.' 12:00:00+00:00',
                'quantity' => 1,
                'amount_kopecks' => 10_000,
                'status' => 'ordered',
            ]);
        }

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $account->id,
                'period_preset' => 'custom',
                'period_start' => '2023-12-31',
                'period_end' => '2024-01-20',
                'return_to' => '/overview',
            ])
            ->assertSessionHasErrors('period_start');

        $this->actingAs($user)
            ->patch(route('preferences.analytics-context.update'), [
                'seller_account_id' => $account->id,
                'period_preset' => 'custom',
                'period_start' => '2024-01-20',
                'period_end' => '2024-01-10',
                'return_to' => '/overview',
            ])
            ->assertSessionHasErrors('period_end');

        $this->assertDatabaseMissing('user_preferences', [
            'user_id' => $user->id,
            'period_preset' => 'custom',
        ]);
    }

    public function test_custom_period_in_url_overrides_saved_period_for_browser_history(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->active()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
        ]);
        $user->preference()->create([
            'active_seller_account_id' => $account->id,
            'period_preset' => 'custom',
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-05',
            'comparison_start' => '2023-12-27',
            'comparison_end' => '2023-12-31',
        ]);
        [$product] = $this->catalog($account);
        foreach (['2024-01-01', '2024-01-31'] as $index => $date) {
            Order::query()->create([
                'seller_account_id' => $account->id,
                'product_id' => $product->id,
                'srid' => 'url-period-order-'.$index,
                'ordered_at' => $date.' 12:00:00+00:00',
                'quantity' => 1,
                'amount_kopecks' => 10_000,
                'status' => 'ordered',
            ]);
        }

        $this->actingAs($user)
            ->get('/overview?period=custom&from=2024-01-10&to=2024-01-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.period.preset', 'custom')
                ->where('analyticsContext.period.start', '2024-01-10')
                ->where('analyticsContext.period.end', '2024-01-20'));

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'period_start' => '2024-01-01 00:00:00',
            'period_end' => '2024-01-05 00:00:00',
        ]);
    }

    /** @return array{Product, Warehouse} */
    private function catalog(SellerAccount $account): array
    {
        $product = Product::query()->create([
            'seller_account_id' => $account->id,
            'nm_id' => 'period-product-'.$account->id,
            'vendor_code' => 'PERIOD-'.$account->id,
            'title' => 'Товар для периода',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'seller_account_id' => $account->id,
            'external_id' => 'period-warehouse-'.$account->id,
            'name' => 'Склад',
        ]);

        return [$product, $warehouse];
    }
}
