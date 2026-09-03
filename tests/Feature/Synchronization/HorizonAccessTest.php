<?php

namespace Tests\Feature\Synchronization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_dashboard_requires_explicit_email_allowlist_even_locally(): void
    {
        $operator = User::factory()->create(['email' => 'operator@sellerscope.local']);
        $seller = User::factory()->create(['email' => 'seller@sellerscope.local']);
        config()->set('horizon.allowed_emails', ['operator@sellerscope.local']);

        $this->get('/horizon')->assertForbidden();
        $this->actingAs($seller)->get('/horizon')->assertForbidden();
        $this->actingAs($operator)->get('/horizon')->assertOk();
    }

    public function test_horizon_supervisors_cover_every_sellerscope_queue(): void
    {
        $defaults = config('horizon.defaults');
        $this->assertIsArray($defaults);
        $queues = collect($defaults)
            ->flatMap(static fn (array $supervisor): array => $supervisor['queue'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['analytics', 'default', 'maintenance', 'notifications', 'sync'],
            $queues,
        );
        $this->assertLessThan(
            (int) config('queue.connections.redis.retry_after'),
            (int) config('horizon.defaults.supervisor-sync.timeout'),
        );
    }
}
