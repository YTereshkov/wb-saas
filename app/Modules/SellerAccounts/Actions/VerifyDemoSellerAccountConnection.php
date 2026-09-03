<?php

namespace App\Modules\SellerAccounts\Actions;

use App\Models\User;
use App\Modules\SellerAccounts\Enums\SellerAccountSource;
use App\Modules\SellerAccounts\Enums\SellerAccountStatus;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\Wildberries\Clients\DemoWildberriesClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class VerifyDemoSellerAccountConnection
{
    public function __construct(
        private DemoWildberriesClient $client,
        private CreateSellerAccount $createSellerAccount,
        private StoreSellerAccountCredential $storeCredential,
    ) {}

    public function handle(User $user, string $token): SellerAccount
    {
        if (! hash_equals((string) config('sellerscope.demo.connection_token'), $token)) {
            throw ValidationException::withMessages([
                'token' => 'Демо-токен не прошёл проверку. Проверьте значение и повторите попытку.',
            ]);
        }

        $connection = $this->client->checkConnection();

        if (! $connection->successful || count($connection->availableResources) !== 4) {
            throw ValidationException::withMessages([
                'token' => 'Не удалось подтвердить все необходимые доступы.',
            ]);
        }

        return DB::transaction(function () use ($user, $token, $connection): SellerAccount {
            $account = $user->sellerAccounts()
                ->where('source', SellerAccountSource::Demo)
                ->where('wb_account_id', $connection->externalAccountId)
                ->first();

            if ($account === null) {
                $account = $this->createSellerAccount->handle(
                    $user,
                    'Текстиль Маркет',
                    SellerAccountSource::Demo,
                    $connection->externalAccountId,
                );
            }

            $this->storeCredential->handle(
                $account,
                $token,
                $connection->availableResources,
                now(),
            );
            $account->update([
                'status' => SellerAccountStatus::Verified,
                'disconnected_at' => null,
            ]);
            $user->preference()->firstOrCreate()->update([
                'active_seller_account_id' => $account->id,
                'period_preset' => 'july_2026',
                'period_start' => '2026-07-01',
                'period_end' => '2026-07-31',
                'comparison_start' => '2026-06-01',
                'comparison_end' => '2026-06-30',
            ]);

            return $account->fresh();
        });
    }
}
