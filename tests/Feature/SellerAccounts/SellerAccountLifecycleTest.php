<?php

namespace Tests\Feature\SellerAccounts;

use App\Models\User;
use App\Modules\SellerAccounts\Actions\CreateSellerAccount;
use App\Modules\SellerAccounts\Actions\StoreSellerAccountCredential;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\SellerAccountCredential;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_one_preference_record(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Юрий',
            'email' => 'owner@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => true,
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'owner@example.com')->sole();

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'active_seller_account_id' => null,
            'period_preset' => 'month',
            'granularity' => 'day',
        ]);
    }

    public function test_verified_user_can_create_accounts_and_first_one_becomes_active(): void
    {
        $user = User::factory()->create();
        $action = app(CreateSellerAccount::class);

        $first = $action->handle($user, 'Основной кабинет');
        $second = $action->handle(
            $user,
            'Второй кабинет',
            SellerAccountSource::Wildberries,
            '01234567890123456789',
        );

        $this->assertSame(SellerAccountStatus::Pending, $first->status);
        $this->assertSame('01234567890123456789', $second->wb_account_id);
        $this->assertSame($first->id, $user->preference()->sole()->active_seller_account_id);
    }

    public function test_unverified_user_cannot_create_a_seller_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->expectException(AuthorizationException::class);

        app(CreateSellerAccount::class)->handle($user, 'Недоступный кабинет');
    }

    public function test_credential_is_encrypted_hidden_and_replaced_in_place(): void
    {
        $sellerAccount = SellerAccount::factory()->create();
        $action = app(StoreSellerAccountCredential::class);
        $plainToken = 'safe-test-token-value';

        $credential = $action->handle(
            $sellerAccount,
            $plainToken,
            ['stocks', 'content', 'stocks'],
            now(),
        );

        $storedToken = DB::table('seller_account_credentials')
            ->where('id', $credential->id)
            ->value('token');

        $this->assertNotSame($plainToken, $storedToken);
        $this->assertSame($plainToken, $credential->fresh()->token);
        $this->assertSame(hash('sha256', $plainToken), $credential->fingerprint);
        $this->assertSame(['content', 'stocks'], $credential->permissions);
        $this->assertArrayNotHasKey('token', $credential->toArray());

        $updated = $action->handle($sellerAccount, 'replacement-test-token');

        $this->assertSame($credential->id, $updated->id);
        $this->assertDatabaseCount('seller_account_credentials', 1);
        $this->assertSame(
            SellerAccountCredential::fingerprint('replacement-test-token'),
            $updated->fingerprint,
        );
    }
}
