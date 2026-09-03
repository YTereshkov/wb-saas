<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\DemoWildberriesClient;
use Illuminate\Validation\ValidationException;

final readonly class UpdateDemoSellerAccountCredential
{
    public function __construct(
        private DemoWildberriesClient $client,
        private StoreSellerAccountCredential $storeCredential,
    ) {}

    public function handle(SellerAccount $account, string $token): void
    {
        $expectedToken = config('sellerscope.demo.connection_token');
        if (! is_string($expectedToken)
            || $expectedToken === ''
            || $token === ''
            || ! hash_equals($expectedToken, $token)) {
            throw ValidationException::withMessages(['token' => 'Демо-токен не прошёл проверку.']);
        }

        $connection = $this->client->checkConnection();
        if (! $connection->successful) {
            throw ValidationException::withMessages(['token' => 'Не удалось проверить подключение.']);
        }

        $this->storeCredential->handle($account, $token, $connection->availableResources, now());
        $account->update(['status' => SellerAccountStatus::Active, 'disconnected_at' => null]);
    }
}
