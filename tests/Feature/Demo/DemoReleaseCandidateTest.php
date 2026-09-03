<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoReleaseCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_seed_builds_a_complete_demo_and_critical_routes_open(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', config('sellerscope.demo.email'))->sole();
        $account = $user->sellerAccounts()->sole();

        $this->assertSame(SellerAccountStatus::Active, $account->status);
        $this->assertGreaterThan(0, $account->products()->count());
        $this->assertGreaterThan(0, $account->accountDailyMetrics()->count());

        $this->actingAs($user)->get(route('overview'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('overview/index')->where('overview.state', 'ready'));

        foreach ([
            route('products.index'), route('sales.dynamics'), route('stocks.index'),
            route('settings.cabinets.index'), route('settings.cabinets.show', $account),
            route('settings.notifications'), route('settings.profile'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_demo_seed_is_repeatable_without_duplicate_facts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = User::query()->where('email', config('sellerscope.demo.email'))->sole()->sellerAccounts()->sole();
        $counts = [$first->products()->count(), $first->orders()->count(), $first->sales()->count(), $first->returnFacts()->count(), $first->stockSnapshots()->count()];

        $this->seed(DatabaseSeeder::class);
        $second = $first->fresh();

        $this->assertSame($counts, [$second->products()->count(), $second->orders()->count(), $second->sales()->count(), $second->returnFacts()->count(), $second->stockSnapshots()->count()]);
    }
}
