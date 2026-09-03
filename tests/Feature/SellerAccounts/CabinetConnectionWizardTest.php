<?php

namespace Tests\Feature\SellerAccounts;

use App\Models\User;
use App\Modules\SellerAccounts\Actions\UpdateDemoSellerAccountCredential;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Synchronization\Enums\ResourceAvailability;
use App\Modules\Synchronization\Enums\SyncResource;
use App\Modules\Synchronization\Enums\SyncResourceStatus;
use App\Modules\Synchronization\Enums\SyncRunStatus;
use App\Modules\Synchronization\Enums\SyncRunType;
use App\Modules\Synchronization\Jobs\RunInitialSyncJob;
use App\Modules\Synchronization\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CabinetConnectionWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_complete_demo_verification_without_exposing_token(): void
    {
        $user = User::factory()->create();
        $token = (string) config('sellerscope.demo.connection_token');

        $response = $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $token],
        );

        $account = $user->sellerAccounts()->firstOrFail();
        $response->assertRedirect(route('settings.cabinets.verification', $account));
        $this->assertSame(SellerAccountStatus::Verified, $account->status);
        $this->assertNotSame(
            $token,
            DB::table('seller_account_credentials')
                ->where('seller_account_id', $account->id)
                ->value('token'),
        );
        $this->assertSame(
            ['orders', 'products', 'sales', 'stocks'],
            $account->credential()->firstOrFail()->permissions,
        );

        $this->actingAs($user)
            ->get(route('settings.cabinets.verification', $account))
            ->assertOk()
            ->assertDontSee($token)
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/cabinets/connect/verification')
                ->missing('cabinet.token'));
    }

    public function test_invalid_demo_token_returns_safe_validation_error_without_creating_account(): void
    {
        $user = User::factory()->create();
        Log::spy();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([], 401),
        ]);

        $this->actingAs($user)
            ->from(route('settings.cabinets.connect'))
            ->post(route('settings.cabinets.connect.verify'), ['token' => 'wrong-token'])
            ->assertRedirect(route('settings.cabinets.connect'))
            ->assertSessionHasErrors('token');

        $this->assertSame(0, $user->sellerAccounts()->count());
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Wildberries credential verification failed.'
                && $context === [
                    'user_id' => $user->id,
                    'error_code' => 'invalid_credentials',
                    'http_status' => 401,
                ],
        );
    }

    public function test_empty_configured_demo_token_can_never_be_accepted(): void
    {
        config()->set('sellerscope.demo.connection_token', null);
        $account = SellerAccount::factory()->create();

        $this->expectException(ValidationException::class);

        app(UpdateDemoSellerAccountCredential::class)->handle($account, '');
    }

    public function test_verified_user_can_connect_real_wildberries_account(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([
                'name' => 'ООО Северный Дом',
                'sid' => '11111111-2222-4333-8444-555555555555',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: true)],
        );

        $account = $user->sellerAccounts()->firstOrFail();
        $response->assertRedirect(route('settings.cabinets.verification', $account));
        $this->assertSame('wildberries', $account->source->value);
        $this->assertSame('ООО Северный Дом', $account->name);
        $this->assertSame(
            ['orders', 'products', 'sales', 'stocks'],
            $account->credential()->firstOrFail()->permissions,
        );
        $this->assertNotSame(
            $this->wildberriesToken(readOnly: true),
            DB::table('seller_account_credentials')->where('seller_account_id', $account->id)->value('token'),
        );
    }

    public function test_basic_token_connects_without_unsupported_stocks_permission(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([
                'name' => 'ООО Северный Дом',
                'sid' => '11111111-2222-4333-8444-555555555555',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: true, accountType: 1)],
        );

        $account = $user->sellerAccounts()->firstOrFail();
        $response->assertRedirect(route('settings.cabinets.verification', $account));
        $this->assertSame(
            ['orders', 'products', 'sales'],
            $account->credential()->firstOrFail()->permissions,
        );
    }

    public function test_test_token_connects_to_an_independent_sandbox_account(): void
    {
        $user = User::factory()->create();
        SellerAccount::factory()->for($user)->create([
            'name' => 'Боевой кабинет',
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::Active,
            'wb_account_id' => '11111111-2222-4333-8444-555555555555',
        ]);
        Http::fake([
            'https://content-api-sandbox.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api-sandbox.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: false, accountType: 2, test: true)],
        );
        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        $this->assertSame(2, $user->sellerAccounts()->count());

        $sandbox = $user->sellerAccounts()
            ->where('wb_account_id', 'sandbox:11111111-2222-4333-8444-555555555555')
            ->firstOrFail();
        $response->assertRedirect(route('settings.cabinets.verification', $sandbox));
        $this->assertSame('Тестовый кабинет WB', $sandbox->name);
        $this->assertSame(SellerAccountStatus::Verified, $sandbox->status);
        $this->assertSame(
            ['orders', 'products', 'sales'],
            $sandbox->credential()->firstOrFail()->permissions,
        );
        Http::assertSentCount(2);

        $this->actingAs($user)
            ->get(route('settings.cabinets.verification', $sandbox))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/cabinets/connect/verification')
                ->where('cabinet.environment', 'sandbox')
                ->where('cabinet.wbAccountId', '11111111-2222-4333-8444-555555555555'));

        $this->actingAs($user)
            ->get(route('settings.cabinets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cabinets.items', 2)
                ->where('cabinets.items.1.environment', 'sandbox')
                ->where('cabinets.items.1.wbAccountId', '11111111-2222-4333-8444-555555555555'));

        $this->actingAs($user)
            ->get(route('settings.cabinets.show', $sandbox))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cabinet.environment', 'sandbox')
                ->where('cabinet.wbAccountId', '11111111-2222-4333-8444-555555555555'));
    }

    public function test_seller_info_rate_limit_does_not_block_connection(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([], 429, [
                'X-Ratelimit-Retry' => '84182',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $response = $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: true, accountType: 1)],
        );

        $response->assertSessionHasNoErrors();
        $account = $user->sellerAccounts()->firstOrFail();
        $response->assertRedirect(route('settings.cabinets.verification', $account));
        $this->assertSame('11111111-2222-4333-8444-555555555555', $account->wb_account_id);
        $this->assertSame('Кабинет Wildberries', $account->name);
        $this->assertSame(
            ['orders', 'products', 'sales'],
            $account->credential()->firstOrFail()->permissions,
        );
    }

    public function test_real_token_without_required_scope_is_rejected(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([
                'name' => 'ООО Северный Дом',
                'sid' => '11111111-2222-4333-8444-555555555555',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response([], 403),
        ]);

        $this->actingAs($user)
            ->post(route('settings.cabinets.connect.verify'), ['token' => 'limited-wb-token'])
            ->assertSessionHasErrors('token');

        $this->assertSame(0, $user->sellerAccounts()->count());
    }

    public function test_real_token_with_write_access_is_rejected(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([
                'name' => 'ООО Северный Дом',
                'sid' => '11111111-2222-4333-8444-555555555555',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);

        $this->actingAs($user)
            ->post(route('settings.cabinets.connect.verify'), [
                'token' => $this->wildberriesToken(readOnly: false),
            ])
            ->assertSessionHasErrors('token');

        $this->assertSame(0, $user->sellerAccounts()->count());
    }

    public function test_start_sync_dispatches_background_job_and_loading_is_tenant_isolated(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $account = SellerAccount::factory()->for($owner)->create([
            'status' => SellerAccountStatus::Verified,
        ]);

        $this->actingAs($owner)
            ->post(route('settings.cabinets.initial-sync.start', $account))
            ->assertRedirect(route('settings.cabinets.initial-sync.show', $account));

        Queue::assertPushed(RunInitialSyncJob::class, 1);

        $this->actingAs($owner)
            ->get(route('settings.cabinets.initial-sync.show', $account))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/cabinets/connect/loading')
                ->where('syncStatus.account.id', $account->id));

        $this->actingAs($stranger)
            ->get(route('settings.cabinets.initial-sync.show', $account))
            ->assertForbidden();
    }

    public function test_replacing_credential_supersedes_active_sync_and_resets_resource_states(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        [$account, $staleRun] = $this->staleRealAccount($user);
        $this->fakeRealConnection();

        $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: true)],
        )->assertRedirect(route('settings.cabinets.verification', $account));

        $this->assertSame(SyncRunStatus::Failed, $staleRun->fresh()->status);
        $this->assertSame('credential_replaced', $staleRun->fresh()->error_code);
        $this->assertNotNull($staleRun->fresh()->finished_at);
        $this->assertSame(0, $account->syncResourceStates()->count());
    }

    public function test_sync_after_credential_replacement_creates_and_dispatches_a_new_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        [$account, $staleRun] = $this->staleRealAccount($user);
        $this->fakeRealConnection();

        $this->actingAs($user)->post(
            route('settings.cabinets.connect.verify'),
            ['token' => $this->wildberriesToken(readOnly: true)],
        );
        $this->actingAs($user)
            ->post(route('settings.cabinets.initial-sync.start', $account))
            ->assertRedirect(route('settings.cabinets.initial-sync.show', $account));

        $newRun = $account->syncRuns()->latest('id')->firstOrFail();
        $this->assertNotSame($staleRun->id, $newRun->id);
        $this->assertSame(SyncRunStatus::Pending, $newRun->status);
        Queue::assertPushed(
            RunInitialSyncJob::class,
            fn (RunInitialSyncJob $job): bool => $job->syncRunId === $newRun->id,
        );
    }

    public function test_cabinet_index_never_contains_encrypted_credential(): void
    {
        $user = User::factory()->create();
        $account = SellerAccount::factory()->for($user)->create();
        $account->credential()->create([
            'token' => 'server-only-demo-token',
            'fingerprint' => hash('sha256', 'server-only-demo-token'),
            'permissions' => ['products'],
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('settings.cabinets.index'))
            ->assertOk()
            ->assertDontSee('server-only-demo-token')
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/cabinets/index')
                ->has('cabinets.items', 1)
                ->missing('cabinets.items.0.token'));
    }

    private function wildberriesToken(bool $readOnly, int $accountType = 3, bool $test = false): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(
            base64_encode(json_encode($value, JSON_THROW_ON_ERROR)),
            '+/',
            '-_',
        ), '=');
        $scope = (1 << 1) | (1 << 2) | (1 << 5);
        if ($readOnly) {
            $scope |= 1 << 30;
        }

        $claims = [
            'acc' => $accountType,
            's' => $scope,
            'sid' => '11111111-2222-4333-8444-555555555555',
        ];
        if ($test) {
            $claims['t'] = true;
        }

        return $encode(['alg' => 'ES256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
    }

    /** @return array{SellerAccount, SyncRun} */
    private function staleRealAccount(User $user): array
    {
        $account = SellerAccount::factory()->for($user)->create([
            'source' => SellerAccountSource::Wildberries,
            'status' => SellerAccountStatus::InitialSync,
            'wb_account_id' => '11111111-2222-4333-8444-555555555555',
        ]);
        $account->credential()->create([
            'token' => 'old-real-token',
            'fingerprint' => hash('sha256', 'old-real-token'),
            'permissions' => ['products', 'orders', 'sales'],
            'verified_at' => now()->subHour(),
        ]);
        $run = $account->syncRuns()->create([
            'type' => SyncRunType::Initial,
            'status' => SyncRunStatus::Pending,
            'progress' => 25,
            'started_at' => now()->subHour(),
            'error_code' => 'rate_limited',
        ]);
        $account->syncResourceStates()->create([
            'resource' => SyncResource::Stocks,
            'status' => SyncResourceStatus::Skipped,
            'availability' => ResourceAvailability::Unavailable,
            'error_code' => 'resource_not_supported_by_token',
        ]);

        return [$account, $run];
    }

    private function fakeRealConnection(): void
    {
        Http::fake([
            'https://common-api.wildberries.ru/api/v1/seller-info' => Http::response([
                'name' => 'ООО Северный Дом',
                'sid' => '11111111-2222-4333-8444-555555555555',
            ]),
            'https://content-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://statistics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
            'https://seller-analytics-api.wildberries.ru/ping' => Http::response(['Status' => 'OK']),
        ]);
    }
}
