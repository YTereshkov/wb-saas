<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Notifications\Actions\QueueSignalNotifications;
use App\Modules\Notifications\Jobs\SendNotificationDelivery;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Notifications\CriticalSignalNotification;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Actions\BackfillHistoricalStocks;
use App\Modules\Synchronization\Actions\RunInitialSync;
use App\Modules\Synchronization\Actions\StartInitialSync;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use App\Modules\Synchronization\Jobs\BackfillHistoricalStocksJob;
use App\Modules\Synchronization\Jobs\RunInitialSyncJob;
use App\Modules\Synchronization\Support\SyncRetryDelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_cabinet_page_never_exposes_token_and_can_be_renamed(): void
    {
        [$user, $account] = $this->syncedAccount();

        $this->actingAs($user)->get(route('settings.cabinets.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/cabinets/show')
                ->where('cabinet.name', 'Дом и уют')
                ->missing('cabinet.credential.token')
                ->missing('demoToken'));

        $this->actingAs($user)->patch(route('settings.cabinets.update', $account), ['name' => 'Основной кабинет'])
            ->assertRedirect();
        $this->assertDatabaseHas('seller_accounts', ['id' => $account->id, 'name' => 'Основной кабинет']);
    }

    public function test_notification_settings_are_tenant_scoped_and_signal_delivery_is_queued(): void
    {
        [$user, $account] = $this->syncedAccount();
        $otherAccount = SellerAccount::factory()->for($user)->create();
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'seller_account_id' => $otherAccount->id,
            'event' => 'low_stock',
            'channel' => 'email',
            'enabled' => false,
            'threshold' => 14,
        ]);
        Queue::fake();

        $events = [
            'low_stock' => ['enabled' => true, 'threshold' => 3, 'frequency' => null],
            'sales_decline' => ['enabled' => false, 'threshold' => 20, 'frequency' => null],
            'returns_growth' => ['enabled' => true, 'threshold' => 3, 'frequency' => null],
            'sync_failed' => ['enabled' => true, 'threshold' => null, 'frequency' => null],
            'daily_digest' => ['enabled' => false, 'threshold' => null, 'frequency' => 'weekly'],
        ];

        $this->actingAs($user)->patch(route('settings.notifications.update'), [
            'seller_account_id' => $account->id,
            'email_enabled' => true,
            'events' => $events,
        ])->assertRedirect();

        $this->assertSame(6, NotificationPreference::query()->where('seller_account_id', $account->id)->count());
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'seller_account_id' => $otherAccount->id,
            'event' => 'low_stock',
            'enabled' => false,
            'threshold' => 14,
        ]);
        app(QueueSignalNotifications::class)->handle($account);
        Queue::assertPushed(SendNotificationDelivery::class);
    }

    public function test_notification_settings_reject_unknown_event_keys(): void
    {
        [$user, $account] = $this->syncedAccount();

        $this->actingAs($user)->patch(route('settings.notifications.update'), [
            'seller_account_id' => $account->id,
            'email_enabled' => true,
            'events' => [
                'low_stock' => ['enabled' => true, 'threshold' => 7],
                'unknown_event' => ['enabled' => true, 'threshold' => 1],
            ],
        ])->assertSessionHasErrors('events');
    }

    public function test_notification_delivery_uses_email_defaults_and_ignores_other_channels(): void
    {
        [$user, $account] = $this->syncedAccount();
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'seller_account_id' => $account->id,
            'event' => '_email_channel',
            'channel' => 'sms',
            'enabled' => false,
        ]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'seller_account_id' => $account->id,
            'event' => 'low_stock',
            'channel' => 'sms',
            'enabled' => false,
        ]);
        Queue::fake();

        app(QueueSignalNotifications::class)->handle($account);

        Queue::assertPushed(SendNotificationDelivery::class);
        $this->assertDatabaseHas('notification_deliveries', [
            'seller_account_id' => $account->id,
            'event' => 'low_stock',
            'channel' => 'email',
        ]);
    }

    public function test_delivery_keeps_signal_copy_after_signal_is_deleted(): void
    {
        [$user, $account] = $this->syncedAccount();
        Queue::fake();

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'seller_account_id' => $account->id,
            'event' => 'low_stock',
            'channel' => 'email',
            'enabled' => true,
        ]);

        app(QueueSignalNotifications::class)->handle($account);
        $delivery = NotificationDelivery::query()->firstOrFail();
        $delivery->productSignal()->firstOrFail()->delete();
        $delivery->refresh();

        $mail = (new CriticalSignalNotification($delivery))->toMail($user);

        $this->assertNotEmpty($delivery->fact);
        $this->assertNotEmpty($delivery->recommendation);
        $this->assertNull($delivery->product_signal_id);
        $this->assertNotEmpty($mail->introLines);
    }

    public function test_signal_delivery_respects_the_configured_threshold(): void
    {
        [, $account] = $this->syncedAccount();
        Queue::fake();
        $signal = $account->productSignals()->where('type', 'stock_low')->firstOrFail();
        $this->assertGreaterThan(3, (float) $signal->evidence['stock_coverage_days']);
        NotificationPreference::query()->create([
            'user_id' => $account->user_id,
            'seller_account_id' => $account->id,
            'event' => 'low_stock',
            'channel' => 'email',
            'enabled' => true,
            'threshold' => 3,
        ]);

        app(QueueSignalNotifications::class)->handle($account);

        $this->assertDatabaseMissing('notification_deliveries', [
            'seller_account_id' => $account->id,
            'product_signal_id' => $signal->id,
        ]);
    }

    public function test_delivery_rechecks_preferences_and_claims_work_once(): void
    {
        [, $account] = $this->syncedAccount();
        Queue::fake();
        Notification::fake();
        NotificationPreference::query()->create([
            'user_id' => $account->user_id,
            'seller_account_id' => $account->id,
            'event' => 'low_stock',
            'channel' => 'email',
            'enabled' => true,
            'threshold' => 7,
        ]);
        app(QueueSignalNotifications::class)->handle($account);
        $delivery = NotificationDelivery::query()->where('event', 'low_stock')->firstOrFail();

        NotificationPreference::query()->updateOrCreate([
            'user_id' => $account->user_id,
            'seller_account_id' => $account->id,
            'event' => '_email_channel',
            'channel' => 'email',
        ], ['enabled' => false]);
        (new SendNotificationDelivery($delivery->id))->handle();

        Notification::assertNothingSent();
        $this->assertSame('suppressed', $delivery->fresh()->status);

        $delivery->update(['status' => 'sending']);
        NotificationPreference::query()->where('event', '_email_channel')->update(['enabled' => true]);
        (new SendNotificationDelivery($delivery->id))->handle();
        Notification::assertNothingSent();
    }

    public function test_profile_and_password_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => 'Юрий Смирнов', 'password' => 'Password1']);

        $this->actingAs($user)->patch(route('settings.profile.update'), [
            'first_name' => 'Иван', 'last_name' => 'Петров', 'email' => $user->email,
        ])->assertRedirect();
        $this->assertSame('Иван Петров', $user->fresh()->name);

        $this->actingAs($user)->put(route('settings.profile.password'), [
            'current_password' => 'Password1', 'password' => 'NewPassword2', 'password_confirmation' => 'NewPassword2',
        ])->assertRedirect();
        $this->assertTrue(password_verify('NewPassword2', $user->fresh()->password));
    }

    public function test_other_database_sessions_can_be_revoked_without_deleting_current_session(): void
    {
        config()->set('session.driver', 'database');
        $this->app->forgetInstance('session');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.profile'))->assertOk();
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->delete(route('settings.profile.sessions'))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_password_mismatch_is_reported_on_confirmation_field(): void
    {
        $user = User::factory()->create(['password' => 'Password1']);

        $this->actingAs($user)->put(route('settings.profile.password'), [
            'current_password' => 'Password1',
            'password' => 'NewPassword2',
            'password_confirmation' => 'Different3',
        ])->assertSessionHasErrors('password_confirmation');

        $this->assertTrue(password_verify('Password1', $user->fresh()->password));
    }

    public function test_other_users_cannot_open_or_change_a_cabinet(): void
    {
        [, $account] = $this->syncedAccount();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('settings.cabinets.show', $account))->assertForbidden();
        $this->actingAs($other)->patch(route('settings.cabinets.update', $account), ['name' => 'Чужой'])->assertForbidden();
        $this->actingAs($other)->delete(route('settings.cabinets.destroy', $account))->assertForbidden();
    }

    public function test_delete_removes_account_data_and_queued_jobs_become_no_ops(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = SellerAccount::factory()->for($user)->create([
            'name' => 'Удаляемый кабинет',
            'status' => SellerAccountStatus::InitialSync,
        ]);
        $replacement = SellerAccount::factory()->for($user)->active()->create([
            'name' => 'Активный кабинет',
        ]);
        $user->preference()->create(['active_seller_account_id' => $account->id]);
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Initial,
            'status' => SyncRunStatus::Running,
            'progress' => 25,
            'started_at' => now()->subMinute(),
        ]);
        $state = $account->syncResourceStates()->create([
            'resource' => SyncResource::Orders,
            'status' => SyncResourceStatus::Running,
            'availability' => ResourceAvailability::Available,
            'last_attempt_at' => now()->subMinute(),
        ]);
        $account->credential()->create([
            'token' => 'credential-to-delete',
            'fingerprint' => hash('sha256', 'credential-to-delete'),
            'permissions' => ['products', 'orders', 'sales', 'stocks'],
            'verified_at' => now(),
        ]);
        $syncJob = new RunInitialSyncJob($run->id, $account->id);
        $backfillJob = new BackfillHistoricalStocksJob($account->id);

        $this->actingAs($user)
            ->delete(route('settings.cabinets.destroy', $account))
            ->assertRedirect(route('settings.cabinets.index'));

        $this->assertDatabaseMissing('seller_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('seller_account_credentials', ['seller_account_id' => $account->id]);
        $this->assertDatabaseMissing('sync_runs', ['id' => $run->id]);
        $this->assertDatabaseMissing('sync_resource_states', ['id' => $state->id]);
        $this->assertSame($replacement->id, $user->preference->fresh()->active_seller_account_id);

        $this->actingAs($user)
            ->get(route('settings.cabinets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('cabinets.items', 1)
                ->where('cabinets.items.0.id', $replacement->id));

        $syncJob->handle(app(RunInitialSync::class), app(SyncRetryDelay::class));
        $backfillJob->handle(
            app(BackfillHistoricalStocks::class),
            app(SyncRetryDelay::class),
        );
    }

    /** @return array{User, SellerAccount} */
    private function syncedAccount(): array
    {
        Queue::fake();
        $user = User::factory()->create();
        $account = SellerAccount::factory()->for($user)->create(['name' => 'Дом и уют']);
        $user->preference()->create(['active_seller_account_id' => $account->id, 'period_preset' => 'july_2026']);
        $run = app(StartInitialSync::class)->handle($account);
        app(RunInitialSync::class)->handle($run);

        return [$user, $account->fresh()];
    }
}
