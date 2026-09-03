<?php

namespace Tests\Feature\Synchronization;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\DispatchDueSellerAccountSyncs;
use App\Modules\Synchronization\Actions\PruneRawImportPages;
use App\Modules\Synchronization\Actions\RecoverStalledSyncRuns;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use App\Modules\Synchronization\Jobs\RunInitialSyncJob;
use App\Modules\Synchronization\Models\ImportBatch;
use App\Modules\Synchronization\Models\RawImportPage;
use App\Modules\Synchronization\Models\SyncRun;
use App\Modules\Synchronization\Support\SyncRetryDelay;
use App\Modules\Wildberries\Exceptions\WildberriesApiException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ScheduledSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_due_active_real_accounts_are_dispatched(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        $due = $this->realAccount([
            'last_sync_completed_at' => $now->subHour(),
        ]);
        $this->realAccount([
            'last_sync_completed_at' => $now->subMinutes(10),
        ]);
        $invalid = $this->realAccount([
            'last_sync_completed_at' => $now->subHour(),
        ]);
        $invalid->credential?->update(['invalidated_at' => $now]);
        $busy = $this->realAccount([
            'last_sync_completed_at' => $now->subHour(),
        ]);
        $busy->syncRuns()->create([
            'type' => SyncRunType::Incremental,
            'status' => SyncRunStatus::Pending,
        ]);
        SellerAccount::factory()->active()->create([
            'source' => SellerAccountSource::Demo,
            'last_sync_completed_at' => $now->subHour(),
        ]);

        $count = app(DispatchDueSellerAccountSyncs::class)->handle($now);

        $this->assertSame(1, $count);
        $run = $due->syncRuns()->sole();
        $this->assertSame(SyncRunType::Incremental, $run->type);
        Queue::assertPushed(
            RunInitialSyncJob::class,
            fn (RunInitialSyncJob $job): bool => $job->syncRunId === $run->id,
        );
    }

    public function test_stalled_run_is_failed_and_account_can_be_dispatched_again(): void
    {
        Queue::fake();
        $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        config()->set('sellerscope.synchronization.stalled_after_minutes', 15);
        $account = $this->realAccount([
            'status' => SellerAccountStatus::InitialSync,
            'last_sync_completed_at' => null,
        ]);
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Initial,
            'status' => SyncRunStatus::Running,
            'progress' => 50,
            'started_at' => $now->subHour(),
        ]);
        $account->syncResourceStates()->create([
            'resource' => SyncResource::Orders,
            'status' => SyncResourceStatus::Running,
        ]);
        DB::table('sync_runs')->where('id', $run->id)->update([
            'updated_at' => $now->subHour(),
        ]);

        $this->assertSame(1, app(RecoverStalledSyncRuns::class)->handle($now));
        $this->assertSame(SyncRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('sync_stalled', $run->fresh()->error_code);
        $this->assertSame(SellerAccountStatus::Partial, $account->fresh()->status);
        $this->assertSame(SyncResourceStatus::Failed, $account->syncResourceStates()->firstOrFail()->status);

        $this->assertSame(1, app(DispatchDueSellerAccountSyncs::class)->handle($now));
        $this->assertSame(2, $account->syncRuns()->count());
    }

    public function test_expired_raw_pages_are_pruned_without_touching_active_pages(): void
    {
        $now = CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC');
        $account = $this->realAccount();
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Incremental,
            'status' => SyncRunStatus::Completed,
        ]);
        $expiredBatch = $this->batch($account, $run, 'expired');
        $activeBatch = $this->batch($account, $run, 'active');
        RawImportPage::query()->create([
            'seller_account_id' => $account->id,
            'import_batch_id' => $expiredBatch->id,
            'payload' => ['data' => []],
            'retrieved_at' => $now->subDays(8),
            'expires_at' => $now->subDay(),
        ]);
        RawImportPage::query()->create([
            'seller_account_id' => $account->id,
            'import_batch_id' => $activeBatch->id,
            'payload' => ['data' => []],
            'retrieved_at' => $now,
            'expires_at' => $now->addWeek(),
        ]);

        $this->assertSame(1, app(PruneRawImportPages::class)->handle($now));
        $this->assertSame(1, RawImportPage::query()->count());
        $this->assertSame($activeBatch->id, RawImportPage::query()->sole()->import_batch_id);
    }

    public function test_exhausted_sync_job_marks_pending_run_failed(): void
    {
        $account = $this->realAccount();
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Incremental,
            'status' => SyncRunStatus::Pending,
        ]);

        (new RunInitialSyncJob($run->id, $account->id))->failed(
            new WildberriesApiException('upstream_unavailable', true),
        );

        $this->assertSame(SyncRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('upstream_unavailable', $run->fresh()->error_code);
        $this->assertSame(SellerAccountStatus::Partial, $account->fresh()->status);
    }

    public function test_permanent_sync_error_fails_job_without_worker_retry(): void
    {
        $account = $this->realAccount([
            'status' => SellerAccountStatus::InitialSync,
            'last_sync_completed_at' => null,
        ]);
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Initial,
            'status' => SyncRunStatus::Pending,
            'progress' => 0,
        ]);
        Http::fake([
            'https://content-api.wildberries.ru/content/v2/get/cards/list' => Http::response([], 401),
        ]);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('fail')->once()->withArgs(
            fn (WildberriesApiException $exception): bool => $exception->errorCode === 'invalid_credentials',
        );
        $job = new RunInitialSyncJob($run->id, $account->id);
        $job->setJob($queueJob);

        $job->handle(app(RunInitialSync::class), app(SyncRetryDelay::class));

        $this->assertSame(SyncRunStatus::Failed, $run->fresh()->status);
        $this->assertSame('invalid_credentials', $run->fresh()->error_code);
        $this->assertSame(SellerAccountStatus::InvalidCredentials, $account->fresh()->status);
        $this->assertNotNull($account->credential()->firstOrFail()->invalidated_at);
    }

    /** @param array<string, mixed> $attributes */
    private function realAccount(array $attributes = []): SellerAccount
    {
        $account = SellerAccount::factory()->active()->create([
            'source' => SellerAccountSource::Wildberries,
            ...$attributes,
        ]);
        $account->credential()->create([
            'token' => 'real-test-token-'.$account->id,
            'fingerprint' => hash('sha256', 'real-test-token-'.$account->id),
            'permissions' => ['products', 'orders', 'sales', 'stocks'],
            'verified_at' => now(),
        ]);

        return $account;
    }

    private function batch(SellerAccount $account, SyncRun $run, string $key): ImportBatch
    {
        return ImportBatch::query()->create([
            'seller_account_id' => $account->id,
            'sync_run_id' => $run->id,
            'source' => SellerAccountSource::Wildberries,
            'resource' => SyncResource::Products,
            'status' => SyncResourceStatus::Completed,
            'page_key' => hash('sha256', $key),
            'checksum' => hash('sha256', '[]'),
        ]);
    }
}
