<?php

namespace Tests\Feature\Synchronization;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Sale;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Jobs\RunInitialSyncJob;
use App\Modules\Synchronization\Models\ImportBatch;
use App\Modules\Synchronization\Models\SyncResourceState;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DemoSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_one_pending_run_and_dispatches_encrypted_unique_job(): void
    {
        Queue::fake();
        $account = SellerAccount::factory()->create();

        $first = app(StartInitialSync::class)->handle($account);
        $second = app(StartInitialSync::class)->handle($account);

        $this->assertTrue($first->is($second));
        $this->assertSame(SellerAccountStatus::InitialSync, $account->fresh()->status);
        Queue::assertPushed(RunInitialSyncJob::class, 1);

        $job = new RunInitialSyncJob($first->id, $account->id);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame("{$account->id}:{$first->id}", $job->uniqueId());
        $this->assertSame(10, $job->tries);
        $this->assertSame(900, $job->timeout);
        $this->assertSame(3600, $job->uniqueFor);
    }

    public function test_initial_sync_imports_canonical_data_and_advances_progress(): void
    {
        Queue::fake();
        $account = SellerAccount::factory()->create();
        $run = app(StartInitialSync::class)->handle($account);

        app(RunInitialSync::class)->handle($run);

        $run->refresh();
        $account->refresh();

        $this->assertSame(SyncRunStatus::Completed, $run->status);
        $this->assertSame(100, $run->progress);
        $this->assertSame(SellerAccountStatus::Active, $account->status);
        $this->assertSame(1, $account->data_revision);
        $this->assertSame(7, $account->products()->count());
        $this->assertSame(427, $account->orders()->count());
        $this->assertSame(427, $account->sales()->count());
        $this->assertSame(14, $account->returnFacts()->count());
        $this->assertSame(434, $account->stockSnapshots()->count());
        $this->assertSame(4, SyncResourceState::query()->where('seller_account_id', $account->id)->count());
        $this->assertSame(19, ImportBatch::query()->where('sync_run_id', $run->id)->count());
        $this->assertTrue(ImportBatch::query()
            ->where('sync_run_id', $run->id)
            ->where('resource', 'orders')
            ->whereNotNull('cursor_out')
            ->exists());
        $this->assertFalse(SyncResourceState::query()
            ->where('seller_account_id', $account->id)
            ->whereNotNull('cursor')
            ->exists());

        $this->assertSame(1_842, (int) Order::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('ordered_at', ['2026-07-01', '2026-07-31 23:59:59'])
            ->sum('quantity'));
        $this->assertSame(128_460_000, (int) Sale::query()
            ->where('seller_account_id', $account->id)
            ->whereBetween('sold_at', ['2026-07-01', '2026-07-31 23:59:59'])
            ->sum('amount_kopecks'));
    }

    public function test_repeated_import_does_not_duplicate_normalized_facts(): void
    {
        Queue::fake();
        $account = SellerAccount::factory()->create();

        $firstRun = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($firstRun);
        $countsAfterFirstRun = $this->factCounts($account);

        $secondRun = app(StartInitialSync::class)->handle($account->fresh());
        app(RunInitialSync::class)->handle($secondRun);

        $this->assertSame($countsAfterFirstRun, $this->factCounts($account));
        $this->assertSame(2, $account->fresh()->data_revision);
        $this->assertNotSame($firstRun->id, $secondRun->id);
    }

    public function test_identical_demo_external_ids_stay_isolated_between_accounts(): void
    {
        Queue::fake();
        $firstAccount = SellerAccount::factory()->create();
        $secondAccount = SellerAccount::factory()->create();

        $firstRun = app(StartInitialSync::class)->handle($firstAccount);
        $secondRun = app(StartInitialSync::class)->handle($secondAccount);
        app(RunInitialSync::class)->handle($firstRun);
        app(RunInitialSync::class)->handle($secondRun);

        $this->assertSame($this->factCounts($firstAccount), $this->factCounts($secondAccount));
        $this->assertSame(2, Order::query()->where('srid', 'demo-order-2026-07-01-100000001')->count());
        $this->assertNotSame(
            $firstAccount->products()->where('nm_id', '100000001')->value('id'),
            $secondAccount->products()->where('nm_id', '100000001')->value('id'),
        );
    }

    public function test_job_payload_contains_only_run_identifier_not_credential(): void
    {
        $account = SellerAccount::factory()->create();
        SellerAccountCredential::query()->create([
            'seller_account_id' => $account->id,
            'token' => 'never-serialize-this-demo-token',
            'fingerprint' => hash('sha256', 'never-serialize-this-demo-token'),
            'available_sections' => ['products'],
            'verified_at' => now(),
        ]);
        $run = $account->syncRuns()->create([
            'type' => 'initial',
            'status' => 'pending',
        ]);

        $serialized = serialize(new RunInitialSyncJob($run->id, $account->id));

        $this->assertStringNotContainsString('never-serialize-this-demo-token', $serialized);
        $this->assertStringContainsString('syncRunId', $serialized);
    }

    /** @return array<string, int> */
    private function factCounts(SellerAccount $account): array
    {
        return [
            'products' => $account->products()->count(),
            'orders' => $account->orders()->count(),
            'sales' => $account->sales()->count(),
            'returns' => $account->returnFacts()->count(),
            'stocks' => $account->stockSnapshots()->count(),
        ];
    }
}
