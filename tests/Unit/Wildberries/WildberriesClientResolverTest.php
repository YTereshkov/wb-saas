<?php

namespace Tests\Unit\Wildberries;

use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\DemoWildberriesClient;
use App\Modules\Wildberries\Clients\WildberriesHttpClient;
use App\Modules\Wildberries\Clients\WildberriesSandboxClient;
use App\Modules\Wildberries\WildberriesClientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WildberriesClientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_demo_client_without_using_credentials(): void
    {
        $account = new SellerAccount(['source' => SellerAccountSource::Demo]);

        $this->assertInstanceOf(
            DemoWildberriesClient::class,
            app(WildberriesClientResolver::class)->resolve($account),
        );
    }

    public function test_it_resolves_real_client_from_server_side_credential(): void
    {
        $account = SellerAccount::factory()->create(['source' => SellerAccountSource::Wildberries]);
        $account->credential()->create([
            'token' => 'resolver-test-token',
            'fingerprint' => hash('sha256', 'resolver-test-token'),
            'permissions' => ['products', 'orders', 'sales', 'stocks'],
            'verified_at' => now(),
        ]);

        $this->assertInstanceOf(
            WildberriesHttpClient::class,
            app(WildberriesClientResolver::class)->resolve($account),
        );
    }

    public function test_it_resolves_sandbox_client_from_test_credential(): void
    {
        $account = SellerAccount::factory()->create([
            'source' => SellerAccountSource::Wildberries,
            'wb_account_id' => 'sandbox:11111111-2222-4333-8444-555555555555',
        ]);
        $token = $this->token([
            'sid' => '11111111-2222-4333-8444-555555555555',
            's' => 0,
            't' => true,
        ]);
        $account->credential()->create([
            'token' => $token,
            'fingerprint' => hash('sha256', $token),
            'permissions' => ['products', 'orders', 'sales'],
            'verified_at' => now(),
        ]);

        $this->assertInstanceOf(
            WildberriesSandboxClient::class,
            app(WildberriesClientResolver::class)->resolve($account),
        );
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(
            base64_encode(json_encode($value, JSON_THROW_ON_ERROR)),
            '+/',
            '-_',
        ), '=');

        return $encode(['alg' => 'ES256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
    }
}
