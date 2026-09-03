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

class OverviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_uses_canonical_metrics_and_backend_signals(): void
    {
        [$user] = $this->syncedAccount();

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('overview/index')
                ->where('overview.state', 'ready')
                ->where('overview.kpis.0.value', 128_460_000)
                ->where('overview.kpis.0.change', 12.4)
                ->where('overview.kpis.1.value', 1_842)
                ->where('overview.kpis.2.value', 1_471)
                ->where('overview.kpis.3.value', 79.9)
                ->where('overview.attention.count', 4)
                ->has('overview.series.day', 31)
                ->where('overview.series.day', fn ($series): bool => $series->sum('current') === 128_460_000)
                ->where('overview.series.day.30.comparison', null)
                ->has('overview.series.week', 5)
                ->has('overview.products.leaders', 5));
    }

    public function test_multi_year_overview_uses_monthly_chart_points(): void
    {
        [$user, $account] = $this->syncedAccount();
        $user->preference()->update([
            'period_preset' => 'custom',
            'period_start' => '2024-01-15',
            'period_end' => '2026-01-15',
            'comparison_start' => '2022-01-14',
            'comparison_end' => '2024-01-14',
        ]);

        $this->actingAs($user)
            ->get(route('overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsContext.activeAccount.id', $account->id)
                ->where('overview.series.granularity', 'month')
                ->has('overview.series.points', 25));
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
