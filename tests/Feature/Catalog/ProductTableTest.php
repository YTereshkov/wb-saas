<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SavedView;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductTableTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('productViews')]
    public function test_product_views_render_backend_counts(string $route, string $view, int $total): void
    {
        [$user] = $this->syncedAccount();

        $this->actingAs($user)
            ->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/index')
                ->where('table.view', $view)
                ->where('table.counts.all', 7)
                ->where('table.counts.attention', 4)
                ->where('table.counts.decline', 3)
                ->where('table.counts.lowStock', 2)
                ->where('table.pagination.total', $total));
    }

    public static function productViews(): array
    {
        return [
            'all' => ['products.index', 'all', 7],
            'attention' => ['products.attention', 'attention', 4],
            'decline' => ['products.decline', 'decline', 3],
            'low stock' => ['products.low-stock', 'low_stock', 2],
        ];
    }

    public function test_search_filters_and_sorting_are_applied_on_backend(): void
    {
        [$user] = $this->syncedAccount();

        $this->actingAs($user)
            ->get(route('products.index', ['search' => 'Memory', 'sort' => 'title', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.pagination.total', 1)
                ->where('table.rows.0.title', 'Подушка Memory 50×70')
                ->where('table.filters.search', 'Memory'));

        $this->actingAs($user)
            ->get(route('products.decline', ['sort' => 'dynamics', 'direction' => 'asc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'Комплект постельного белья Gray')
                ->where('table.rows.0.dynamics', -18.2));
    }

    public function test_saved_view_is_scoped_to_user_account_and_restored(): void
    {
        [$user, $account] = $this->syncedAccount();
        $otherUser = User::factory()->create();
        $otherAccount = SellerAccount::factory()->for($otherUser)->create();
        SavedView::query()->create([
            'user_id' => $otherUser->id,
            'seller_account_id' => $otherAccount->id,
            'section' => 'products',
            'view_key' => 'all',
            'filters' => [
                'search' => 'Чужой товар',
                'category' => null,
                'status' => 'inactive',
                'stock' => 'out',
                'performance' => 'decline',
            ],
            'columns' => ['returns'],
            'sort_column' => 'returns',
            'sort_direction' => 'desc',
            'page_size' => 10,
        ]);

        $this->actingAs($user)
            ->patch(route('preferences.product-view.update'), [
                'view' => 'all',
                'filters' => [
                    'search' => 'Подушка',
                    'category' => null,
                    'status' => 'active',
                    'stock' => 'all',
                    'performance' => 'all',
                ],
                'columns' => ['revenue', 'dynamics', 'stock'],
                'sort_column' => 'dynamics',
                'sort_direction' => 'asc',
                'page_size' => 50,
                'return_to' => '/products',
            ])
            ->assertRedirect('/products');

        $this->assertDatabaseHas('saved_views', [
            'user_id' => $user->id,
            'seller_account_id' => $account->id,
            'view_key' => 'all',
            'page_size' => 50,
        ]);
        $this->assertDatabaseHas('saved_views', [
            'user_id' => $otherUser->id,
            'seller_account_id' => $otherAccount->id,
            'page_size' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.isSaved', true)
                ->where('table.filters.search', 'Подушка')
                ->where('table.pagination.perPage', 50)
                ->where('table.columns', ['revenue', 'dynamics', 'stock'])
                ->where('table.pagination.total', 2));
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
